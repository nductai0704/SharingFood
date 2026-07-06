<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClaimStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $claimId;
    public string $newStatus;
    public int $recipientUserId;  // User ID của người xin (người nhận)
    public int $donorUserId;      // User ID của người cho
    public ?string $cancelReason;

    public function __construct(int $claimId, string $newStatus, int $recipientUserId, int $donorUserId, ?string $cancelReason = null)
    {
        $this->claimId = $claimId;
        $this->newStatus = $newStatus;
        $this->recipientUserId = $recipientUserId;
        $this->donorUserId = $donorUserId;
        $this->cancelReason = $cancelReason;
    }

    public function broadcastOn(): array
    {
        // Broadcast trên Private Channel của người nhận
        return [
            new PrivateChannel('user.' . $this->recipientUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'claim.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'claim_id'    => $this->claimId,
            'new_status'  => $this->newStatus,
            'cancel_reason' => $this->cancelReason,
            'donor_user_id' => $this->donorUserId,
        ];
    }
}
