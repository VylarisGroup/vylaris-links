@extends('layouts.app')
@section('title', 'Admin – Vylaris Links')
@section('content')
<div class="container-v py-12">
    <p class="eyebrow">Admin</p>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight">Moderation</h1>
        <a href="{{ route('admin.update') }}" class="btn btn-ghost btn-sm">Updates
            @if (app(\App\Support\Updater\Updater::class)->updateAvailable())<span class="rounded-full bg-emerald-500 px-1.5 text-[10px] text-white">neu</span>@endif
        </a>
    </div>
    <dl class="mt-6 grid grid-cols-3 gap-3 sm:max-w-md">
        <div class="card p-3 text-center"><dt class="text-xs text-dim">Konten</dt><dd class="text-xl font-bold">{{ $counts['users'] }}</dd></div>
        <div class="card p-3 text-center"><dt class="text-xs text-dim">Seiten</dt><dd class="text-xl font-bold">{{ $counts['pages'] }}</dd></div>
        <div class="card p-3 text-center"><dt class="text-xs text-dim">Gesperrt</dt><dd class="text-xl font-bold">{{ $counts['blocked'] }}</dd></div>
    </dl>

    <h2 class="mt-10 text-xl font-semibold">Offene Meldungen ({{ $openReports->count() }})</h2>
    <div class="mt-4 space-y-3">
        @forelse ($openReports as $r)
            <article class="card flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <a class="font-semibold text-brand hover:underline" href="{{ route('profile.show', $r->page->slug) }}" target="_blank" rel="noopener">/{{ $r->page->slug }}</a>
                    <span class="badge ml-2">{{ \App\Models\Report::REASONS[$r->reason] ?? $r->reason }}</span>
                    <p class="mt-1 text-sm text-muted">{{ $r->details ?: 'Keine Details angegeben.' }}</p>
                    <p class="mt-1 text-xs text-dim">{{ $r->created_at->format('d.m.Y H:i') }} · Besitzer: {{ $r->page->user->name }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="{{ route('admin.pages.block', $r->page_id) }}" class="flex gap-2">@csrf
                        <input class="field !w-44 !py-1.5 text-xs" name="reason" placeholder="Grund (optional)" maxlength="200">
                        <button class="btn btn-danger btn-sm" type="submit">Seite sperren</button>
                    </form>
                    <form method="post" action="{{ route('admin.reports.dismiss', $r->id) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Abweisen</button></form>
                </div>
            </article>
        @empty
            <p class="text-sm text-muted">Keine offenen Meldungen.</p>
        @endforelse
    </div>

    <h2 class="mt-10 text-xl font-semibold">Alle Seiten</h2>
    <div class="mt-4 overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface text-xs uppercase tracking-wide text-dim"><tr><th class="p-3">Adresse</th><th class="p-3">Besitzer</th><th class="p-3">Status</th><th class="p-3 text-right">Aktionen</th></tr></thead>
            <tbody class="divide-y divide-line">
            @foreach ($pages as $p)
                <tr>
                    <td class="p-3"><a class="text-brand hover:underline" href="{{ route('profile.show', $p->slug) }}" target="_blank" rel="noopener">/{{ $p->slug }}</a></td>
                    <td class="p-3 text-muted">{{ $p->user->name }} <span class="text-dim">({{ $p->user->email }})</span></td>
                    <td class="p-3">
                        @if ($p->blocked_at)<span class="badge border-red-500/40 text-red-300">Seite gesperrt</span>@endif
                        @if ($p->user->isBlocked())<span class="badge border-red-500/40 text-red-300">Konto gesperrt</span>@endif
                        @if (! $p->blocked_at && ! $p->user->isBlocked())<span class="badge">{{ $p->is_published ? 'Online' : 'Entwurf' }}</span>@endif
                    </td>
                    <td class="flex justify-end gap-2 p-3">
                        @if ($p->blocked_at)
                            <form method="post" action="{{ route('admin.pages.unblock', $p->id) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Freigeben</button></form>
                        @else
                            <form method="post" action="{{ route('admin.pages.block', $p->id) }}">@csrf<button class="btn btn-danger btn-sm" type="submit">Sperren</button></form>
                        @endif
                        @if ($p->user->isBlocked())
                            <form method="post" action="{{ route('admin.users.unblock', $p->user_id) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Konto freigeben</button></form>
                        @elseif (! $p->user->is_admin)
                            <form method="post" action="{{ route('admin.users.block', $p->user_id) }}">@csrf<button class="btn btn-danger btn-sm" type="submit">Konto sperren</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $pages->links() }}</div>
</div>
@endsection
