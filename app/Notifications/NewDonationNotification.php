<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDonationNotification extends Notification
{
    use Queueable;

    public $donationCode;
    public $donorName;
    public $campaignTitle;
    public $itemCount;

    public function __construct($donationCode, $donorName, $campaignTitle, $itemCount)
    {
        $this->donationCode = $donationCode;
        $this->donorName = $donorName;
        $this->campaignTitle = $campaignTitle;
        $this->itemCount = $itemCount;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_donation',
            'message' => "{$this->donorName} đã gửi {$this->itemCount} món đồ quyên góp cho chiến dịch {$this->campaignTitle}.",
            'donation_code' => $this->donationCode,
            'url' => route('charity.campaigns') // Link to verification page
        ];
    }
}
