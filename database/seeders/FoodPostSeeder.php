<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FoodPost;
use App\Models\User;
use Carbon\Carbon;

class FoodPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::where('role', 'personal')->first()?->id ?? 1;

        $posts = [
            [
                'user_id' => $userId,
                'category_id' => 2, // Bánh mì/Bánh bao
                'title' => '10 phần Bánh bao chay nóng',
                'description' => 'Bánh bao chuẩn bị cho sự kiện sáng nay nhưng dư ra, còn nguyên vẹn trong tủ hấp ủ ấm.',
                'original_quantity' => 10,
                'remain_quantity' => 10,
                'unit' => 'phần',
                'image_url' => null,
                'expires_at' => Carbon::now()->addHours(6),
                'status' => 'available', // Khớp với Database Enum
                'ai_status' => 'safe',    // Khớp với Database Enum
                'latitude' => 10.77350000,
                'longitude' => 106.69450000,
            ],
            [
                'user_id' => $userId,
                'category_id' => 1, // Cơm/Món mặn
                'title' => '5 hộp Cơm tấm sườn bì chả',
                'description' => 'Cơm tấm đặt dư cho văn phòng trưa nay, hộp nguyên tem chưa khui kèm nước mắm đầy đủ.',
                'original_quantity' => 5,
                'remain_quantity' => 5,
                'unit' => 'hộp',
                'image_url' => null,
                'expires_at' => Carbon::now()->addHours(4),
                'status' => 'available',
                'ai_status' => 'safe',
                'latitude' => 10.76450000,
                'longitude' => 106.68950000,
            ],
            [
                'user_id' => $userId,
                'category_id' => 3, // Rau củ/Trái cây
                'title' => '3 kg Cam sành mọng nước',
                'description' => 'Cam vườn nhà gửi lên nhiều quá ăn không hết, trái chín mọng nước cực kỳ ngọt ngon.',
                'original_quantity' => 3,
                'remain_quantity' => 3,
                'unit' => 'kg',
                'image_url' => null,
                'expires_at' => Carbon::now()->addDays(2),
                'status' => 'available',
                'ai_status' => 'safe',
                'latitude' => 10.79500000,
                'longitude' => 106.67800000,
            ],
            [
                'user_id' => $userId,
                'category_id' => 4, // Khác
                'title' => '15 bánh tét chuối ngọt lịm',
                'description' => 'Bánh tét chuối gói ăn tết đoan ngọ còn dư, nếp dẻo thơm ngon ngọt lành.',
                'original_quantity' => 15,
                'remain_quantity' => 10,
                'unit' => 'cái',
                'image_url' => null,
                'expires_at' => Carbon::now()->addDays(3),
                'status' => 'available',
                'ai_status' => 'safe',
                'latitude' => 10.84500000,
                'longitude' => 106.63500000,
            ],
            [
                'user_id' => $userId,
                'category_id' => 1, // Cơm/Món mặn
                'title' => '20 phần Cháo lòng nóng hổi',
                'description' => 'Món cháo thơm phức vừa nấu xong còn thừa do tiệc gia đình kết thúc sớm.',
                'original_quantity' => 20,
                'remain_quantity' => 15,
                'unit' => 'phần',
                'image_url' => null,
                'expires_at' => Carbon::now()->addHours(3),
                'status' => 'available',
                'ai_status' => 'safe',
                'latitude' => 10.87100000,
                'longitude' => 106.79100000,
            ],
        ];

        foreach ($posts as $post) {
            FoodPost::create($post);
        }
    }
}
