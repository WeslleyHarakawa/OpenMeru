<?php

namespace App\Support;

use Symfony\Component\Process\Process;

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
        $process = Process::fromShellCommandline('git rev-parse --short HEAD', base_path());
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    public static function newerAvailable(string $latest): bool
    {
        return version_compare(self::normalize($latest), self::normalize(self::current()), '>');
    }

    /** 1.2.3 -> 1.2.4 (patch), 1.3.0 (minor) or 2.0.0 (major). */
    public static function bump(string $version, string $level = 'patch'): string
    {
        [$major, $minor, $patch] = array_map('intval', array_pad(explode('.', self::normalize($version)), 3, 0));

        return match ($level) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    public static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV') ?: '0.0.0';
    }
}
