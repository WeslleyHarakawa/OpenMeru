<?php

namespace App\Support;

/**
 * The texts ship with the domain openmeru.com in them (demo addresses, examples).
 * After an install they must show the real mail domain instead.
 */
class MailDomain
{
    public static function valid(string $domain): bool
    {
        return (bool) preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $domain);
    }

    /** Rewrites every lang/*.json so the previous domain reads as $domain. */
    public static function rewriteTexts(string $domain, ?string $previous = null): void
    {
        $previous ??= str_contains((string) file_get_contents(lang_path('en.json')), '@openmeru.com')
            ? 'openmeru.com'
            : (string) config('inbound.domain');
        if ($previous === '' || $previous === $domain) {
            return;
        }
        foreach (glob(lang_path('*.json')) ?: [] as $file) {
            file_put_contents($file, str_replace($previous, $domain, (string) file_get_contents($file)));
        }
    }
}
