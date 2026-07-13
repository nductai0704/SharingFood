<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class CheckExpiredTransactions extends Command
{
    protected $signature = 'transactions:check-expired';
    protected $description = 'Kiểm tra và tự động hủy các giao dịch quá hạn, đồng thời trừ điểm uy tín (Anti-Spam).';

    public function handle()
    {
        $this->info('Starting to check expired transactions...');

        // 1. Food Claims (Yêu cầu nhận thực phẩm lẻ)
        // Nếu đã được duyệt (approved) quá 60 phút mà chưa hoàn thành -> Tự động hủy
        $expiredClaims = \App\Models\FoodClaim::where('status', 'approved')
            ->where('approved_at', '<', now()->subMinutes(60))
            ->get();

        foreach ($expiredClaims as $claim) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($claim) {
                // Hủy đơn
                $claim->status = 'cancelled';
                $claim->cancel_reason = 'Hệ thống tự động hủy do quá hạn 60 phút không lấy hàng';
                $claim->cancelled_by = 'system';
                $claim->save();

                // Hoàn số lượng về kho bài viết
                $post = $claim->foodPost;
                if ($post) {
                    $post->remain_quantity += $claim->quantity;
                    if ($post->remain_quantity > 0 && new \DateTime($post->expires_at) > new \DateTime()) {
                        $post->status = 'available';
                    }
                    $post->save();
                }

                // Phạt điểm người dùng
                if ($claim->user) {
                    $claim->user->penalizeTrustScore(20);
                }
            });
            $this->info("Auto-cancelled Food Claim ID: {$claim->id}");
        }

        // 2. Campaign Donations (Đóng góp chiến dịch)
        // Nếu chiến dịch đã đóng (end_date < now) mà đơn quyên góp vẫn pending -> Tự động hủy
        $expiredDonations = \App\Models\CampaignDonation::where('status', 'pending')
            ->whereHas('campaign', function ($q) {
                $q->where('end_date', '<', now());
            })
            ->get();

        foreach ($expiredDonations as $donation) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($donation) {
                $donation->status = 'cancelled';
                $donation->cancel_reason = 'Hệ thống tự động hủy do chiến dịch đã kết thúc mà hàng chưa được giao';
                $donation->cancelled_by = 'system';
                $donation->save();

                if ($donation->user) {
                    $donation->user->penalizeTrustScore(20);
                }
            });
            $this->info("Auto-cancelled Campaign Donation Code: {$donation->donation_code}");
        }

        $this->info('Check expired transactions completed.');
    }
}
