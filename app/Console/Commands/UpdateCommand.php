<?php

namespace App\Console\Commands;

use App\Support\Git;
use App\Support\Updates;
use App\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Updates a git checkout in place: database backup, git pull, composer install,
 * migrations, caches. Progress goes to the status file that /admin watches.
 */
class UpdateCommand extends Command
{
    protected $signature = 'openmeru:update {--no-backup : Skip the database dump}';

    protected $description = 'Update this install to the latest published version';

    private string $log = '';

    public function handle(): int
    {
        if (! Version::isGitCheckout()) {
            $this->components->error('Not a git checkout. Update with: '.Updates::installCommand());

            return self::FAILURE;
        }

        $from = Version::current();
        Updates::writeStatus(['state' => 'running', 'log' => '', 'started_at' => now()->toIso8601String(), 'finished_at' => null, 'from' => $from, 'to' => null]);

        try {
            $this->step('Checking the working tree');
            $dirty = trim($this->sh(Git::command('status --porcelain --untracked-files=no')));
            if ($dirty !== '') {
                throw new \RuntimeException("Local changes would be overwritten by the update:\n".$dirty."\nCommit them (php artisan openmeru:publish) or discard them (git checkout -- .) first.");
            }

            if (! $this->option('no-backup')) {
                $this->backup();
            }

            $this->step('Fetching the latest code');
            $this->sh(Git::command('fetch --quiet origin'));
            $branch = trim($this->sh(Git::command('rev-parse --abbrev-ref HEAD')));
            $this->clearUntrackedCopies('origin/'.$branch);
            $this->sh(Git::command('pull --ff-only --quiet origin '.escapeshellarg($branch)));

            $this->step('Installing PHP dependencies');
            $composer = $this->composer();
            $this->sh(escapeshellarg(PHP_BINARY).' '.escapeshellarg($composer).' install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1');

            $this->step('Migrating the database');
            $this->callSilently('migrate', ['--force' => true]);

            $this->step('Rebuilding caches');
            foreach (['optimize:clear', 'config:cache', 'route:cache', 'view:cache'] as $command) {
                $this->callSilently($command);
            }

            $to = Version::current();
            $this->note("Updated from {$from} to {$to}.");
            Updates::writeStatus(['state' => 'done', 'log' => $this->log, 'finished_at' => now()->toIso8601String(), 'to' => $to]);
            $this->components->info("Updated from {$from} to {$to}.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->note('FAILED: '.$e->getMessage());
            Updates::writeStatus(['state' => 'failed', 'log' => $this->log, 'finished_at' => now()->toIso8601String()]);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Files the new version adds that already exist here untracked (Laravel creates the
     * storage .gitignore files on its own) would make the pull refuse. When the local copy
     * is identical to the incoming one it is just dropped; anything different stops the update.
     */
    private function clearUntrackedCopies(string $target): void
    {
        $incoming = array_filter(explode("\n", trim($this->sh(Git::command('diff --name-only --diff-filter=A HEAD '.escapeshellarg($target))))));
        $tracked = array_flip(array_filter(explode("\n", trim($this->sh(Git::command('ls-files'))))));
        $blocking = [];
        foreach ($incoming as $path) {
            if (isset($tracked[$path]) || ! file_exists(base_path($path))) {
                continue;
            }
            $theirs = $this->sh(Git::command('show '.escapeshellarg($target.':'.$path)));
            if ($theirs === (string) file_get_contents(base_path($path))) {
                unlink(base_path($path));
                $this->note('Dropped untracked copy of '.$path.' (identical to the update)');
            } else {
                $blocking[] = $path;
            }
        }
        if ($blocking) {
            throw new \RuntimeException("These untracked files differ from the ones the update brings:\n".implode("\n", $blocking)."\nMove them away and run the update again.");
        }
    }

    private function backup(): void
    {
        $this->step('Backing up the database');
        $connection = config('database.default');
        if ($connection !== 'mysql' && $connection !== 'mariadb') {
            $this->note('Skipped: not MySQL ('.$connection.').');

            return;
        }
        $dump = trim($this->sh('command -v mariadb-dump || command -v mysqldump || true'));
        if ($dump === '') {
            $this->note('Skipped: mysqldump not found.');

            return;
        }
        $cfg = config('database.connections.'.$connection);
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir.'/'.$cfg['database'].'-'.date('Ymd-His').'.sql.gz';
        $cmd = sprintf('%s --single-transaction --quick --no-tablespaces -h %s -P %s -u %s %s | gzip > %s',
            escapeshellarg($dump), escapeshellarg($cfg['host']), escapeshellarg((string) $cfg['port']),
            escapeshellarg($cfg['username']), escapeshellarg($cfg['database']), escapeshellarg($file));
        $process = Process::fromShellCommandline($cmd, base_path(), ['MYSQL_PWD' => (string) $cfg['password']], null, 600);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) < 100) {
            throw new \RuntimeException('Database backup failed: '.trim($process->getErrorOutput()));
        }
        $this->note('Saved to '.$file);
        // Keep the last five.
        $old = glob($dir.'/*.sql.gz') ?: [];
        rsort($old);
        foreach (array_slice($old, 5) as $stale) {
            @unlink($stale);
        }
        DB::disconnect();
    }

    private function composer(): string
    {
        foreach ([getenv('COMPOSER_BINARY') ?: null, '/usr/local/bin/composer', '/usr/bin/composer', trim($this->sh('command -v composer || true'))] as $candidate) {
            if ($candidate && is_file($candidate)) {
                return $candidate;
            }
        }
        throw new \RuntimeException('composer not found.');
    }

    private function sh(string $command): string
    {
        $process = Process::fromShellCommandline($command, base_path(), null, null, 900);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'command failed: '.$command);
        }

        return $process->getOutput();
    }

    private function step(string $text): void
    {
        $this->note('▶ '.$text);
    }

    private function note(string $text): void
    {
        $this->log .= $text."\n";
        $this->line($text);
        Updates::writeStatus(['log' => $this->log]);
    }
}
