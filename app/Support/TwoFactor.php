<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time passwords (Google Authenticator, Authy, 1Password…):
 * a secret per mailbox, a QR code to scan, 6-digit codes to confirm and
 * sign in, and one-time recovery codes for a lost phone.
 */
class TwoFactor
{
    public const RECOVERY_CODES = 8;

    /** Settings → Security: when on, every mailbox must set 2FA up before using the webmail. */
    public static function required(): bool
    {
        return Setting::get('require_2fa') === '1';
    }

    public static function setRequired(bool $on): void
    {
        Setting::put('require_2fa', $on ? '1' : '0');
    }

    public static function generateSecret(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    public static function otpauthUrl(User $user, string $secret): string
    {
        return (new Google2FA)->getQRCodeUrl(Brand::name(), $user->email, $secret);
    }

    /** An inline SVG of the QR code (no third-party image service sees the secret). */
    public static function qrSvg(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220, 2, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(27, 34, 48))),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($otpauthUrl);
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }

        // One step of tolerance (30 s) for clocks that drift a little.
        return (bool) (new Google2FA)->verifyKey($secret, $code, 1);
    }

    /** @return string[] */
    public static function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => strtolower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** Uses up one recovery code; false when it is not one of the user's. */
    public static function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtolower(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search($code, $codes, true);
        if ($index === false) {
            return false;
        }
        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    public static function enabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret;
    }

    /** Clears everything, as after "disable" or an admin reset. */
    public static function reset(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
