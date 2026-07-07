<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard with overview statistics and user data.
     */
    public function index()
    {
        $totalUsers = User::count();
        $totalFoodPosts = \App\Models\FoodPost::count();
        
        $claimsStats = \App\Models\FoodClaim::selectRaw('
            count(*) as total,
            sum(case when status = "completed" then 1 else 0 end) as completed_count,
            sum(case when status = "pending" then 1 else 0 end) as pending_count,
            sum(case when status = "cancelled" then 1 else 0 end) as cancelled_count
        ')->first();

        // 2. BÓC TÁCH CHIẾN DỊCH QUYÊN GÓP (TÍNH THEO SỐ LƯỢNG)
        $donationsStats = \App\Models\CampaignDonation::selectRaw('
            count(*) as total_donations,
            sum(case when status = "completed" then donation_quantity else 0 end) as completed_quantity,
            sum(case when status = "pending" then donation_quantity else 0 end) as pending_quantity
        ')->first();

        $totalCampaigns = Campaign::count();
        $rescuedFoodVolume = \App\Models\FoodClaim::where('status', 'completed')->sum('quantity') 
                           + ($donationsStats->completed_quantity ?? 0);

        $stats = [
            'total_users' => $totalUsers,
            'total_food_posts' => $totalFoodPosts,
            'total_campaigns' => $totalCampaigns,
            'claims_breakdown' => $claimsStats,
            'donations_breakdown' => $donationsStats,
            'rescued_volume' => $rescuedFoodVolume,
            'success_rate' => ($claimsStats->total ?? 0) > 0 ? round((($claimsStats->completed_count ?? 0) / $claimsStats->total) * 100, 1) . '%' : '0%',
        ];

        // Chart Data: 7 ngày qua
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            return now()->subDays($daysAgo)->format('Y-m-d');
        });

        $foodPostsChart = [];
        $campaignsChart = [];
        $usersChart = [];
        foreach ($last7Days as $date) {
            $foodPostsChart[] = \App\Models\FoodPost::whereDate('created_at', $date)->count();
            $campaignsChart[] = Campaign::whereDate('created_at', $date)->count();
            $usersChart[] = User::whereDate('created_at', $date)->count();
        }

        $shippingMethods = \App\Models\FoodClaim::selectRaw('shipping_method, count(*) as total')
            ->whereNotNull('shipping_method')
            ->groupBy('shipping_method')
            ->pluck('total', 'shipping_method')
            ->toArray();

        $chartData = [
            'labels' => $last7Days->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))->toArray(),
            'food_posts' => $foodPostsChart,
            'campaigns' => $campaignsChart,
            'users' => $usersChart,
            'transport_methods' => [
                'labels' => ['Tự đến lấy', 'Nhờ người thân', 'Gọi xe công nghệ'],
                'data' => [
                    $shippingMethods['self_pickup'] ?? 0,
                    $shippingMethods['relative_pickup'] ?? 0,
                    $shippingMethods['delivery_service'] ?? 0,
                ]
            ]
        ];

        $users = User::with('documents')->orderBy('created_at', 'desc')->get();

        // 5. NHẬT KÝ HOẠT ĐỘNG GẦN ĐÂY (5 hành động mới nhất)
        $recentActivities = \App\Models\SystemLog::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'time' => $log->created_at->diffForHumans(),
                    'type' => $log->action,
                    'details' => $log->description,
                    'status' => $log->user ? $log->user->name : 'Hệ thống',
                    'created_at' => $log->created_at
                ];
            });

        // Lấy danh sách nhật ký hệ thống kèm thông tin user thực hiện (cho Tab Logs)
        $systemLogs = \App\Models\SystemLog::with('user')->orderBy('created_at', 'desc')->take(100)->get();

        // Danh sách chiến dịch chờ duyệt và đã duyệt
        $pendingCampaigns = Campaign::with(['user', 'items'])->where('status', 'pending')->orderBy('created_at', 'desc')->get();
        $activeCampaigns = Campaign::with(['user', 'items'])->where('status', 'active')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'chartData' => $chartData,
            'recentActivities' => $recentActivities,
            'users' => $users,
            'pendingCampaigns' => $pendingCampaigns,
            'activeCampaigns' => $activeCampaigns,
            'systemLogs' => $systemLogs,
        ]);
    }

    /**
     * Phê duyệt hoặc từ chối cấp quyền cho Tổ chức từ thiện.
     */
    public function verifyUser(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:verified,rejected',
        ]);

        $user->update([
            'status' => $request->status,
        ]);

        // Ghi nhật ký hệ thống
        \App\Models\SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'CHARITY_VERIFICATION',
            'description' => "Admin đã cập nhật trạng thái xác minh của tổ chức từ thiện '{$user->name}' (ID: {$user->id}) thành: " . ($request->status === 'verified' ? 'Đã xác thực' : 'Từ chối') . ".",
            'ip_address' => $request->ip(),
            'created_at' => now()
        ]);

        return redirect()->back()->with('success', 'Đã cập nhật trạng thái Tổ chức từ thiện thành công.');
    }

    /**
     * Khóa hoặc mở khóa tài khoản.
     */
    public function toggleBanUser(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:banned,verified,pending',
        ]);

        $user->update([
            'status' => $request->status,
        ]);

        // Ghi nhật ký hệ thống
        \App\Models\SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'USER_BAN_TOGGLE',
            'description' => "Admin đã cập nhật trạng thái tài khoản '{$user->name}' (ID: {$user->id}) thành: " . ($request->status === 'banned' ? 'Đã khóa' : 'Hoạt động/Chờ duyệt') . ".",
            'ip_address' => $request->ip(),
            'created_at' => now()
        ]);

        return redirect()->back()->with('success', 'Đã cập nhật trạng thái tài khoản thành công.');
    }

    /**
     * Xử lý bài đăng vi phạm (placeholder).
     */
    public function moderatePost(Request $request, $postId)
    {
        // TODO: Cập nhật logic cho FoodPost khi có model
        return redirect()->back();
    }

    /**
     * Xử lý duyệt chiến dịch.
     */
    public function moderateCampaign(Request $request, $campaignId)
    {
        $request->validate([
            'status' => 'required|in:active,rejected',
        ]);

        $campaign = Campaign::findOrFail($campaignId);
        $campaign->update([
            'status' => $request->status,
        ]);

        \App\Models\SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'CAMPAIGN_MODERATION',
            'description' => "Admin đã " . ($request->status === 'active' ? 'phê duyệt' : 'từ chối') . " xuất bản chiến dịch '{$campaign->title}' (ID: {$campaign->id}).",
            'ip_address' => $request->ip(),
            'created_at' => now()
        ]);

        return redirect()->back()->with('success', 'Đã xử lý chiến dịch thành công.');
    }
}
