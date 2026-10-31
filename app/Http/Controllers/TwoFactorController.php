<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTwoFactor;
use App\Support\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /** The code screen after the password, once per session. */
    public function challenge(Request $request): View|JsonResponse
    {
        if (! TwoFactor::enabled($request->user())) {
            return response()->json(['redirect' => route('login')]);
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $lockKey = '2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($lockKey, 6)) {
            return response()->json(['message' => 'throttled'], 429);
        }

        $code = trim($data['code']);
        $ok = TwoFactor::verify((string) $user->two_factor_secret, $code) || TwoFactor::consumeRecoveryCode($user, $code);
        if (! $ok) {
            RateLimiter::hit($lockKey, 600);

            return response()->json(['message' => 'invalid'], 422);
        }
        RateLimiter::clear($lockKey);
        $request->session()->put(EnsureTwoFactor::SESSION_KEY, $user->id);

        return response()->json(['redirect' => redirect()->intended(route('login'))->getTargetUrl()]);
    }

    /** Step 1 of setup: a fresh secret and its QR code; nothing is on until confirmed. */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        if (TwoFactor::enabled($user)) {
            return response()->json(['message' => 'already_enabled'], 422);
        }
        $secret = TwoFactor::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();
        $url = TwoFactor::otpauthUrl($user, $secret);

        return response()->json(['secret' => $secret, 'qr' => TwoFactor::qrSvg($url)]);
    }

    /** Step 2: the first code from the app proves the phone has the secret. */
    public function confirm(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        if (! $user->two_factor_secret || TwoFactor::enabled($user)) {
            return response()->json(['message' => 'not_pending'], 422);
        }
        if (! TwoFactor::verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'twoFactorWrongCode']);
        }
        $codes = TwoFactor::generateRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();
        $request->session()->put(EnsureTwoFactor::SESSION_KEY, $user->id);

        return response()->json(['ok' => true, 'recovery_codes' => $codes]);
    }

    /** Turning it off needs a current code (or a recovery code), not just a session. */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        if (TwoFactor::enabled($user)) {
            $code = trim($data['code']);
            if (! TwoFactor::verify((string) $user->two_factor_secret, $code) && ! TwoFactor::consumeRecoveryCode($user, $code)) {
                throw ValidationException::withMessages(['code' => 'twoFactorWrongCode']);
            }
        }
        TwoFactor::reset($user);
        $request->session()->forget(EnsureTwoFactor::SESSION_KEY);

        return response()->json(['ok' => true, 'required' => TwoFactor::required()]);
    }

    public function recovery(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        if (! TwoFactor::enabled($user) || ! TwoFactor::verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'twoFactorWrongCode']);
        }
        $codes = TwoFactor::generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return response()->json(['recovery_codes' => $codes]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => TwoFactor::enabled($user),
            'required' => TwoFactor::required(),
            'recovery_left' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }
}
