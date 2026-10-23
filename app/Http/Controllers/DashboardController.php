<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Stats;
use App\Support\Themes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $pages = $request->user()->pages()->withCount(['items', 'messages as unread_messages_count' => fn ($q) => $q->whereNull('read_at')])->get();
        $totals = $pages->mapWithKeys(fn (Page $p) => [$p->id => Stats::summary($p, 7)]);

        return view('dashboard', [
            'pages' => $pages,
            'totals' => $totals,
            'max' => (int) config('links.max_pages'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->pages()->count() >= (int) config('links.max_pages')) {
            return back()->withErrors(['slug' => 'Du hast die maximale Anzahl an Seiten erreicht.']);
        }

        $data = $request->validate([
            'slug' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(Themes::reservedSlugs()), 'unique:pages,slug',
            ],
            'title' => ['required', 'string', 'max:80'],
        ], [
            'slug.regex' => 'Erlaubt sind Kleinbuchstaben, Zahlen und Bindestriche (nicht am Anfang oder Ende).',
            'slug.not_in' => 'Dieser Name ist reserviert.',
            'slug.unique' => 'Dieser Name ist schon vergeben.',
        ]);

        $page = $user->pages()->create($data + ['theme' => 'vylaris', 'layout' => 'buttons']);

        return redirect()->route('editor.edit', $page)->with('status', 'Seite angelegt. Füge jetzt deine ersten Links hinzu.');
    }

    public function destroy(Request $request, int $page)
    {
        $page = $request->user()->pages()->findOrFail($page);
        foreach ([$page->avatar_path, $page->background_path] as $path) {
            if ($path) {
                \Storage::disk('public')->delete($path);
            }
        }
        $page->delete();

        return redirect()->route('dashboard')->with('status', 'Seite gelöscht.');
    }
}
