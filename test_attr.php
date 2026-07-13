<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = \App\Models\Campaign::with(['items' => function ($q) {
    $q->withSum(['donations as pending_quantity' => function($q2) {
        $q2->where('status', 'pending');
    }], 'donation_quantity');
}])->where('id', 6)->first();

echo json_encode($c->items->first());
