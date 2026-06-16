<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard with overview statistics and user data.
     */
    public function index()
    {
        // Thống kê giả lập tạm thời (sau này sẽ query từ bảng food_posts, campaigns, v.v.)
        $stats = [
            'new_users_count' => User::whereMonth('created_at', now()->month)->count(),
            'posts_today' => 42,
            'success_rate' => '87.5%',
            'active_campaigns' => 5,
        ];

        // Lấy danh sách users và load kèm tài liệu (documents)
        $users = User::with('documents')->orderBy('created_at', 'desc')->get();

        // Lấy danh sách nhật ký hệ thống kèm thông tin user thực hiện
        $systemLogs = \App\Models\SystemLog::with('user')->orderBy('created_at', 'desc')->take(100)->get();

        // Danh sách giả lập cho bài đăng vi phạm và chiến dịch chờ duyệt
        $flaggedPosts = [];
        $pendingCampaigns = [];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'users' => $users,
            'flaggedPosts' => $flaggedPosts,
            'pendingCampaigns' => $pendingCampaigns,
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
     * Xử lý duyệt chiến dịch (placeholder).
     */
    public function moderateCampaign(Request $request, $campaignId)
    {
         // TODO: Cập nhật logic cho Campaign khi có model
        return redirect()->back();
    }
}
