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
                'brand_logo' => null,
            ]);
        }

        return view('admin.settings', [
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
            'brand_logo' => Brand::logoPath(),
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
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'brevo_api_key' => ['nullable', 'string', 'max:500'],
            // The relay only sends from the authenticated domain: a Gmail or other outside sender is silently dropped.
            'system_from' => ['required', 'email', 'max:255', 'regex:/@'.preg_quote((string) config('inbound.domain'), '/').'$/i'],
        ], ['system_from.regex' => __('admin.fromMustBeDomain', ['domain' => config('inbound.domain')])]);

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

    /** Settings → General: the name and logo this install shows instead of OpenMeru Mail. */
    public function brand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:60'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
        ]);
        Setting::put('brand_name', trim((string) ($data['brand_name'] ?? '')));
        if ($request->hasFile('logo')) {
            $old = Setting::get('brand_logo');
            $path = $request->file('logo')->storeAs('branding', 'logo-'.time().'.'.strtolower($request->file('logo')->getClientOriginalExtension()), 'local');
            Setting::put('brand_logo', $path);
            if ($old && $old !== $path) {
                Storage::disk('local')->delete($old);
            }
        }
        Brand::forget();

        return redirect()->route('admin.settings', ['#general'])->with('status', __('admin.brandSaved'));
    }

    public function removeLogo(): RedirectResponse
    {
        if ($old = Setting::get('brand_logo')) {
            Storage::disk('local')->delete($old);
        }
        Setting::put('brand_logo', '');
        Brand::forget();

        return redirect()->route('admin.settings', ['#general'])->with('status', __('admin.brandLogoRemoved'));
    }

    /** The uploaded logo, public: it is on the sign-in page. */
    public function logo(): StreamedResponse
    {
        $path = Brand::logoPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

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
