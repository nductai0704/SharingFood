<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified (for legacy signed link support, if any).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
    }

    /**
     * Mark the authenticated user's email address as verified using 6-digit OTP.
     *
     * @throws ValidationException
     */
    public function verifyOTP(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ], [
            'otp.required' => 'Vui lòng nhập mã OTP.',
            'otp.size' => 'Mã OTP phải gồm 6 chữ số.',
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($user->verification_otp !== $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['Mã OTP không chính xác.'],
            ]);
        }

        if (now()->gt($user->verification_otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => ['Mã OTP đã hết hạn. Vui lòng bấm gửi lại mã mới.'],
            ]);
        }

        // Mark user as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // Clear OTP fields
        $user->forceFill([
            'verification_otp' => null,
            'verification_otp_expires_at' => null,
        ])->save();

        // Nếu là tài khoản Tổ chức từ thiện (Mái ấm), chuyển hướng thẳng tới trang chờ duyệt
        if ($user->role === 'charity') {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Đối với các vai trò khác (Cá nhân, Doanh nghiệp), đăng xuất và chuyển hướng về trang đăng nhập kèm thông báo
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Đăng ký và xác thực tài khoản thành công! Vui lòng đăng nhập.');
    }
}
