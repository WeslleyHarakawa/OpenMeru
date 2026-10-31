<?php

namespace App\Support;

use Symfony\Component\Process\Process;

/**
 * Runs git in the project folder. The web server user is often not the
 * owner of the files (CloudPanel: site user vs. SSH user), which makes git
 * refuse with "dubious ownership"; the project folder is always trusted here.
 */
class Git
{
    public static function run(string $args, int $timeout = 300): Process
    {
        $process = Process::fromShellCommandline(self::command($args), base_path(), null, null, $timeout);
        $process->run();

        return $process;
    }

    public static function command(string $args): string
    {
        return 'git -c safe.directory='.escapeshellarg(base_path()).' '.$args;
    }

    public static function available(): bool
    {
        return Version::isGitCheckout() && self::run('rev-parse --git-dir', 30)->isSuccessful();
    }
}
