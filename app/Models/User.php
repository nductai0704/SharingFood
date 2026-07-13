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
            'locked_until' => 'datetime',
            'last_penalty_at' => 'datetime',
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

    /**
     * Deduct trust score and handle penalties (locking, banning).
     */
    public function penalizeTrustScore(int $points)
    {
        $this->trust_score = max(0, $this->trust_score - $points);
        $this->last_penalty_at = now();

        if ($this->trust_score < 50 && $this->status !== 'banned') {
            $this->locked_until = now()->addDays(5);
            $this->lock_count += 1;

            // Ban permanently if locked 2 times
            if ($this->lock_count >= 2) {
                $this->status = 'banned';
                $this->locked_until = null; // Banned is permanent
            }
        }

        $this->save();
    }

    /**
     * Add trust score up to a maximum of 100.
     */
    public function addTrustScore(int $points)
    {
        $this->trust_score = min(100, $this->trust_score + $points);
        $this->save();
    }

    /**
     * Check if user is currently locked from making transactions.
     */
    public function isLocked(): bool
    {
        if ($this->status === 'banned') {
            return true;
        }

        if ($this->locked_until && $this->locked_until->isFuture()) {
            return true;
        }

        // If locked_until has passed, clear it (auto-unlock)
        if ($this->locked_until && $this->locked_until->isPast()) {
            $this->locked_until = null;
            $this->save();
            return false;
        }

        return false;
    }

    /**
     * Get is_locked attribute for frontend.
     */
    public function getIsLockedAttribute(): bool
    {
        return $this->isLocked();
    }

    protected $appends = ['is_locked'];
}
