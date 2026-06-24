<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodPost;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ModerateFoodPostImage;

class FoodPostController extends Controller
{
    /**
     * Lấy danh sách thực phẩm lân cận dựa vào thuật toán Haversine
     */
    public function getNearbyFood(Request $request)
    {
        // Tự động ẩn các tin đã quá hạn sử dụng
        FoodPost::where('expires_at', '<', now())
            ->where('status', 'available')
            ->update(['status' => 'expired']);

        $latitude = $request->input('latitude', 10.7719);
        $longitude = $request->input('longitude', 106.6983);
        $radius = $request->input('radius', 5);
        $search = $request->input('search');

        $query = FoodPost::with('user')
            ->select('food_posts.*')
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )
            ->where('status', 'available')
            ->where('ai_status', 'safe');

        // Áp dụng bộ lọc tìm kiếm nếu có
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $posts = $query->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->get();

        return response()->json([
            'success' => true,
            'user_location' => [
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude,
            ],
            'radius_km' => (float)$radius,
            'total' => $posts->count(),
            'data' => $posts
        ]);
    }

    /**
     * Lưu bài đăng tặng thực phẩm lẻ
     */
    public function store(Request $request)
    {
        // 1. Thực hiện validate dữ liệu gửi lên
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id,is_allowed,1',
            'original_quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:50',
            'description' => 'required|string',
            'expires_at' => 'required|date|after:now',
            'image' => 'nullable|image|max:2048', // File ảnh tối đa 2MB
        ], [
            'title.required' => 'Vui lòng nhập tên món ăn / thực phẩm.',
            'title.max' => 'Tên món ăn không được vượt quá 255 ký tự.',
            'category_id.required' => 'Vui lòng chọn danh mục thực phẩm.',
            'category_id.exists' => 'Danh mục đã chọn không hợp lệ hoặc không an toàn.',
            'original_quantity.required' => 'Vui lòng nhập số lượng.',
            'original_quantity.integer' => 'Số lượng phải là một số nguyên.',
            'original_quantity.min' => 'Số lượng tối thiểu phải là 1.',
            'unit.required' => 'Vui lòng nhập đơn vị tính.',
            'description.required' => 'Vui lòng nhập mô tả chi tiết.',
            'expires_at.required' => 'Vui lòng nhập hạn sử dụng.',
            'expires_at.date' => 'Hạn sử dụng không đúng định dạng ngày giờ.',
            'expires_at.after' => 'Hạn sử dụng phải ở thời gian tương lai.',
            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.max' => 'Kích thước hình ảnh tối đa là 2MB.',
        ]);

        // 2. Xử lý upload ảnh
        $imageUrl = null;
        if ($request->hasFile('image')) {
            // TODO: Upload file ảnh vào thư mục 'food_posts' trên disk 'public' và lấy path lưu vào DB
            $path = $request->file('image')->store('food_posts', 'public');
            $imageUrl = '/storage/' . $path;
        }

        // 3. Tự động lấy thông tin người dùng đang đăng nhập
        $user = auth()->user();
        // 4. Lưu thông tin bài đăng vào database thông qua FoodPost::create()
        $foodPost = FoodPost::create([
            'user_id' => $user->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'original_quantity' => $validated['original_quantity'],
            'remain_quantity' => $validated['original_quantity'],
            'unit' => $validated['unit'],
            'image_url' => $imageUrl,
            'expires_at' => $validated['expires_at'],
            'ai_status' => 'unchecked', // mặc định chờ AI duyệt
            'status' => 'available',
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
        ]);

        // Đẩy công việc kiểm duyệt ảnh bằng AI vào hàng đợi (Queue) bất đồng bộ
        ModerateFoodPostImage::dispatch($foodPost);

        // 5. Quay trở lại trang trước kèm thông báo thành công
        return redirect()->back()->with('success', 'Đăng bài tặng thực phẩm thành công! Tin đăng đang được AI kiểm duyệt ngầm.');
    }

    /**
     * Bật/Tắt trạng thái hiển thị của bài đăng (hoặc gia hạn nếu đã hết hạn)
     */
    public function toggleStatus(FoodPost $post)
    {
        if ($post->user_id !== auth()->id()) {
            abort(403);
        }

        if ($post->status === 'available') {
            $post->status = 'expired';
        } else {
            // Nếu bài đăng đã hết hạn so với thời gian hiện tại, tự động gia hạn thêm 24 giờ
            if (new \DateTime($post->expires_at) < new \DateTime()) {
                $post->expires_at = now()->addDays(1);
            }
            $post->status = 'available';
        }
        $post->save();

        return redirect()->back()->with('success', 'Cập nhật trạng thái thực phẩm thành công!');
    }

    /**
     * Yêu cầu nhận thực phẩm lẻ (Concurrency Lock - lockForUpdate)
     */
    public function claim(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);
        
        $requestedQty = $request->input('quantity');
        $user = auth()->user();
        
        try {
            $result = \DB::transaction(function() use ($id, $requestedQty, $user) {
                // Pessimistic Locking (Khóa dòng dữ liệu để chống tranh giành)
                $post = FoodPost::lockForUpdate()->findOrFail($id);
                
                // Tránh tự nhận đồ ăn của mình
                if ($post->user_id === $user->id) {
                    return ['success' => false, 'message' => 'Bạn không thể tự nhận thực phẩm của chính mình.'];
                }
                
                // Kiểm tra hạn sử dụng và trạng thái
                if (new \DateTime($post->expires_at) < new \DateTime() || $post->status !== 'available') {
                    return ['success' => false, 'message' => 'Rất tiếc, thực phẩm này đã hết hạn hoặc không còn chia sẻ.'];
                }
                
                // Kiểm tra số lượng tồn kho
                if ($post->remain_quantity < $requestedQty) {
                    return ['success' => false, 'message' => "Rất tiếc, thực phẩm đã được nhận hết hoặc không đủ số lượng (chỉ còn {$post->remain_quantity} {$post->unit})."];
                }
                
                // Khấu trừ số lượng
                $post->remain_quantity -= $requestedQty;
                if ($post->remain_quantity <= 0) {
                    $post->status = 'expired';
                }
                $post->save();
                
                // Tạo yêu cầu nhận thực phẩm (Hóa đơn)
                \App\Models\FoodClaim::create([
                    'food_post_id' => $post->id,
                    'user_id' => $user->id,
                    'quantity' => $requestedQty,
                    'status' => 'pending',
                ]);
                
                // Nhật ký hệ thống
                \App\Models\SystemLog::create([
                    'action' => 'Yêu cầu nhận thực phẩm',
                    'description' => "Người dùng {$user->name} đã gửi yêu cầu nhận {$requestedQty} {$post->unit} từ bài viết \"{$post->title}\"",
                    'user_id' => $user->id,
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
                
                return ['success' => true, 'message' => 'Gửi yêu cầu nhận thực phẩm thành công! Vui lòng chờ người chia sẻ phê duyệt.'];
            });
            
            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->with('error', $result['message']);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    /**
     * Phê duyệt / Từ chối yêu cầu nhận thực phẩm
     */
    public function updateClaimStatus(Request $request, $claimId)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);
        
        $newStatus = $request->input('status');
        $user = auth()->user();
        
        try {
            $result = \DB::transaction(function() use ($claimId, $newStatus, $user) {
                // Khóa yêu cầu và bài viết liên quan
                $claim = \App\Models\FoodClaim::lockForUpdate()->findOrFail($claimId);
                $post = FoodPost::lockForUpdate()->findOrFail($claim->food_post_id);
                
                // Xác thực quyền sở hữu
                if ($post->user_id !== $user->id) {
                    return ['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
                }
                
                if ($claim->status !== 'pending') {
                    return ['success' => false, 'message' => 'Yêu cầu này đã được xử lý trước đó.'];
                }
                
                if ($newStatus === 'approved') {
                    $claim->status = 'approved';
                    $claim->save();
                    
                    \App\Models\SystemLog::create([
                        'action' => 'Phê duyệt yêu cầu',
                        'description' => "Người chia sẻ {$user->name} đã phê duyệt yêu cầu nhận {$claim->quantity} {$post->unit} của {$claim->user->name}",
                        'user_id' => $user->id,
                        'ip_address' => request()->ip(),
                        'created_at' => now()
                    ]);
                } else {
                    $claim->status = 'rejected';
                    $claim->save();
                    
                    // Hoàn lại kho thực phẩm
                    $post->remain_quantity += $claim->quantity;
                    if ($post->status === 'expired' && $post->remain_quantity > 0 && new \DateTime($post->expires_at) > new \DateTime()) {
                        $post->status = 'available';
                    }
                    $post->save();
                    
                    \App\Models\SystemLog::create([
                        'action' => 'Từ chối yêu cầu nhận',
                        'description' => "Người chia sẻ {$user->name} từ chối/hủy yêu cầu nhận của {$claim->user->name}, hoàn lại {$claim->quantity} {$post->unit}",
                        'user_id' => $user->id,
                        'ip_address' => request()->ip(),
                        'created_at' => now()
                    ]);
                }
                
                return ['success' => true, 'message' => 'Cập nhật trạng thái yêu cầu thành công!'];
            });
            
            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->with('error', $result['message']);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Lỗi khi xử lý: ' . $e->getMessage());
        }
    }

    /**
     * Hủy yêu cầu nhận thực phẩm (Hủy giao dịch & Rollback hoàn kho)
     */
    public function cancelClaim(Request $request, $claimId)
    {
        $user = auth()->user();
        
        try {
            $result = \DB::transaction(function() use ($claimId, $user) {
                // Khóa yêu cầu và bài viết liên quan
                $claim = \App\Models\FoodClaim::lockForUpdate()->findOrFail($claimId);
                $post = FoodPost::lockForUpdate()->findOrFail($claim->food_post_id);
                
                // Xác thực quyền: chỉ Người cho hoặc Người nhận mới được phép hủy
                if ($post->user_id !== $user->id && $claim->user_id !== $user->id) {
                    return ['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
                }
                
                // Chỉ cho phép hủy khi trạng thái là pending hoặc approved
                if (!in_array($claim->status, ['pending', 'approved'])) {
                    return ['success' => false, 'message' => 'Yêu cầu này không thể hủy ở trạng thái hiện tại.'];
                }
                
                $claim->status = 'cancelled';
                $claim->save();
                
                // Hoàn lại kho thực phẩm
                $post->remain_quantity += $claim->quantity;
                
                // Nếu bài đăng đã hết hạn hoặc tạm dừng ẩn, nhưng còn số lượng và thời gian hết hạn vẫn ở tương lai, tự động khôi phục status
                if ($post->remain_quantity > 0 && new \DateTime($post->expires_at) > new \DateTime()) {
                    $post->status = 'available';
                }
                $post->save();
                
                // Ghi nhật ký hệ thống
                $party = ($user->id === $post->user_id) ? "Người cho" : "Người nhận";
                \App\Models\SystemLog::create([
                    'action' => 'Hủy yêu cầu nhận',
                    'description' => "{$party} {$user->name} đã hủy giao dịch nhận {$claim->quantity} {$post->unit} từ bài đăng \"{$post->title}\". Đã hoàn kho số lượng.",
                    'user_id' => $user->id,
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
                
                return ['success' => true, 'message' => 'Hủy giao dịch và hoàn lại kho thành công!'];
            });
            
            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->with('error', $result['message']);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Lỗi khi xử lý hủy: ' . $e->getMessage());
        }
    }

    /**
     * Xác nhận hoàn thành giao dịch (Đã lấy đồ)
     */
    public function completeClaim(Request $request, $claimId)
    {
        $user = auth()->user();
        
        try {
            $result = \DB::transaction(function() use ($claimId, $user) {
                // Khóa yêu cầu và bài viết liên quan
                $claim = \App\Models\FoodClaim::lockForUpdate()->findOrFail($claimId);
                $post = FoodPost::lockForUpdate()->findOrFail($claim->food_post_id);
                
                // Xác thực quyền: chỉ Người cho mới được phép hoàn thành
                if ($post->user_id !== $user->id) {
                    return ['success' => false, 'message' => 'Chỉ chủ nhà (người chia sẻ) mới có quyền xác nhận giao đồ.'];
                }
                
                // Chỉ cho phép xác nhận khi trạng thái là approved
                if ($claim->status !== 'approved') {
                    return ['success' => false, 'message' => 'Giao dịch chỉ có thể hoàn thành khi đã được duyệt trước đó.'];
                }
                
                $claim->status = 'completed';
                $claim->save();
                
                // Ghi nhật ký hệ thống
                \App\Models\SystemLog::create([
                    'action' => 'Hoàn thành giao dịch',
                    'description' => "Chủ nhà {$user->name} đã xác nhận giao đồ ăn thành công cho {$claim->user->name} ({$claim->quantity} {$post->unit} từ bài đăng \"{$post->title}\")",
                    'user_id' => $user->id,
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
                
                return ['success' => true, 'message' => 'Xác nhận hoàn thành giao dịch thành công! Chúc mừng hai bên!'];
            });
            
            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->with('error', $result['message']);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Lỗi khi xác nhận hoàn thành: ' . $e->getMessage());
        }
    }

    /**
     * Gỡ bài đăng tặng thực phẩm (Xóa mềm - Chuyển sang trạng thái 'deleted' và hủy các yêu cầu liên quan)
     */
    public function destroy(FoodPost $post)
    {
        if ($post->user_id !== auth()->id()) {
            abort(403);
        }

        \DB::transaction(function() use ($post) {
            // 1. Chuyển trạng thái bài đăng sang 'hidden' (ẩn đi hoàn toàn)
            $post->status = 'hidden';
            $post->save();

            // 2. Tự động hủy các yêu cầu nhận đang ở trạng thái 'pending' hoặc 'approved'
            $claimsToCancel = $post->claims()
                ->whereIn('status', ['pending', 'approved'])
                ->get();

            foreach ($claimsToCancel as $claim) {
                $claim->status = 'cancelled';
                $claim->save();

                // Nhật ký hệ thống cho mỗi claim bị hủy
                \App\Models\SystemLog::create([
                    'action' => 'Hủy yêu cầu do gỡ bài',
                    'description' => "Yêu cầu nhận của người dùng " . ($claim->user ? $claim->user->name : 'Người dùng') . " đã bị tự động hủy do chủ bài viết gỡ tin đăng \"{$post->title}\"",
                    'user_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'created_at' => now()
                ]);
            }

            // Nhật ký hệ thống khi gỡ bài viết
            \App\Models\SystemLog::create([
                'action' => 'Gỡ bài đăng thực phẩm',
                'description' => "Người dùng " . auth()->user()->name . " đã gỡ bài đăng \"{$post->title}\"",
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'created_at' => now()
            ]);
        });

        return redirect()->back()->with('success', 'Gỡ bài đăng thực phẩm và hủy các yêu cầu liên quan thành công!');
    }

    /**
     * Cập nhật và gia hạn bài đăng thực phẩm
     */
    public function update(Request $request, FoodPost $post)
    {
        if ($post->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id,is_allowed,1',
            'original_quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:50',
            'description' => 'required|string',
            'expires_at' => 'required|date|after:now',
            'image' => 'nullable|image|max:2048',
        ], [
            'title.required' => 'Vui lòng nhập tên món ăn / thực phẩm.',
            'title.max' => 'Tên món ăn không được vượt quá 255 ký tự.',
            'category_id.required' => 'Vui lòng chọn danh mục thực phẩm.',
            'category_id.exists' => 'Danh mục đã chọn không hợp lệ hoặc không an toàn.',
            'original_quantity.required' => 'Vui lòng nhập số lượng.',
            'original_quantity.integer' => 'Số lượng phải là một số nguyên.',
            'original_quantity.min' => 'Số lượng tối thiểu phải là 1.',
            'unit.required' => 'Vui lòng nhập đơn vị tính.',
            'description.required' => 'Vui lòng nhập mô tả chi tiết.',
            'expires_at.required' => 'Vui lòng nhập hạn sử dụng mới.',
            'expires_at.date' => 'Hạn sử dụng không đúng định dạng ngày giờ.',
            'expires_at.after' => 'Hạn sử dụng phải ở thời gian tương lai.',
            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.max' => 'Kích thước hình ảnh tối đa là 2MB.',
        ]);

        $imageUrl = $post->image_url;
        $imageChanged = false;

        if ($request->hasFile('image')) {
            // Upload file ảnh mới
            $path = $request->file('image')->store('food_posts', 'public');
            $imageUrl = '/storage/' . $path;
            $imageChanged = true;
        }

        $user = auth()->user();
        // Cập nhật thông tin bài đăng
        $post->fill([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'original_quantity' => $validated['original_quantity'],
            'remain_quantity' => $validated['original_quantity'], // Reset remain quantity when updated/renewed
            'unit' => $validated['unit'],
            'image_url' => $imageUrl,
            'expires_at' => $validated['expires_at'],
            'status' => 'available', // Kích hoạt hiển thị lại khi gia hạn
            'latitude' => $user->latitude, // Cập nhật vị trí hiện tại của người đăng
            'longitude' => $user->longitude, // Cập nhật vị trí hiện tại của người đăng
            'created_at' => now(), // Cập nhật thời gian đăng bài thành thời điểm gia hạn hiện tại
        ]);

        if ($imageChanged) {
            $post->ai_status = 'unchecked'; // Chờ duyệt lại ảnh mới
        }

        $post->save();

        if ($imageChanged) {
            // Chạy kiểm duyệt ảnh bằng AI bất đồng bộ
            \App\Jobs\ModerateFoodPostImage::dispatch($post);
        }

        // Ghi nhật ký hệ thống
        \App\Models\SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'UPDATE_FOOD_POST',
            'description' => "Người dùng " . auth()->user()->name . " đã cập nhật và gia hạn bài đăng thực phẩm \"{$post->title}\" (ID: {$post->id})." . ($imageChanged ? " Đang chờ AI duyệt lại ảnh mới." : ""),
            'ip_address' => $request->ip(),
            'created_at' => now()
        ]);

        return redirect()->back()->with('success', 'Cập nhật và gia hạn thực phẩm thành công!' . ($imageChanged ? ' Ảnh mới đang được kiểm duyệt ngầm bởi AI.' : ''));
    }
}
