<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r = \App\Models\Report::latest()->first();
if($r && is_null($r->campaign_donation_id) && $r->campaign_id) {
    $d = \App\Models\CampaignDonation::where('campaign_id', $r->campaign_id)->where('user_id', $r->reporter_id)->first();
    if($d) {
        $r->campaign_donation_id = $d->id;
        $r->save();
        echo 'Fixed';
    }
}
