<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\VerifyController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /admin keeps its own URL: a signed-out visitor gets the sign-in form right
 * there instead of a redirect, and lands back on /admin after signing in.
 */
class AdminArea
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return response()->view('auth.login', ['next' => '/admin', 'admin' => true])
                    ->header('Cache-Control', 'no-store, private');
            }

            return response()->json(['message' => 'unauthenticated'], 401);
        }

        if (! $user->is_admin) {
            return response()->view('auth.login', ['next' => '/admin', 'admin' => true, 'notAdmin' => $user->email], 403)
                ->header('Cache-Control', 'no-store, private');
        }

        if (! VerifyController::verified($request)) {
            return $request->isMethod('GET') && ! $request->expectsJson()
                ? VerifyController::prompt($request)
                : response()->json(['message' => 'verification required'], 403);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
