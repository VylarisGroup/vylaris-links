<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        return view('account', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'weekly_report' => ['sometimes', 'boolean'],
        ]);
        $user->update(['name' => $data['name'], 'weekly_report' => $request->boolean('weekly_report')]);

        return back()->with('status', 'Gespeichert.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], ['current_password.current_password' => 'Das aktuelle Passwort stimmt nicht.']);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Passwort geändert.');
    }

    public function destroy(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']], ['current_password.current_password' => 'Das Passwort stimmt nicht.']);
        $user = $request->user();

        foreach ($user->pages as $page) {
            Storage::disk('public')->deleteDirectory("pages/{$page->id}");
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $user->delete();

        return redirect()->route('landing')->with('status', 'Dein Konto wurde gelöscht.');
    }
}
