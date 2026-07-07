<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\CampaignDonation;
use App\Models\CampaignItem;
use Illuminate\Support\Facades\DB;

class CampaignDonationController extends Controller
{
    public function store(Request $request, $campaignId)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.campaign_item_id' => 'required|exists:campaign_items,id',
            'items.*.donation_quantity' => 'required|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'food_description' => 'nullable|string',
            'shipping_method' => 'required|in:self_delivery,delivery_service',
        ]);

        $donationCode = 'SF-' . strtoupper(\Illuminate\Support\Str::random(6));

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $campaignId, $donationCode) {
            foreach ($validated['items'] as $item) {
                CampaignDonation::create([
                    'campaign_id' => $campaignId,
                    'user_id' => auth()->id(),
                    'campaign_item_id' => $item['campaign_item_id'],
                    'donation_quantity' => $item['donation_quantity'],
                    'unit' => $item['unit'] ?? null,
                    'food_description' => $validated['food_description'],
                    'shipping_method' => $validated['shipping_method'],
                    'donation_code' => $donationCode,
                    'status' => 'pending',
                ]);
            }
        });

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
        });

        return back()->with('success', 'Đã xác nhận nhận hàng và cập nhật tiến độ chiến dịch!');
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
}
