<?php

namespace App\Console\Commands;

use App\Support\Git;
use App\Support\Version;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * For the install that is the source of truth (openmeru.com): commits what
 * changed on this server, bumps VERSION and pushes, so every other install
 * sees a new version and can update to exactly this code.
 */
class PublishCommand extends Command
{
    protected $signature = 'openmeru:publish {message : What changed, in one line}
        {--minor : Bump the minor version (new feature) instead of the patch}
        {--major : Bump the major version (breaking change)}
        {--no-push : Commit and bump, but do not push}';

    protected $description = 'Commit the changes on this server, bump VERSION and push them';

    public function handle(): int
    {
        if (! Version::isGitCheckout()) {
            $this->components->error('Not a git checkout: nothing to publish from here.');

            return self::FAILURE;
        }

        $changes = trim($this->git('status --porcelain'));
        if ($changes === '') {
            $this->components->warn('Nothing changed since the last publish.');

            return self::SUCCESS;
        }
        $this->line($changes);

        $from = Version::current();
        $to = Version::bump($from, $this->option('major') ? 'major' : ($this->option('minor') ? 'minor' : 'patch'));
        file_put_contents(base_path('VERSION'), $to."\n");

        $this->git('add -A');
        $this->git('commit --quiet -m '.escapeshellarg($to.': '.$this->argument('message')));
        $this->components->task("Committed {$to}", fn () => true);

        if (! $this->option('no-push')) {
            $branch = trim($this->git('rev-parse --abbrev-ref HEAD'));
            $this->git('push --quiet origin '.escapeshellarg($branch));
            $this->components->task('Pushed to origin/'.$branch, fn () => true);
        }
        $this->components->info("Published {$from} → {$to}. Other installs will see the update within a day, or at once with \"Check now\".");

        return self::SUCCESS;
    }

    private function git(string $args): string
    {
        $process = Process::fromShellCommandline(Git::command($args), base_path(), null, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->components->error(trim($process->getErrorOutput() ?: $process->getOutput()));
            exit(self::FAILURE);
        }

        return $process->getOutput();
    }
}
