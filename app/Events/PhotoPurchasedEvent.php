<?php

namespace App\Events;

use App\Models\PhotoPurchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A photo purchase's STK push was confirmed paid by Kopokopo's webhook.
 */
class PhotoPurchasedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public PhotoPurchase $purchase)
    {
        //
    }
}
