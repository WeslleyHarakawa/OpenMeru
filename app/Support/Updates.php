<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Knows whether a newer OpenMeru is out, and runs the update in the background
 * (same steps as install.sh: pull, composer, migrate, caches) while /admin
 * watches the log.
 */
class Updates
{
    public const STATUS_FILE = 'private/openmeru-update.json';

    public const CACHE_KEY = 'openmeru:update-check';

    /** @return array{current: string, latest: ?string, available: bool, url: ?string, checked_at: ?string, error: ?string} */
    public static function check(bool $force = false): array
    {
        $current = Version::current();
        $empty = ['current' => $current, 'latest' => null, 'available' => false, 'url' => null, 'checked_at' => null, 'error' => null];
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
                    'checked_at' => now()->toIso8601String(),
                    'error' => null,
                ];
            } catch (Throwable $e) {
                return ['error' => $e->getMessage(), 'checked_at' => now()->toIso8601String()] + $empty;
            }
        });
    }

    /** The "Update now" button only works on a git checkout, where pulling is safe. */
    public static function canSelfUpdate(): bool
    {
        return Version::isGitCheckout() && is_file(base_path('install.sh')) && ! app()->environment('testing');
    }

    public static function installCommand(): string
    {
        $url = (string) config('updates.url');
        $base = preg_replace('#/version/?$#', '', $url) ?: 'https://openmeru.com';

        return 'curl -fsSL '.$base.'/install | bash';
    }

    /** @return array{state: string, log: string, started_at: ?string, finished_at: ?string, from: ?string, to: ?string} */
    public static function status(): array
    {
        $idle = ['state' => 'idle', 'log' => '', 'started_at' => null, 'finished_at' => null, 'from' => null, 'to' => null];
        if (! Storage::disk('local')->exists(self::STATUS_FILE)) {
            return $idle;
        }
        $data = json_decode((string) Storage::disk('local')->get(self::STATUS_FILE), true);

        return is_array($data) ? $data + $idle : $idle;
    }

    public static function writeStatus(array $status): void
    {
        Storage::disk('local')->put(self::STATUS_FILE, json_encode($status + self::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function clearStatus(): void
    {
        Storage::disk('local')->delete(self::STATUS_FILE);
    }

    public static function isRunning(): bool
    {
        $status = self::status();
        // A run that never finished and is older than an hour is a crash, not a run.
        return $status['state'] === 'running'
            && $status['started_at'] && Carbon::parse($status['started_at'])->diffInMinutes(now()) < 60;
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
}
