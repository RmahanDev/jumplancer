<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root super admin
    |--------------------------------------------------------------------------
    |
    | The first super admin is created by SuperAdminSeeder from these values.
    | The password is read from .env only, so it never lands in the Git history.
    | This account cannot be demoted, suspended or deleted from the dashboard.
    |
    */

    'super_admin' => [
        'username' => env('SUPER_ADMIN_USERNAME', 'mahan'),
        'name' => env('SUPER_ADMIN_NAME', 'ماهان'),
        'email' => env('SUPER_ADMIN_EMAIL', 'mahan@jumplancer.test'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Until a real gateway (ZarinPal / IDPay) is wired in, the sandbox gateway
    | lets employers top up their wallet from the dashboard to test escrow.
    |
    */

    'payments' => [
        'sandbox' => (bool) env('PAYMENTS_SANDBOX', env('APP_ENV', 'production') !== 'production'),
        'max_sandbox_deposit' => 500_000_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard tables
    |--------------------------------------------------------------------------
    */

    'per_page' => 12,

];
