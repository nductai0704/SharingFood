<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FoodPostController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\FoodPost;
use Inertia\Inertia;

Route::get('/', function () {
    $dbMyClaims = [];
    if (auth()->check()) {
        $dbMyClaims = \App\Models\FoodClaim::where('user_id', auth()->id())
            ->with(['foodPost.user'])
            ->latest()
            ->get();
    }
    return Inertia::render('Home', [
        'dbMyClaims' => $dbMyClaims
    ]);
});

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
    
    // 2. Truy vấn danh sách food_posts của user đang đăng nhập cùng các yêu cầu xin nhận thực tế
    $dbFoodPosts = FoodPost::where('user_id', auth()->id())
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
Route::post('/food-posts/{post}/claim', [FoodPostController::class, 'claim'])->middleware('auth')->name('food-posts.claim');
Route::post('/food-claims/{claim}/status', [FoodPostController::class, 'updateClaimStatus'])->middleware('auth')->name('food-claims.status');
Route::post('/food-claims/{claim}/cancel', [FoodPostController::class, 'cancelClaim'])->middleware('auth')->name('food-claims.cancel');
Route::post('/food-claims/{claim}/complete', [FoodPostController::class, 'completeClaim'])->middleware('auth')->name('food-claims.complete');



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
        // personal or small_business
        return redirect('/');
    }
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin panel routes
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [\App\Http\Controllers\AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/users/{user}/verify', [\App\Http\Controllers\AdminController::class, 'verifyUser'])->name('admin.users.verify');
    Route::post('/admin/users/{user}/toggle-ban', [\App\Http\Controllers\AdminController::class, 'toggleBanUser'])->name('admin.users.toggle-ban');
    Route::post('/admin/posts/{post}/moderate', [\App\Http\Controllers\AdminController::class, 'moderatePost'])->name('admin.posts.moderate');
    Route::post('/admin/campaigns/{campaign}/moderate', [\App\Http\Controllers\AdminController::class, 'moderateCampaign'])->name('admin.campaigns.moderate');
});

// Charity routes (general group checking for role:charity)
Route::middleware(['auth', 'verified', 'role:charity'])->group(function () {
    // Verified charities only can access the main dashboard
    Route::get('/charity/dashboard', function () {
        if (auth()->user()->status !== 'verified') {
            return redirect()->route('charity.pending');
        }
        $dbMyClaims = \App\Models\FoodClaim::where('user_id', auth()->id())
            ->with(['foodPost.user'])
            ->latest()
            ->get();
        return Inertia::render('Charity/Dashboard', [
            'dbMyClaims' => $dbMyClaims
        ]);
    })->name('charity.dashboard');

        // Trang quản lý chiến dịch quyên góp của Mái ấm
    Route::get('/charity/campaigns', function () {
        if (auth()->user()->status !== 'verified') {
            return redirect()->route('charity.pending');
        }
        return Inertia::render('Charity/Campaigns');
    })->name('charity.campaigns');


    // Pending charities page
    Route::get('/charity/pending', function () {
        if (auth()->user()->status === 'verified') {
            return redirect()->route('charity.dashboard');
        }
        return Inertia::render('Charity/Pending');
    })->name('charity.pending');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
