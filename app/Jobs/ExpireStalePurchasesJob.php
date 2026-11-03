<?php

namespace App\Jobs;

use App\Models\PhotoPurchase;
use App\Models\PhotoSlotPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * An STK prompt the buyer ignores often gets no webhook at all, which left
 * its purchase "pending" forever. This writes those off as "expired" (not
 * "failed", and never deleted) so a payment that still lands late gets
 * matched and confirmed by the usual listeners.
 */
class ExpireStalePurchasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(PhotoSlotPurchase::EXPIRE_AFTER_MINUTES);

        PhotoSlotPurchase::query()
            ->where('status', PhotoSlotPurchase::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => PhotoSlotPurchase::STATUS_EXPIRED]);

        PhotoPurchase::query()
            ->where('status', PhotoPurchase::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => PhotoPurchase::STATUS_EXPIRED]);
    }
}
