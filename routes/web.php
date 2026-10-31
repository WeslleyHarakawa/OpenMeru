<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\VerifyController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\InboundController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\TranslateController;
use Illuminate\Support\Facades\Route;

Route::post('/hooks/inbound', [InboundController::class, 'store'])->name('inbound');

Route::middleware('guest')->group(function () {
    Route::get('/forgot', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

// The site root is the project's landing page; the webmail lives under /mail.
Route::view('/', 'landing')->name('home');
// One-line installer: curl -fsSL https://openmeru.com/install | bash
Route::get('/install', fn () => response(file_get_contents(base_path('install.sh')), 200, [
    'Content-Type' => 'text/plain; charset=utf-8',
    'Cache-Control' => 'public, max-age=300',
]))->name('install');
Route::get('/mail', [MailboxController::class, 'entry'])->name('login');
// Public read-only demo.
Route::get('/demo', [DemoController::class, 'mailbox'])->middleware('throttle:30,1')->name('demo');
Route::get('/demo/admin', [DemoController::class, 'admin'])->middleware('throttle:30,1')->name('demo.admin');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');

// Usual webmail addresses lead to the sign-in page.
foreach (['webmail', 'email'] as $alias) {
    Route::redirect('/'.$alias, '/mail', 301);
}

Route::middleware(['auth', 'mailbox.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/impersonate/stop', [ImpersonateController::class, 'stop'])->name('impersonate.stop');

    Route::get('/api/me', [MailboxController::class, 'me']);
    Route::get('/api/messages', [MailboxController::class, 'index']);
    Route::get('/api/messages/{id}', [MailboxController::class, 'show'])->whereNumber('id');
    Route::patch('/api/messages/{id}', [MailboxController::class, 'update'])->whereNumber('id');
    Route::delete('/api/messages/{id}', [MailboxController::class, 'destroy'])->whereNumber('id');
    Route::post('/api/send', [MailboxController::class, 'send'])->middleware('throttle:30,1');
    Route::post('/api/drafts', [MailboxController::class, 'saveDraft']);
    Route::get('/attachments/{attachment}', [MailboxController::class, 'attachment'])->name('attachments.show');

    Route::get('/settings', [AccountSettingsController::class, 'page'])->name('settings');
    Route::get('/account/avatar', [AccountSettingsController::class, 'avatar'])->name('account.avatar');
    Route::post('/api/account/avatar', [AccountSettingsController::class, 'uploadAvatar']);
    Route::delete('/api/account/avatar', [AccountSettingsController::class, 'removeAvatar']);
    Route::delete('/api/account', [AccountSettingsController::class, 'destroy'])->middleware('throttle:5,1');
    Route::put('/api/account/profile', [AccountSettingsController::class, 'profile']);
    Route::put('/api/account/password', [AccountSettingsController::class, 'password'])->middleware('throttle:5,1');
    Route::put('/api/account/forwarding', [AccountSettingsController::class, 'forwarding']);
    Route::post('/api/translate', [TranslateController::class, 'translate'])->middleware('throttle:60,1')->name('translate');
    Route::get('/api/notifications', [AccountSettingsController::class, 'notifications']);
    Route::post('/api/push/subscribe', [AccountSettingsController::class, 'subscribe']);
    Route::post('/api/push/unsubscribe', [AccountSettingsController::class, 'unsubscribe']);
    Route::post('/api/push/test', [AccountSettingsController::class, 'testPush'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'mailbox.active', 'throttle:10,1'])->group(function () {
    Route::post('/admin/verify', [VerifyController::class, 'verify'])->name('admin.verify');
    Route::post('/admin/verify/resend', [VerifyController::class, 'resend'])->name('admin.verify.resend');
});

Route::prefix('admin')->middleware('admin.area')->name('admin.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('accounts');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::post('/accounts/{account}/impersonate', [ImpersonateController::class, 'start'])->name('accounts.impersonate');
    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/translate', [SettingsController::class, 'translate'])->name('settings.translate');
    Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');
    Route::post('/settings/vapid', [SettingsController::class, 'generateVapid'])->name('settings.vapid');
});
