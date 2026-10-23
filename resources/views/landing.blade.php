@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
<section class="container-v pt-16 pb-12 text-center md:pt-24">
    <span class="eyebrow">Aus der Schweiz, offen für alle</span>
    <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl md:text-6xl">
        Alle deine Links. <span class="gradient-text">Eine Seite.</span>
    </h1>
    <p class="mx-auto mt-5 max-w-2xl text-lg text-muted">
        Bau dir in zwei Minuten deine eigene Link-Seite für Instagram, TikTok, YouTube oder Twitch –
        mit Statistik, ohne Werbung und ohne Tracking-Cookies. Kostenlos mit deinem Vylaris-Konto.
    </p>
    <form action="{{ route('register') }}" method="get" class="mx-auto mt-8 flex max-w-lg flex-col gap-2 sm:flex-row">
        <div class="flex flex-1 items-center rounded-xl border border-line bg-surface px-3.5 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30">
            <label for="hero-slug" class="shrink-0 text-sm text-dim">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</label>
            <input id="hero-slug" name="name" type="text" inputmode="url" autocomplete="off" maxlength="40" pattern="[a-z0-9\-]+"
                   placeholder="dein-name" class="w-full border-0 bg-transparent px-1 py-3 text-sm text-fg outline-none placeholder:text-dim">
        </div>
        <button class="btn btn-primary" type="submit">Seite sichern</button>
    </form>
    <ul class="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs text-muted">
        <li class="badge">Kostenlos</li><li class="badge">Ohne Cookies</li><li class="badge">Ohne Werbung</li><li class="badge">Aus der Schweiz</li>
    </ul>
</section>

<section id="funktionen" class="container-v scroll-mt-24 py-14">
    <p class="eyebrow">Funktionen</p>
    <h2 class="mt-4 text-3xl font-bold tracking-tight">Alles, was eine Link-Seite braucht</h2>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @php
            $features = [
                ['Links & Social-Icons', 'Beliebig viele Links mit passendem Symbol, dazu Icons für Instagram, TikTok, YouTube, Twitch, Discord und viele mehr.'],
                ['Videos, Musik & Bilder', 'YouTube, Twitch und Spotify direkt auf der Seite – dazu Bilder, Überschriften und Text.'],
                ['12 Designs', 'Vylaris als Standard, dazu Glas, Brutal, Candy, Zen, Code, Café, Galaxie, Notizbuch, Comic, Ozean und Mitternacht.'],
                ['Live-Vorschau', 'Im Editor siehst du sofort, wie deine Seite auf dem Handy aussieht.'],
                ['10 Anordnungen', 'Knöpfe, breite Karten, Kacheln, App-Raster, Zeitstrahl, Wolke, zwei Spalten, Bento, Polaroid und Magazin.'],
                ['Effekte', 'Animierter Ring ums Profilbild, Glanz oder Wackeln für einzelne Links, Schnee, Konfetti oder Glühwürmchen und Funken am Mauszeiger.'],
                ['Statistik ohne Cookies', 'Aufrufe und Klicks je Link – ohne Cookies, ohne IP-Adressen, ohne Cookie-Banner für deine Besucher.'],
                ['Zeitsteuerung', '„Jetzt live“ nur freitags 18–23 Uhr? Links erscheinen und verschwinden automatisch.'],
                ['Geschützte Links', 'Einzelne Links nur mit Passwort oder erst nach „Ab 18“-Bestätigung.'],
                ['Bis zu 3 Seiten', 'Eine Seite für dich, eine fürs Projekt – mit eigener Adresse und eigener Statistik.'],
                ['Wochenbericht', 'Auf Wunsch jeden Montag eine kurze Mail mit Aufrufen, Klicks und deinem besten Link.'],
                ['Eingang mit Musik', 'Besucher tippen zum Betreten, die Seite öffnet sich mit Animation und deine Musik startet.'],
                ['Countdown & Galerie', 'Zähl bis zum nächsten Release herunter oder zeig mehrere Bilder zum Wischen.'],
                ['Kontaktformular', 'Besucher schreiben dir direkt – mit Schutzfilter gegen Spam und Hinweis im Editor.'],
            ];
        @endphp
        @foreach ($features as [$t, $d])
            <article class="card card-hover">
                <h3 class="font-semibold">{{ $t }}</h3>
                <p class="mt-1.5 text-sm text-muted">{{ $d }}</p>
            </article>
        @endforeach
    </div>
</section>

<section id="designs" class="container-v scroll-mt-24 py-14">
    <p class="eyebrow">Designs</p>
    <h2 class="mt-4 text-3xl font-bold tracking-tight">Von Vylaris bis Neon-Nacht</h2>
    <p class="mt-2 max-w-2xl text-muted">Jedes Design lässt sich mit jeder Anordnung kombinieren – und mit eigenen Farben, Schrift und Knopfform anpassen.</p>
    <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($themes as $key => $label)
            <div class="card p-3">
                <div class="theme-preview theme-{{ $key }} flex h-40 flex-col items-center justify-center gap-2 rounded-xl p-3" aria-hidden="true">
                    <span class="tp-avatar"></span>
                    <span class="tp-btn"></span><span class="tp-btn w-4/5"></span><span class="tp-btn w-3/5"></span>
                </div>
                <p class="mt-3 text-sm font-semibold">{{ $label }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-5 text-sm text-dim">Anordnungen: {{ implode(' · ', $layouts) }}</p>
</section>

<section id="statistik" class="container-v scroll-mt-24 py-14">
    <div class="card grid gap-6 md:grid-cols-2 md:items-center">
        <div>
            <p class="eyebrow">Datenschutz</p>
            <h2 class="mt-4 text-3xl font-bold tracking-tight">Statistik, die Besucher in Ruhe lässt</h2>
            <p class="mt-3 text-muted">Wir zählen nur, wie oft deine Seite aufgerufen und welcher Link angeklickt wurde – als anonyme Tageszähler. Es werden keine Cookies gesetzt und keine IP-Adressen gespeichert. YouTube wird über youtube-nocookie eingebunden.</p>
        </div>
        <ul class="space-y-2 text-sm text-muted">
            <li class="flex gap-2"><span class="text-brand">✓</span> Aufrufe und Klicks pro Link und Tag</li>
            <li class="flex gap-2"><span class="text-brand">✓</span> Geräte (Handy, Tablet, Desktop) und Herkunft der Besucher</li>
            <li class="flex gap-2"><span class="text-brand">✓</span> Kein Cookie-Banner für deine Besucher nötig</li>
            <li class="flex gap-2"><span class="text-brand">✓</span> Wochenbericht per E-Mail – optional</li>
        </ul>
    </div>
</section>

<section id="fragen" class="container-v scroll-mt-24 py-14">
    <p class="eyebrow">Fragen</p>
    <h2 class="mt-4 text-3xl font-bold tracking-tight">Häufige Fragen</h2>
    <div class="mt-8 space-y-3" x-data="{ open: 0 }">
        @php
            $faq = [
                ['Kostet Vylaris Links etwas?', 'Nein. Alle Funktionen sind kostenlos, ohne Werbung. Du brauchst nur ein Konto.'],
                ['Brauchen meine Besucher einen Cookie-Banner?', 'Nein. Profilseiten setzen keine Cookies und speichern keine IP-Adressen. Gezählt wird nur, wie oft die Seite aufgerufen und welcher Link angeklickt wurde.'],
                ['Kann ich meinen Namen später ändern?', 'Ja, im Editor unter „Adresse“. Die alte Adresse funktioniert danach nicht mehr – teile also am besten gleich den richtigen Namen.'],
                ['Wie viele Seiten kann ich anlegen?', 'Bis zu '.config('links.max_pages').' Seiten pro Konto, jede mit eigener Adresse und Statistik.'],
                ['Was passiert bei Missbrauch?', 'Besucher können Seiten melden. Das Team prüft Meldungen und sperrt Seiten, die gegen die Regeln verstossen (Spam, Betrug, illegale Inhalte).'],
            ];
        @endphp
        @foreach ($faq as $i => [$q, $a])
            <div class="card p-0">
                <button type="button" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left font-semibold"
                        @click="open = open === {{ $i + 1 }} ? 0 : {{ $i + 1 }}" :aria-expanded="open === {{ $i + 1 }}">
                    {{ $q }}
                    <span class="text-brand" x-text="open === {{ $i + 1 }} ? '–' : '+'">+</span>
                </button>
                <div class="px-5 pb-4 text-sm text-muted" x-show="open === {{ $i + 1 }}" x-cloak x-transition>{{ $a }}</div>
            </div>
        @endforeach
    </div>
</section>

<section class="container-v py-14">
    <div class="card text-center" style="background: linear-gradient(135deg, rgb(79 140 255 / .14), rgb(124 92 255 / .14))">
        <h2 class="text-3xl font-bold tracking-tight">Bereit für deine Link-Seite?</h2>
        <p class="mt-2 text-muted">Kostenlos mit deinem Konto. In zwei Minuten online.</p>
        <a href="{{ route('register') }}" class="btn btn-primary mt-6">Kostenlos starten</a>
    </div>
</section>
@endsection
