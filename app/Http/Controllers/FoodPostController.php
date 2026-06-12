<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodPost;

class FoodPostController extends Controller
{
    /**
     * Lấy danh sách thực phẩm lân cận dựa vào thuật toán Haversine
     */
    public function getNearbyFood(Request $request)
    {
        // Nhận tọa độ từ Frontend, nếu không truyền lên thì mặc định lấy Chợ Bến Thành làm tâm
        $latitude = $request->input('latitude', 10.7719);
        $longitude = $request->input('longitude', 106.6983);
        $radius = $request->input('radius', 5); // Bán kính mặc định 5 km

        // Câu lệnh SQL tích hợp công thức Haversine
        $posts = FoodPost::select('food_posts.*')
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )
            ->where('status', 'available') // Chỉ lấy thực phẩm chưa ai nhận
            ->where('ai_status', 'safe')    // Chỉ lấy thực phẩm đã qua AI duyệt an toàn
            ->having('distance', '<=', $radius) // Lọc theo bán kính (km)
            ->orderBy('distance') // Ưu tiên những thực phẩm gần nhất xếp trước
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
}
