<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\RedirectToSetup;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Support\Install\SetupState;

// Frische Installation (Git-Checkout oder Zip ohne storage/): Laufzeit-Ordner anlegen, bevor irgendetwas
// kompiliert, gecacht oder geloggt wird. Sonst scheitert schon der Einrichtungsassistent mit Fehler 500.
foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $runtimeDir) {
    if (! is_dir(dirname(__DIR__).'/'.$runtimeDir)) {
        @mkdir(dirname(__DIR__).'/'.$runtimeDir, 0775, true);
    }
}
// Ist der View-Cache nicht beschreibbar, weicht Blade auf das Temp-Verzeichnis aus, damit /setup trotzdem erscheint
// (die Systemprüfung zeigt dort dann rot, dass storage/ beschreibbar gemacht werden muss).
if (! is_writable(dirname(__DIR__).'/storage/framework/views') && getenv('VIEW_COMPILED_PATH') === false) {
    $fallback = sys_get_temp_dir().'/vylaris-links-views-'.md5(__DIR__);
    @mkdir($fallback, 0775, true);
    putenv('VIEW_COMPILED_PATH='.$fallback);
    $_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $fallback;
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RedirectToSetup::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'not.blocked' => EnsureNotBlocked::class,
        ]);
        $middleware->preventRequestsDuringMaintenance(except: ['admin/update/status']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Solange die Instanz nicht eingerichtet ist, nennt ein Serverfehler seine Ursache (Klasse, Meldung, Stelle),
        // statt nur "500" zu zeigen. Nach der Einrichtung (Marker storage/app/installed.json) geschieht das nie.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface || $e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }
            try {
                if (! config('links.setup.enabled', true) || is_file(SetupState::markerPath())) {
                    return null;
                }
                if (! $request->is('setup', 'setup/*') && config('app.key')) {
                    return null;
                }
            } catch (Throwable) {
                // Konfiguration nicht lesbar: genau dann ist die Meldung am nötigsten
            }

            $text = get_class($e).': '.$e->getMessage().' ('.str_replace(dirname(__DIR__).'/', '', $e->getFile()).':'.$e->getLine().')';
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Serverfehler – '.$text], 500);
            }

            return response('<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Fehler – Vylaris Links</title>'
                .'<body style="margin:0;background:#05070c;color:#e6edf7;font:16px/1.55 system-ui,sans-serif"><main style="max-width:640px;margin:0 auto;padding:32px 16px">'
                .'<h1 style="font-size:1.4rem">Ein Fehler ist aufgetreten</h1>'
                .'<p style="padding:12px;border-radius:10px;background:#121a2b;overflow-wrap:anywhere">'.e($text).'</p>'
                .'<p style="color:#93a1b8">Dieser Hinweis erscheint nur, solange die Einrichtung nicht abgeschlossen ist. Weitere Details: storage/logs/laravel.log.</p></main></body>', 500);
        });
    })->create();
