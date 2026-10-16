<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Kopokopo reported that an STK push never turned into a payment (the user
 * cancelled the prompt, let it time out, had insufficient funds, ...). No
 * money moved, so there's no MPESATransaction to record.
 */
class MpesaPaymentFailedEvent
{
    use Dispatchable;

    /**
     * @param  string  $kopokopoReference  The incoming payment's id, the same
     *                                     value stored as a purchase's
     *                                     kopokopo_reference.
     * @param  array<int, string>  $errors  Kopokopo's reasons, e.g.
     *                                      "Request Cancelled by user."
     */
    public function __construct(
        public string $kopokopoReference,
        public array $errors = [],
    ) {}
}
