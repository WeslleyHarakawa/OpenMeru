<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locales::detect($request);
        App::setLocale($locale);

        // A ?lang= link sticks, like a pick in the language menu.
        if ($request->query('lang') === $locale && $request->cookie(Locales::COOKIE) !== $locale) {
            Cookie::queue(Cookie::make(Locales::COOKIE, $locale, 525600, '/', null, null, false, false, 'lax'));
        }

        // Remember it on the account, for mail written while the person is away.
        $user = $request->user();
        if ($user && $user->locale !== $locale && Locales::supported($request->cookie(Locales::COOKIE))) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return $next($request);
    }
}
