<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Message;
use App\Models\Page;
use App\Models\Report;
use App\Support\Stats;
use App\Support\UrlGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Öffentliche Profilseiten laufen bewusst OHNE Session und OHNE Cookies
 * (siehe routes/web.php). Daher keine Flash-Daten, kein CSRF-Token und
 * keine Session-basierte Freischaltung – Rückmeldungen laufen über Query-Parameter.
 */
class ProfileController extends Controller
{
    private function publicPage(string $slug): Page
    {
        $page = Page::where('slug', strtolower($slug))->with(['user', 'items', 'socials'])->firstOrFail();
        abort_unless($page->isPublic(), 404);

        return $page;
    }

    public function show(Request $request, string $slug)
    {
        $page = $this->publicPage($slug);
        Stats::record($page, null, 'view', $request);

        return response()->view('profile.show', ['page' => $page, 'preview' => false]);
    }

    private function goableItem(int $item): Item
    {
        $item = Item::with('page.user')->findOrFail($item);
        abort_unless($item->page->isPublic() && $item->isActiveAt(), 404);
        abort_unless(in_array($item->type, ['link', 'image', 'countdown'], true) && $item->url, 404);

        return $item;
    }

    public function go(Request $request, int $item)
    {
        $item = $this->goableItem($item);

        if ($item->isProtected()) {
            return view('profile.unlock', ['item' => $item, 'page' => $item->page, 'error' => null, 'adultError' => null]);
        }

        return $this->forward($request, $item);
    }

    public function unlock(Request $request, int $item)
    {
        $item = $this->goableItem($item);
        abort_unless($item->isProtected(), 404);

        $key = 'unlock:'.$item->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return response()->view('profile.unlock', ['item' => $item, 'page' => $item->page, 'error' => 'Zu viele Versuche. Bitte warte kurz.', 'adultError' => null], 429);
        }

        if ($item->age_gate && ! $request->boolean('adult')) {
            return response()->view('profile.unlock', ['item' => $item, 'page' => $item->page, 'error' => null, 'adultError' => 'Bitte bestätige, dass du mindestens 18 Jahre alt bist.'], 422);
        }

        if ($item->password_hash !== null && ! Hash::check((string) $request->input('password'), $item->password_hash)) {
            RateLimiter::hit($key, 120);

            return response()->view('profile.unlock', ['item' => $item, 'page' => $item->page, 'error' => 'Das Passwort stimmt nicht.', 'adultError' => null], 422);
        }

        RateLimiter::clear($key);

        return $this->forward($request, $item);
    }

    private function forward(Request $request, Item $item)
    {
        $target = UrlGuard::clean($item->url);
        abort_unless($target, 404);

        Stats::record($item->page, $item, 'click', $request);

        return redirect()->away($target)->header('Referrer-Policy', 'no-referrer');
    }

    public function contact(Request $request, string $slug)
    {
        $page = $this->publicPage($slug);
        abort_unless($page->contact_enabled, 404);
        $back = fn (string $q) => redirect($page->publicUrl().'?'.$q.'#kontakt');

        // Honeypot: echte Besucher lassen dieses Feld leer.
        if ($request->filled('website')) {
            return $back('danke=kontakt');
        }

        $v = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        // Schutzfilter: sehr viele Links sind meist Spam.
        if ($v->fails() || preg_match_all('#https?://#i', (string) $request->input('body')) > 2) {
            return $back('fehler=kontakt');
        }
        $data = $v->validated();

        if ($page->messages()->count() >= 500) {
            $page->messages()->oldest()->limit(50)->get()->each->delete();
        }

        $message = new Message($data);
        $message->page_id = $page->id;
        $message->save();

        return $back('danke=kontakt');
    }

    public function report(Request $request, string $slug)
    {
        $page = Page::where('slug', strtolower($slug))->firstOrFail();
        $back = fn (string $q) => redirect($page->publicUrl().'?'.$q.'#melden');

        if ($request->filled('website')) {
            return $back('danke=meldung');
        }

        $v = Validator::make($request->all(), [
            'reason' => ['required', 'in:'.implode(',', array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($v->fails()) {
            return $back('fehler=meldung');
        }

        $report = new Report($v->validated());
        $report->page_id = $page->id;
        $report->save();

        return $back('danke=meldung');
    }
}
