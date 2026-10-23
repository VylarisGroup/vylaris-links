<?php

namespace App\Support;

/**
 * Lässt nur harmlose Link-Schemata zu (kein javascript:, data: usw.).
 */
class UrlGuard
{
    public const ALLOWED = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $url, array $allowed = self::ALLOWED): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
            return null;
        }
        if (! preg_match('#^([a-z][a-z0-9+.\-]*):#i', $url, $m)) {
            // "beispiel.ch/seite" -> https://beispiel.ch/seite
            if (! preg_match('#^[\w\-]+(\.[\w\-]+)+(/.*)?$#u', $url)) {
                return null;
            }
            $url = 'https://'.$url;
            $m = [1 => 'https'];
        }
        $scheme = strtolower($m[1]);
        if (! in_array($scheme, $allowed, true)) {
            return null;
        }
        if (in_array($scheme, ['http', 'https'], true)) {
            $host = parse_url($url, PHP_URL_HOST);
            if (! $host || ! str_contains($host, '.') && $host !== 'localhost') {
                return null;
            }
        }

        return mb_substr($url, 0, 500);
    }

    public static function isValid(?string $url, array $allowed = self::ALLOWED): bool
    {
        return self::clean($url, $allowed) !== null;
    }
}
