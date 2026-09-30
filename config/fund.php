<?php

return [
    'currency' => 'USD',
    'timezone' => 'America/Guayaquil',
    'initial_administrator' => [
        'name' => env('FUND_ADMIN_NAME'),
        'email' => env('FUND_ADMIN_EMAIL'),
        'password' => env('FUND_ADMIN_PASSWORD'),
    ],
];
