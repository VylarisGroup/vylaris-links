<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);

/**
 * Verständliche Fehlerseite statt leerem 500 – für alles, was vor dem Start von Laravel schiefgehen kann.
 */
function vylarisStartupFailure(string $title, string $detail, string $fix = ''): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        .'<title>Start nicht möglich – Vylaris Links</title>'
        .'<body style="margin:0;background:#05070c;color:#e6edf7;font:16px/1.55 system-ui,sans-serif"><main style="max-width:640px;margin:0 auto;padding:32px 16px">'
        .'<h1 style="font-size:1.4rem">'.$e($title).'</h1><p style="color:#93a1b8">'.$e($detail).'</p>'
        .($fix !== '' ? '<p style="padding:12px;border-radius:10px;background:#121a2b">'.$e($fix).'</p>' : '')
        .'</main></body></html>';
    exit(1);
}

if (PHP_VERSION_ID < 80300) {
    vylarisStartupFailure(
        'PHP-Version zu alt',
        'Dieser Server nutzt PHP '.PHP_VERSION.'. Vylaris Links braucht PHP 8.3 oder neuer.',
        'Stelle die PHP-Version in deinem Hosting-Panel (oder in der Webserver-Konfiguration) auf 8.3 oder neuer.',
    );
}

$missing = array_values(array_filter(['mbstring', 'openssl', 'pdo', 'ctype', 'tokenizer', 'xml', 'fileinfo'], fn ($x) => ! extension_loaded($x)));
if ($missing) {
    vylarisStartupFailure(
        'PHP-Erweiterungen fehlen',
        'Folgende PHP-Erweiterungen sind nicht aktiviert: '.implode(', ', $missing).'.',
        'Aktiviere sie im Hosting-Panel oder in der php.ini und lade die Seite neu.',
    );
}

if (! is_file($root.'/vendor/autoload.php')) {
    vylarisStartupFailure(
        'Der Ordner vendor/ fehlt',
        'Die PHP-Pakete von Vylaris Links sind nicht installiert.',
        'Nimm das source-code.zip aus dem neuesten Release (enthält alles) oder führe im Projektordner „composer install --no-dev --optimize-autoloader“ aus.',
    );
}

// Laufzeit-Ordner anlegen und prüfen: Darf der Webserver-Benutzer dort nicht schreiben, bricht Laravel ohne jede
// Meldung ab ("Class view does not exist", leerer 500). Das kommt vor, wenn die Dateien einem anderen Benutzer gehören.
foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    if (! is_dir($root.'/'.$dir)) {
        @mkdir($root.'/'.$dir, 0775, true);
    }
}
$locked = array_values(array_filter(
    ['bootstrap/cache', 'storage', 'storage/framework', 'storage/logs'],
    fn ($dir) => ! is_dir($root.'/'.$dir) || ! is_writable($root.'/'.$dir),
));
if ($locked) {
    $who = function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : '';
    $who = $who !== '' ? $who : get_current_user();
    vylarisStartupFailure(
        'Der Webserver darf nicht in den Projektordner schreiben',
        'Der PHP-Benutzer „'.$who.'“ darf hier nicht schreiben: '.implode(', ', $locked).'. Die Dateien gehören vermutlich einem anderen Benutzer (z. B. weil sie als root hochgeladen wurden).',
        'Auf dem Server ausführen: cd '.$root.' && chown -R '.$who.' storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache',
    );
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

try {
    // Register the Composer autoloader...
    require $root.'/vendor/autoload.php';

    // Eine ungültige .env führt bei Laravel zu einem leeren 500 – hier wird die Zeile benannt (nie der Inhalt: Passwörter!)
    if (is_file($root.'/.env') && class_exists(\Dotenv\Dotenv::class)) {
        foreach (preg_split('/\R/', (string) file_get_contents($root.'/.env')) as $i => $line) {
            try {
                \Dotenv\Dotenv::parse($line);
            } catch (Throwable) {
                vylarisStartupFailure(
                    'Die Datei .env ist ungültig',
                    'Zeile '.($i + 1).' der Datei .env kann nicht gelesen werden.',
                    'Werte mit Leerzeichen oder # gehören in doppelte Anführungszeichen, z. B. APP_NAME="Meine Links". Oder lösche die .env: Der Einrichtungsassistent legt sie neu an.',
                );
            }
        }
    }

    // Bootstrap Laravel...
    /** @var Application $app */
    $app = require_once $root.'/bootstrap/app.php';
} catch (Throwable $e) {
    // Nach der Einrichtung keine Details nach aussen
    if (is_file($root.'/storage/app/installed.json')) {
        throw $e;
    }
    vylarisStartupFailure(
        'Beim Start ist ein Fehler aufgetreten',
        get_class($e).': '.$e->getMessage().' ('.str_replace($root.'/', '', $e->getFile()).':'.$e->getLine().')',
        'Prüfe, ob die Datei .env gültig ist (Werte mit Leerzeichen gehören in Anführungszeichen) und ob vendor/ vollständig ist. Details stehen in storage/logs/laravel.log.',
    );
}

// ...and handle the request
$app->handleRequest(Request::capture());
