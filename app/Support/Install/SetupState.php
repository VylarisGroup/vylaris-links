<?php

namespace App\Support\Install;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Zustand der Einrichtung: ist die Instanz eingerichtet, und welches Token schützt den Assistenten?
 */
class SetupState
{
    public static function enabled(): bool
    {
        return (bool) config('links.setup.enabled', true);
    }

    public static function dir(): string
    {
        return rtrim((string) (config('links.setup.dir') ?: storage_path('app')), '/\\');
    }

    public static function markerPath(): string
    {
        return self::dir().'/installed.json';
    }

    public static function tokenPath(): string
    {
        return self::dir().'/setup-token';
    }

    /** Eingerichtet = Marker vorhanden – oder eine bereits laufende Instanz mit Administrator (wird dann nachträglich markiert). */
    public static function isInstalled(): bool
    {
        if (! self::enabled() || is_file(self::markerPath())) {
            return true;
        }
        if (self::detectExisting()) {
            self::markInstalled();

            return true;
        }

        return false;
    }

    public static function detectExisting(): bool
    {
        if (! config('app.key')) {
            return false;
        }
        try {
            return Schema::hasTable('users') && Schema::hasColumn('users', 'is_admin') && User::where('is_admin', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function markInstalled(): bool
    {
        $ok = @file_put_contents(self::markerPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => config('updater.version'),
        ], JSON_PRETTY_PRINT)) !== false;
        @unlink(self::tokenPath());

        return $ok;
    }

    /** Das Token beweist Zugriff auf den Server (Dateisystem oder .env). */
    public static function token(bool $create = false): ?string
    {
        $fixed = trim((string) config('links.setup.token'));
        if ($fixed !== '') {
            return $fixed;
        }
        $file = self::tokenPath();
        if (is_file($file)) {
            $v = trim((string) @file_get_contents($file));
            if ($v !== '') {
                return $v;
            }
        }
        if (! $create) {
            return null;
        }
        $value = implode('-', str_split(strtolower(Str::random(20)), 5));
        if (@file_put_contents($file, $value."\n", LOCK_EX) === false) {
            return null;
        }
        @chmod($file, 0600);

        return $value;
    }

    public static function verify(?string $given): bool
    {
        $token = self::token(false);
        if ($token === null || $given === null || trim($given) === '') {
            return false;
        }

        return hash_equals(self::normalize($token), self::normalize($given));
    }

    private static function normalize(string $t): string
    {
        return strtolower((string) preg_replace('/[\s\-]+/', '', $t));
    }
}
