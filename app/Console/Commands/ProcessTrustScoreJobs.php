<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trust:process-jobs')]
#[Description('Process trust score jobs: auto-cancel expired claims and process passive recovery.')]
class ProcessTrustScoreJobs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->processExpiredClaims();
        $this->processPassiveRecovery();
        
        $this->info('Trust score jobs processed successfully.');
    }

    private function processExpiredClaims()
    {
        // Cancel food claims that are approved but expired
        $expiredClaims = \App\Models\FoodClaim::where('status', 'approved')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredClaims as $claim) {
            $claim->status = 'cancelled';
            $claim->cancel_reason = 'Tự động hủy do quá hạn 60 phút';
            $claim->save();

            if ($claim->user) {
                $claim->user->penalizeTrustScore(20);
            }
            
            \App\Models\SystemLog::create([
                'action' => 'Hủy tự động quá hạn',
                'description' => "Hệ thống tự động hủy đơn nhận {$claim->id} do người nhận quá hạn lấy.",
                'user_id' => $claim->user_id ?? 0,
                'ip_address' => '127.0.0.1',
                'created_at' => now()
            ]);
        }
        
        $this->info("Cancelled {$expiredClaims->count()} expired food claims.");
    }

    private function processPassiveRecovery()
    {
        // 7 days without penalty -> +20 trust score
        $users = \App\Models\User::whereNotNull('last_penalty_at')
            ->where('trust_score', '<', 100)
            ->where('last_penalty_at', '<=', now()->subDays(7))
            ->get();

        foreach ($users as $user) {
            $user->addTrustScore(20);
            $user->last_penalty_at = null; // Reset so they don't get 20 points every day
            $user->save();
        }

        $this->info("Recovered points for {$users->count()} users.");
    }
}
