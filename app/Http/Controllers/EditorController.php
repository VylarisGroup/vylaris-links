<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Embed;
use App\Support\Platforms;
use App\Support\Stats;
use App\Support\Themes;
use App\Support\UrlGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EditorController extends Controller
{
    private function own(Request $request, int $page): Page
    {
        return $request->user()->pages()->findOrFail($page);
    }

    public function edit(Request $request, int $page)
    {
        $page = $this->own($request, $page);
        $page->load(['items', 'socials']);

        return view('editor.edit', [
            'page' => $page,
            'state' => [
                'page' => $this->pagePayload($page),
                'items' => $page->items,
                'socials' => $page->socials,
            ],
            'options' => [
                'themes' => Themes::THEMES, 'layouts' => Themes::LAYOUTS, 'rings' => Themes::RINGS,
                'particles' => Themes::PARTICLES, 'effects' => Themes::EFFECTS, 'fonts' => Themes::FONTS,
                'shapes' => Themes::SHAPES,
                'platforms' => collect(Platforms::LIST)->map(fn ($v) => $v[0]),
                'types' => [
                    'link' => 'Link', 'heading' => 'Überschrift', 'text' => 'Text', 'image' => 'Bild',
                    'youtube' => 'YouTube', 'twitch' => 'Twitch', 'spotify' => 'Spotify',
                    'countdown' => 'Countdown', 'gallery' => 'Galerie',
                ],
            ],
            'stats' => Stats::summary($page, 30),
        ]);
    }

    public function preview(Request $request, int $page)
    {
        $page = $this->own($request, $page);

        return response()->view('profile.show', ['page' => $page->load(['items', 'socials']), 'preview' => true])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function update(Request $request, int $page)
    {
        $page = $this->own($request, $page);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
            'theme' => ['sometimes', Rule::in(array_keys(Themes::THEMES))],
            'layout' => ['sometimes', Rule::in(array_keys(Themes::LAYOUTS))],
            'avatar_ring' => ['sometimes', Rule::in(array_keys(Themes::RINGS))],
            'particles' => ['sometimes', Rule::in(array_keys(Themes::PARTICLES))],
            'cursor_sparks' => ['sometimes', 'boolean'],
            'entrance_enabled' => ['sometimes', 'boolean'],
            'entrance_text' => ['sometimes', 'nullable', 'string', 'max:80'],
            'music_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'hide_branding' => ['sometimes', 'boolean'],
            'contact_enabled' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'colors' => ['sometimes', 'nullable', 'array'],
            'colors.bg' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.text' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.button_bg' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.button_text' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.font' => ['nullable', Rule::in(array_keys(Themes::FONTS))],
            'colors.shape' => ['nullable', Rule::in(array_keys(Themes::SHAPES))],
        ]);

        if (array_key_exists('music_url', $data) && $data['music_url'] !== null && $data['music_url'] !== '') {
            $clean = UrlGuard::clean($data['music_url'], ['http', 'https']);
            if (! $clean) {
                return response()->json(['message' => 'Die Musik-Adresse ist ungültig.', 'errors' => ['music_url' => ['Bitte eine gültige https-Adresse einer Audiodatei angeben.']]], 422);
            }
            $data['music_url'] = $clean;
        } elseif (array_key_exists('music_url', $data)) {
            $data['music_url'] = null;
        }

        if (isset($data['colors'])) {
            $data['colors'] = array_filter($data['colors'], fn ($v) => $v !== null && $v !== '');
        }

        $page->update($data);

        return response()->json(['ok' => true, 'page' => $this->pagePayload($page->fresh())]);
    }

    public function upload(Request $request, int $page, string $kind)
    {
        abort_unless(in_array($kind, ['avatar', 'background'], true), 404);
        $page = $this->own($request, $page);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.($kind === 'avatar' ? 2048 : 4096), 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'file.mimes' => 'Erlaubt sind JPG, PNG und WebP.',
            'file.max' => 'Die Datei ist zu gross.',
        ]);

        $column = $kind.'_path';
        if ($page->{$column}) {
            Storage::disk('public')->delete($page->{$column});
        }
        $path = $request->file('file')->store("pages/{$page->id}", 'public');
        $page->forceFill([$column => $path])->save();

        return response()->json(['ok' => true, 'page' => $this->pagePayload($page->fresh())]);
    }

    public function removeUpload(Request $request, int $page, string $kind)
    {
        abort_unless(in_array($kind, ['avatar', 'background'], true), 404);
        $page = $this->own($request, $page);
        $column = $kind.'_path';
        if ($page->{$column}) {
            Storage::disk('public')->delete($page->{$column});
            $page->forceFill([$column => null])->save();
        }

        return response()->json(['ok' => true, 'page' => $this->pagePayload($page->fresh())]);
    }

    public function slug(Request $request, int $page)
    {
        $page = $this->own($request, $page);
        $data = $request->validate([
            'slug' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(Themes::reservedSlugs()), Rule::unique('pages', 'slug')->ignore($page->id),
            ],
        ], [
            'slug.regex' => 'Erlaubt sind Kleinbuchstaben, Zahlen und Bindestriche.',
            'slug.not_in' => 'Dieser Name ist reserviert.',
            'slug.unique' => 'Dieser Name ist schon vergeben.',
        ]);
        $page->update(['slug' => $data['slug']]);

        return response()->json(['ok' => true, 'page' => $this->pagePayload($page->fresh())]);
    }

    public function messages(Request $request, int $page)
    {
        $page = $this->own($request, $page);
        $messages = $page->messages()->limit(200)->get();
        $page->messages()->whereNull('read_at')->update(['read_at' => now()]);

        return view('editor.messages', ['page' => $page, 'messages' => $messages]);
    }

    public function destroyMessage(Request $request, int $page, int $message)
    {
        $page = $this->own($request, $page);
        $page->messages()->whereKey($message)->delete();

        return back()->with('status', 'Nachricht gelöscht.');
    }

    private function pagePayload(Page $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'bio' => $page->bio,
            'theme' => $page->theme,
            'layout' => $page->layout,
            'avatar_ring' => $page->avatar_ring,
            'particles' => $page->particles,
            'cursor_sparks' => $page->cursor_sparks,
            'entrance_enabled' => $page->entrance_enabled,
            'entrance_text' => $page->entrance_text,
            'music_url' => $page->music_url,
            'hide_branding' => $page->hide_branding,
            'contact_enabled' => $page->contact_enabled,
            'is_published' => $page->is_published,
            'colors' => (object) ($page->colors ?? []),
            'avatar_url' => $page->avatarUrl(),
            'background_url' => $page->backgroundUrl(),
            'public_url' => $page->publicUrl(),
            'blocked' => $page->blocked_at !== null,
        ];
    }
}
