<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Display Module Configuration
    |--------------------------------------------------------------------------
    |
    | Footer ticker text for the public display. This is read via:
    |   config('display.footer_ticker_text')
    |
    | Set the value in your .env file:
    |   FOOTER_TICKER_TEXT="Teks berjalan di footer"
    |
    | This works correctly even when configuration is cached.
    |
    */
    'footer_ticker_text' => env('FOOTER_TICKER_TEXT', ''),
];
