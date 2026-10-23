<?php

namespace App\Support\Install;

use App\Support\EnvFile;

/**
 * Systemvoraussetzungen – gemeinsam genutzt vom Assistenten und von "links:install --check".
 */
class Requirements
{
    /** @return list<array{label: string, state: string, hint: string}> state: ok | warn | fail */
    public static function system(?EnvFile $env = null): array
    {
        $rows = [];
        $add = function (string $label, string $state, string $hint = '') use (&$rows) {
            $rows[] = ['label' => $label, 'state' => $state, 'hint' => $hint];
        };

        $add('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail', 'PHP 8.3 oder neuer ist nötig.');

        foreach (['mbstring', 'openssl', 'pdo', 'ctype', 'tokenizer', 'xml', 'fileinfo'] as $ext) {
            $add("PHP-Erweiterung {$ext}", extension_loaded($ext) ? 'ok' : 'fail', "Die Erweiterung {$ext} muss aktiviert werden.");
        }
        $drivers = array_values(array_filter(['pdo_mysql', 'pdo_sqlite'], 'extension_loaded'));
        $add('Datenbanktreiber', $drivers ? 'ok' : 'fail', $drivers ? implode(', ', $drivers) : 'pdo_mysql oder pdo_sqlite muss aktiviert werden.');
        $add('PHP-Erweiterung zip', extension_loaded('zip') ? 'ok' : 'warn', 'Ohne zip funktioniert das Update-System nicht.');
        $add('PHP-Erweiterung gd', extension_loaded('gd') ? 'ok' : 'warn', 'Ohne gd können hochgeladene Bilder nicht verkleinert werden.');
        $add('PHP-Erweiterung intl', extension_loaded('intl') ? 'ok' : 'warn', 'Empfohlen für saubere Textverarbeitung.');
        $add('Prozesse starten (proc_open)', function_exists('proc_open') ? 'ok' : 'warn', 'Ohne proc_open laufen Update und Cache-Aufbau eingeschränkt.');

        $envPath = $env?->path() ?? app()->environmentFilePath();
        $envWritable = is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath));
        $add('.env-Datei beschreibbar', $envWritable ? 'ok' : 'fail', 'Der Webserver-Benutzer muss die Datei .env anlegen und ändern dürfen.');

        foreach (['storage', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $d) {
            $add("{$d} beschreibbar", is_writable(base_path($d)) ? 'ok' : 'fail', "chmod -R ug+w {$d} und den Besitzer des Webservers setzen.");
        }

        $assets = is_file(public_path('build/manifest.json')) || is_file(public_path('hot'));
        $add('Fertige Assets (public/build)', $assets ? 'ok' : 'fail', 'Fehlt: Die Seite würde mit Fehler 500 antworten. Nimm das source-code.zip aus dem Release oder führe "npm ci && npm run build" aus.');
        $add('Speicher-Link (public/storage)', file_exists(public_path('storage')) ? 'ok' : 'warn', 'Wird bei der Installation angelegt, sonst sind hochgeladene Bilder nicht sichtbar.');

        return $rows;
    }

    public static function blocking(array $rows): int
    {
        return count(array_filter($rows, fn ($r) => $r['state'] === 'fail'));
    }
}
