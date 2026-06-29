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
            
            // Validate mảng vật phẩm
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:100',
            'items.*.target_quantity' => 'required|integer|min:1',
        ], [
            'end_date.after' => 'Ngày kết thúc quyên góp phải sau ngày hôm nay.',
            'items.required' => 'Bạn phải thêm ít nhất một vật phẩm kêu gọi.',
            'items.min' => 'Bạn phải thêm ít nhất một vật phẩm kêu gọi.',
            'items.*.item_name.required' => 'Tên vật phẩm không được để trống.',
            'items.*.target_quantity.min' => 'Số lượng mục tiêu phải lớn hơn hoặc bằng 1.',
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
}
