<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Geschützter Link – {{ $page->title }}</title>
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
</head>
<body class="page theme-{{ $page->theme }} layout-{{ $page->layout }}">
<div class="wrap">
    <h1 class="title">Geschützter Link</h1>
    <p class="bio">{{ $item->title ?: 'Dieser Link' }} von {{ $page->title }}</p>
    <form method="post" action="{{ route('go.unlock', $item->id) }}" class="panel" style="display:block">
        <div style="display:grid;gap:10px;margin-top:16px;padding:16px;border-radius:var(--radius);border:var(--bd-w) solid var(--btn-bd);background:var(--btn-bg)">
            @if ($item->password_hash)
                <div><label for="pw">Passwort</label><input id="pw" type="password" name="password" required autofocus autocomplete="off"></div>
                @if ($error)<p class="err" role="alert">{{ $error }}</p>@endif
            @endif
            @if ($item->age_gate)
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:.9rem;color:var(--fg)"><input type="checkbox" name="adult" value="1" style="width:auto;margin-top:3px"> Ich bin mindestens 18 Jahre alt.</label>
                @if ($adultError)<p class="err" role="alert">{{ $adultError }}</p>@endif
            @endif
            <button type="submit">Weiter</button>
        </div>
    </form>
    <div class="foot"><a href="{{ route('profile.show', $page->slug) }}">Zurück zu {{ $page->title }}</a></div>
</div>
</body>
</html>
