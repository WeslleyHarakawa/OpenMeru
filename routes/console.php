<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('openmeru:admin {email} {name} {password}', function (string $email, string $name, string $password) {
    $user = User::updateOrCreate(
        ['email' => mb_strtolower($email)],
        ['name' => $name, 'password' => $password, 'is_admin' => true, 'is_active' => true]
    );
    $this->info('Admin pronto: '.$user->email);
})->purpose('Create or reset an admin mailbox');

Artisan::command('openmeru:smtp {host} {port} {username} {password} {from} {apiKey?}', function () {
    foreach (['host', 'port', 'username', 'password'] as $field) {
        Setting::put('smtp_'.$field, $this->argument($field));
    }
    Setting::put('system_from', $this->argument('from'));
    if ($this->argument('apiKey')) {
        Setting::put('brevo_api_key', $this->argument('apiKey'));
    }
    $this->info('SMTP salvo.');
})->purpose('Save the SMTP relay settings');

// Switches the install to your own domain: sets MAIL_DOMAIN in .env and
// rewrites the domain shown in the translation files.
Artisan::command('openmeru:domain {domain}', function (string $domain) {
    $domain = mb_strtolower(trim($domain));
    if (! preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $domain)) {
        $this->error('Invalid domain: '.$domain);

        return 1;
    }
    // The texts ship with openmeru.com; after a first change they carry MAIL_DOMAIN.
    $current = str_contains((string) file_get_contents(lang_path('en.json')), '@openmeru.com')
        ? 'openmeru.com'
        : (string) config('inbound.domain');

    $env = base_path('.env');
    $text = (string) file_get_contents($env);
    $text = preg_match('/^MAIL_DOMAIN=.*$/m', $text)
        ? preg_replace('/^MAIL_DOMAIN=.*$/m', 'MAIL_DOMAIN='.$domain, $text)
        : rtrim($text)."\nMAIL_DOMAIN=".$domain."\n";
    file_put_contents($env, $text);

    foreach (glob(lang_path('*.json')) as $file) {
        file_put_contents($file, str_replace($current, $domain, (string) file_get_contents($file)));
    }
    $this->call('config:clear');
    $this->info('Domain set to '.$domain.'. Mailboxes are now user@'.$domain.'.');
})->purpose('Use your own mail domain');
