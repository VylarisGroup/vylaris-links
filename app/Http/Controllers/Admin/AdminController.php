<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'openReports' => Report::where('status', 'open')->with('page.user')->latest()->limit(50)->get(),
            'pages' => Page::with('user')->latest()->paginate(25, ['*'], 'seiten'),
            'counts' => ['users' => User::count(), 'pages' => Page::count(), 'blocked' => Page::whereNotNull('blocked_at')->count()],
        ]);
    }

    public function blockPage(Request $request, int $page)
    {
        $page = Page::findOrFail($page);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $page->forceFill(['blocked_at' => now(), 'blocked_reason' => $data['reason'] ?? null])->save();
        $page->reports()->where('status', 'open')->update(['status' => 'done', 'handled_at' => now()]);

        return back()->with('status', "Seite „{$page->slug}“ gesperrt.");
    }

    public function unblockPage(int $page)
    {
        $page = Page::findOrFail($page);
        $page->forceFill(['blocked_at' => null, 'blocked_reason' => null])->save();

        return back()->with('status', "Seite „{$page->slug}“ freigegeben.");
    }

    public function blockUser(Request $request, int $user)
    {
        $user = User::findOrFail($user);
        abort_if($user->id === $request->user()->id || $user->is_admin, 422, 'Dieses Konto kann nicht gesperrt werden.');
        $user->forceFill(['blocked_at' => now()])->save();

        return back()->with('status', "Konto von {$user->name} gesperrt.");
    }

    public function unblockUser(int $user)
    {
        User::findOrFail($user)->forceFill(['blocked_at' => null])->save();

        return back()->with('status', 'Konto freigegeben.');
    }

    public function dismissReport(int $report)
    {
        Report::findOrFail($report)->forceFill(['status' => 'dismissed', 'handled_at' => now()])->save();

        return back()->with('status', 'Meldung abgewiesen.');
    }
}
