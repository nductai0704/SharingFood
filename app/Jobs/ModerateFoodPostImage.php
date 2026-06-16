<?php

namespace App\Jobs;

use App\Models\FoodPost;
use App\Services\AiVisionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ModerateFoodPostImage implements ShouldQueue
{
    use Queueable;

    /**
     * The food post instance.
     *
     * @var \App\Models\FoodPost
     */
    protected $foodPost;

    /**
     * Create a new job instance.
     */
    public function __construct(FoodPost $foodPost)
    {
        $this->foodPost = $foodPost;
    }

    /**
     * Execute the job.
     */
    public function handle(AiVisionService $aiVisionService): void
    {
        // Kiểm tra xem bài đăng còn tồn tại hay không
        $post = FoodPost::find($this->foodPost->id);
        if (!$post) {
            Log::warning("Moderation job skipped: Food post ID {$this->foodPost->id} not found.");
            return;
        }

        Log::info("Starting AI Vision moderation job for Food Post ID: {$post->id}");

        // Nếu bài viết không có ảnh, tự động chuyển trạng thái duyệt sang 'safe' và đăng hoạt động
        if (empty($post->image_url)) {
            $post->update([
                'ai_status' => 'safe',
                'status' => 'available'
            ]);
            Log::info("Food Post ID {$post->id} has no image. Moderated as safe automatically.");
            return;
        }

        // Gọi dịch vụ AI Vision để kiểm duyệt ảnh
        $result = $aiVisionService->moderateImage($post->image_url);

        if ($result['is_safe']) {
            $post->update([
                'ai_status' => 'safe',
                'status' => 'available' // Kích hoạt hiển thị ra bản đồ
            ]);
            Log::info("Food Post ID {$post->id} image moderated as SAFE. Post is now available.");

            // Ghi nhật ký hệ thống
            \App\Models\SystemLog::create([
                'user_id' => $post->user_id,
                'action' => 'AI_MODERATION',
                'description' => "AI Vision đã phê duyệt bài đăng thực phẩm ID {$post->id} ('{$post->title}'). Lý do: " . $result['reason'],
                'ip_address' => '127.0.0.1',
                'created_at' => now()
            ]);
        } else {
            $post->update([
                'ai_status' => 'flagged',
                'status' => 'hidden' // Ẩn bài đăng khỏi bản đồ
            ]);
            Log::warning("Food Post ID {$post->id} image moderated as UNSAFE. Post is hidden. Reason: " . $result['reason']);

            // Ghi nhật ký hệ thống
            \App\Models\SystemLog::create([
                'user_id' => $post->user_id,
                'action' => 'AI_MODERATION',
                'description' => "AI Vision đã chặn/gắn cờ bài đăng thực phẩm ID {$post->id} ('{$post->title}'). Lý do: " . $result['reason'],
                'ip_address' => '127.0.0.1',
                'created_at' => now()
            ]);
        }
    }
}
