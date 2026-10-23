@extends('layouts.app')
@section('title', 'Updates – Vylaris Links')
@section('content')
<div class="container-v max-w-3xl py-12">
    <a href="{{ route('admin.index') }}" class="text-xs text-muted hover:text-fg">← Moderation</a>
    <p class="eyebrow mt-3">Admin</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Updates</h1>
    <p class="mt-1 text-sm text-muted">Aktualisiert die Anwendung aus der Datei <code class="text-fg">{{ $asset }}</code> im neuesten Release von <code class="text-fg">{{ $repo }}</code>.</p>

    @if (! $enabled)
        <p class="mt-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-200">Das Update-System ist abgeschaltet (<code>UPDATER_ENABLED=false</code>).</p>
    @endif
    @error('update')<p class="mt-6 rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="card">
            <p class="text-xs text-dim">Installiert</p>
            <p class="text-3xl font-bold">v{{ $current }}</p>
        </div>
        <div class="card">
            <p class="text-xs text-dim">Neuestes Release</p>
            @if ($state && ! empty($state['latest']))
                <p class="text-3xl font-bold">v{{ $state['latest']['version'] }}</p>
                <p class="mt-1 text-xs text-dim">
                    @if ($state['available'])<span class="text-emerald-300">Update verfügbar</span>@else Aktuell @endif
                    · geprüft {{ \Illuminate\Support\Carbon::parse($state['checked_at'])->format('d.m.Y H:i') }}
                </p>
            @elseif ($state && $state['error'])
                <p class="text-sm text-red-300">{{ $state['error'] }}</p>
            @else
                <p class="text-sm text-muted">{{ $state ? 'Noch kein Release veröffentlicht.' : 'Noch nicht geprüft.' }}</p>
            @endif
        </div>
    </div>

    <form method="post" action="{{ route('admin.update.check') }}" class="mt-4">@csrf
        <button class="btn btn-ghost btn-sm" type="submit" @disabled(! $enabled)>Jetzt nach Updates suchen</button>
    </form>

    @if ($state && ! empty($state['latest']) && $state['available'])
        <div class="card mt-6">
            <h2 class="font-semibold">{{ $state['latest']['name'] }}</h2>
            @if ($state['latest']['notes'])
                <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-xl bg-ink/60 p-3 text-xs text-muted">{{ $state['latest']['notes'] }}</pre>
            @endif
            @if ($state['latest']['url'])
                <a class="mt-3 inline-block text-xs text-brand hover:underline" href="{{ $state['latest']['url'] }}" target="_blank" rel="noopener">Release auf GitHub ansehen</a>
            @endif
        </div>
    @endif

    <div class="card mt-6" x-data="updater()" x-init="init()"
         data-status="{{ json_encode($status, JSON_UNESCAPED_UNICODE) }}" data-install-url="{{ route('admin.update.install') }}" data-status-url="{{ route('admin.update.status') }}">
        <h2 class="font-semibold">Installation</h2>
        <p class="mt-1 text-sm text-muted">Vor dem Austausch wird die Datenbank gesichert und die Seite geht kurz in den Wartungsmodus. Bei einem Fehler wird automatisch auf die bisherige Version zurückgesetzt.</p>

        <p class="error mt-3" x-show="error" x-cloak x-text="error" role="alert"></p>

        <div class="mt-4" x-show="status.state && status.state !== 'idle'" x-cloak>
            <div class="h-2 overflow-hidden rounded-full bg-ink/70" role="progressbar" :aria-valuenow="status.percent || 0" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full transition-all" :class="status.state === 'failed' ? 'bg-red-500' : 'bg-brand'" :style="'width:' + (status.state === 'failed' ? 100 : (status.percent || 0)) + '%'"></div>
            </div>
            <p class="mt-2 text-sm" :class="status.state === 'failed' ? 'text-red-300' : status.state === 'done' ? 'text-emerald-300' : 'text-muted'" x-text="status.message" aria-live="polite"></p>
            <pre class="mt-3 max-h-48 overflow-auto whitespace-pre-wrap rounded-xl bg-ink/60 p-3 text-xs text-dim" x-show="status.log && status.log.length" x-text="(status.log || []).join('\n')"></pre>
        </div>

        <button type="button" class="btn btn-primary mt-4" @click="install()" :disabled="busy"
                @if (! $enabled || ! $state || ! $state['available'] || collect($requirements)->contains(fn ($r) => $r['blocking'] && ! $r['ok'])) disabled @endif>
            <span x-text="busy ? 'Update läuft …' : 'Update installieren'"></span>
        </button>
    </div>

    <div class="card mt-6">
        <h2 class="font-semibold">Voraussetzungen</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($requirements as $r)
                <li class="flex items-start gap-2">
                    <span class="{{ $r['ok'] ? 'text-emerald-300' : ($r['blocking'] ? 'text-red-300' : 'text-amber-300') }}" aria-hidden="true">{{ $r['ok'] ? '✓' : ($r['blocking'] ? '✕' : '!') }}</span>
                    <span><span class="sr-only">{{ $r['ok'] ? 'Erfüllt:' : 'Nicht erfüllt:' }}</span>{{ $r['label'] }} <span class="text-dim">– {{ $r['hint'] }}</span></span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card mt-6">
        <h2 class="font-semibold">Sicherungen</h2>
        <p class="mt-1 text-sm text-muted">Die letzten Sicherungen (Dateien und Datenbank) liegen in <code class="text-fg">storage/app/updater/backups</code>.</p>
        <ul class="mt-3 space-y-1 text-sm text-muted">
            @forelse ($backups as $b)
                <li>{{ $b['name'] }} <span class="text-dim">· {{ $b['created'] }}</span></li>
            @empty
                <li class="text-dim">Noch keine Sicherungen.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
