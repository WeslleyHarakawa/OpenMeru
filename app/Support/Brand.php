<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * What the install calls itself. The admin sets a name and a logo in
 * Settings → General; until then the product shows as OpenMeru Mail.
 */
class Brand
{
    public const DEFAULT_NAME = 'OpenMeru Mail';

    public const LOGO_PATH = 'branding/logo';

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

    /** Small word under it: "MAIL" (or "ADMIN") for the default brand, nothing for a custom one. */
    public static function product(string $default = 'MAIL'): string
    {
        return self::isCustom() ? '' : $default;
    }

    public static function logoPath(): ?string
    {
        return self::settings()['logo'] ?: null;
    }

    public static function logoUrl(): ?string
    {
        $path = self::logoPath();
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return route('brand.logo', ['v' => Storage::disk('local')->lastModified($path)]);
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** @return array{name: string, logo: string} */
    private static function settings(): array
    {
        return self::$cache ??= [
            'name' => trim((string) Setting::get('brand_name')),
            'logo' => trim((string) Setting::get('brand_logo')),
        ];
    }
}
