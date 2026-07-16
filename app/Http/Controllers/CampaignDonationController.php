<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\CampaignDonation;
use App\Models\CampaignItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class CampaignDonationController extends Controller
{
    public function store(Request $request, $campaignId)
    {
        $user = auth()->user();

        // 1. Kiểm tra tài khoản có bị khóa giao dịch không
        if ($user->isLocked()) {
            return back()->with('error', 'Tài khoản của bạn đang bị khóa giao dịch do điểm uy tín thấp. Vui lòng thử lại sau khi hết hạn khóa.');
        }

        // 2. Max Pending Limit: Tối đa 2 đơn quyên góp pending
        $pendingDonationsCount = CampaignDonation::where('user_id', $user->id)
            ->where('status', 'pending')
            ->distinct('donation_code') // Đếm theo mã đơn (vì 1 mã đơn có thể có nhiều items)
            ->count('donation_code');
            
        if ($pendingDonationsCount >= 2) {
            return back()->with('error', 'Bạn đang có 2 đơn quyên góp Đang chờ xử lý. Vui lòng đợi xác nhận hoặc hủy bớt trước khi tạo đơn mới.');
        }

        // 3. Rate Limiter (Cooldown 3 phút cho cùng 1 chiến dịch)
        $rateLimitKey = 'donate_campaign:' . $user->id . ':' . $campaignId;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 2)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);
            return back()->with('error', "Bạn thao tác quá nhanh trên chiến dịch này. Vui lòng thử lại sau {$minutes} phút.");
        }
        RateLimiter::hit($rateLimitKey, 180); // 180 giây = 3 phút

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.campaign_item_id' => 'required|exists:campaign_items,id',
            'items.*.donation_quantity' => 'required|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'food_description' => 'nullable|string',
            'shipping_method' => 'required|in:self_delivery,delivery_service',
        ]);

        $donationCode = 'SF-' . strtoupper(\Illuminate\Support\Str::random(6));

        $error = null;
        $campaign = Campaign::findOrFail($campaignId);
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $campaign, $donationCode, &$error) {
            foreach ($validated['items'] as $item) {
                $campaignItem = CampaignItem::withSum(['donations as pending_quantity' => function ($q) {
                    $q->where('status', 'pending');
                }], 'donation_quantity')->lockForUpdate()->findOrFail($item['campaign_item_id']);

                // Backend Validation: Chặn đồ tươi trước web_deadline
                if ($campaignItem->item_type === 'fresh' && now()->isBefore($campaign->web_deadline)) {
                    $error = "Vật phẩm '{$campaignItem->item_name}' là đồ tươi. Chỉ mở nhận quyên góp ở Mốc 2 (sau " . $campaign->web_deadline->format('d/m/Y H:i') . ").";
                    return; // exit loop
                }

                $total = $campaignItem->current_quantity + ($campaignItem->pending_quantity ?? 0);
                $remaining = max(0, $campaignItem->target_quantity - $total);

                if ($item['donation_quantity'] > $remaining) {
                    $error = "Số lượng quyên góp cho {$campaignItem->item_name} vượt quá số lượng cần thiết (còn thiếu {$remaining}).";
                    return; // exit loop
                }

                // Tính toán expires_at
                $maxHours = $campaignItem->item_type === 'fresh' ? 24 : 48;
                $maxExpiryDate = now()->addHours($maxHours);
                $eventDate = \Carbon\Carbon::parse($campaign->event_date);
                
                $expiresAt = $maxExpiryDate->min($eventDate);

                CampaignDonation::create([
                    'campaign_id' => $campaign->id,
                    'user_id' => auth()->id(),
                    'campaign_item_id' => $item['campaign_item_id'],
                    'donation_quantity' => $item['donation_quantity'],
                    'unit' => $item['unit'] ?? null,
                    'food_description' => $validated['food_description'],
                    'shipping_method' => $validated['shipping_method'],
                    'donation_code' => $donationCode,
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                ]);
            }
        });

        if ($error) {
            return back()->with('error', $error);
        }


        // Gửi thông báo cho chủ chiến dịch
        $campaign = Campaign::with('user')->find($campaignId);
        if ($campaign && $campaign->user) {
            $campaign->user->notify(new \App\Notifications\NewDonationNotification(
                $donationCode,
                auth()->user()->name,
                $campaign->title,
                count($validated['items'])
            ));
        }

        return back()->with('success', 'Gửi yêu cầu quyên góp thành công! Mã đơn của bạn là: ' . $donationCode);
    }

    public function verify($donationCode)
    {
        $donations = CampaignDonation::where('donation_code', $donationCode)
            ->whereHas('campaign', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->where('status', 'pending')
            ->get();
            
        if ($donations->isEmpty()) {
            return back()->with('error', 'Đơn này đã được xử lý hoặc không tồn tại.');
        }
        
        $firstDonation = $donations->first();
        if ($firstDonation->shipping_method === 'delivery_service' && empty($firstDonation->shipper_name)) {
            return back()->with('error', 'Không thể xác nhận vì nhà hảo tâm chưa cập nhật thông tin shipper.');
        }

        DB::transaction(function () use ($donations) {
            foreach ($donations as $donation) {
                $donation->status = 'completed';
                $donation->save();

                $campaignItem = CampaignItem::lockForUpdate()->findOrFail($donation->campaign_item_id);
                $campaignItem->current_quantity += $donation->donation_quantity;
                $campaignItem->save();
            }

            // Reward trust score to the donor
            $firstDonation = $donations->first();
            $donor = \App\Models\User::find($firstDonation->user_id);
            if ($donor) {
                $donor->addTrustScore(10);
                $campaignTitle = $firstDonation->campaign->title ?? 'Chiến dịch từ thiện';
                $donor->notify(new \App\Notifications\TrustScoreRewarded(10, 'Quyên góp thành công', "Bạn được cộng 10 điểm uy tín vì đã quyên góp thành công cho chiến dịch \"{$campaignTitle}\"."));
            }
        });

        // Mark corresponding notification as read
        auth()->user()->unreadNotifications
            ->where('type', 'App\Notifications\NewDonationNotification')
            ->each(function ($notification) use ($donationCode) {
                if (isset($notification->data['donation_code']) && $notification->data['donation_code'] === $donationCode) {
                    $notification->markAsRead();
                }
            });

        return back()->with('success', 'Đã xác nhận nhận hàng và cập nhật tiến độ chiến dịch! Cộng 10 điểm uy tín cho người quyên góp.');
    }

    public function updateShipper(Request $request, $donationCode)
    {
        $validated = $request->validate([
            'shipper_name' => 'required|string|max:255',
            'shipper_license_plate' => 'required|string|max:50',
        ]);

        $updated = CampaignDonation::where('user_id', auth()->id())
            ->where('donation_code', $donationCode)
            ->where('status', 'pending')
            ->where('shipping_method', 'delivery_service')
            ->update([
                'shipper_name' => $validated['shipper_name'],
                'shipper_license_plate' => $validated['shipper_license_plate'],
            ]);

        if ($updated) {
            return back()->with('success', 'Đã cập nhật thông tin tài xế thành công!');
        }

        return back()->with('error', 'Không tìm thấy đơn quyên góp phù hợp hoặc không thể cập nhật.');
    }

    public function cancel(Request $request, $donationCode)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $donations = CampaignDonation::where('user_id', auth()->id())
            ->where('donation_code', $donationCode)
            ->where('status', 'pending')
            ->get();
            
        if ($donations->isEmpty()) {
            return back()->with('error', 'Không tìm thấy đơn quyên góp phù hợp hoặc không thể hủy.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($donations, $validated) {
            foreach ($donations as $donation) {
                $donation->status = 'cancelled';
                $donation->cancel_reason = $validated['reason'];
                $donation->cancelled_by = auth()->id();
                $donation->save();
            }
            
            $firstDonation = $donations->first();
            $minutesSinceCreated = $firstDonation->created_at->diffInMinutes(now());
            
            // Trừ 10 điểm uy tín nếu hủy SAU 10 phút (thời gian ân hạn)
            if ($minutesSinceCreated > 10) {
                $user = auth()->user();
                $user->penalizeTrustScore(10);
                $campaignTitle = $firstDonation->campaign->title ?? 'Chiến dịch từ thiện';
                $user->notify(new \App\Notifications\TrustScorePenalized(10, 'Hủy đơn quyên góp', "Bạn bị trừ 10 điểm uy tín do tự hủy đơn quyên góp cho chiến dịch \"{$campaignTitle}\" quá muộn (vượt quá 10 phút ân hạn)."));
            }
        });

        return back()->with('success', 'Đã hủy đơn quyên góp thành công!');
    }

    public function rejectDonation(Request $request, $donationCode)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $donations = CampaignDonation::where('donation_code', $donationCode)
            ->whereHas('campaign', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->where('status', 'pending')
            ->get();
            
        if ($donations->isEmpty()) {
            return back()->with('error', 'Đơn này đã được xử lý hoặc không tồn tại.');
        }

        // Apply penalty to the donor
        $donor = \App\Models\User::find($donations->first()->user_id);
        if ($donor && in_array($request->reason, ['Spam/Phá bĩnh', 'Không giao hàng', 'Liên lạc không được'])) {
            $donor->penalizeTrustScore(20);
            $campaignTitle = $donations->first()->campaign->title ?? 'Chiến dịch từ thiện';
            $donor->notify(new \App\Notifications\TrustScorePenalized(20, $request->reason, "Đơn quyên góp cho '{$campaignTitle}' đã bị từ chối."));
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($donations, $request) {
            foreach ($donations as $donation) {
                $donation->status = 'cancelled';
                $donation->cancel_reason = $request->reason;
                $donation->cancelled_by = auth()->id();
                $donation->save();
            }
        });

        // Mark corresponding notification as read
        auth()->user()->unreadNotifications
            ->where('type', 'App\Notifications\NewDonationNotification')
            ->each(function ($notification) use ($donationCode) {
                if (isset($notification->data['donation_code']) && $notification->data['donation_code'] === $donationCode) {
                    $notification->markAsRead();
                }
            });

        return back()->with('success', 'Đã từ chối đơn quyên góp thành công!');
    }
}
