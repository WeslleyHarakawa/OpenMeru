<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['hooks/inbound']);
        // The page script writes the language cookie, so it is stored in plain text.
        $middleware->encryptCookies(except: [\App\Support\Locales::COOKIE]);
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class, \App\Http\Middleware\DemoReadOnly::class]);
        $middleware->trustProxies(at: '*');
        // The sign-in page is the site root on a normal install and /mail on openmeru.com.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('login'));
        $middleware->alias([
            'mailbox.active' => \App\Http\Middleware\EnsureMailboxActive::class,
            'admin.area' => \App\Http\Middleware\AdminArea::class,
            'source.only' => \App\Http\Middleware\SourceOnly::class,
        ]);
        // Check who is asking before resolving /admin/accounts/{account}: otherwise a visitor
        // can tell which account ids exist (404 for a missing one, 401 for a real one).
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\AdminArea::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('hooks/*') || $request->expectsJson(),
        );
    })->create();
