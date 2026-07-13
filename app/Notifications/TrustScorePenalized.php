<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrustScorePenalized extends Notification
{
    use Queueable;

    public $points;
    public $reason;
    public $messageStr;

    /**
     * Create a new notification instance.
     */
    public function __construct($points, $reason, $messageStr)
    {
        $this->points = $points;
        $this->reason = $reason;
        $this->messageStr = $messageStr;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trust_score_penalized',
            'points' => $this->points,
            'reason' => $this->reason,
            'message' => $this->messageStr
        ];
    }
}
