<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'avatar', 'password', 'role', 'status', 'phone', 'address', 'latitude', 'longitude'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function documents()
    {
        return $this->hasMany(CharityDocument::class);
    }

    public function sendEmailVerificationOTP()
    {
        $otp = (string) rand(100000, 999999);
        $this->forceFill([
            'verification_otp' => $otp,
            'verification_otp_expires_at' => now()->addMinutes(15),
        ])->save();

        try {
            \Illuminate\Support\Facades\Mail::to($this->email)->send(new \App\Mail\SendVerificationOTP($otp));
        } catch (\Exception $e) {
            logger()->error('Failed to send verification OTP to ' . $this->email . ': ' . $e->getMessage());
        }
    }

    public function sendEmailVerificationNotification()
    {
        $this->sendEmailVerificationOTP();
    }

    /**
     * Mối quan hệ với các yêu cầu nhận thực phẩm của người dùng
     */
    public function claims()
    {
        return $this->hasMany(FoodClaim::class);
    }
}
