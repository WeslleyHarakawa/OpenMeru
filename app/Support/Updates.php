<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Knows whether a newer OpenMeru is out, and runs the update in the background
 * (same steps as install.sh: new code, composer, migrate, caches) while /admin
 * watches the log. With automatic updates on (the default) it starts the run
 * itself the first time it sees a newer version.
 */
class Updates
{
    // On the "local" disk, whose root is storage/app/private.
    public const STATUS_FILE = 'openmeru-update.json';

    public const CACHE_KEY = 'openmeru:update-check';

    /** Everything the package update must leave alone: this install's own data. */
    public const KEEP = ['.env', '.git', 'storage', 'vendor', 'node_modules', 'bootstrap/cache', 'public_root/storage'];

    /** @return array{current: string, latest: ?string, available: bool, url: ?string, tarball: ?string, checked_at: ?string, error: ?string} */
    public static function check(bool $force = false): array
    {
        $current = Version::current();
        $empty = ['current' => $current, 'latest' => null, 'available' => false, 'url' => null, 'tarball' => null, 'checked_at' => null, 'error' => null];
        if (! config('updates.check') || ! config('updates.url')) {
            return $empty;
        }
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addHours((int) config('updates.ttl_hours', 24)), function () use ($current, $empty) {
            try {
                $response = Http::timeout(8)->acceptJson()->get((string) config('updates.url'));
                $latest = (string) $response->json('version');
                if (! $response->successful() || $latest === '') {
                    return ['error' => 'bad response ('.$response->status().')', 'checked_at' => now()->toIso8601String()] + $empty;
                }

                return [
                    'current' => $current,
                    'latest' => Version::normalize($latest),
                    'available' => Version::newerAvailable($latest),
                    'url' => $response->json('url') ?: $response->json('repo'),
                    'tarball' => $response->json('tarball') ?: null,
                    'checked_at' => now()->toIso8601String(),
                    'error' => null,
                ];
            } catch (Throwable $e) {
                return ['error' => $e->getMessage(), 'checked_at' => now()->toIso8601String()] + $empty;
            }
        });
    }

    /** The package every install can fall back to when git is not usable from PHP. */
    public static function tarballUrl(): string
    {
        return (string) (self::check()['tarball'] ?: config('updates.tarball'));
    }

    /** Whether "Update now" (and the automatic run) can do the work from inside the app. */
    public static function canSelfUpdate(): bool
    {
        return is_file(base_path('install.sh')) && is_file(base_path('artisan')) && ! app()->environment('testing');
    }

    public static function installCommand(): string
    {
        $url = (string) config('updates.url');
        $base = preg_replace('#/version/?$#', '', $url) ?: 'https://openmeru.com';

        return 'curl -fsSL '.$base.'/install | bash';
    }

    /** Settings → Updates: on unless the admin turned it off (or there is no database yet). */
    public static function autoEnabled(): bool
    {
        try {
            return Setting::get('auto_update', '1') !== '0';
        } catch (Throwable) {
            return false;
        }
    }

    public static function setAutoEnabled(bool $on): void
    {
        Setting::put('auto_update', $on ? '1' : '0');
    }

    /**
     * Starts the update by itself when one is out and nothing is in the way. A failed run
     * stays on screen until the admin closes it, so a broken server is not retried forever.
     */
    public static function maybeAutoStart(): bool
    {
        if (! self::autoEnabled() || ! self::canSelfUpdate() || self::status()['state'] !== 'idle') {
            return false;
        }
        if (! self::check()['available']) {
            return false;
        }

        return self::start();
    }

    /** @return array{state: string, log: string, started_at: ?string, finished_at: ?string, from: ?string, to: ?string} */
    public static function status(): array
    {
        $idle = ['state' => 'idle', 'log' => '', 'started_at' => null, 'finished_at' => null, 'from' => null, 'to' => null];
        if (! Storage::disk('local')->exists(self::STATUS_FILE)) {
            return $idle;
        }
        $data = json_decode((string) Storage::disk('local')->get(self::STATUS_FILE), true);
        if (! is_array($data)) {
            return $idle;
        }
        $data += $idle;
        // A run that never finished and is older than an hour is a crash, not a run.
        if ($data['state'] === 'running' && ! ($data['started_at'] && Carbon::parse($data['started_at'])->diffInMinutes(now()) < 60)) {
            $data['state'] = 'failed';
            $data['log'] .= "\nFAILED: the update stopped without finishing.";
        }

        return $data;
    }

    public static function writeStatus(array $status): void
    {
        $current = Storage::disk('local')->exists(self::STATUS_FILE)
            ? (json_decode((string) Storage::disk('local')->get(self::STATUS_FILE), true) ?: [])
            : [];
        Storage::disk('local')->put(self::STATUS_FILE, json_encode($status + $current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function clearStatus(): void
    {
        Storage::disk('local')->delete(self::STATUS_FILE);
    }

    public static function isRunning(): bool
    {
        return self::status()['state'] === 'running';
    }

    /** Starts `php artisan openmeru:update` detached; the status file tells how it goes. */
    public static function start(): bool
    {
        if (self::isRunning() || ! self::canSelfUpdate()) {
            return false;
        }
        self::writeStatus(['state' => 'running', 'log' => "Starting…\n", 'started_at' => now()->toIso8601String(), 'finished_at' => null, 'from' => Version::current(), 'to' => null]);

        $php = PHP_BINARY ?: 'php';
        $cmd = sprintf('nohup %s artisan openmeru:update > /dev/null 2>&1 &', escapeshellarg($php));
        $process = Process::fromShellCommandline($cmd, base_path());
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Copies a downloaded release over this install, leaving the install's own data
     * (KEEP) untouched. Returns how many files were written.
     */
    public static function applyTree(string $from, string $to): int
    {
        $from = rtrim($from, '/\\');
        $to = rtrim($to, '/\\');
        $written = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($from) + 1));
            if (self::kept($relative)) {
                continue;
            }
            $target = $to.'/'.$relative;
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }
            if (is_file($target) && file_get_contents($target) === file_get_contents($item->getPathname())) {
                continue;
            }
            if (! copy($item->getPathname(), $target)) {
                throw new \RuntimeException('Could not write '.$relative);
            }
            if ($item->isExecutable()) {
                @chmod($target, 0755);
            }
            $written++;
        }

        return $written;
    }

    private static function kept(string $relative): bool
    {
        foreach (self::KEEP as $keep) {
            if ($relative === $keep || str_starts_with($relative, $keep.'/')) {
                return true;
            }
        }

        return false;
    }
}
