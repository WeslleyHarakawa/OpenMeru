<?php

namespace App\Support;

/** The version of the code that is running, from the VERSION file at the project root. */
class Version
{
    public static function current(): string
    {
        $raw = @file_get_contents(base_path('VERSION'));

        return trim((string) $raw) ?: '0.0.0';
    }

    /** When the VERSION file was last changed: a fair "released at" for the /version endpoint. */
    public static function releasedAt(): ?string
    {
        $time = @filemtime(base_path('VERSION'));

        return $time ? date(DATE_ATOM, $time) : null;
    }

    public static function isGitCheckout(): bool
    {
        return is_dir(base_path('.git'));
    }

    public static function commit(): ?string
    {
        if (! self::isGitCheckout()) {
            return null;
        }
        $process = Git::run('rev-parse --short HEAD', 30);
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        // No usable git binary for this user: read the ref files directly.
        $head = trim((string) @file_get_contents(base_path('.git/HEAD')));
        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $sha = trim((string) @file_get_contents(base_path('.git/'.$ref)));
            if ($sha === '' && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', (string) @file_get_contents(base_path('.git/packed-refs')), $m)) {
                $sha = $m[1];
            }
            $head = $sha;
        }

        return preg_match('/^[0-9a-f]{40}$/', $head) ? substr($head, 0, 7) : null;
    }

    public static function newerAvailable(string $latest): bool
    {
        return version_compare(self::normalize($latest), self::normalize(self::current()), '>');
    }

    /**
     * Versions read X.X.XX like a counter: the last number goes up on every release and
     * rolls over into the middle one at 99 (1.1.99 -> 1.2.00), which rolls over into the
     * first at 99 (1.99.99 -> 2.0.00). --minor and --major move the bigger number at once.
     */
    public static function bump(string $version, string $level = 'patch'): string
    {
        [$major, $minor, $patch] = array_map('intval', array_pad(explode('.', self::normalize($version)), 3, 0));

        [$major, $minor, $patch] = match ($level) {
            'major' => [$major + 1, 0, 0],
            'minor' => [$major, $minor + 1, 0],
            default => [$major, $minor, $patch + 1],
        };
        if ($patch > 99) {
            $patch = 0;
            $minor++;
        }
        if ($minor > 99) {
            $minor = 0;
            $major++;
        }

        return self::format($major, $minor, $patch);
    }

    public static function format(int $major, int $minor, int $patch): string
    {
        return sprintf('%d.%d.%02d', $major, $minor, $patch);
    }

    /** Comparable form: no "v", no zero padding (version_compare reads 1.01.010 as 1.1.10 anyway). */
    public static function normalize(string $version): string
    {
        $parts = array_map('intval', explode('.', ltrim(trim($version), 'vV')));

        return $parts === [0] || $parts === [] ? '0.0.0' : implode('.', $parts);
    }
}
