<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/anmelden', [AuthController::class, 'login']);
    Route::get('/registrieren', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registrieren', [AuthController::class, 'register'])->middleware('throttle:10,60');
    Route::get('/passwort/vergessen', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('/passwort/vergessen', [PasswordController::class, 'sendLink'])->middleware('throttle:5,10')->name('password.email');
    Route::get('/passwort/zuruecksetzen/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/passwort/zuruecksetzen', [PasswordController::class, 'reset'])->name('password.update');
});

Route::post('/abmelden', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'not.blocked'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/seiten', [DashboardController::class, 'store'])->name('pages.store');
    Route::delete('/dashboard/seiten/{page}', [DashboardController::class, 'destroy'])->name('pages.destroy');

    Route::get('/konto', [AccountController::class, 'show'])->name('account');
    Route::patch('/konto', [AccountController::class, 'update'])->name('account.update');
    Route::put('/konto/passwort', [AccountController::class, 'password'])->name('account.password');
    Route::delete('/konto', [AccountController::class, 'destroy'])->name('account.destroy');

    Route::prefix('editor/{page}')->whereNumber('page')->group(function () {
        Route::get('/', [EditorController::class, 'edit'])->name('editor.edit');
        Route::get('/vorschau', [EditorController::class, 'preview'])->name('editor.preview');
        Route::patch('/', [EditorController::class, 'update'])->name('editor.update');
        Route::patch('/adresse', [EditorController::class, 'slug'])->name('editor.slug');
        Route::post('/upload/{kind}', [EditorController::class, 'upload'])->name('editor.upload');
        Route::delete('/upload/{kind}', [EditorController::class, 'removeUpload'])->name('editor.upload.remove');
        Route::get('/nachrichten', [EditorController::class, 'messages'])->name('editor.messages');
        Route::delete('/nachrichten/{message}', [EditorController::class, 'destroyMessage'])->name('editor.messages.destroy');

        Route::post('/eintraege', [ItemController::class, 'store'])->name('items.store');
        Route::post('/eintraege/reihenfolge', [ItemController::class, 'reorder'])->name('items.reorder');
        Route::patch('/eintraege/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('/eintraege/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        Route::post('/socials', [ItemController::class, 'storeSocial'])->name('socials.store');
        Route::delete('/socials/{social}', [ItemController::class, 'destroySocial'])->name('socials.destroy');
    });
});

Route::prefix('admin')->middleware(['auth', 'not.blocked', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/seiten/{page}/sperren', [AdminController::class, 'blockPage'])->name('admin.pages.block');
    Route::post('/seiten/{page}/freigeben', [AdminController::class, 'unblockPage'])->name('admin.pages.unblock');
    Route::post('/konten/{user}/sperren', [AdminController::class, 'blockUser'])->name('admin.users.block');
    Route::post('/konten/{user}/freigeben', [AdminController::class, 'unblockUser'])->name('admin.users.unblock');
    Route::get('/update', [UpdateController::class, 'index'])->name('admin.update');
    Route::post('/update/pruefen', [UpdateController::class, 'check'])->middleware('throttle:10,1')->name('admin.update.check');
    Route::post('/update/installieren', [UpdateController::class, 'install'])->middleware('throttle:3,10')->name('admin.update.install');
    Route::get('/update/status', [UpdateController::class, 'status'])->name('admin.update.status');
    Route::post('/meldungen/{report}/abweisen', [AdminController::class, 'dismissReport'])->name('admin.reports.dismiss');
});

// Öffentliche Seiten und Weiterleitungen: bewusst OHNE Session und OHNE Cookies,
// damit Besucher keinen Cookie-Banner brauchen.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () {
    Route::get('/go/{item}', [ProfileController::class, 'go'])->whereNumber('item')->name('go');
    Route::post('/go/{item}', [ProfileController::class, 'unlock'])->whereNumber('item')->middleware('throttle:20,1')->name('go.unlock');

    // Öffentliche Profile – muss zuletzt stehen
    $slug = '[A-Za-z0-9][A-Za-z0-9\-]{1,38}[A-Za-z0-9]';
    Route::get('/{slug}', [ProfileController::class, 'show'])->where('slug', $slug)->name('profile.show');
    Route::post('/{slug}/kontakt', [ProfileController::class, 'contact'])->where('slug', $slug)->middleware('throttle:5,10')->name('profile.contact');
    Route::post('/{slug}/melden', [ProfileController::class, 'report'])->where('slug', $slug)->middleware('throttle:3,60')->name('profile.report');
});
