<?php

namespace App\Http\Middleware;

use App\Support\Install\SetupState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solange die Instanz nicht eingerichtet ist, führt jede Seite zum Einrichtungsassistenten.
 */
class RedirectToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('setup', 'setup/*', 'up') || SetupState::isInstalled()) {
            return $next($request);
        }

        // Relative Adresse: funktioniert auch hinter Proxys, die das Schema verschleiern.
        return response('', 302, ['Location' => '/setup', 'Cache-Control' => 'no-store']);
    }
}
