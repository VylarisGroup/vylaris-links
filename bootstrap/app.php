<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\RedirectToSetup;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    })->create();
