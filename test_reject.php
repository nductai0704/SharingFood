<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$claim = App\Models\FoodClaim::latest()->first();
echo "Claim ID: " . $claim->id . "\n";
$post = App\Models\FoodPost::find($claim->food_post_id);
echo "Post ID: " . $post->id . ", remain: " . $post->remain_quantity . "\n";

try {
    $result = DB::transaction(function() use ($claim) {
        $claim = App\Models\FoodClaim::lockForUpdate()->find($claim->id);
        $post = App\Models\FoodPost::lockForUpdate()->find($claim->food_post_id);
        
        $claim->status = 'rejected';
        $claim->cancel_reason = 'Người chia sẻ từ chối/hủy yêu cầu';
        $claim->save();
        
        $post->remain_quantity += $claim->quantity;
        if ($post->remain_quantity > 0 && new \DateTime($post->expires_at) > new \DateTime()) {
            $post->status = 'available';
        }
        $post->save();
        
        return ['success' => true];
    });
    
    echo "Success: " . json_encode($result) . "\n";
    $post->refresh();
    echo "New remain: " . $post->remain_quantity . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
