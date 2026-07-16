<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDonation extends Model
{
    protected $table = 'campaign_donations';
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignItem()
    {
        return $this->belongsTo(CampaignItem::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'campaign_donation_id');
    }
}
