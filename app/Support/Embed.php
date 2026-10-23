<?php

namespace App\Support;

class Embed
{
    public static function youtubeId(?string $url): ?string
    {
        $url = (string) $url;
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', trim($url)) ? trim($url) : null;
    }

    public static function twitchChannel(?string $url): ?string
    {
        $url = trim((string) $url);
        if (preg_match('#twitch\.tv/([A-Za-z0-9_]{3,25})#', $url, $m)) {
            return strtolower($m[1]);
        }

        return preg_match('/^[A-Za-z0-9_]{3,25}$/', $url) ? strtolower($url) : null;
    }

    /** Gibt "type/id" zurück, z. B. "track/4uLU6hMCjMI75M1A2tKUQC". */
    public static function spotifyPath(?string $url): ?string
    {
        $url = (string) $url;
        if (preg_match('#open\.spotify\.com/(?:intl-[a-z]+/)?(track|album|playlist|artist|episode|show)/([A-Za-z0-9]{22})#', $url, $m)) {
            return $m[1].'/'.$m[2];
        }
        if (preg_match('#^spotify:(track|album|playlist|artist|episode|show):([A-Za-z0-9]{22})$#', trim($url), $m)) {
            return $m[1].'/'.$m[2];
        }

        return null;
    }
}
