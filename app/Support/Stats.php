<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Page;
use App\Models\StatDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cookielose Statistik: keine IP-Adressen, keine Cookies, nur tägliche Zähler.
 */
class Stats
{
    public static function record(Page $page, ?Item $item, string $kind, Request $request): void
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget|headless/', $ua)) {
            return;
        }

        $device = match (true) {
            (bool) preg_match('/ipad|tablet/', $ua) => 'tablet',
            (bool) preg_match('/mobi|android|iphone/', $ua) => 'mobile',
            (bool) preg_match('/windows|macintosh|linux|cros/', $ua) => 'desktop',
            default => 'other',
        };

        $source = 'direkt';
        $ref = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if ($ref && ! str_contains($ref, parse_url(config('app.url'), PHP_URL_HOST) ?: '~')) {
            $source = mb_substr(preg_replace('/^www\./', '', strtolower($ref)), 0, 60);
        }

        // Länderkennung nur, wenn ein Proxy (z. B. Cloudflare) sie liefert; die IP selbst wird nie gespeichert.
        $country = strtoupper((string) $request->headers->get('CF-IPCountry', ''));
        $country = preg_match('/^[A-Z]{2}$/', $country) ? $country : null;

        $keys = [
            'page_id' => $page->id,
            'item_id' => $item?->id ?? 0,
            'day' => now()->toDateString(),
            'kind' => $kind,
            'device' => $device,
            'source' => $source,
        ];

        $row = StatDay::where($keys)->first();
        if ($row) {
            StatDay::where('id', $row->id)->update(['count' => DB::raw('count + 1')]);
        } else {
            try {
                StatDay::create($keys + ['country' => $country, 'count' => 1]);
            } catch (\Illuminate\Database\QueryException) {
                StatDay::where($keys)->update(['count' => DB::raw('count + 1')]);
            }
        }
    }

    /** Zusammenfassung für den Editor / Wochenbericht. */
    public static function summary(Page $page, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->toDateString();
        $rows = StatDay::where('page_id', $page->id)->where('day', '>=', $from)->get();

        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[now()->subDays($i)->toDateString()] = ['views' => 0, 'clicks' => 0];
        }
        foreach ($rows as $r) {
            $k = $r->day->toDateString();
            if (isset($daily[$k])) {
                $daily[$k][$r->kind === 'view' ? 'views' : 'clicks'] += $r->count;
            }
        }

        $byItem = $rows->where('kind', 'click')->groupBy('item_id')->map->sum('count')->sortDesc();

        return [
            'views' => $rows->where('kind', 'view')->sum('count'),
            'clicks' => $rows->where('kind', 'click')->sum('count'),
            'daily' => $daily,
            'items' => $byItem,
            'devices' => $rows->where('kind', 'view')->groupBy('device')->map->sum('count')->sortDesc(),
            'sources' => $rows->where('kind', 'view')->groupBy('source')->map->sum('count')->sortDesc()->take(8),
            'countries' => $rows->where('kind', 'view')->whereNotNull('country')->groupBy('country')->map->sum('count')->sortDesc()->take(8),
        ];
    }
}
