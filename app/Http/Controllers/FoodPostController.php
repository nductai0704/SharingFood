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

        $posts = FoodPost::with('user')
            ->select('food_posts.*')
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )
            ->where('status', 'available')
            ->where('ai_status', 'safe')
            ->having('distance', '<=', $radius)
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
}
