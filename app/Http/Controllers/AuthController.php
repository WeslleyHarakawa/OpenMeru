<?php

namespace App\Http\Controllers;

use App\Mail\Outbox;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        return $request->user() ? redirect()->route('login') : view('auth.login');
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials + ['is_active' => true, 'is_demo' => false], $request->boolean('remember'))) {
            return response()->json(['message' => 'invalid'], 422);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        if ($request->input('next') === '/admin') {
            return response()->json(['redirect' => '/admin']);
        }

        return response()->json(['redirect' => redirect()->intended(route('login'))->getTargetUrl()]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return match ($request->input('next')) {
            '/admin' => redirect('/admin'),
            '/' => redirect('/'),
            default => redirect()->route('login'),
        };
    }

    public function showForgot(): View
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', mb_strtolower(trim($data['email'])))->where('is_active', true)->first();

        // Same answer either way, so the form does not reveal which accounts exist.
        if ($user && $user->recovery_email && Outbox::configured()) {
            try {
                Outbox::applyToLaravel();
                Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['redirect' => route('login')])
            : response()->json(['message' => 'invalid'], 422);
    }
}
