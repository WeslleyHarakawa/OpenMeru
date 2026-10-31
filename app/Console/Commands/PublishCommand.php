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

    /** Pathspecs excluded from `git add`: server data never travels with the code. */
    private const NEVER_PUBLISH = [':!storage', ':!.env', ':!.env.*', ':!public_root/.well-known', ':!database/*.sqlite', ':!.claude', ':!vendor', ':!node_modules'];

    /** Anything matching this is refused even if it slipped past the pathspecs. */
    private const FORBIDDEN = '#^(storage/|\.env|database/.*\.sqlite$|.*\.(sql|sql\.gz|dump|bak|log|key|pem)$|.*/sessions/|.*backups?/)#i';

    public function handle(): int
    {
        if (! Version::isGitCheckout()) {
            $this->components->error('Not a git checkout: nothing to publish from here.');

            return self::FAILURE;
        }

        // Only source files are ever published. Nothing that lives on this server (data, uploads,
        // backups, secrets, local tooling) may leave it, whatever .gitignore says.
        $this->git('add -A -- . '.implode(' ', array_map('escapeshellarg', self::NEVER_PUBLISH)));
        $staged = array_filter(explode("\n", trim($this->git('diff --cached --name-only'))));
        $forbidden = array_filter($staged, [self::class, 'isForbidden']);
        if ($forbidden) {
            $this->git('reset -q');
            $this->components->error("Refusing to publish files that belong to this server, not to the project:\n  ".implode("\n  ", $forbidden));

            return self::FAILURE;
        }
        if (! $staged) {
            $this->components->warn('Nothing changed since the last publish.');

            return self::SUCCESS;
        }
        $this->line(implode("\n", $staged));

        $from = Version::current();
        $to = Version::bump($from, $this->option('major') ? 'major' : ($this->option('minor') ? 'minor' : 'patch'));
        file_put_contents(base_path('VERSION'), $to."\n");
        $this->git('add VERSION');
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

    public static function isForbidden(string $path): bool
    {
        return (bool) preg_match(self::FORBIDDEN, $path);
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
