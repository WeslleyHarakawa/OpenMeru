<?php

namespace App\Support;

use Symfony\Component\Process\Process;

/**
 * Runs git in the project folder. The web server user is often not the
 * owner of the files (CloudPanel: site user vs. SSH user), which makes git
 * refuse with "dubious ownership"; the project folder is always trusted here.
 * PHP-FPM also runs with a short PATH, so the binary is looked up by hand.
 */
class Git
{
    private static ?string $binary = null;

    private static bool $resolved = false;

    public static function run(string $args, int $timeout = 300): Process
    {
        $process = Process::fromShellCommandline(self::command($args), base_path(), null, null, $timeout);
        $process->run();

        return $process;
    }

    public static function command(string $args): string
    {
        return escapeshellarg(self::binary() ?? 'git').' -c safe.directory='.escapeshellarg(base_path()).' '.$args;
    }

    /** The git executable, or null when this user has none (then updates download the package instead). */
    public static function binary(): ?string
    {
        if (self::$resolved) {
            return self::$binary;
        }
        self::$resolved = true;
        $candidates = array_filter([getenv('GIT_BINARY') ?: null, '/usr/bin/git', '/usr/local/bin/git', '/opt/homebrew/bin/git', '/bin/git']);
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return self::$binary = $candidate;
            }
        }
        $which = Process::fromShellCommandline('command -v git || true', base_path(), null, null, 10);
        $which->run();
        $found = trim($which->getOutput());

        return self::$binary = ($found !== '' && is_executable($found)) ? $found : null;
    }

    public static function available(): bool
    {
        return Version::isGitCheckout() && self::binary() !== null && self::run('rev-parse --git-dir', 30)->isSuccessful();
    }

    /** Only for tests. */
    public static function forget(): void
    {
        self::$resolved = false;
        self::$binary = null;
    }
}
