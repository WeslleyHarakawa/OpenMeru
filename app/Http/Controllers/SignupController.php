<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Self-service mailboxes, only while Settings → Security allows it. */
class SignupController extends Controller
{
    public static function allowed(): bool
    {
        try {
            return Setting::get('allow_signup') === '1';
        } catch (QueryException) {
            // The sign-in page renders before the database exists on a fresh install.
            return false;
        }
    }

    public function show(): View
    {
        abort_unless(self::allowed(), 404);

        return view('auth.signup');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(self::allowed(), 404);
        $domain = (string) config('inbound.domain');
        $request->merge(['local' => mb_strtolower(trim((string) $request->input('local')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'local' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
            'recovery_email' => ['nullable', 'email', 'max:255', 'not_regex:/@'.preg_quote($domain, '/').'$/i'],
        ]);
        $email = $data['local'].'@'.$domain;
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'taken', 'errors' => ['local' => ['taken']]], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => $data['password'],
            'recovery_email' => filled($data['recovery_email'] ?? null) ? mb_strtolower($data['recovery_email']) : null,
            'is_admin' => false,
            'is_active' => true,
        ]);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['redirect' => route('login'), 'email' => $email]);
    }
}
