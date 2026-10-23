<?php

namespace App\Support;

class Themes
{
    public const THEMES = [
        'vylaris' => 'Vylaris',
        'glas' => 'Glas',
        'brutal' => 'Brutal',
        'candy' => 'Candy',
        'zen' => 'Zen',
        'code' => 'Code',
        'cafe' => 'Café',
        'galaxie' => 'Galaxie',
        'notizbuch' => 'Notizbuch',
        'comic' => 'Comic',
        'ozean' => 'Ozean',
        'mitternacht' => 'Mitternacht',
    ];

    public const LAYOUTS = [
        'buttons' => 'Knöpfe',
        'cards' => 'Breite Karten',
        'tiles' => 'Kacheln',
        'grid' => 'App-Raster',
        'timeline' => 'Zeitstrahl',
        'cloud' => 'Wolke',
        'columns' => 'Zwei Spalten',
        'bento' => 'Bento',
        'polaroid' => 'Polaroid',
        'magazine' => 'Magazin',
    ];

    public const RINGS = ['none' => 'Kein Ring', 'spin' => 'Drehender Verlauf', 'pulse' => 'Pulsierend', 'glow' => 'Leuchtend'];

    public const PARTICLES = ['none' => 'Keine', 'snow' => 'Schnee', 'hearts' => 'Herzen', 'confetti' => 'Konfetti', 'fireflies' => 'Glühwürmchen'];

    public const EFFECTS = ['none' => 'Kein Effekt', 'shine' => 'Glanz', 'wiggle' => 'Wackeln'];

    public const FONTS = ['sans' => 'Sans-Serif', 'serif' => 'Serif', 'mono' => 'Monospace', 'rounded' => 'Rund'];

    public const SHAPES = ['default' => 'Standard', 'square' => 'Eckig', 'round' => 'Abgerundet', 'pill' => 'Pille'];

    /** Reservierte Adressen, die nicht als Profilname vergeben werden. */
    public static function reservedSlugs(): array
    {
        $extra = array_filter(array_map('trim', explode(',', (string) env('LINKS_RESERVED_SLUGS', ''))));

        return array_merge([
            'admin', 'api', 'anmelden', 'login', 'logout', 'registrieren', 'register', 'passwort', 'dashboard',
            'editor', 'konto', 'go', 'up', 'storage', 'assets', 'css', 'js', 'build', 'static', 'support',
            'impressum', 'datenschutz', 'hilfe', 'help', 'about', 'ueber-uns', 'www', 'mail', 'vylaris',
            'melden', 'sprache', 'robots', 'sitemap', 'favicon', 'dev', 'setup',
        ], $extra);
    }
}
