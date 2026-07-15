<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'food_post_id',
        'food_claim_id',
        'reason',
        'details',
        'proof_image',
        'status',
        'resolved_by',
        'resolved_at'
    ];

    protected $casts = [
        'proof_image' => 'array',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function foodPost()
    {
        return $this->belongsTo(FoodPost::class, 'food_post_id');
    }

    public function foodClaim()
    {
        return $this->belongsTo(FoodClaim::class, 'food_claim_id');
    }
    
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
