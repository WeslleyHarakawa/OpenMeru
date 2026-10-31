<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo users can look at everything and change nothing: any request that
 * would write is answered with "demo" instead of reaching a controller, and
 * the page shows a notice explaining why.
 */
class DemoReadOnly
{
    /** Routes a demo visitor may still post to. */
    private const ALLOWED = ['logout', 'impersonate.stop', 'admin.accounts.impersonate', 'translate', 'two-factor.verify'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_demo || $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        $text = $request->is('api/send') ? __('demoSendBlocked') : __('demoBlocked');
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'demo', 'detail' => $text], 403);
        }

        return redirect()->back()->with('demo_notice', $text);
    }
}
