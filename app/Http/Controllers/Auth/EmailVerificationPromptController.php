<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Generate and send OTP if it doesn't exist or is expired
        if (!$request->user()->verification_otp || now()->gt($request->user()->verification_otp_expires_at)) {
            $request->user()->sendEmailVerificationOTP();
        }

        return Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
            'email' => $request->user()->email,
        ]);
    }
}
