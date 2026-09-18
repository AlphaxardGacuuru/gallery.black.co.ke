<?php

namespace App\Http\Services;

class Service
{
    public ?string $id;

    public function __construct()
    {
        // Current User ID
        $auth = auth('sanctum')->user();

        $this->id = $auth ? $auth->id : null;
    }

    /**
     * Kopokopo/M-Pesa expects "254XXXXXXXXX" (no "+", no leading 0).
     * Public static so controllers can reach it too, without instantiating
     * a service, when they need to normalize a phone number themselves
     * (e.g. to dispatch an event carrying the normalized number).
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254'.substr($digits, 1);
        }

        return $digits;
    }
}
