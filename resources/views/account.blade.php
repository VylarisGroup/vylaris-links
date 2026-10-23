@extends('layouts.app')
@section('title', 'Konto – Vylaris Links')
@section('content')
<div class="container-v max-w-2xl py-12">
    <p class="eyebrow">Konto</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Einstellungen</h1>

    <form method="post" action="{{ route('account.update') }}" class="card mt-8 space-y-4">
        @csrf @method('PATCH')
        <div>
            <label class="label" for="name">Name</label>
            <input class="field" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="80">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="label">E-Mail</span>
            <p class="text-sm text-muted">{{ $user->email }}</p>
        </div>
        <label class="flex items-center gap-3 text-sm">
            <input type="checkbox" name="weekly_report" value="1" class="rounded border-line bg-ink" @checked(old('weekly_report', $user->weekly_report))>
            Jeden Montag den Wochenbericht per E-Mail senden
        </label>
        <button class="btn btn-primary btn-sm" type="submit">Speichern</button>
    </form>

    <form method="post" action="{{ route('account.password') }}" class="card mt-6 space-y-4">
        @csrf @method('PUT')
        <h2 class="font-semibold">Passwort ändern</h2>
        <div>
            <label class="label" for="current_password">Aktuelles Passwort</label>
            <input class="field" id="current_password" name="current_password" type="password" required autocomplete="current-password">
            @error('current_password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Neues Passwort</label>
            <input class="field" id="password" name="password" type="password" required autocomplete="new-password">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Wiederholen</label>
            <input class="field" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-sm" type="submit">Passwort ändern</button>
    </form>

    <form method="post" action="{{ route('account.destroy') }}" class="card mt-6 space-y-4 border-red-500/30" onsubmit="return confirm('Konto und alle Seiten endgültig löschen?')">
        @csrf @method('DELETE')
        <h2 class="font-semibold text-red-300">Konto löschen</h2>
        <p class="text-sm text-muted">Dabei werden alle deine Seiten, Links, Nachrichten und Statistiken unwiderruflich gelöscht.</p>
        <div>
            <label class="label" for="del_password">Zur Bestätigung dein Passwort</label>
            <input class="field" id="del_password" name="current_password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-danger btn-sm" type="submit">Konto endgültig löschen</button>
    </form>
</div>
@endsection
