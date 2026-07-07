<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    /**
     * Màn hình tạo chiến dịch quyên góp.
     */
    public function create()
    {
        // 1. Phân quyền và bảo mật (chặn nếu không phải charity hoặc chưa verified)
        $user = auth()->user();
        if ($user->role !== 'charity' || $user->status !== 'verified') {
            abort(403, 'Tài khoản của bạn chưa được xác thực hoặc không có quyền tạo chiến dịch.');
        }

        return Inertia::render('Charity/CreateCampaign');
    }

    /**
     * Xử lý lưu chiến dịch và danh sách vật phẩm.
     */
    public function store(Request $request)
    {
        // 1. Bảo mật
        $user = auth()->user();
        if ($user->role !== 'charity' || $user->status !== 'verified') {
            abort(403, 'Tài khoản của bạn chưa được xác thực hoặc không có quyền tạo chiến dịch.');
        }

        // 2. Validate dữ liệu
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location_details' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'end_date' => 'required|date|after:today',
            'execution_date' => 'required|date|after_or_equal:end_date',
            
            // Validate mảng vật phẩm
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:100',
            'items.*.target_quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|string|max:50',
        ], [
            'end_date.after' => 'Ngày đóng cổng quyên góp phải sau ngày hôm nay.',
            'execution_date.after_or_equal' => 'Ngày đi phát phải bằng hoặc sau ngày đóng cổng.',
            'items.required' => 'Bạn phải thêm ít nhất một vật phẩm kêu gọi.',
            'items.min' => 'Bạn phải thêm ít nhất một vật phẩm kêu gọi.',
            'items.*.item_name.required' => 'Tên vật phẩm không được để trống.',
            'items.*.target_quantity.min' => 'Số lượng mục tiêu phải lớn hơn hoặc bằng 1.',
            'items.*.unit.required' => 'Đơn vị không được để trống.',
        ]);

        try {
            DB::beginTransaction();

            // 3. Tạo chiến dịch (mặc định pending)
            $campaign = Campaign::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => 'pending',
                'location_details' => $validated['location_details'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'end_date' => $validated['end_date'],
                'execution_date' => $validated['execution_date'],
            ]);

            // 4. Lưu danh sách vật phẩm và phân loại tự động
            $itemsData = [];
            foreach ($validated['items'] as $item) {
                $itemNameLower = Str::lower($item['item_name']);
                $assignedCategoryId = 3; // Mặc định danh mục "Thực phẩm đóng gói / Đồ khô"

                // TỰ ĐỘNG NHẬN DIỆN: Nếu tên món chứa các từ khóa liên quan đến bánh mì, bánh ngọt
                $breadKeywords = ['bánh mì', 'bánh mi', 'bánh ngọt', 'banh ngot', 'bánh quy', 'bánh bông lan', 'croissant', 'cake'];
                if (Str::contains($itemNameLower, $breadKeywords)) {
                    $assignedCategoryId = 2; // Tự động chuyển sang danh mục "Bánh mì / Bánh ngọt"
                }

                $itemsData[] = [
                    'campaign_id' => $campaign->id,
                    'category_id' => $assignedCategoryId,
                    'item_name' => $item['item_name'],
                    'target_quantity' => $item['target_quantity'],
                    'unit' => $item['unit'],
                    'current_quantity' => 0,
                ];
            }
            CampaignItem::insert($itemsData);

            // Ghi log hệ thống
            Log::info("Chiến dịch mới được tạo bởi Charity ID {$user->id}. Campaign ID: {$campaign->id}");

            DB::commit();

            return redirect()->route('charity.campaigns')->with('success', 'Chiến dịch đã được khởi tạo thành công và đang chờ Ban quản trị phê duyệt nội dung!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi tạo chiến dịch: " . $e->getMessage());
            return back()->with('error', 'Có lỗi xảy ra trong quá trình tạo chiến dịch. Vui lòng thử lại.');
        }
    }

    /**
     * Màn hình sửa chiến dịch.
     */
    public function edit(Campaign $campaign)
    {
        $user = auth()->user();
        if ($campaign->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền sửa chiến dịch này.');
        }

        $campaign->load('items');
        return Inertia::render('Charity/EditCampaign', [
            'campaign' => $campaign
        ]);
    }

    /**
     * Cập nhật chiến dịch và danh sách vật phẩm.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $user = auth()->user();
        if ($campaign->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền sửa chiến dịch này.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location_details' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'end_date' => 'required|date',
            'execution_date' => 'required|date|after_or_equal:end_date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:campaign_items,id',
            'items.*.item_name' => 'required|string|max:100',
            'items.*.target_quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|string|max:50',
        ], [
            'end_date.after' => 'Ngày đóng cổng quyên góp phải sau ngày hôm nay.',
            'execution_date.after_or_equal' => 'Ngày đi phát phải bằng hoặc sau ngày đóng cổng.',
            'items.required' => 'Bạn phải thêm ít nhất một vật phẩm kêu gọi.',
            'items.min' => 'Bạn phải thêm ít nhất một vật phẩm kêu gọi.',
            'items.*.item_name.required' => 'Tên vật phẩm không được để trống.',
            'items.*.target_quantity.min' => 'Số lượng mục tiêu phải lớn hơn hoặc bằng 1.',
            'items.*.unit.required' => 'Đơn vị không được để trống.',
        ]);

        try {
            DB::beginTransaction();

            $campaign->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'location_details' => $validated['location_details'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'end_date' => $validated['end_date'],
                'execution_date' => $validated['execution_date'],
            ]);

            $keepItemIds = [];
            $breadKeywords = ['bánh mì', 'bánh mi', 'bánh ngọt', 'banh ngot', 'bánh quy', 'bánh bông lan', 'croissant', 'cake'];

            foreach ($validated['items'] as $item) {
                $itemNameLower = Str::lower($item['item_name']);
                $assignedCategoryId = 3;
                if (Str::contains($itemNameLower, $breadKeywords)) {
                    $assignedCategoryId = 2;
                }

                if (!empty($item['id'])) {
                    $existingItem = CampaignItem::find($item['id']);
                    if ($existingItem && $existingItem->campaign_id === $campaign->id) {
                        $existingItem->update([
                            'item_name' => $item['item_name'],
                            'target_quantity' => $item['target_quantity'],
                            'unit' => $item['unit'],
                            'category_id' => $assignedCategoryId,
                        ]);
                        $keepItemIds[] = $existingItem->id;
                    }
                } else {
                    $newItem = CampaignItem::create([
                        'campaign_id' => $campaign->id,
                        'category_id' => $assignedCategoryId,
                        'item_name' => $item['item_name'],
                        'target_quantity' => $item['target_quantity'],
                        'unit' => $item['unit'],
                        'current_quantity' => 0,
                    ]);
                    $keepItemIds[] = $newItem->id;
                }
            }

            // Xóa các item bị gỡ (chưa có người đóng góp hoặc kệ nó cascade nếu không ràng buộc - ở đây giả định có thể xóa)
            // LƯU Ý: Nếu đã có đóng góp, việc xóa có thể gây lỗi khóa ngoại nếu donation cascadeOnDelete
            // Tuy nhiên, để linh hoạt, sẽ thực hiện xóa. Nếu không muốn mất donation thì phải xử lý thêm logic.
            CampaignItem::where('campaign_id', $campaign->id)
                ->whereNotIn('id', $keepItemIds)
                ->delete();

            DB::commit();

            return redirect()->route('charity.campaigns')->with('success', 'Đã cập nhật chiến dịch thành công!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi cập nhật chiến dịch: " . $e->getMessage());
            return back()->with('error', 'Có lỗi xảy ra trong quá trình cập nhật chiến dịch. Vui lòng thử lại.');
        }
    }

    public function closeCampaign(Campaign $campaign)
    {
        $user = auth()->user();
        if ($campaign->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'closed']);

            // Hủy các đơn pending
            \App\Models\CampaignDonation::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    // Ghi chú lý do hủy vào description nếu muốn
                    'food_description' => DB::raw("CONCAT(COALESCE(food_description, ''), ' [Hủy do quá hạn tập kết thực tế trước ngày đi phát]')")
                ]);
        });

        return back()->with('success', 'Đã chốt chiến dịch và hủy các đơn chờ quyên góp.');
    }

    public function exportReport(Campaign $campaign)
    {
        $user = auth()->user();
        if ($campaign->user_id !== $user->id) {
            abort(403);
        }

        $donations = \App\Models\CampaignDonation::with(['campaignItem', 'user'])
            ->where('campaign_id', $campaign->id)
            ->where('status', 'completed')
            ->get();

        $csvHeader = ["Người quyên góp", "Vật phẩm", "Số lượng", "Đơn vị", "Ngày nhận"];
        $csvData = [];
        foreach ($donations as $donation) {
            $csvData[] = [
                $donation->user->name ?? 'Ẩn danh',
                $donation->campaignItem->item_name ?? '',
                $donation->donation_quantity,
                $donation->unit ?? '',
                $donation->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = "bao-cao-chot-chien-dich-{$campaign->id}.csv";
        
        $callback = function () use ($csvHeader, $csvData) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, $csvHeader);
            foreach ($csvData as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
