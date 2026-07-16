<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CampaignCancelledNotification extends Notification
{
    use Queueable;

    protected $campaignTitle;
    protected $reason;

    public function __construct($campaignTitle, $reason)
    {
        $this->campaignTitle = $campaignTitle;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'campaign_cancelled',
            'title' => 'Chiến dịch đã bị hủy',
            'message' => "Chiến dịch \"{$this->campaignTitle}\" đã bị hủy với lý do: {$this->reason}. Các đơn quyên góp liên quan của bạn đã tự động bị hủy, vui lòng ngừng giao hàng.",
        ];
    }
}
