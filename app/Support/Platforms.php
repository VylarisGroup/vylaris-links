<?php

namespace App\Support;

class Platforms
{
    /** key => [Label, Farbe, Kürzel] */
    public const LIST = [
        'instagram' => ['Instagram', '#e1306c', 'IG'],
        'tiktok' => ['TikTok', '#25f4ee', 'TT'],
        'youtube' => ['YouTube', '#ff0033', 'YT'],
        'twitch' => ['Twitch', '#9146ff', 'TW'],
        'discord' => ['Discord', '#5865f2', 'DC'],
        'x' => ['X / Twitter', '#e7e9ea', 'X'],
        'github' => ['GitHub', '#8b949e', 'GH'],
        'spotify' => ['Spotify', '#1db954', 'SP'],
        'facebook' => ['Facebook', '#1877f2', 'FB'],
        'linkedin' => ['LinkedIn', '#0a66c2', 'IN'],
        'snapchat' => ['Snapchat', '#fffc00', 'SC'],
        'telegram' => ['Telegram', '#26a5e4', 'TG'],
        'whatsapp' => ['WhatsApp', '#25d366', 'WA'],
        'reddit' => ['Reddit', '#ff4500', 'RD'],
        'pinterest' => ['Pinterest', '#e60023', 'PI'],
        'mastodon' => ['Mastodon', '#6364ff', 'MA'],
        'bluesky' => ['Bluesky', '#1185fe', 'BS'],
        'steam' => ['Steam', '#66c0f4', 'ST'],
        'patreon' => ['Patreon', '#ff424d', 'PA'],
        'kofi' => ['Ko-fi', '#ff5e5b', 'KO'],
        'paypal' => ['PayPal', '#00457c', 'PP'],
        'email' => ['E-Mail', '#a1a1aa', '@'],
        'website' => ['Webseite', '#38bdf8', 'WW'],
        'phone' => ['Telefon', '#a1a1aa', 'TEL'],
    ];

    public static function keys(): array
    {
        return array_keys(self::LIST);
    }

    public static function label(string $key): string
    {
        return self::LIST[$key][0] ?? ucfirst($key);
    }

    public static function color(string $key): string
    {
        return self::LIST[$key][1] ?? '#a1a1aa';
    }

    public static function badge(string $key): string
    {
        return self::LIST[$key][2] ?? strtoupper(substr($key, 0, 2));
    }
}
