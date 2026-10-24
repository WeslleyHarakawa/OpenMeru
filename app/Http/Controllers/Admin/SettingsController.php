<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Outbox;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Support\Push;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Throwable;

class SettingsController extends Controller
{
    public function show(): View
    {
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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'brevo_api_key' => ['nullable', 'string', 'max:500'],
            'system_from' => ['required', 'email', 'max:255'],
        ]);

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
