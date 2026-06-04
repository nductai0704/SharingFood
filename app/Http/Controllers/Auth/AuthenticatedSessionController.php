<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Kiểm tra trạng thái tài khoản Mái ấm đang chờ duyệt
        if ($user->role === 'charity' && $user->status === 'pending') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Tài khoản Tổ chức từ thiện của bạn đang chờ Ban quản trị phê duyệt hồ sơ pháp lý.'],
            ]);
        }

        // Kiểm tra trạng thái tài khoản bị khóa
        if ($user->status === 'banned') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.'],
            ]);
        }

        // Kiểm tra trạng thái tài khoản bị từ chối duyệt
        if ($user->status === 'rejected') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Tài khoản của bạn đã bị từ chối phê duyệt hồ sơ pháp lý.'],
            ]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $role = Auth::user()?->role;

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($role === 'admin') {
            return redirect('/login');
        }

        return redirect('/');
    }
}
