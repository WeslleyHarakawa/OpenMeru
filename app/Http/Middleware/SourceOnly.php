<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages that belong to the project site (openmeru.com) and not to the webmail
 * of whoever installed it: landing extras, demo, installer, version feed.
 */
class SourceOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('updates.source'), 404);

        return $next($request);
    }
}
