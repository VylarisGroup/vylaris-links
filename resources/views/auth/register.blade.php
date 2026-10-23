@extends('auth._shell')
@section('title', 'Konto erstellen – Vylaris Links')
@section('heading', 'Konto erstellen')
@section('sub', 'Kostenlos, ohne Werbung und ohne Tracking.')
@section('form')
<form method="post" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <div>
        <label class="label" for="name">Name</label>
        <input class="field" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        @error('name')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="email">E-Mail</label>
        <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        @error('email')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="password">Passwort</label>
        <input class="field" id="password" name="password" type="password" required autocomplete="new-password">
        <p class="hint">Mindestens 10 Zeichen, mit Buchstaben und Zahlen.</p>
        @error('password')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="password_confirmation">Passwort wiederholen</label>
        <input class="field" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
    </div>
    <label class="flex items-start gap-2 text-sm text-muted">
        <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-line bg-ink" required>
        <span>Ich habe den <a class="text-brand hover:underline" href="https://dev.vylaris.ch/datenschutz" target="_blank" rel="noopener">Datenschutz</a> gelesen und akzeptiere ihn.</span>
    </label>
    @error('terms')<p class="error">{{ $message }}</p>@enderror
    <button class="btn btn-primary w-full" type="submit">Konto erstellen</button>
    <p class="text-center text-sm text-muted">Schon registriert? <a class="text-brand hover:underline" href="{{ route('login') }}">Anmelden</a></p>
</form>
@endsection
