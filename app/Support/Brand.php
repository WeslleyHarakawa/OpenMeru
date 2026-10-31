<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

/**
 * What the install calls itself and looks like. The admin sets a name, a logo,
 * a small icon, a favicon, a sign-in background and two colours in
 * Settings → General; until then the product shows as OpenMeru Mail.
 */
class Brand
{
    public const DEFAULT_NAME = 'OpenMeru Mail';

    public const DEFAULT_PRIMARY = '#d2151a';

    public const DEFAULT_SECONDARY = '#a60f14';

    /** Uploadable files, with the types each accepts and its size limit in KB. */
    public const ASSETS = [
        'logo' => ['mimes' => 'png,jpg,jpeg,webp,svg', 'max' => 5120],
        'icon' => ['mimes' => 'png,jpg,jpeg,webp,svg', 'max' => 5120],
        'favicon' => ['mimes' => 'ico,png,svg', 'max' => 2048],
        'background' => ['mimes' => 'png,jpg,jpeg,webp', 'max' => 10240],
    ];

    private static ?array $cache = null;

    public static function name(): string
    {
        return self::settings()['name'] ?: self::DEFAULT_NAME;
    }

    public static function isCustom(): bool
    {
        return self::settings()['name'] !== '';
    }

    /** Big word on the sign-in page and sidebar: the custom name, or "OpenMeru". */
    public static function wordmark(): string
    {
        return self::isCustom() ? self::name() : 'OpenMeru';
    }

    /** Small word under the name, "MAIL" or "ADMIN": with a custom brand too, so it reads as an e-mail service. */
    public static function product(string $default = 'MAIL'): string
    {
        return $default;
    }

    public static function assetPath(string $asset): ?string
    {
        $path = self::settings()[$asset] ?? '';

        return $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    public static function assetUrl(string $asset): ?string
    {
        $path = self::assetPath($asset);

        return $path ? route('brand.asset', ['asset' => $asset, 'v' => Storage::disk('local')->lastModified($path)]) : null;
    }

    public static function logoPath(): ?string
    {
        return self::assetPath('logo');
    }

    public static function logoUrl(): ?string
    {
        return self::assetUrl('logo');
    }

    /** The small mark (sidebar, admin header, app icon): the icon, else the logo. */
    public static function iconUrl(): ?string
    {
        return self::assetUrl('icon') ?: self::assetUrl('logo');
    }

    public static function faviconUrl(): ?string
    {
        return self::assetUrl('favicon') ?: self::assetUrl('icon');
    }

    public static function faviconType(): string
    {
        $path = self::assetPath('favicon') ?: self::assetPath('icon');

        return match (strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    public static function backgroundPath(): ?string
    {
        return self::assetPath('background');
    }

    public static function backgroundUrl(): ?string
    {
        return self::assetUrl('background');
    }

    /** The main colour (buttons, links, highlights), as #rrggbb. */
    public static function primary(): string
    {
        return self::settings()['primary'] ?: self::DEFAULT_PRIMARY;
    }

    /** The darker companion (hover states, gradient ends). */
    public static function secondary(): string
    {
        return self::settings()['secondary'] ?: self::DEFAULT_SECONDARY;
    }

    /** "#d2151a" as "210, 21, 26", the form Bootstrap's *-rgb variables want. */
    public static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return implode(', ', array_map(fn ($i) => hexdec(substr($hex, $i, 2)), [0, 2, 4]));
    }

    public static function hasCustomColors(): bool
    {
        return self::settings()['primary'] !== '' || self::settings()['secondary'] !== '';
    }

    /**
     * The biggest upload PHP itself lets through, in kilobytes. The app ships .user.ini and
     * .htaccess to raise it; a server that ignores those (Apache without AllowOverride)
     * stays at its own limit, and the admin is told so instead of seeing uploads fail.
     */
    public static function phpUploadLimitKb(): int
    {
        $bytes = fn (string $v) => match (strtoupper(substr(trim($v), -1))) {
            'G' => (int) $v * 1024 * 1024 * 1024,
            'M' => (int) $v * 1024 * 1024,
            'K' => (int) $v * 1024,
            default => (int) $v,
        };
        $limits = array_filter([(string) ini_get('upload_max_filesize'), (string) ini_get('post_max_size')], fn ($v) => $v !== '' && (int) $v > 0);

        return $limits ? (int) floor(min(array_map($bytes, $limits)) / 1024) : PHP_INT_MAX;
    }

    /** The largest size the brand uploads need (the sign-in background). */
    public static function neededUploadKb(): int
    {
        return max(array_column(self::ASSETS, 'max'));
    }

    /** CSS that overrides the stylesheet's brand variables with the custom colours, or '' when none are set. */
    public static function cssVars(): string
    {
        if (! self::hasCustomColors()) {
            return '';
        }
        $p = self::primary();
        $s = self::secondary();

        return ':root,[data-theme="dark"]{--brand:'.$p.';--brand-dark:'.$s.';--brand-2:'.$p.';--brand-3:'.$s.';'
            .'--brand-1:color-mix(in srgb,'.$p.' 55%,white);--brand-soft:color-mix(in srgb,'.$p.' 14%,white);'
            .'--bs-primary-rgb:'.self::rgb($p).';--bs-link-color-rgb:'.self::rgb($p).';--bs-link-hover-color-rgb:'.self::rgb($s).'}'
            .'[data-theme="dark"]{--brand-soft:color-mix(in srgb,'.$p.' 28%,#0f172a)}';
    }

    public static function validColor(?string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', (string) $value);
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** @return array<string, string> */
    private static function settings(): array
    {
        if (self::$cache === null) {
            $empty = ['name' => '', 'primary' => '', 'secondary' => ''] + array_fill_keys(array_keys(self::ASSETS), '');
            try {
                $get = fn (string $key) => trim((string) Setting::get($key));
                $primary = strtolower($get('brand_primary'));
                $secondary = strtolower($get('brand_secondary'));
                $values = [
                    'name' => $get('brand_name'),
                    'primary' => self::validColor($primary) ? $primary : '',
                    'secondary' => self::validColor($secondary) ? $secondary : '',
                ];
                foreach (array_keys(self::ASSETS) as $asset) {
                    $values[$asset] = $get('brand_'.$asset);
                }
                self::$cache = $values;
            } catch (QueryException) {
                // No settings table yet (fresh install before migrate, or a page rendered without a database): defaults.
                self::$cache = $empty;
            }
        }

        return self::$cache;
    }
}
