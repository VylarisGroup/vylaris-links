<?php

return [
    // Update-System an/aus
    'enabled' => (bool) env('UPDATER_ENABLED', true),

    // Aktuell installierte Version (steht in der Datei VERSION im Projektstamm)
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    // GitHub-Repo, aus dessen Releases aktualisiert wird, und Name des Release-Anhangs
    'repo' => env('UPDATER_REPO', 'VylarisGroup/vylaris-links'),
    'asset' => env('UPDATER_ASSET', 'source-code.zip'),
    'api' => 'https://api.github.com',

    // Optional: Lese-Token (nur nötig, falls das Repo privat wird oder das Rate-Limit knapp ist)
    'token' => env('UPDATER_GITHUB_TOKEN'),

    // Downloads und Weiterleitungen sind nur zu diesen Hosts erlaubt
    'allowed_hosts' => [
        'api.github.com',
        'github.com',
        'objects.githubusercontent.com',
        'release-assets.githubusercontent.com',
    ],

    // Arbeitsverzeichnis für Downloads, Entpacken und Backups (liegt in storage/, bleibt beim Update unangetastet)
    'work_dir' => env('UPDATER_WORK_DIR', storage_path('app/updater')),
    'base_path' => null, // nur für Tests

    // Wartungsmodus während des Austauschs
    'maintenance_mode' => true,

    // Wie viele Backups behalten werden
    'keep_backups' => (int) env('UPDATER_KEEP_BACKUPS', 3),

    // Grenzen gegen Zip-Bomben
    'max_zip_mb' => 200,
    'max_unzipped_mb' => 600,
    'max_files' => 60000,

    // Diese Pfade fasst der Updater nie an (relativ zum Projektstamm, * erlaubt)
    'protected' => [
        '.env', 'storage', 'node_modules', '.git',
        'public/storage', 'public/hot', 'bootstrap/cache',
        'database/*.sqlite', 'database/*.sqlite-*',
    ],

    // Diese Ordner werden Eintrag für Eintrag ausgetauscht statt als Ganzes
    'merge_dirs' => ['database', 'public', 'bootstrap'],
];
