<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Outbox;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Second step for /admin: after the password, a 6-digit code goes to the
 * admin verification address (ADMIN_OTP_EMAIL in .env by default).
 */
class VerifyController extends Controller
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public static function verified(Request $request): bool
    {
        return $request->session()->get('admin_verified') === $request->user()->id;
    }

    public static function destination(): string
    {
        return Setting::get('admin_otp_email', (string) config('inbound.otp_email'));
    }

    /** Shows the code form, sending a fresh code when none is pending. */
    public static function prompt(Request $request): Response
    {
        $pending = $request->session()->get('admin_otp');
        $error = null;
        if (! $pending || $pending['user'] !== $request->user()->id || now()->timestamp > $pending['expires']) {
            $error = self::send($request);
        }

        return response()->view('auth.verify', [
            'destination' => self::mask(self::destination()),
            'sendError' => $error,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        $pending = $request->session()->get('admin_otp');

        if (! $pending || $pending['user'] !== $request->user()->id || now()->timestamp > $pending['expires']) {
            $request->session()->forget('admin_otp');

            return redirect('/admin')->with('otp_error', __('admin.codeExpired'));
        }

        if (! Hash::check($code, $pending['hash'])) {
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $request->session()->forget('admin_otp');

                return redirect('/admin')->with('otp_error', __('admin.tooManyWrong'));
            }
            $request->session()->put('admin_otp', $pending);

            return redirect('/admin')->with('otp_error', __('admin.wrongCode'));
        }

        $request->session()->forget('admin_otp');
        $request->session()->regenerate();
        $request->session()->put('admin_verified', $request->user()->id);

        return redirect('/admin');
    }

    public function resend(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $pending = $request->session()->get('admin_otp');
        if ($pending && now()->timestamp - $pending['sent'] < 60) {
            return redirect('/admin')->with('otp_error', __('admin.waitMinute'));
        }

        $error = self::send($request);

        return redirect('/admin')->with($error ? 'otp_error' : 'otp_status', $error ?: __('admin.newCodeSent'));
    }

    private static function send(Request $request): ?string
    {
        $code = (string) random_int(100000, 999999);
        $request->session()->put('admin_otp', [
            'user' => $request->user()->id,
            'hash' => Hash::make($code),
            'expires' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
            'sent' => now()->timestamp,
            'attempts' => 0,
        ]);

        try {
            $email = (new Email)
                ->from(Setting::get('system_from', 'hello@'.config('inbound.domain')))
                ->to(self::destination())
                ->subject(__('admin.otpSubject', ['code' => $code]))
                ->text(__('admin.otpBody', [
                    'code' => $code,
                    'email' => $request->user()->email,
                    'ip' => $request->ip(),
                    'minutes' => self::TTL_MINUTES,
                ]));
            (new Mailer(Outbox::transport()))->send($email);
        } catch (Throwable $e) {
            report($e);

            return __('admin.cannotSendCode', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('•', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }
}
