<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\FoodPost;
use App\Models\FoodClaim;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'reported_user_id' => 'required|exists:users,id',
            'food_post_id' => 'nullable|exists:food_posts,id',
            'food_claim_id' => 'nullable|exists:food_claims,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'campaign_donation_id' => 'nullable|exists:campaign_donations,id',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string',
            'proof_images' => 'nullable|array|max:5',
            'proof_images.*' => 'image|max:5120', // Max 5MB per image
        ]);

        if ($request->food_claim_id || $request->campaign_donation_id) {
            $request->validate([
                'proof_images' => 'required|array|min:1',
            ], [
                'proof_images.required' => 'Vui lòng cung cấp hình ảnh bằng chứng cho giao dịch này.',
            ]);
        }

        $proofImagePaths = [];
        if ($request->hasFile('proof_images')) {
            foreach ($request->file('proof_images') as $file) {
                $path = $file->store('reports', 'public');
                $proofImagePaths[] = '/storage/' . $path;
            }
        }

        $report = Report::create([
            'reporter_id' => auth()->id(),
            'reported_user_id' => $request->reported_user_id,
            'food_post_id' => $request->food_post_id,
            'food_claim_id' => $request->food_claim_id,
            'campaign_id' => $request->campaign_id,
            'campaign_donation_id' => $request->campaign_donation_id,
            'reason' => $request->reason,
            'details' => $request->details,
            'proof_image' => $proofImagePaths,
            'status' => 'pending',
        ]);

        // Logic Ẩn bài đăng nếu có >= 2 reports
        if ($request->food_post_id) {
            $pendingReportsCount = Report::where('food_post_id', $request->food_post_id)
                                         ->where('status', 'pending')
                                         ->count();
            if ($pendingReportsCount >= 2) {
                $post = FoodPost::find($request->food_post_id);
                if ($post && $post->status === 'available') {
                    $post->status = 'under_review';
                    $post->save();
                }
            }
        }

        return redirect()->back()->with('success', 'Đã gửi báo cáo vi phạm. Chúng tôi sẽ xem xét trong thời gian sớm nhất!');
    }

    public function resolve(Request $request, Report $report)
    {
        $request->validate([
            'action' => 'required|in:dismiss,penalize_reporter,penalize_20,penalize_50,ban_user',
        ]);

        DB::transaction(function () use ($request, $report) {
            $report->status = 'resolved';
            $report->resolved_by = auth()->id();
            $report->resolved_at = now();
            $report->save();

            $reportedUser = $report->reportedUser;
            $reporter = $report->reporter;

            switch ($request->action) {
                case 'dismiss':
                    $report->status = 'dismissed';
                    $report->save();
                    break;
                case 'penalize_reporter':
                    // Vu khống/Spam
                    if ($reporter) {
                        $reporter->penalizeTrustScore(20);
                        $reporter->notify(new \App\Notifications\TrustScorePenalized(20, 'Báo cáo sai sự thật / Vu khống', 'Bạn bị trừ 20 điểm uy tín do gửi báo cáo vi phạm không đúng sự thật.'));
                    }
                    $report->status = 'dismissed';
                    $report->save();
                    break;
                case 'penalize_20':
                    if ($reportedUser) {
                        $reportedUser->penalizeTrustScore(20);
                        $reportedUser->notify(new \App\Notifications\TrustScorePenalized(20, 'Vi phạm quy định', 'Bạn bị trừ 20 điểm uy tín do bài đăng thực phẩm bị báo cáo vi phạm. Lỗi: ' . $report->reason));
                    }
                    break;
                case 'penalize_50':
                    if ($reportedUser) {
                        $reportedUser->penalizeTrustScore(50);
                        $reportedUser->notify(new \App\Notifications\TrustScorePenalized(50, 'Vi phạm nghiêm trọng', 'Bạn bị trừ 50 điểm uy tín do bài đăng thực phẩm bị báo cáo vi phạm nghiêm trọng. Lỗi: ' . $report->reason));
                    }
                    break;
                case 'ban_user':
                    if ($reportedUser) {
                        $reportedUser->is_locked = true;
                        $reportedUser->locked_until = now()->addYears(100); // Vĩnh viễn
                        $reportedUser->save();
                    }
                    break;
            }

            // Restore post if dismissed and no other pending reports
            if ($report->food_post_id && in_array($request->action, ['dismiss', 'penalize_reporter'])) {
                $pendingCount = Report::where('food_post_id', $report->food_post_id)->where('status', 'pending')->count();
                if ($pendingCount == 0) {
                    $post = FoodPost::find($report->food_post_id);
                    if ($post && $post->status === 'under_review') {
                        $post->status = 'available';
                        $post->save();
                    }
                }
            }

            // Hide post and cancel pending claims if penalized
            if ($report->food_post_id && in_array($request->action, ['penalize_20', 'penalize_50', 'ban_user'])) {
                $post = FoodPost::find($report->food_post_id);
                if ($post && in_array($post->status, ['available', 'under_review'])) {
                    $post->status = 'hidden';
                    $post->save();

                    // Cancel pending and approved claims
                    $pendingClaims = FoodClaim::where('food_post_id', $post->id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->get();
                    
                    foreach ($pendingClaims as $claim) {
                        $claim->status = 'cancelled';
                        $claim->cancel_reason = 'Bài đăng đã bị gỡ bỏ do vi phạm quy chuẩn cộng đồng';
                        $claim->cancelled_by = 'system';
                        $claim->save();

                        \App\Models\SystemLog::create([
                            'action' => 'Hủy yêu cầu do gỡ bài (Bị Phạt)',
                            'description' => "Yêu cầu nhận của người dùng " . ($claim->user ? $claim->user->name : 'Người dùng') . " đã bị tự động hủy do bài đăng \"{$post->title}\" bị gỡ bỏ vì vi phạm quy chuẩn.",
                            'user_id' => auth()->id(),
                            'ip_address' => request()->ip(),
                            'created_at' => now()
                        ]);
                    }
                }

                // Tự động đóng (resolve) các báo cáo khác cùng bài viết để Admin không phải duyệt lại
                Report::where('food_post_id', $report->food_post_id)
                    ->where('id', '!=', $report->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'resolved',
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
            }

            // Hide campaign and cancel pending donations if penalized
            if ($report->campaign_id && in_array($request->action, ['penalize_20', 'penalize_50', 'ban_user'])) {
                $campaign = \App\Models\Campaign::find($report->campaign_id);
                if ($campaign && in_array($campaign->status, ['active'])) {
                    $campaign->status = 'closed';
                    $campaign->save();

                    // Cancel pending donations
                    $pendingDonations = \App\Models\CampaignDonation::where('campaign_id', $campaign->id)
                        ->where('status', 'pending')
                        ->get();
                    
                    foreach ($pendingDonations as $donation) {
                        $donation->status = 'cancelled';
                        $donation->cancel_reason = 'Hủy tự động: Chiến dịch bị đình chỉ do vi phạm quy chuẩn';
                        $donation->cancelled_by = 'system';
                        $donation->save();

                        \App\Models\SystemLog::create([
                            'action' => 'Hủy đơn đóng góp do gỡ chiến dịch (Bị Phạt)',
                            'description' => "Đơn đóng góp của " . ($donation->user ? $donation->user->name : 'Người dùng') . " đã bị tự động hủy do chiến dịch \"{$campaign->title}\" bị gỡ bỏ vì vi phạm quy chuẩn.",
                            'user_id' => auth()->id(),
                            'ip_address' => request()->ip(),
                            'created_at' => now()
                        ]);

                        if ($donation->user) {
                            $donation->user->notify(new \App\Notifications\CampaignCancelledNotification($campaign->title, $report->reason));
                        }
                    }
                }

                // Tự động đóng (resolve) các báo cáo khác cùng chiến dịch để Admin không phải duyệt lại
                Report::where('campaign_id', $report->campaign_id)
                    ->where('id', '!=', $report->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'resolved',
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
            }
        });

        return redirect()->back()->with('success', 'Đã xử lý báo cáo thành công.');
    }
}
