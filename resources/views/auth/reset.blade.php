@extends('auth._shell')
@section('title', 'Neues Passwort – Vylaris Links')
@section('heading', 'Neues Passwort setzen')
@section('sub', 'Wähle ein sicheres Passwort.')
@section('form')
<form method="post" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label class="label" for="email">E-Mail</label>
        <input class="field" id="email" name="email" type="email" value="{{ old('email', $email) }}" required>
        @error('email')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="password">Neues Passwort</label>
        <input class="field" id="password" name="password" type="password" required autocomplete="new-password">
        <p class="hint">Mindestens 10 Zeichen, mit Buchstaben und Zahlen.</p>
        @error('password')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="password_confirmation">Wiederholen</label>
        <input class="field" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
    </div>
    <button class="btn btn-primary w-full" type="submit">Passwort speichern</button>
</form>
@endsection
