@extends('auth._shell')
@section('title', 'Anmelden – Vylaris Links')
@section('heading', 'Anmelden')
@section('sub', 'Schön, dass du wieder da bist.')
@section('form')
<form method="post" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label class="label" for="email">E-Mail</label>
        <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="password">Passwort</label>
        <input class="field" id="password" name="password" type="password" required autocomplete="current-password">
        @error('password')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2 text-muted"><input type="checkbox" name="remember" class="rounded border-line bg-ink"> Angemeldet bleiben</label>
        <a class="text-brand hover:underline" href="{{ route('password.request') }}">Passwort vergessen?</a>
    </div>
    <button class="btn btn-primary w-full" type="submit">Anmelden</button>
    @if (config('links.registration_open'))
        <p class="text-center text-sm text-muted">Noch kein Konto? <a class="text-brand hover:underline" href="{{ route('register') }}">Konto erstellen</a></p>
    @endif
</form>
@endsection
