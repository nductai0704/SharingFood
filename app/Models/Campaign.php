<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'campaigns';
    protected $guarded = [];

    // Tự động ép kiểu ngày tháng
    protected $casts = [
        'web_deadline' => 'datetime',
        'event_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CampaignItem::class, 'campaign_id', 'id');
    }

    public function donations()
    {
        return $this->hasMany(CampaignDonation::class, 'campaign_id', 'id');
    }
}
