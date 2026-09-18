<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class KopokopoTransferInitiated
{
    use Dispatchable;

    /**
     * @param  string  $phoneNumber  Normalized "254XXXXXXXXX" recipient number.
     */
    public function __construct(
        public string $phoneNumber,
        public float $amount,
        public ?string $recipientName = null,
        public ?string $description = null,
    ) {}
}
