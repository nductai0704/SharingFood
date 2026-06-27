<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignItem extends Model
{
    protected $table = 'campaign_items';
    protected $guarded = [];
    public $timestamps = false;

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
