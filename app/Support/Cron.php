<?php

namespace App\Support;

/**
 * Cron per URL: Adresse und Zeitpunkt des letzten Aufrufs.
 */
class Cron
{
    public static function token(): ?string
    {
        $t = (string) config('links.cron.token');

        return strlen($t) >= 32 ? $t : null;
    }

    public static function url(?string $base = null, ?string $token = null): ?string
    {
        $token ??= self::token();
        if ($token === null) {
            return null;
        }

        return rtrim($base ?? (string) config('app.url'), '/').'/cron/'.$token;
    }

    public static function lastRunPath(): string
    {
        return storage_path('app/cron-last-run');
    }

    public static function lastRun(): ?int
    {
        $v = @file_get_contents(self::lastRunPath());

        return $v !== false && ctype_digit(trim($v)) ? (int) trim($v) : null;
    }

    public static function markRun(): void
    {
        @file_put_contents(self::lastRunPath(), (string) time(), LOCK_EX);
    }
}
