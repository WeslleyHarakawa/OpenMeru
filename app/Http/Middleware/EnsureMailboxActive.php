<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMailboxActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return $request->expectsJson()
                ? response()->json(['message' => 'inactive'], 401)
                : redirect()->route('login');
        }

        $response = $next($request);
        // Signed-in pages are private: keep Varnish and Cloudflare from caching them.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
