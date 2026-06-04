<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): RedirectResponse
    {
        // Redirect back to forgot password since we do resets inline via OTP
        return redirect()->route('password.request');
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.required' => 'Vui lòng cung cấp email.',
            'email.exists' => 'Email không tồn tại.',
            'otp.required' => 'Vui lòng nhập mã OTP.',
            'otp.size' => 'Mã OTP phải gồm 6 chữ số.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 8 ký tự.',
        ]);

        // Find token/otp record
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->otp)
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'otp' => ['Mã OTP không chính xác hoặc đã hết hạn.'],
            ]);
        }

        // Check if OTP is expired (e.g., 10 minutes)
        if (now()->subMinutes(10)->gt($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'otp' => ['Mã OTP đã hết hạn. Vui lòng gửi lại yêu cầu.'],
            ]);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'Mật khẩu của bạn đã được cập nhật thành công!');
    }
}
