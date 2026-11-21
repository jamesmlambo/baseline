<?php

declare(strict_types=1);

return [
    /*
    | SMS and WhatsApp providers (MSG-01). Only the "log" driver exists until the client chooses
    | providers (PEND-07): messages are written to the log and the messages table, never sent.
    */
    'sms' => env('SMS_DRIVER', 'log'),

    'whatsapp' => env('WHATSAPP_DRIVER', 'log'),

    /*
    | Owner alert threshold for the monthly spend cap (MSG-06).
    */
    'cap_alert_percent' => 80,
];
