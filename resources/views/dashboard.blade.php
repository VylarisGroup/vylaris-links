@extends('layouts.app')
@section('title', 'Meine Seiten – Vylaris Links')
@section('content')
<div class="container-v py-12">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Dashboard</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight">Meine Seiten</h1>
            <p class="mt-1 text-sm text-muted">{{ $pages->count() }} von {{ $max }} Seiten genutzt.</p>
        </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($pages as $page)
            @php $s = $totals[$page->id]; @endphp
            <article class="card">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">{{ $page->title }}</h2>
                        <a class="truncate text-sm text-brand hover:underline" href="{{ route('profile.show', $page->slug) }}" target="_blank" rel="noopener">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/{{ $page->slug }}</a>
                    </div>
                    @if ($page->blocked_at)
                        <span class="badge border-red-500/40 text-red-300">Gesperrt</span>
                    @elseif (! $page->is_published)
                        <span class="badge">Entwurf</span>
                    @else
                        <span class="badge border-emerald-500/30 text-emerald-300">Online</span>
                    @endif
                </div>
                @if ($page->blocked_at)
                    <p class="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 p-2.5 text-xs text-red-200">Diese Seite wurde vom Team gesperrt{{ $page->blocked_reason ? ': '.$page->blocked_reason : '.' }} Melde dich beim Support, wenn du das für einen Irrtum hältst.</p>
                @endif
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-ink/50 p-2"><dt class="text-[11px] text-dim">Aufrufe (7 T.)</dt><dd class="text-lg font-bold">{{ $s['views'] }}</dd></div>
                    <div class="rounded-xl bg-ink/50 p-2"><dt class="text-[11px] text-dim">Klicks (7 T.)</dt><dd class="text-lg font-bold">{{ $s['clicks'] }}</dd></div>
                    <div class="rounded-xl bg-ink/50 p-2"><dt class="text-[11px] text-dim">Einträge</dt><dd class="text-lg font-bold">{{ $page->items_count }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('editor.edit', $page) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
                    <a href="{{ route('editor.messages', $page) }}" class="btn btn-ghost btn-sm">Nachrichten @if ($page->unread_messages_count)<span class="rounded-full bg-brand px-1.5 text-[10px] text-white">{{ $page->unread_messages_count }}</span>@endif</a>
                    <form method="post" action="{{ route('pages.destroy', $page->id) }}" onsubmit="return confirm('Seite „{{ $page->slug }}“ wirklich löschen? Das kann nicht rückgängig gemacht werden.')" class="ml-auto">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
                    </form>
                </div>
            </article>
        @endforeach

        @if ($pages->count() < $max)
            <article class="card border-dashed">
                <h2 class="font-semibold">Neue Seite anlegen</h2>
                <form method="post" action="{{ route('pages.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="label" for="title">Titel</label>
                        <input class="field" id="title" name="title" value="{{ old('title', auth()->user()->name) }}" maxlength="80" required>
                        @error('title')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="slug">Adresse</label>
                        <div class="flex items-center rounded-xl border border-line bg-ink/60 px-3 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30">
                            <span class="shrink-0 text-xs text-dim">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</span>
                            <input class="w-full border-0 bg-transparent px-1 py-2.5 text-sm outline-none" id="slug" name="slug" value="{{ old('slug', session('wanted_slug')) }}" maxlength="40" pattern="[a-z0-9\-]+" required>
                        </div>
                        @error('slug')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Seite anlegen</button>
                </form>
            </article>
        @endif
    </div>
</div>
@endsection
