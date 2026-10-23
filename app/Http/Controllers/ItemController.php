<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Page;
use App\Models\Social;
use App\Support\Embed;
use App\Support\Platforms;
use App\Support\Themes;
use App\Support\UrlGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    private function page(Request $request, int $page): Page
    {
        return $request->user()->pages()->findOrFail($page);
    }

    private function item(Page $page, int $item): Item
    {
        return $page->items()->findOrFail($item);
    }

    public function store(Request $request, int $page)
    {
        $page = $this->page($request, $page);
        abort_if($page->items()->count() >= config('links.max_items'), 422, 'Maximale Anzahl an Einträgen erreicht.');

        $type = $request->validate(['type' => ['required', Rule::in(Item::TYPES)]])['type'];

        $item = new Item(['type' => $type, 'position' => (int) $page->items()->max('position') + 1, 'is_visible' => true]);
        $item->page_id = $page->id;
        if ($type === 'link') {
            $item->title = 'Neuer Link';
        } elseif ($type === 'heading') {
            $item->title = 'Neue Überschrift';
        } elseif ($type === 'countdown') {
            $item->title = 'Bald geht es los';
            $item->data = ['target' => now()->addDays(7)->format('Y-m-d\TH:i')];
        } elseif ($type === 'text') {
            $item->body = 'Dein Text …';
        }
        $item->save();

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function update(Request $request, int $page, int $item)
    {
        $page = $this->page($request, $page);
        $item = $this->item($page, $item);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'icon' => ['sometimes', 'nullable', Rule::in(Platforms::keys())],
            'body' => ['sometimes', 'nullable', 'string', 'max:1500'],
            'effect' => ['sometimes', Rule::in(array_keys(Themes::EFFECTS))],
            'is_visible' => ['sometimes', 'boolean'],
            'age_gate' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:100'],
            'remove_password' => ['sometimes', 'boolean'],
            'schedule' => ['sometimes', 'nullable', 'array'],
            'schedule.enabled' => ['boolean'],
            'schedule.days' => ['nullable', 'array'],
            'schedule.days.*' => ['integer', 'between:1,7'],
            'schedule.from' => ['nullable', 'date_format:H:i'],
            'schedule.to' => ['nullable', 'date_format:H:i'],
            'data' => ['sometimes', 'nullable', 'array'],
            'data.target' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'data.images' => ['nullable', 'array', 'max:12'],
            'data.images.*' => ['string', 'max:500'],
        ]);

        // Typabhängige Prüfung der Adresse.
        if (array_key_exists('url', $data)) {
            $raw = $data['url'];
            $clean = null;
            if ($raw === null || trim($raw) === '') {
                $clean = null;
            } else {
                $clean = match ($item->type) {
                    'link' => UrlGuard::clean($raw),
                    'image' => UrlGuard::clean($raw, ['http', 'https']),
                    'youtube' => Embed::youtubeId($raw) ? UrlGuard::clean($raw, ['http', 'https']) ?? Embed::youtubeId($raw) : null,
                    'twitch' => Embed::twitchChannel($raw) ? (UrlGuard::clean($raw, ['http', 'https']) ?? Embed::twitchChannel($raw)) : null,
                    'spotify' => Embed::spotifyPath($raw) ? $raw : null,
                    default => UrlGuard::clean($raw),
                };
                if ($clean === null) {
                    throw ValidationException::withMessages(['url' => 'Diese Adresse passt nicht zum Typ „'.$item->type.'“.']);
                }
            }
            $data['url'] = $clean;
        }

        if (isset($data['data']['images'])) {
            $imgs = [];
            foreach ($data['data']['images'] as $img) {
                if ($c = UrlGuard::clean($img, ['http', 'https'])) {
                    $imgs[] = $c;
                }
            }
            $data['data']['images'] = $imgs;
        }

        if (array_key_exists('password', $data) && $data['password']) {
            $item->password_hash = Hash::make($data['password']);
        }
        if (! empty($data['remove_password'])) {
            $item->password_hash = null;
        }
        unset($data['password'], $data['remove_password']);

        if (isset($data['data'])) {
            $data['data'] = array_merge($item->data ?? [], $data['data']);
        }
        $item->fill($data)->save();

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function destroy(Request $request, int $page, int $item)
    {
        $page = $this->page($request, $page);
        $this->item($page, $item)->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, int $page)
    {
        $page = $this->page($request, $page);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $valid = $page->items()->pluck('id')->all();
        foreach (array_values(array_filter($ids, fn ($i) => in_array($i, $valid, true))) as $pos => $id) {
            $page->items()->whereKey($id)->update(['position' => $pos]);
        }

        return response()->json(['ok' => true]);
    }

    public function storeSocial(Request $request, int $page)
    {
        $page = $this->page($request, $page);
        abort_if($page->socials()->count() >= config('links.max_socials'), 422, 'Maximale Anzahl erreicht.');

        $data = $request->validate([
            'platform' => ['required', Rule::in(Platforms::keys())],
            'url' => ['required', 'string', 'max:500'],
        ]);

        $url = $data['url'];
        if ($data['platform'] === 'email' && ! str_starts_with($url, 'mailto:') && filter_var($url, FILTER_VALIDATE_EMAIL)) {
            $url = 'mailto:'.$url;
        }
        if ($data['platform'] === 'phone' && ! str_starts_with($url, 'tel:') && preg_match('/^[+0-9 ()\-]{5,25}$/', $url)) {
            $url = 'tel:'.preg_replace('/[ ()\-]/', '', $url);
        }
        $clean = UrlGuard::clean($url);
        if (! $clean) {
            throw ValidationException::withMessages(['url' => 'Bitte eine gültige Adresse angeben.']);
        }

        $social = new Social(['platform' => $data['platform'], 'url' => $clean, 'position' => (int) $page->socials()->max('position') + 1]);
        $social->page_id = $page->id;
        $social->save();

        return response()->json(['ok' => true, 'social' => $social]);
    }

    public function destroySocial(Request $request, int $page, int $social)
    {
        $page = $this->page($request, $page);
        $page->socials()->whereKey($social)->delete();

        return response()->json(['ok' => true]);
    }
}
