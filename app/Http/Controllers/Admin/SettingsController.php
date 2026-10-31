<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Outbox;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Support\Brand;
use App\Support\Push;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Throwable;

class SettingsController extends Controller
{
    public function show(): View
    {
        if (auth()->user()->is_demo) {
            return view('admin.settings', [
                'smtp_provider' => 'brevo',
                'smtp_host' => 'smtp-relay.brevo.com',
                'smtp_port' => '587',
                'smtp_username' => 'demo@smtp-brevo.com',
                'smtp_password' => 'xsmtpsib-demo-demo-demo',
                'brevo_api_key' => '',
                'vapid_public' => 'demo-public-key',
                'vapid_private' => 'demo-private-key',
                'push_devices' => 3,
                'system_from' => 'hello@'.config('inbound.domain'),
                'inbound_url' => route('inbound'),
                'inbound_secret_set' => true,
                'cf_account_id' => '0123456789abcdef0123456789abcdef',
                'cf_ai_token_set' => true,
                'brand_name' => '',
                'brand_primary' => Brand::DEFAULT_PRIMARY,
                'brand_secondary' => Brand::DEFAULT_SECONDARY,
                'brand_colors_custom' => false,
                'brand_assets' => array_fill_keys(array_keys(Brand::ASSETS), null),
                'allow_signup' => false,
                'require_2fa' => false,
            ]);
        }

        return view('admin.settings', [
            'smtp_provider' => Outbox::provider(),
            'smtp_host' => Setting::get('smtp_host', 'smtp-relay.brevo.com'),
            'smtp_port' => Setting::get('smtp_port', '587'),
            'smtp_username' => Setting::get('smtp_username'),
            'smtp_password' => Setting::get('smtp_password'),
            'brevo_api_key' => Setting::get('brevo_api_key'),
            'vapid_public' => Setting::get('vapid_public'),
            'vapid_private' => Setting::get('vapid_private'),
            'push_devices' => PushSubscription::count(),
            'system_from' => Setting::get('system_from', 'hello@'.config('inbound.domain')),
            'inbound_url' => route('inbound'),
            'inbound_secret_set' => (string) config('inbound.secret') !== '',
            'cf_account_id' => Setting::get('cf_account_id'),
            'cf_ai_token_set' => (bool) Setting::get('cf_ai_token'),
            'brand_name' => (string) Setting::get('brand_name'),
            'brand_primary' => Brand::primary(),
            'brand_secondary' => Brand::secondary(),
            'brand_colors_custom' => Brand::hasCustomColors(),
            'brand_assets' => array_combine(array_keys(Brand::ASSETS), array_map(fn ($a) => Brand::assetUrl($a), array_keys(Brand::ASSETS))),
            'allow_signup' => Setting::get('allow_signup') === '1',
            'require_2fa' => \App\Support\TwoFactor::required(),
        ]);
    }

    /** Cloudflare Workers AI credentials for translating mail in browsers without a built-in translator. */
    public function translate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cf_account_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/i'],
            'cf_ai_token' => ['nullable', 'string', 'max:200'],
        ]);
        Setting::put('cf_account_id', trim((string) ($data['cf_account_id'] ?? '')));
        if (filled($data['cf_ai_token'] ?? null)) {
            Setting::put('cf_ai_token', trim($data['cf_ai_token']));
        }

        return redirect()->route('admin.settings')->with('status', __('admin.trSaved'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'smtp_provider' => ['nullable', 'in:'.implode(',', array_keys(Outbox::PROVIDERS))],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'brevo_api_key' => ['nullable', 'string', 'max:500'],
            // The relay only sends from the authenticated domain: a Gmail or other outside sender is silently dropped.
            'system_from' => ['required', 'email', 'max:255', 'regex:/@'.preg_quote((string) config('inbound.domain'), '/').'$/i'],
        ], ['system_from.regex' => __('admin.fromMustBeDomain', ['domain' => config('inbound.domain')])]);

        Setting::put('smtp_provider', $data['smtp_provider'] ?? 'custom');
        foreach (['smtp_host', 'smtp_port', 'smtp_username', 'system_from'] as $key) {
            Setting::put($key, trim((string) $data[$key]));
        }
        // Blank secret fields keep the saved value.
        foreach (['smtp_password', 'brevo_api_key'] as $key) {
            if (filled($data[$key] ?? null)) {
                Setting::put($key, trim($data[$key]));
            }
        }

        return redirect()->route('admin.settings')->with('status', __('admin.settingsSaved'));
    }

    /** Settings → Security: self-service sign-up and mandatory 2FA. */
    public function security(Request $request): RedirectResponse
    {
        Setting::put('allow_signup', $request->boolean('allow_signup') ? '1' : '0');
        \App\Support\TwoFactor::setRequired($request->boolean('require_2fa'));

        return redirect()->route('admin.settings', ['#security'])->with('status', __('admin.securitySaved'));
    }

    /** Settings → General: name, logo, icon, favicon, sign-in background and colours. */
    public function brand(Request $request): RedirectResponse
    {
        $rules = [
            'brand_name' => ['nullable', 'string', 'max:60'],
            'brand_primary' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'brand_secondary' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
        ];
        foreach (Brand::ASSETS as $asset => $spec) {
            $rules[$asset] = ['nullable', 'file', 'mimes:'.$spec['mimes'], 'max:'.$spec['max']];
        }
        $data = $request->validate($rules);

        Setting::put('brand_name', trim((string) ($data['brand_name'] ?? '')));

        if ($request->boolean('reset_colors')) {
            Setting::put('brand_primary', '');
            Setting::put('brand_secondary', '');
        } else {
            // The pickers always post a value; the defaults mean "not customised".
            $primary = strtolower((string) ($data['brand_primary'] ?? ''));
            $secondary = strtolower((string) ($data['brand_secondary'] ?? ''));
            $custom = $primary !== Brand::DEFAULT_PRIMARY || $secondary !== Brand::DEFAULT_SECONDARY;
            Setting::put('brand_primary', $custom ? $primary : '');
            Setting::put('brand_secondary', $custom ? $secondary : '');
        }

        foreach (array_keys(Brand::ASSETS) as $asset) {
            $old = (string) Setting::get('brand_'.$asset);
            if ($request->boolean('remove_'.$asset)) {
                Setting::put('brand_'.$asset, '');
                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            } elseif ($request->hasFile($asset)) {
                $file = $request->file($asset);
                $path = $file->storeAs('branding', $asset.'-'.time().'.'.strtolower($file->getClientOriginalExtension()), 'local');
                Setting::put('brand_'.$asset, $path);
                if ($old && $old !== $path) {
                    Storage::disk('local')->delete($old);
                }
            }
        }
        Brand::forget();

        return redirect()->route('admin.settings', ['#general'])->with('status', __('admin.brandSaved'));
    }

    /** The uploaded brand files, public: they are on the sign-in page and in the browser tab. */
    public function asset(string $asset): StreamedResponse
    {
        abort_unless(isset(Brand::ASSETS[$asset]), 404);
        $path = Brand::assetPath($asset);
        abort_unless($path, 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function generateVapid(): RedirectResponse
    {
        Push::generateKeys();
        // Old subscriptions were made with the previous key and stop working.
        PushSubscription::query()->delete();

        return redirect()->route('admin.settings')->with('status', __('admin.vapidNew'));
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_to' => ['required', 'email']]);

        try {
            $from = Setting::get('system_from', 'hello@'.config('inbound.domain'));
            $email = (new Email)
                ->from($from)
                ->to($data['test_to'])
                ->subject(__('admin.testSubject'))
                ->text(__('admin.testBody')."\n\n".now()->toDateTimeString());
            (new Mailer(Outbox::transport()))->send($email);
        } catch (Throwable $e) {
            return redirect()->route('admin.settings')->with('error', __('admin.failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('admin.settings')->with('status', __('admin.testSent', ['email' => $data['test_to']]));
    }
}
