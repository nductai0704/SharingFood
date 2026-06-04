<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SendResetPasswordOTP;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'email' => session('email'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'email.exists' => 'Email này không tồn tại trên hệ thống.',
        ]);

        $otp = (string) rand(100000, 999999);

        // Save OTP to password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        // Send Email with OTP
        try {
            Mail::to($request->email)->send(new SendResetPasswordOTP($otp));
        } catch (\Exception $e) {
            // Log mail sending error
            logger()->error('Failed to send Reset Password OTP to ' . $request->email . ': ' . $e->getMessage());
            throw ValidationException::withMessages([
                'email' => ['Không thể gửi email OTP lúc này. Vui lòng kiểm tra cấu hình SMTP.'],
            ]);
        }

        return back()
            ->with('status', 'otp-sent')
            ->with('email', $request->email);
    }
}
