<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The ten interface languages, their native names and the matching
 * Intl/HTML codes. The choice lives in the "openmeru_lang" cookie.
 */
class Locales
{
    public const COOKIE = 'openmeru_lang';

    /** code => [native name, BCP 47 tag] */
    public const ALL = [
        'en' => ['English', 'en-GB'],
        'pt' => ['Português', 'pt-PT'],
        'pt_BR' => ['Português (Brasil)', 'pt-BR'],
        'es' => ['Español', 'es-ES'],
        'fr' => ['Français', 'fr-FR'],
        'de' => ['Deutsch', 'de-DE'],
        'it' => ['Italiano', 'it-IT'],
        'nl' => ['Nederlands', 'nl-NL'],
        'ru' => ['Русский', 'ru-RU'],
        'zh' => ['中文', 'zh-CN'],
    ];

    public static function supported(?string $code): bool
    {
        return $code !== null && isset(self::ALL[$code]);
    }

    /** ?lang= first (links from search engines), then the cookie, then the browser's Accept-Language, then English. */
    public static function detect(Request $request): string
    {
        $query = $request->query('lang');
        if (is_string($query) && self::supported($query)) {
            return $query;
        }
        $cookie = $request->cookie(self::COOKIE);
        if (self::supported($cookie)) {
            return $cookie;
        }

        foreach ($request->getLanguages() as $lang) {
            $lang = str_replace('-', '_', $lang);
            if (strcasecmp($lang, 'pt_BR') === 0) {
                return 'pt_BR';
            }
            $short = strtolower(substr($lang, 0, 2));
            if (self::supported($short)) {
                return $short;
            }
        }

        return 'en';
    }

    public static function tag(string $code): string
    {
        return self::ALL[$code][1] ?? 'en-GB';
    }

    /** Dictionary for the page script, English filling any gap. The landing page's texts stay out: only Blade renders those. */
    public static function dictionary(string $code): array
    {
        static $cache = [];
        if (! isset($cache[$code])) {
            $load = fn (string $c) => json_decode((string) @file_get_contents(lang_path($c.'.json')), true) ?: [];
            $all = array_merge($load('en'), $code === 'en' ? [] : $load($code));
            $cache[$code] = array_filter($all, fn ($key) => ! str_starts_with($key, 'landing.'), ARRAY_FILTER_USE_KEY);
        }

        return $cache[$code];
    }
}
