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
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254'.substr($digits, 1);
        }

        return $digits;
    }
}
