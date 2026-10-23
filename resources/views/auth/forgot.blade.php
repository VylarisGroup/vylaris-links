@extends('auth._shell')
@section('title', 'Passwort vergessen – Vylaris Links')
@section('heading', 'Passwort vergessen')
@section('sub', 'Wir senden dir einen Link zum Zurücksetzen.')
@section('form')
<form method="post" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
        <label class="label" for="email">E-Mail</label>
        <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        @error('email')<p class="error">{{ $message }}</p>@enderror
    </div>
    <button class="btn btn-primary w-full" type="submit">Link senden</button>
    <p class="text-center text-sm"><a class="text-brand hover:underline" href="{{ route('login') }}">Zurück zur Anmeldung</a></p>
</form>
@endsection
