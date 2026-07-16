<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FoodPostController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\FoodPost;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/home', function () {
    $dbMyClaims = [];
    $dbMyDonations = [];
    if (auth()->check()) {
        $dbMyClaims = \App\Models\FoodClaim::where('user_id', auth()->id())
            ->with(['foodPost.user'])
            ->withExists(['reports as is_disputed' => function($q) {
                $q->where('status', 'pending');
            }])
            ->latest()
            ->get();
        
        $dbMyDonations = \App\Models\CampaignDonation::where('user_id', auth()->id())
            ->with(['campaign.user', 'campaign.items' => function ($query) {
                $query->withSum(['donations as pending_quantity' => function ($q) {
                    $q->where('status', 'pending');
                }], 'donation_quantity');
            }, 'campaignItem'])
            ->withExists(['reports as is_disputed' => function($q) {
                $q->where('status', 'pending');
            }])
            ->where('status', 'pending') // Only show pending donations
            ->latest()
            ->get();
    }
    
    // Load các chiến dịch đã được duyệt để hiển thị ngoài trang chủ
    $campaignQuery = \App\Models\Campaign::with(['user', 'items' => function ($query) {
        $query->withSum(['donations as pending_quantity' => function ($q) {
            $q->where('status', 'pending');
        }], 'donation_quantity');
    }])
        ->where('status', 'active')
        ->where('event_date', '>=', now()->startOfDay());

    if (auth()->check()) {
        $campaignQuery->where('user_id', '!=', auth()->id());
    }

    $dbActiveCampaigns = $campaignQuery->latest()->get()->filter(function ($campaign) {
        return $campaign->items->contains(function ($item) {
            $total = $item->current_quantity + ($item->pending_quantity ?? 0);
            return $total < $item->target_quantity;
        });
    })->values();

    return Inertia::render('Home', [
        'dbMyClaims' => $dbMyClaims,
        'dbMyDonations' => $dbMyDonations,
        'dbActiveCampaigns' => $dbActiveCampaigns
    ]);
})->middleware(['auth', 'verified'])->name('home');

// Cập nhật thông tin shipper cho đơn khuyên góp
Route::patch('/donations/{donation_code}/shipper', [\App\Http\Controllers\CampaignDonationController::class, 'updateShipper'])->middleware(['auth', 'verified'])->name('donations.update_shipper');
Route::post('/donations/{donation_code}/cancel', [\App\Http\Controllers\CampaignDonationController::class, 'cancel'])->middleware(['auth', 'verified'])->name('donations.cancel');

// API Frontend: Tìm đồ ăn xung quanh
Route::get('/api/nearby-food', [FoodPostController::class, 'getNearbyFood']);

// Route quản lý thực phẩm lẻ
Route::get('/food-posts', function () {
    // Tự động ẩn các tin đã quá hạn sử dụng trước khi tải dữ liệu
    FoodPost::where('expires_at', '<', now())
        ->where('status', 'available')
        ->update(['status' => 'expired']);

    // 1. Truy vấn danh sách categories có is_allowed = 1
    $dbCategories = DB::table('categories')->where('is_allowed', 1)->get();
    
    // 2. Truy vấn danh sách food_posts của user đang đăng nhập cùng các yêu cầu xin nhận thực tế (bỏ qua tin đã xóa)
    $dbFoodPosts = FoodPost::where('user_id', auth()->id())
        ->where(function ($query) {
            $query->where('status', '!=', 'hidden')
                  ->orWhere('ai_status', 'flagged');
        })
        ->with(['claims.user'])
        ->get();

    // 3. Truyền dữ liệu sang Inertia component
    return Inertia::render('FoodPosts/Index', [
        'dbCategories' => $dbCategories,
        'dbFoodPosts' => $dbFoodPosts
    ]);
})->middleware('auth')->name('food-posts.index');

Route::post('/food-posts', [FoodPostController::class, 'store'])->middleware('auth')->name('food-posts.store');
Route::post('/food-posts/{post}/toggle-status', [FoodPostController::class, 'toggleStatus'])->middleware('auth')->name('food-posts.toggle-status');
Route::post('/food-posts/{post}', [FoodPostController::class, 'update'])->middleware('auth')->name('food-posts.update');
Route::delete('/food-posts/{post}', [FoodPostController::class, 'destroy'])->middleware('auth')->name('food-posts.destroy');
Route::post('/food-posts/{post}/claim', [FoodPostController::class, 'claim'])->middleware('auth')->name('food-posts.claim');
Route::post('/food-claims/{claim}/status', [FoodPostController::class, 'updateClaimStatus'])->middleware('auth')->name('food-claims.status');
Route::post('/food-claims/{claim}/cancel', [FoodPostController::class, 'cancelClaim'])->middleware('auth')->name('food-claims.cancel');
Route::post('/food-claims/{claim}/complete', [FoodPostController::class, 'completeClaim'])->middleware('auth')->name('food-claims.complete');
Route::patch('/food-claims/{claim}/shipper', [FoodPostController::class, 'updateShipper'])->middleware('auth')->name('food-claims.update_shipper');

Route::post('/api/ai/categorize-food', [\App\Http\Controllers\AiServiceController::class, 'categorizeFood'])->middleware('auth');



// Dynamic dashboard redirector based on User Role & Status
Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user->role === 'admin') {
        return redirect()->route('admin.dashboard');
    } elseif ($user->role === 'charity') {
        return $user->status === 'verified'
            ? redirect()->route('charity.dashboard')
            : redirect()->route('charity.pending');
    } else {
        // personal
        return redirect()->route('home');
    }
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin panel routes
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [\App\Http\Controllers\AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/export-pdf', [\App\Http\Controllers\AdminController::class, 'exportPdf'])->name('admin.export.pdf');
    Route::get('/admin/export-excel', [\App\Http\Controllers\AdminController::class, 'exportExcel'])->name('admin.export.excel');
    Route::post('/admin/users/{user}/verify', [\App\Http\Controllers\AdminController::class, 'verifyUser'])->name('admin.users.verify');
    Route::post('/admin/users/{user}/toggle-ban', [\App\Http\Controllers\AdminController::class, 'toggleBanUser'])->name('admin.users.toggle-ban');
    Route::post('/admin/posts/{post}/moderate', [\App\Http\Controllers\AdminController::class, 'moderatePost'])->name('admin.posts.moderate');
    Route::post('/admin/campaigns/{campaign}/moderate', [\App\Http\Controllers\AdminController::class, 'moderateCampaign'])->name('admin.campaigns.moderate');
    Route::post('/admin/reports/{report}/resolve', [\App\Http\Controllers\ReportController::class, 'resolve'])->name('admin.reports.resolve');
});

// Reports
Route::post('/reports', [\App\Http\Controllers\ReportController::class, 'store'])->middleware('auth')->name('reports.store');

// Charity routes (general group checking for role:charity)
Route::middleware(['auth', 'verified', 'role:charity'])->group(function () {
    // Verified charities only can access the main dashboard
    Route::get('/charity/dashboard', function () {
        if (auth()->user()->status !== 'verified') {
            return redirect()->route('charity.pending');
        }
        $dbMyClaims = \App\Models\FoodClaim::where('user_id', auth()->id())
            ->with(['foodPost.user'])
            ->withExists(['reports as is_disputed' => function($q) {
                $q->where('status', 'pending');
            }])
            ->latest()
            ->get();
            
        $campaignQuery = \App\Models\Campaign::with(['user', 'items' => function ($query) {
            $query->withSum(['donations as pending_quantity' => function ($q) {
                $q->where('status', 'pending');
            }], 'donation_quantity');
        }])
            ->where('status', 'active')
            ->where('event_date', '>=', now()->startOfDay())
            ->where('user_id', '!=', auth()->id());
            
        $dbActiveCampaigns = $campaignQuery->latest()->get()->filter(function ($campaign) {
            return $campaign->items->contains(function ($item) {
                $total = $item->current_quantity + ($item->pending_quantity ?? 0);
                return $total < $item->target_quantity;
            });
        })->values();

        $dbMyDonations = \App\Models\CampaignDonation::where('user_id', auth()->id())
            ->with(['campaign.user', 'campaign.items' => function ($query) {
                $query->withSum(['donations as pending_quantity' => function ($q) {
                    $q->where('status', 'pending');
                }], 'donation_quantity');
            }, 'campaignItem'])
            ->withExists(['reports as is_disputed' => function($q) {
                $q->where('status', 'pending');
            }])
            ->where('status', 'pending')
            ->latest()
            ->get();
            
        return Inertia::render('Charity/Dashboard', [
            'dbMyClaims' => $dbMyClaims,
            'dbActiveCampaigns' => $dbActiveCampaigns,
            'dbMyDonations' => $dbMyDonations
        ]);
    })->name('charity.dashboard');

        // Trang quản lý chiến dịch quyên góp của Mái ấm
    Route::get('/charity/campaigns', function () {
        if (auth()->user()->status !== 'verified') {
            return redirect()->route('charity.pending');
        }
        $campaigns = \App\Models\Campaign::where('user_id', auth()->id())
            ->with('items')
            ->latest()
            ->get();
            
        $dbPendingDonations = \App\Models\CampaignDonation::with(['user', 'campaign', 'campaignItem'])
            ->whereHas('campaign', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->withExists(['reports as is_disputed' => function($q) {
                $q->where('status', 'pending');
            }])
            ->where('status', 'pending')
            ->latest()
            ->get();
            
        return Inertia::render('Charity/Campaigns', [
            'dbCampaigns' => $campaigns,
            'dbPendingDonations' => $dbPendingDonations
        ]);
    })->name('charity.campaigns');


    // Pending charities page
    Route::get('/charity/pending', function () {
        if (auth()->user()->status === 'verified') {
            return redirect()->route('charity.dashboard');
        }
        return Inertia::render('Charity/Pending');
    })->name('charity.pending');
    
    // Trang khởi tạo và lưu chiến dịch quyên góp
    Route::get('/charity/campaigns/create', [\App\Http\Controllers\CampaignController::class, 'create'])->name('charity.campaigns.create');
    Route::post('/charity/campaigns', [\App\Http\Controllers\CampaignController::class, 'store'])->name('charity.campaigns.store');
    
    // Cập nhật chiến dịch
    Route::get('/charity/campaigns/{campaign}/edit', [\App\Http\Controllers\CampaignController::class, 'edit'])->name('charity.campaigns.edit');
    Route::post('/charity/campaigns/{campaign}/update', [\App\Http\Controllers\CampaignController::class, 'update'])->name('charity.campaigns.update');
    
    // Chốt chiến dịch và xuất báo cáo
    Route::post('/charity/campaigns/{campaign}/close', [\App\Http\Controllers\CampaignController::class, 'closeCampaign'])->name('charity.campaigns.close');
    Route::get('/charity/campaigns/{campaign}/export', [\App\Http\Controllers\CampaignController::class, 'exportReport'])->name('charity.campaigns.export');
    
    // Xac nhan don quyen gop
    Route::post('/charity/donations/{donationCode}/verify', [\App\Http\Controllers\CampaignDonationController::class, 'verify'])->name('charity.donations.verify');
    Route::post('/charity/donations/{donationCode}/reject', [\App\Http\Controllers\CampaignDonationController::class, 'rejectDonation'])->name('charity.donations.reject');
});

Route::middleware('auth')->group(function () {
    // Gui don quyen gop vao campaign
    Route::post('/campaigns/{campaign}/donate', [\App\Http\Controllers\CampaignDonationController::class, 'store'])->name('campaign-donations.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/notifications/{id}/read', function ($id) {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true]);
        }
        return back();
    })->name('notifications.read');
});

require __DIR__.'/auth.php';

// --- QR Login Mock ---
Route::get('/qr-scanner-mock', function () {
    return Inertia::render('Auth/QrScannerMock');
})->middleware('auth')->name('qr.scanner');

Route::post('/qr-verify', function (\Illuminate\Http\Request $request) {
    $request->validate(['token' => 'required|string']);
    
    // Broadcast sự kiện
    broadcast(new \App\Events\QrLoginSuccessful($request->token, auth()->id()));
    
    return back()->with('status', 'Quét mã thành công! Trình duyệt bên kia đang đăng nhập...');
})->middleware('auth')->name('qr.verify');

// Bí mật: route để login nhanh từ QR (chỉ dùng cho Demo trên cùng 1 máy tính)
Route::post('/qr-login-callback', function (\Illuminate\Http\Request $request) {
    $request->validate(['user_id' => 'required|exists:users,id']);
    
    // Đăng nhập User ID này
    auth()->loginUsingId($request->user_id);
    
    return redirect()->intended(route('dashboard'));
})->name('qr.login.callback');
