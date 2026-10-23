<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05070c">
    <title>@yield('title', 'Vylaris Links – alle deine Links auf einer Seite')</title>
    <meta name="description" content="@yield('description', 'Vylaris Links: deine Link-in-Bio-Seite. Kostenlos, ohne Werbung, ohne Tracking-Cookies. Ein Dienst der VylarisGroup Schweiz.')">
    <meta property="og:site_name" content="Vylaris Links">
    <meta property="og:title" content="@yield('title', 'Vylaris Links')">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">
<a href="#main" class="sr-only-focusable btn btn-primary left-4 top-4 z-50">Zum Inhalt springen</a>

<header class="sticky top-0 z-30 border-b border-line/70 bg-ink/80 backdrop-blur">
    <div class="container-v flex h-16 items-center justify-between gap-4">
        <div class="flex items-center gap-6">
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5 font-bold tracking-tight">
                <img src="{{ asset('img/logo.svg') }}" alt="" class="h-8 w-8">
                <span>Vylaris <span class="gradient-text">Links</span></span>
            </a>
            <nav class="hidden items-center gap-1 md:flex" aria-label="Hauptnavigation">
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
                    <button type="button" class="btn btn-sm text-muted hover:text-fg" @click="open = !open" :aria-expanded="open">
                        Funktionen
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg>
                    </button>
                    <div class="menu-panel" x-show="open" x-cloak x-transition>
                        <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-dim">Gestalten</p>
                        <a class="block rounded-lg px-2 py-2 text-sm hover:bg-surface-2" href="{{ route('landing') }}#funktionen">Links, Icons &amp; Blöcke</a>
                        <a class="block rounded-lg px-2 py-2 text-sm hover:bg-surface-2" href="{{ route('landing') }}#designs">Designs &amp; Anordnungen</a>
                        <p class="px-2 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-dim">Auswerten</p>
                        <a class="block rounded-lg px-2 py-2 text-sm hover:bg-surface-2" href="{{ route('landing') }}#statistik">Statistik ohne Cookies</a>
                    </div>
                </div>
                <a class="btn btn-sm text-muted hover:text-fg" href="{{ route('landing') }}#designs">Designs</a>
                <a class="btn btn-sm text-muted hover:text-fg" href="{{ route('landing') }}#fragen">Fragen</a>
                <a class="btn btn-sm text-muted hover:text-fg" href="https://dev.vylaris.ch" target="_blank" rel="noopener">VylarisGroup<span class="sr-only"> (öffnet in neuem Tab)</span></a>
                <a class="btn btn-sm text-muted hover:text-fg" href="https://support.vylaris.ch" target="_blank" rel="noopener">Support<span class="sr-only"> (öffnet in neuem Tab)</span></a>
            </nav>
        </div>
        <div class="flex items-center gap-2">
            @auth
                @if (auth()->user()->is_admin)
                    <a href="{{ route('admin.index') }}" class="btn btn-ghost btn-sm">Admin</a>
                @endif
                <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">Meine Seiten</a>
                <a href="{{ route('account') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex">Konto</a>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Abmelden</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Anmelden</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Konto erstellen</a>
            @endauth
        </div>
    </div>
</header>

@if (session('status'))
    <div class="container-v pt-4" role="status">
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    </div>
@endif

<main id="main">
    @yield('content')
</main>

<footer class="mt-24 border-t border-line/70">
    <div class="container-v flex flex-col gap-6 py-10 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-2.5 font-bold">
            <img src="{{ asset('img/logo.svg') }}" alt="" class="h-7 w-7">
            Vylaris Links <span class="font-normal text-dim">· ein Dienst der VylarisGroup Schweiz</span>
        </div>
        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted">
            <li><a class="hover:text-fg" href="https://dev.vylaris.ch/impressum" target="_blank" rel="noopener">Impressum</a></li>
            <li><a class="hover:text-fg" href="https://dev.vylaris.ch/datenschutz" target="_blank" rel="noopener">Datenschutz</a></li>
            <li><a class="hover:text-fg" href="https://dev.vylaris.ch/ueber-uns" target="_blank" rel="noopener">Über uns</a></li>
            <li><a class="hover:text-fg" href="https://support.vylaris.ch" target="_blank" rel="noopener">Support</a></li>
            <li><a class="hover:text-fg" href="https://x.com/vylaris_schweiz" target="_blank" rel="noopener">X</a></li>
            <li><a class="hover:text-fg" href="https://www.youtube.com/@vylaris_schweiz" target="_blank" rel="noopener">YouTube</a></li>
            <li><a class="hover:text-fg" href="https://www.instagram.com/vylaris_schweiz/" target="_blank" rel="noopener">Instagram</a></li>
            <li><a class="hover:text-fg" href="https://discord.gg/2AMZtStBQa" target="_blank" rel="noopener">Discord</a></li>
        </ul>
    </div>
    <div class="container-v pb-8 text-xs text-dim">© {{ date('Y') }} VylarisGroup Schweiz</div>
</footer>
@stack('scripts')
</body>
</html>
