<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodClaim extends Model
{
    protected $fillable = [
        'food_post_id',
        'user_id',
        'quantity',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function foodPost()
    {
        return $this->belongsTo(FoodPost::class);
    }
}
