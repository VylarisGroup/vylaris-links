<?php

return [
    'max_pages' => (int) env('LINKS_MAX_PAGES', 3),
    'max_items' => 100,
    'max_socials' => 24,

    // Neue Konten dürfen sich selbst registrieren ("closed" = nur der Administrator legt Konten an).
    'registration_open' => env('LINKS_REGISTRATION', 'open') !== 'closed',

    // Cron per URL: /cron/{token} führt den Scheduler aus. Ohne Token (oder unter 32 Zeichen) ist der Aufruf abgeschaltet.
    'cron' => [
        'token' => env('LINKS_CRON_TOKEN'),
    ],

    // Einrichtungsassistent (/setup). Nach der Einrichtung ist er gesperrt.
    'setup' => [
        'enabled' => (bool) env('LINKS_SETUP', true),
        // Optional: festes Token statt der Datei storage/app/setup-token
        'token' => env('LINKS_SETUP_TOKEN'),
        'dir' => null,      // Ablage von Marker und Token (Standard: storage/app)
        'sqlite' => null,   // SQLite-Datei des Assistenten (Standard: database/database.sqlite)
    ],
];
