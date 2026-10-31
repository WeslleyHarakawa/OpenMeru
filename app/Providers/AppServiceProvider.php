<?php

namespace App\Providers;

use App\Models\User;
use App\Support\DomainAwareLoader;
use App\Support\Updates;
use Illuminate\Support\Facades\Gate;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Texts show this install's mail domain instead of the shipped openmeru.com.
        $this->app->extend('translation.loader', fn (FileLoader $loader, $app) => DomainAwareLoader::fromExisting(
            $loader,
            [dirname((new \ReflectionClass(Translator::class))->getFileName()).'/lang', $app['path.lang']],
        ));
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Installs update themselves: after any web response, start the update when one is out.
        // The check is cached for a day, so this costs one cache read per request.
        if (! $this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $this->app->terminating(function () {
                try {
                    Updates::maybeAutoStart();
                } catch (\Throwable) {
                    // Never let the updater break a page.
                }
            });
        }
    }
}
