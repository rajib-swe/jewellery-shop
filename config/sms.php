<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | "log" writes the message to the log and sends nothing. "http" posts a form
    | encoded body to the gateway below. The log driver is the default so a fresh
    | install, a test run and a scheduler tick cannot post to a real paid
    | gateway by accident.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    |
    | Every Bangladesh SMS provider accepts a destination, a sender id and a
    | message body, authenticated with HTTP basic. Point these at whichever
    | provider the shop has an account with.
    |
    */

    'endpoint' => env('SMS_ENDPOINT'),

    'api_key' => env('SMS_API_KEY'),

    'api_secret' => env('SMS_API_SECRET'),

    'sender_id' => env('SMS_SENDER_ID', 'JEWELLERYSHOP'),

    'timeout' => (int) env('SMS_TIMEOUT', 20),

];
