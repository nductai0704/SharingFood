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
    private function getStatsData(Request $request)
    {
        $period = $request->input('period', '7days'); // Mặc định 7 ngày
        
        $applyFilter = function($query) use ($period, $request) {
            if ($period === 'today') {
                return $query->whereDate('created_at', today());
            } elseif ($period === '7days') {
                return $query->where('created_at', '>=', now()->subDays(7));
            } elseif ($period === '30days') {
                return $query->where('created_at', '>=', now()->subDays(30));
            } elseif ($period === 'custom') {
                if ($request->filled('start_date')) {
                    $query->whereDate('created_at', '>=', $request->input('start_date'));
                }
                if ($request->filled('end_date')) {
                    $query->whereDate('created_at', '<=', $request->input('end_date'));
                }
                return $query;
            }
            // 'all' thì không filter
            return $query;
        };

        $totalUsers = $applyFilter(User::query())->count();
        $totalFoodPosts = $applyFilter(\App\Models\FoodPost::query())->count();
        
        $claimsStats = $applyFilter(\App\Models\FoodClaim::query())->selectRaw('
            count(*) as total,
            sum(case when status = "completed" then 1 else 0 end) as completed_count,
            sum(case when status = "pending" then 1 else 0 end) as pending_count,
            sum(case when status = "cancelled" then 1 else 0 end) as cancelled_count
        ')->first();

        // 2. BÓC TÁCH CHIẾN DỊCH QUYÊN GÓP (TÍNH THEO SỐ LƯỢNG)
        $donationsStats = $applyFilter(\App\Models\CampaignDonation::query())->selectRaw('
            count(*) as total_donations,
            sum(case when status = "completed" then donation_quantity else 0 end) as completed_quantity,
            sum(case when status = "pending" then donation_quantity else 0 end) as pending_quantity
        ')->first();

        $totalCampaigns = $applyFilter(Campaign::query())->count();
        $rescuedFoodVolume = $applyFilter(\App\Models\FoodClaim::where('status', 'completed'))->sum('quantity') 
                           + ($donationsStats->completed_quantity ?? 0);

        return [
            'total_users' => $totalUsers,
            'total_food_posts' => $totalFoodPosts,
            'total_campaigns' => $totalCampaigns,
            'claims_breakdown' => $claimsStats,
            'donations_breakdown' => $donationsStats,
            'rescued_volume' => $rescuedFoodVolume,
            'success_rate' => ($claimsStats->total ?? 0) > 0 ? round((($claimsStats->completed_count ?? 0) / $claimsStats->total) * 100, 1) . '%' : '0%',
        ];
    }

    public function index(Request $request)
    {
        $stats = $this->getStatsData($request);

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

    public function exportExcel(Request $request)
    {
        $stats = $this->getStatsData($request);

        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Thong Ke</x:Name>
                            <x:WorksheetOptions>
                                <x:DisplayGridlines/>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
        </head>
        <body>
            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th style="background-color: #10b981; color: white; font-weight: bold; width: 350px;">LOẠI DỮ LIỆU</th>
                    <th style="background-color: #10b981; color: white; font-weight: bold; width: 200px;">SỐ LƯỢNG / THÔNG SỐ</th>
                </tr>
                <tr><td>Tổng số người dùng tham gia</td><td>' . $stats['total_users'] . '</td></tr>
                <tr><td>Tổng số bài đăng chia sẻ thực phẩm</td><td>' . $stats['total_food_posts'] . '</td></tr>
                <tr><td>Tổng số chiến dịch quyên góp</td><td>' . $stats['total_campaigns'] . '</td></tr>
                <tr><td>Sản lượng thực phẩm đã giải cứu thành công</td><td>' . $stats['rescued_volume'] . ' KG</td></tr>
                <tr><td>Tỷ lệ giao dịch hoàn thành (Thành công)</td><td>' . $stats['success_rate'] . '</td></tr>
                
                <tr><td colspan="2"></td></tr>
                
                <tr>
                    <th style="background-color: #f3f4f6; font-weight: bold; text-align: left;">CHI TIẾT YÊU CẦU NHẬN THỰC PHẨM LẺ</th>
                    <th style="background-color: #f3f4f6;"></th>
                </tr>
                <tr><td>- Đã hoàn thành</td><td>' . ($stats['claims_breakdown']->completed_count ?? 0) . '</td></tr>
                <tr><td>- Đang chờ xử lý / Chờ xác nhận</td><td>' . ($stats['claims_breakdown']->pending_count ?? 0) . '</td></tr>
                <tr><td>- Đã hủy / Thất bại</td><td>' . ($stats['claims_breakdown']->cancelled_count ?? 0) . '</td></tr>
                
                <tr><td colspan="2"></td></tr>
                
                <tr>
                    <th style="background-color: #f3f4f6; font-weight: bold; text-align: left;">CHI TIẾT QUYÊN GÓP VÀO CHIẾN DỊCH</th>
                    <th style="background-color: #f3f4f6;"></th>
                </tr>
                <tr><td>- Khối lượng đã hoàn thành (Nhận thành công)</td><td>' . ($stats['donations_breakdown']->completed_quantity ?? 0) . ' KG</td></tr>
                <tr><td>- Khối lượng đang chờ xử lý</td><td>' . ($stats['donations_breakdown']->pending_quantity ?? 0) . ' KG</td></tr>
            </table>
        </body>
        </html>';

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="Thong_ke_SharingFood.xls"');
    }

    public function exportPdf(Request $request)
    {
        $stats = $this->getStatsData($request);
        $period = $request->input('period', '7days');
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.pdf.report', compact('stats', 'period'));
        return $pdf->download('Bao_cao_SharingFood.pdf');
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
