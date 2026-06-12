<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FoodPostController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

// API Frontend: Tìm đồ ăn xung quanh
Route::get('/api/nearby-food', [FoodPostController::class, 'getNearbyFood']);

// Route quản lý và đăng tặng thực phẩm lẻ (Cả cá nhân, doanh nghiệp và mái ấm)
Route::get('/food-posts', function () {
    return Inertia::render('FoodPosts/Index');
})->name('food-posts.index');



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
        return Inertia::render('Charity/Dashboard');
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
