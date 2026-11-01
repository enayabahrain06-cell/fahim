<?php

return [
    // Timestamps are stored in UTC and shown in this timezone (see README).
    'timezone' => env('APP_TIMEZONE', 'Asia/Bahrain'),

    // Most guests one invitation may bring, including the invitee.
    'max_seats' => 999,

    // Guests can bring any number, not limited by their seats; this only guards against a mistyped reply.
    'max_guests' => 999,

    // The short, readable address of the shared invitation link (https://yourdomain/<slug>).
    'share_slug' => 'join',

    // The server error log shown (and clearable) in Users > Logs.
    'error_log_path' => storage_path('logs/laravel.log'),

    // Photo slots on the invitation.
    'photo_slots' => ['couple', 'venue'],

    // Backups kept by `php artisan wedding:backup`.
    'backups_kept' => 14,
];
