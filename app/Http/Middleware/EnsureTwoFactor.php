<?php

namespace App\Http\Middleware;

use App\Support\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two gates after the password:
 *  - a mailbox with 2FA on must enter a code once per session (the challenge);
 *  - when the admin requires 2FA for everyone, a mailbox without it must set it
 *    up before anything else (only the settings page and its 2FA calls stay open).
 */
class EnsureTwoFactor
{
    public const SESSION_KEY = 'two_factor_passed';

    /** Routes a signed-in person may still reach while a gate is closed. */
    private const OPEN = ['logout', 'two-factor.challenge', 'two-factor.verify', 'settings', 'two-factor.enable', 'two-factor.confirm', 'two-factor.recovery', 'account.avatar', 'brand.asset'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs('logout')) {
            return $next($request);
        }

        if (TwoFactor::enabled($user) && $request->session()->get(self::SESSION_KEY) !== $user->id) {
            if ($request->routeIs('two-factor.challenge', 'two-factor.verify')) {
                return $next($request);
            }

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => 'two_factor', 'redirect' => route('two-factor.challenge')], 403)
                : redirect()->route('two-factor.challenge');
        }

        if (TwoFactor::required() && ! TwoFactor::enabled($user) && ! $user->is_demo) {
            if ($request->routeIs(...self::OPEN)) {
                return $next($request);
            }

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => 'two_factor_setup', 'redirect' => route('settings').'#security'], 403)
                : redirect(route('settings').'#security')->with('two_factor_required', true);
        }

        return $next($request);
    }
}
