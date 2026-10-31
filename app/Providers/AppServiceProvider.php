<?php

namespace App\Providers;

use App\Models\User;
use App\Support\DomainAwareLoader;
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
    }
}
