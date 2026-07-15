<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodClaim extends Model
{
    protected $fillable = [
        'food_post_id',
        'user_id',
        'quantity',
        'status',
        'message',
        'shipping_method',
        'pickup_contact_name',
        'pickup_contact_phone',
        'delivery_service_company',
        'driver_license_plate',
        'cancel_reason',
        'cancelled_by',
        'approved_at',
        'expires_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function foodPost()
    {
        return $this->belongsTo(FoodPost::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'food_claim_id');
    }
}
