<?php

namespace App\Events;

use App\Models\KopokopoTransfer;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once a Kopokopo send-money webhook payload has been saved as a
 * KopokopoTransfer row (see KopokopoTransferService::store()), whether it
 * confirms success or failure, so listeners can correlate it back to
 * whatever initiated the transfer (a competition prize, a referral
 * reward, ...) via its kopokopo_id.
 */
class KopokopoTransferRecordedEvent
{
    use Dispatchable;

    public function __construct(public KopokopoTransfer $kopokopoTransfer) {}
}
