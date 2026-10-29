<?php

return [
    // Timestamps are stored in UTC and shown in this timezone (see README).
    'timezone' => env('APP_TIMEZONE', 'Asia/Bahrain'),

    // Most guests one invitation may bring, including the invitee.
    'max_seats' => 20,

    // Guests can bring any number, not limited by their seats; this only guards against a mistyped reply.
    'max_guests' => 99,

    // Photo slots on the invitation.
    'photo_slots' => ['couple', 'venue'],

    // Backups kept by `php artisan wedding:backup`.
    'backups_kept' => 14,
];
