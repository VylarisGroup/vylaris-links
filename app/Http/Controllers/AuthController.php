<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $sec = RateLimiter::availableIn($key);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Zu viele Versuche. Bitte warte {$sec} Sekunden."]);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'E-Mail oder Passwort stimmt nicht.']);
        }

        RateLimiter::clear($key);

        if ($request->user()->isBlocked()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Dieses Konto wurde gesperrt.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request)
    {
        $wanted = Str::of((string) $request->query('name'))->lower()->replaceMatches('/[^a-z0-9\-]/', '')->limit(40, '')->toString();
        if ($wanted !== '') {
            $request->session()->put('wanted_slug', $wanted);
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Bitte akzeptiere Datenschutz und Nutzungsbedingungen.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Willkommen! Lege jetzt deine erste Seite an.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
