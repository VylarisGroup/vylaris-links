@extends('layouts.app')
@section('title', 'Editor – '.$page->title)
@push('head')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
@endpush

@section('content')
<script type="application/json" id="editor-state">{!! json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

<div class="container-v py-8" x-data="editor()" x-init="init()">
    {{-- Kopfzeile --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('dashboard') }}" class="text-xs text-muted hover:text-fg">← Meine Seiten</a>
            <h1 class="truncate text-2xl font-bold tracking-tight" x-text="page.title"></h1>
            <a class="text-sm text-brand hover:underline" :href="page.public_url" target="_blank" rel="noopener" x-text="page.public_url"></a>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-muted" x-show="saving" x-cloak>Speichert …</span>
            <span class="text-xs text-emerald-300" x-show="saved && !saving" x-cloak>Gespeichert ✓</span>
            <button type="button" class="btn btn-ghost btn-sm" @click="copyUrl()" x-text="copied ? 'Kopiert ✓' : 'Adresse kopieren'"></button>
            <label class="flex items-center gap-2 text-sm">
                <button type="button" class="toggle" role="switch" :aria-checked="page.is_published" @click="page.is_published = !page.is_published; field('is_published')"><span></span></button>
                <span x-text="page.is_published ? 'Online' : 'Entwurf'"></span>
            </label>
        </div>
    </div>

    <p x-show="page.blocked" x-cloak class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200">Diese Seite wurde vom Team gesperrt und ist nicht öffentlich sichtbar.</p>
    <p x-show="message" x-cloak class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200" role="alert" x-text="message"></p>

    <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
        {{-- ============ Bearbeiten ============ --}}
        <div class="min-w-0">
            <div class="flex flex-wrap gap-1 rounded-2xl border border-line bg-surface/70 p-1" role="tablist">
                @foreach (['inhalt' => 'Inhalt', 'design' => 'Design', 'effekte' => 'Effekte', 'statistik' => 'Statistik', 'einstellungen' => 'Einstellungen'] as $k => $l)
                    <button type="button" role="tab" class="btn btn-sm flex-1" :class="tab === '{{ $k }}' ? 'btn-primary' : 'text-muted hover:text-fg'" :aria-selected="tab === '{{ $k }}'" @click="tab = '{{ $k }}'">{{ $l }}</button>
                @endforeach
            </div>

            {{-- ---- Inhalt ---- --}}
            <section x-show="tab === 'inhalt'" class="mt-6 space-y-6">
                <div class="card space-y-4">
                    <h2 class="font-semibold">Profil</h2>
                    <div class="flex items-center gap-4">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full border border-line bg-ink">
                            <template x-if="page.avatar_url"><img :src="page.avatar_url" alt="" class="h-full w-full object-cover"></template>
                            <template x-if="!page.avatar_url"><div class="grid h-full w-full place-items-center text-2xl font-bold text-brand" x-text="(page.title || '?').charAt(0).toUpperCase()"></div></template>
                        </div>
                        <div class="space-y-2 text-sm">
                            <label class="btn btn-ghost btn-sm cursor-pointer">Bild hochladen<input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="upload('avatar', $event)"></label>
                            <button type="button" class="btn btn-ghost btn-sm" x-show="page.avatar_url" x-cloak @click="removeUpload('avatar')">Entfernen</button>
                            <p class="hint">JPG, PNG oder WebP, höchstens 2 MB.</p>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="f-title">Titel</label>
                        <input id="f-title" class="field" maxlength="80" x-model="page.title" @input.debounce.600ms="field('title')">
                    </div>
                    <div>
                        <label class="label" for="f-bio">Beschreibung</label>
                        <textarea id="f-bio" class="field" rows="3" maxlength="280" x-model="page.bio" @input.debounce.600ms="field('bio')"></textarea>
                    </div>
                </div>

                <div class="card space-y-3">
                    <h2 class="font-semibold">Social-Icons</h2>
                    <ul class="flex flex-wrap gap-2">
                        <template x-for="s in socials" :key="s.id">
                            <li class="badge gap-2"><span x-text="s.platform"></span>
                                <button type="button" class="text-red-300 hover:text-red-200" :aria-label="'Entfernen: ' + s.platform" @click="removeSocial(s)">✕</button>
                            </li>
                        </template>
                    </ul>
                    <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="addSocial()">
                        <select class="field sm:w-44" x-model="newSocial.platform" aria-label="Plattform">
                            @foreach ($options['platforms'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                        </select>
                        <input class="field" x-model="newSocial.url" placeholder="Adresse, Profil-Link oder E-Mail" aria-label="Adresse" maxlength="500">
                        <button class="btn btn-primary btn-sm" type="submit">Hinzufügen</button>
                    </form>
                </div>

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold">Einträge</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($options['types'] as $k => $l)
                            <button type="button" class="btn btn-ghost btn-sm" @click="addItem('{{ $k }}')">+ {{ $l }}</button>
                        @endforeach
                    </div>

                    <p class="card text-sm text-muted" x-show="items.length === 0" x-cloak>Noch keine Einträge. Füge oben deinen ersten Link hinzu.</p>

                    <template x-for="(item, idx) in items" :key="item.id">
                        <article class="card p-0" :class="!item.is_visible && 'opacity-60'">
                            <div class="flex items-center gap-2 px-4 py-3">
                                <div class="flex flex-col">
                                    <button type="button" class="text-xs text-muted hover:text-fg disabled:opacity-30" :disabled="idx === 0" aria-label="Nach oben" @click="move(item, -1)">▲</button>
                                    <button type="button" class="text-xs text-muted hover:text-fg disabled:opacity-30" :disabled="idx === items.length - 1" aria-label="Nach unten" @click="move(item, 1)">▼</button>
                                </div>
                                <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="item._open = !item._open" :aria-expanded="item._open">
                                    <span class="badge" x-text="typeLabel(item.type)"></span>
                                    <span class="truncate text-sm font-medium" x-text="item.title || item.url || item.body || '(leer)'"></span>
                                    <span class="badge border-amber-500/40 text-amber-300" x-show="item.has_password || item.age_gate" x-cloak>geschützt</span>
                                    <span class="badge" x-show="item.schedule.enabled" x-cloak>Zeitplan</span>
                                </button>
                                <button type="button" class="toggle" role="switch" :aria-checked="item.is_visible" aria-label="Sichtbar" @click="item.is_visible = !item.is_visible; saveItem(item)"><span></span></button>
                            </div>

                            <div class="space-y-4 border-t border-line px-4 py-4" x-show="item._open" x-cloak>
                                <p class="error" x-show="errors[item.id]" x-text="errors[item.id]" role="alert"></p>

                                <template x-if="['link','heading','image','youtube','twitch','spotify','countdown','gallery'].includes(item.type)">
                                    <div>
                                        <label class="label" x-text="item.type === 'image' ? 'Bildunterschrift' : 'Titel'"></label>
                                        <input class="field" maxlength="120" x-model="item.title" @input="queueSave(item)">
                                    </div>
                                </template>

                                <template x-if="item.type === 'text'">
                                    <div><label class="label">Text</label><textarea class="field" rows="4" maxlength="1500" x-model="item.body" @input="queueSave(item)"></textarea></div>
                                </template>

                                <template x-if="['link','image','youtube','twitch','spotify'].includes(item.type)">
                                    <div>
                                        <label class="label" x-text="{link:'Adresse', image:'Bild-Adresse (https)', youtube:'YouTube-Link', twitch:'Twitch-Kanal oder -Link', spotify:'Spotify-Link'}[item.type]"></label>
                                        <input class="field" maxlength="500" x-model="item.url" @input="queueSave(item)" placeholder="https://…">
                                    </div>
                                </template>

                                <template x-if="item.type === 'countdown'">
                                    <div><label class="label">Zeitpunkt</label><input type="datetime-local" class="field" x-model="item.data.target" @change="queueSave(item)"></div>
                                </template>

                                <template x-if="item.type === 'gallery'">
                                    <div><label class="label">Bild-Adressen (eine pro Zeile, max. 12)</label><textarea class="field" rows="4" x-model="item._images" @input="queueSave(item)" placeholder="https://…"></textarea></div>
                                </template>

                                <template x-if="item.type === 'link'">
                                    <div class="space-y-4">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="label">Symbol</label>
                                                <select class="field" x-model="item.icon" @change="queueSave(item)">
                                                    <option value="">Kein Symbol</option>
                                                    @foreach ($options['platforms'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="label">Effekt</label>
                                                <select class="field" x-model="item.effect" @change="queueSave(item)">
                                                    @foreach ($options['effects'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-line p-3">
                                            <label class="flex items-center justify-between gap-3 text-sm font-semibold">Zeitsteuerung
                                                <button type="button" class="toggle" role="switch" :aria-checked="item.schedule.enabled" @click="item.schedule.enabled = !item.schedule.enabled; queueSave(item)"><span></span></button>
                                            </label>
                                            <div class="mt-3 space-y-3" x-show="item.schedule.enabled" x-cloak>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $n => $d)
                                                        <button type="button" class="btn btn-sm" :class="item.schedule.days.includes({{ $n }}) ? 'btn-primary' : 'btn-ghost'" @click="toggleDay(item, {{ $n }})">{{ $d }}</button>
                                                    @endforeach
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div><label class="label">Von</label><input type="time" class="field" x-model="item.schedule.from" @change="queueSave(item)"></div>
                                                    <div><label class="label">Bis</label><input type="time" class="field" x-model="item.schedule.to" @change="queueSave(item)"></div>
                                                </div>
                                                <p class="hint">Ohne Tage gilt jeder Tag, ohne Uhrzeit die ganze Zeit. Zeiten in Schweizer Zeit.</p>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-line p-3 space-y-3">
                                            <p class="text-sm font-semibold">Schutz</p>
                                            <label class="flex items-center justify-between gap-3 text-sm">„Ab 18“-Bestätigung verlangen
                                                <button type="button" class="toggle" role="switch" :aria-checked="item.age_gate" @click="item.age_gate = !item.age_gate; queueSave(item)"><span></span></button>
                                            </label>
                                            <div class="flex flex-col gap-2 sm:flex-row">
                                                <input type="password" class="field" autocomplete="new-password" x-model="passwords[item.id]" :placeholder="item.has_password ? 'Neues Passwort setzen' : 'Passwort (mind. 4 Zeichen)'">
                                                <button type="button" class="btn btn-ghost btn-sm" @click="setPassword(item)">Setzen</button>
                                                <button type="button" class="btn btn-danger btn-sm" x-show="item.has_password" x-cloak @click="removePassword(item)">Entfernen</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div class="flex justify-end"><button type="button" class="btn btn-danger btn-sm" @click="removeItem(item)">Eintrag löschen</button></div>
                            </div>
                        </article>
                    </template>
                </div>
            </section>

            {{-- ---- Design ---- --}}
            <section x-show="tab === 'design'" x-cloak class="mt-6 space-y-6">
                <div class="card">
                    <h2 class="font-semibold">Design</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($options['themes'] as $k => $l)
                            <button type="button" class="rounded-xl border p-2 text-left transition" :class="page.theme === '{{ $k }}' ? 'border-brand ring-2 ring-brand/40' : 'border-line hover:border-brand/50'" @click="page.theme = '{{ $k }}'; field('theme')">
                                <span class="theme-preview theme-{{ $k }} flex h-20 flex-col items-center justify-center gap-1.5 rounded-lg p-2" aria-hidden="true"><span class="tp-btn"></span><span class="tp-btn w-4/5"></span></span>
                                <span class="mt-2 block text-sm font-medium">{{ $l }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="card">
                    <h2 class="font-semibold">Anordnung</h2>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($options['layouts'] as $k => $l)
                            <button type="button" class="btn btn-sm" :class="page.layout === '{{ $k }}' ? 'btn-primary' : 'btn-ghost'" @click="page.layout = '{{ $k }}'; field('layout')">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="card space-y-4">
                    <h2 class="font-semibold">Eigene Gestaltung</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['bg' => 'Hintergrund', 'text' => 'Textfarbe', 'accent' => 'Akzent', 'button_bg' => 'Knopf-Hintergrund', 'button_text' => 'Knopf-Text'] as $k => $l)
                            <div>
                                <label class="label">{{ $l }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-line bg-transparent" :value="page.colors.{{ $k }} || '#4f8cff'" @change="setColor('{{ $k }}', $event.target.value)" aria-label="{{ $l }}">
                                    <button type="button" class="btn btn-ghost btn-sm" x-show="page.colors.{{ $k }}" x-cloak @click="setColor('{{ $k }}', null)">Zurücksetzen</button>
                                </div>
                            </div>
                        @endforeach
                        <div>
                            <label class="label">Schrift</label>
                            <select class="field" :value="page.colors.font || ''" @change="setColor('font', $event.target.value)">
                                <option value="">Vom Design</option>
                                @foreach ($options['fonts'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Knopfform</label>
                            <select class="field" :value="page.colors.shape || ''" @change="setColor('shape', $event.target.value)">
                                <option value="">Vom Design</option>
                                @foreach ($options['shapes'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label">Hintergrundbild</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="btn btn-ghost btn-sm cursor-pointer">Bild hochladen<input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="upload('background', $event)"></label>
                            <button type="button" class="btn btn-ghost btn-sm" x-show="page.background_url" x-cloak @click="removeUpload('background')">Entfernen</button>
                        </div>
                        <p class="hint">JPG, PNG oder WebP, höchstens 4 MB.</p>
                    </div>
                </div>
            </section>

            {{-- ---- Effekte ---- --}}
            <section x-show="tab === 'effekte'" x-cloak class="mt-6 space-y-6">
                <div class="card grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Ring ums Profilbild</label>
                        <select class="field" x-model="page.avatar_ring" @change="field('avatar_ring')">@foreach ($options['rings'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="label">Partikel über der Seite</label>
                        <select class="field" x-model="page.particles" @change="field('particles')">@foreach ($options['particles'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    </div>
                    <label class="flex items-center justify-between gap-3 text-sm sm:col-span-2">Funken hinter dem Mauszeiger
                        <button type="button" class="toggle" role="switch" :aria-checked="page.cursor_sparks" @click="page.cursor_sparks = !page.cursor_sparks; field('cursor_sparks')"><span></span></button>
                    </label>
                </div>
                <div class="card space-y-4">
                    <label class="flex items-center justify-between gap-3 text-sm font-semibold">Eingang mit Animation
                        <button type="button" class="toggle" role="switch" :aria-checked="page.entrance_enabled" @click="page.entrance_enabled = !page.entrance_enabled; field('entrance_enabled')"><span></span></button>
                    </label>
                    <div x-show="page.entrance_enabled" x-cloak class="space-y-4">
                        <div><label class="label">Text auf dem Eingang</label><input class="field" maxlength="80" x-model="page.entrance_text" placeholder="Tippen zum Betreten" @input.debounce.600ms="field('entrance_text')"></div>
                    </div>
                    <div>
                        <label class="label">Hintergrundmusik (Adresse einer Audiodatei)</label>
                        <input class="field" maxlength="500" x-model="page.music_url" placeholder="https://…/lied.mp3" @input.debounce.800ms="field('music_url')">
                        <p class="hint">Die Musik startet erst nach einem Klick, entweder auf dem Eingang oder über den Musik-Knopf. Nutze nur Musik, für die du die Rechte hast.</p>
                    </div>
                </div>
                <div class="card space-y-3">
                    <label class="flex items-center justify-between gap-3 text-sm">Kontaktformular anzeigen
                        <button type="button" class="toggle" role="switch" :aria-checked="page.contact_enabled" @click="page.contact_enabled = !page.contact_enabled; field('contact_enabled')"><span></span></button>
                    </label>
                    <label class="flex items-center justify-between gap-3 text-sm">„Erstellt mit Vylaris Links“ ausblenden
                        <button type="button" class="toggle" role="switch" :aria-checked="page.hide_branding" @click="page.hide_branding = !page.hide_branding; field('hide_branding')"><span></span></button>
                    </label>
                    <a href="{{ route('editor.messages', $page) }}" class="btn btn-ghost btn-sm">Nachrichten ansehen</a>
                </div>
            </section>

            {{-- ---- Statistik ---- --}}
            <section x-show="tab === 'statistik'" x-cloak class="mt-6 space-y-6">
                @php
                    $titles = $page->items->mapWithKeys(fn ($i) => [$i->id => $i->title ?: $i->url]);
                    $maxDay = max(1, collect($stats['daily'])->max(fn ($d) => max($d['views'], $d['clicks'])));
                    $devLabel = ['mobile' => 'Handy', 'tablet' => 'Tablet', 'desktop' => 'Desktop', 'other' => 'Andere'];
                @endphp
                <div class="grid grid-cols-2 gap-3">
                    <div class="card text-center"><p class="text-xs text-dim">Aufrufe (30 Tage)</p><p class="text-3xl font-bold">{{ $stats['views'] }}</p></div>
                    <div class="card text-center"><p class="text-xs text-dim">Klicks (30 Tage)</p><p class="text-3xl font-bold">{{ $stats['clicks'] }}</p></div>
                </div>
                <div class="card">
                    <h2 class="font-semibold">Verlauf</h2>
                    <div class="mt-4 flex h-32 items-end gap-0.5" role="img" aria-label="Aufrufe und Klicks pro Tag der letzten 30 Tage">
                        @foreach ($stats['daily'] as $day => $d)
                            <div class="flex h-full flex-1 flex-col justify-end gap-px" title="{{ \Illuminate\Support\Carbon::parse($day)->format('d.m.') }}: {{ $d['views'] }} Aufrufe, {{ $d['clicks'] }} Klicks">
                                <div class="rounded-t bg-brand/80" style="height: {{ round($d['views'] / $maxDay * 100) }}%"></div>
                                <div class="bg-brand-2/80" style="height: {{ round($d['clicks'] / $maxDay * 100) }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 flex gap-4 text-xs text-dim"><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-brand"></span>Aufrufe</span><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-brand-2"></span>Klicks</span></p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="card">
                        <h2 class="font-semibold">Beliebteste Links</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @forelse ($stats['items'] as $id => $n)
                                <li class="flex justify-between gap-3"><span class="truncate text-muted">{{ $titles[$id] ?? '(gelöscht)' }}</span><b>{{ $n }}</b></li>
                            @empty
                                <li class="text-dim">Noch keine Klicks.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card">
                        <h2 class="font-semibold">Geräte</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @forelse ($stats['devices'] as $dev => $n)
                                <li class="flex justify-between"><span class="text-muted">{{ $devLabel[$dev] ?? $dev }}</span><b>{{ $n }}</b></li>
                            @empty
                                <li class="text-dim">Noch keine Daten.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card">
                        <h2 class="font-semibold">Herkunft</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @forelse ($stats['sources'] as $src => $n)
                                <li class="flex justify-between gap-3"><span class="truncate text-muted">{{ $src }}</span><b>{{ $n }}</b></li>
                            @empty
                                <li class="text-dim">Noch keine Daten.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="card">
                        <h2 class="font-semibold">Länder</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @forelse ($stats['countries'] as $cc => $n)
                                <li class="flex justify-between"><span class="text-muted">{{ $cc }}</span><b>{{ $n }}</b></li>
                            @empty
                                <li class="text-dim">Keine Länderdaten (nur verfügbar, wenn der Server sie liefert).</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <p class="text-xs text-dim">Die Statistik zählt anonym pro Tag, ohne Cookies und ohne IP-Adressen. Roboter werden ignoriert.</p>
            </section>

            {{-- ---- Einstellungen ---- --}}
            <section x-show="tab === 'einstellungen'" x-cloak class="mt-6 space-y-6">
                <div class="card space-y-3">
                    <h2 class="font-semibold">Adresse ändern</h2>
                    <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="saveSlug()">
                        <div class="flex flex-1 items-center rounded-xl border border-line bg-ink/60 px-3 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30">
                            <span class="shrink-0 text-xs text-dim">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/</span>
                            <input class="w-full border-0 bg-transparent px-1 py-2.5 text-sm outline-none" x-model="newSlug" maxlength="40" pattern="[a-z0-9\-]+" aria-label="Adresse">
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Speichern</button>
                    </form>
                    <p class="hint">Die alte Adresse funktioniert danach nicht mehr. Erlaubt: Kleinbuchstaben, Zahlen, Bindestriche.</p>
                </div>
                <p class="text-sm text-muted">Wochenbericht, Passwort und Konto löschen findest du unter <a class="text-brand hover:underline" href="{{ route('account') }}">Konto</a>. Seiten löschst du im <a class="text-brand hover:underline" href="{{ route('dashboard') }}">Dashboard</a>.</p>
            </section>
        </div>

        {{-- ============ Live-Vorschau ============ --}}
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <p class="mb-2 text-center text-xs font-semibold uppercase tracking-wide text-dim">Live-Vorschau</p>
            <div class="mx-auto w-full max-w-[380px] rounded-[2.2rem] border-4 border-line bg-black p-2 shadow-2xl shadow-black/60">
                <iframe x-ref="preview" src="{{ route('editor.preview', $page) }}" title="Vorschau deiner Seite" class="block h-[640px] w-full rounded-[1.7rem] bg-black" loading="lazy"></iframe>
            </div>
        </aside>
    </div>
</div>
@endsection
