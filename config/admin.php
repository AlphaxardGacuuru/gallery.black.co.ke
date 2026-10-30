<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Email
    |--------------------------------------------------------------------------
    |
    | Only the user with this email address may access the /admin section
    | of the application (both the API routes and the SPA pages).
    |
    */

    'email' => env('ADMIN_EMAIL', 'alphaxardgacuuru47@gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Wallet Top-up Phone
    |--------------------------------------------------------------------------
    |
    | The M-Pesa number that receives the STK push when the admin loads the
    | Kopokopo wallet ahead of paying out a competition's prizes.
    |
    */

    'wallet_phone' => env('ADMIN_WALLET_PHONE', '0700364446'),
];
