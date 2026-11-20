<?php

declare(strict_types=1);

return [
    /*
    | Online payment gateway (PAY-05). Only the "fake" driver exists until the client chooses
    | a provider (PEND-07). The fake driver refuses to run in production.
    */
    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'gateways' => [
        'fake' => [
            'secret' => env('FAKE_GATEWAY_SECRET'),
        ],
    ],
];
