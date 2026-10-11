<?php

namespace App\Http\Services\Concerns;

trait ParsesKopokopoLocation
{
    /**
     * The trailing UUID segment of a Kopokopo Location URL, e.g.
     * ".../incoming_payments/{this}", the same id that later shows up as
     * the webhook payload's top-level "data.id".
     */
    protected function locationId(string $location): ?string
    {
        if ($location === '') {
            return null;
        }

        $segments = explode('/', rtrim($location, '/'));

        return end($segments) ?: null;
    }
}
