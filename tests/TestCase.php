<?php

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The suite refreshes the database, so it must never run against a real
     * install: stop before anything touches a connection unless the app booted
     * as "testing" on an in-memory SQLite database (what phpunit.xml sets up).
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = config('database.default');
        if (! $app->environment('testing')
            || $connection !== 'sqlite'
            || config("database.connections.{$connection}.database") !== ':memory:') {
            throw new RuntimeException(
                'Refusing to run tests: the app did not boot in the "testing" environment on in-memory SQLite '
                .'(env='.$app->environment().', db='.$connection.'). Check phpunit.xml and that no .env or '
                .'bootstrap/cache/config.php of a real install is being loaded.'
            );
        }

        // The assets live in public_root (the web root of a real install, see public_root/index.php).
        $app->usePublicPath($app->basePath('public_root'));

        return $app;
    }
}
