<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QrLoginSuccessful implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $token;
    public $userId;

    public function __construct($token, $userId)
    {
        $this->token = $token;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        // Sử dụng Public Channel cho trang Đăng nhập (vì lúc này máy tính chưa login)
        return [
            new Channel('qr-login.' . $this->token),
        ];
    }

    public function broadcastAs(): string
    {
        return 'qr.login';
    }
}
