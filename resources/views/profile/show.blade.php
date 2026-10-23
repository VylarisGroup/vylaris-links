@php
    use App\Support\Embed;
    use App\Support\Platforms;
    use Illuminate\Support\Carbon;

    $c = $page->colors ?? [];
    $vars = [];
    if (! empty($c['bg'])) { $vars[] = '--bg-col:'.$c['bg']; $vars[] = '--bg-img:none'; }
    if (! empty($c['text'])) { $vars[] = '--fg:'.$c['text']; }
    if (! empty($c['accent'])) { $vars[] = '--accent:'.$c['accent']; $vars[] = '--hover-bd:'.$c['accent']; }
    if (! empty($c['button_bg'])) { $vars[] = '--btn-bg:'.$c['button_bg']; }
    if (! empty($c['button_text'])) { $vars[] = '--btn-fg:'.$c['button_text']; }
    if ($page->backgroundUrl()) { $vars[] = "--bg-img:url('".$page->backgroundUrl()."')"; }
    $font = $c['font'] ?? null;
    $shape = $c['shape'] ?? 'default';
    $items = $preview ? $page->items->filter(fn ($i) => $i->is_visible) : $page->items->filter(fn ($i) => $i->isActiveAt());
    $entrance = $page->entrance_enabled && ! $preview;
    $hasMusic = (bool) $page->music_url;
    $contactOk = request('danke') === 'kontakt';
    $contactErr = request('fehler') === 'kontakt';
    $reportOk = request('danke') === 'meldung';
    $reportErr = request('fehler') === 'meldung';
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} – Vylaris Links</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($page->bio ?: 'Alle Links von '.$page->title.' auf einer Seite.', 150) }}">
    <meta property="og:title" content="{{ $page->title }}">
    <meta property="og:type" content="profile">
    @if ($page->avatarUrl())<meta property="og:image" content="{{ $page->avatarUrl() }}">@endif
    @if ($preview)<meta name="robots" content="noindex,nofollow"><base target="_blank">@endif
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
</head>
<body class="page theme-{{ $page->theme }} layout-{{ $page->layout }} {{ $font ? 'font-'.$font : '' }} shape-{{ $shape }} {{ $page->backgroundUrl() ? 'has-bg' : '' }}"
      style="{{ implode(';', $vars) }}"
      data-particles="{{ $page->particles }}" data-sparks="{{ $page->cursor_sparks ? '1' : '0' }}" data-entrance="{{ $entrance ? '1' : '0' }}">
<a class="skip" href="#inhalt">Zum Inhalt springen</a>

@if ($page->particles !== 'none')<canvas id="fx" aria-hidden="true"></canvas>@endif
@if ($page->cursor_sparks && ! $preview)<canvas id="sparks" aria-hidden="true"></canvas>@endif
@if ($preview)<span class="preview-flag">Vorschau</span>@endif

@if ($entrance)
    <button type="button" id="entrance" aria-label="Seite betreten">
        <div><span>{{ $page->entrance_text ?: 'Tippen zum Betreten' }}</span>@if ($hasMusic)<small>mit Musik</small>@endif</div>
    </button>
@endif

<div class="wrap" id="inhalt">
    <div class="avatar-wrap ring-{{ $page->avatar_ring }}">
        @if ($page->avatarUrl())
            <img class="avatar" src="{{ $page->avatarUrl() }}" alt="Profilbild von {{ $page->title }}" width="104" height="104">
        @else
            <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($page->title, 0, 1)) }}</div>
        @endif
    </div>
    <h1 class="title">{{ $page->title }}</h1>
    @if ($page->bio)<p class="bio">{{ $page->bio }}</p>@endif

    @if ($page->socials->isNotEmpty())
        <ul class="socials" aria-label="Soziale Netzwerke">
            @foreach ($page->socials as $s)
                <li><a class="social" href="{{ $s->url }}" target="_blank" rel="noopener nofollow ugc" style="--sc:{{ Platforms::color($s->platform) }}" aria-label="{{ Platforms::label($s->platform) }}" title="{{ Platforms::label($s->platform) }}">{{ Platforms::badge($s->platform) }}</a></li>
            @endforeach
        </ul>
    @endif

    <div class="items">
        @foreach ($items as $item)
            @include('profile._item', ['item' => $item])
        @endforeach
    </div>

    @if ($page->contact_enabled)
        <details class="panel" id="kontakt" @if ($contactOk || $contactErr) open @endif>
            <summary>Schreib mir</summary>
            @if ($contactOk)<p class="notice" role="status">Danke, deine Nachricht ist angekommen.</p>@endif
            @if ($contactErr)<p class="err" role="alert">Das hat nicht geklappt. Bitte fülle Name und Nachricht aus (mindestens 5 Zeichen) und nutze höchstens zwei Links.</p>@endif
            <form method="post" action="{{ $preview ? '#' : route('profile.contact', $page->slug) }}" @if ($preview) onsubmit="return false" @endif>
                <div class="hp" aria-hidden="true"><label>Webseite<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div><label for="c-name">Name</label><input id="c-name" name="name" maxlength="80" required></div>
                <div><label for="c-mail">E-Mail (optional, für eine Antwort)</label><input id="c-mail" name="email" type="email" maxlength="120"></div>
                <div><label for="c-body">Nachricht</label><textarea id="c-body" name="body" rows="4" maxlength="2000" required></textarea></div>
                <button type="submit">{{ $preview ? 'Senden (in der Vorschau aus)' : 'Senden' }}</button>
            </form>
        </details>
    @endif

    <div class="foot">
        @unless ($page->hide_branding)
            <a href="{{ route('landing') }}" target="_blank" rel="noopener">Erstellt mit Vylaris Links</a>
        @endunless
        @unless ($preview)
            <details class="report" id="melden" @if ($reportOk || $reportErr) open @endif>
                <summary>Seite melden</summary>
                @if ($reportOk)<p class="notice" role="status">Danke, wir prüfen die Meldung.</p>@endif
                @if ($reportErr)<p class="err" role="alert">Bitte wähle einen Grund aus.</p>@endif
                <form method="post" action="{{ route('profile.report', $page->slug) }}">
                    <div class="hp" aria-hidden="true"><label>Webseite<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <select name="reason" required aria-label="Grund">
                        @foreach (\App\Models\Report::REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                    <textarea name="details" rows="2" maxlength="1000" placeholder="Details (optional)" aria-label="Details"></textarea>
                    <button type="submit">Melden</button>
                </form>
            </details>
        @endunless
    </div>
</div>

@if ($hasMusic && ! $preview)
    <audio id="bgm" src="{{ $page->music_url }}" loop preload="none"></audio>
    <button type="button" id="music-toggle" aria-pressed="false" aria-label="Musik ein- oder ausschalten">Musik</button>
@endif

<script src="{{ asset('js/profile.js') }}?v={{ filemtime(public_path('js/profile.js')) }}" defer></script>
</body>
</html>
