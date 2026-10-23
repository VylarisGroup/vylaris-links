@extends('layouts.app')
@section('title', 'Nachrichten – '.$page->title)
@section('content')
<div class="container-v max-w-3xl py-12">
    <a href="{{ route('editor.edit', $page) }}" class="text-xs text-muted hover:text-fg">← Zurück zum Editor</a>
    <h1 class="mt-2 text-3xl font-bold tracking-tight">Nachrichten</h1>
    <p class="mt-1 text-sm text-muted">Von deinem Kontaktformular auf /{{ $page->slug }}.</p>
    <div class="mt-8 space-y-3">
        @forelse ($messages as $m)
            <article class="card">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $m->name }}</p>
                        <p class="text-xs text-dim">{{ $m->created_at->format('d.m.Y H:i') }} @if ($m->email)· <a class="text-brand hover:underline" href="mailto:{{ $m->email }}">{{ $m->email }}</a>@endif</p>
                    </div>
                    <form method="post" action="{{ route('editor.messages.destroy', [$page, $m->id]) }}" onsubmit="return confirm('Nachricht löschen?')">@csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
                    </form>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm text-muted">{{ $m->body }}</p>
            </article>
        @empty
            <p class="card text-sm text-muted">Noch keine Nachrichten.</p>
        @endforelse
    </div>
</div>
@endsection
