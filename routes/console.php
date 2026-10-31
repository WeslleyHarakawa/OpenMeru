<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\EnvFile;
use App\Support\MailDomain;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Where the scheduler runs (cron: * * * * * php artisan schedule:run), installs update at night
// even when nobody opens the site. Without cron, the web requests do the same during the day.
Schedule::command('openmeru:update --auto')->dailyAt('04:30')->withoutOverlapping();

// The password can be left out: it is then asked for without echo, so it never lands in the
// shell history or in the process list (a password given as an argument does).
Artisan::command('openmeru:admin {email} {name} {password?}', function (string $email, string $name, ?string $password = null) {
    if ($password === null || $password === '') {
        $password = (string) $this->secret('Password (min. 8 characters, not shown)');
        if ($password !== (string) $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return 1;
        }
    }
    if (mb_strlen($password) < 8) {
        $this->error('The password needs at least 8 characters.');

        return 1;
    }

    $user = User::updateOrCreate(
        ['email' => mb_strtolower($email)],
        ['name' => $name, 'password' => $password, 'is_admin' => true, 'is_active' => true]
    );
    $this->info('Admin pronto: '.$user->email);
})->purpose('Create or reset an admin mailbox');

// Pass "-" as the password to be asked for it without echo (keeps the key out of the shell history).
Artisan::command('openmeru:smtp {host} {port} {username} {password} {from} {apiKey?}', function () {
    $password = (string) $this->argument('password');
    if ($password === '-') {
        $password = (string) $this->secret('SMTP password / key (not shown)');
        if ($password === '') {
            $this->error('Empty password, nothing saved.');

            return 1;
        }
    }
    foreach (['host', 'port', 'username'] as $field) {
        Setting::put('smtp_'.$field, $this->argument($field));
    }
    Setting::put('smtp_password', $password);
    Setting::put('system_from', $this->argument('from'));
    if ($this->argument('apiKey')) {
        Setting::put('brevo_api_key', $this->argument('apiKey'));
    }
    $this->info('SMTP salvo.');
})->purpose('Save the SMTP relay settings');

// The cloud-hosting waitlist from the landing page, newest first (openmeru.com only).
Artisan::command('openmeru:waitlist', function () {
    $rows = \Illuminate\Support\Facades\DB::table('cloud_waitlist')->orderByDesc('created_at')->get();
    $this->table(['Email', 'Language', 'Since'], $rows->map(fn ($r) => [$r->email, $r->locale, $r->created_at])->all());
    $this->info($rows->count().' on the list.');
})->purpose('List the cloud-hosting waitlist');

// Switches the install to your own domain (MAIL_DOMAIN in .env).
Artisan::command('openmeru:domain {domain}', function (string $domain) {
    $domain = mb_strtolower(trim($domain));
    if (! MailDomain::valid($domain)) {
        $this->error('Invalid domain: '.$domain);

        return 1;
    }
    // Only .env changes: the texts swap openmeru.com for MAIL_DOMAIN when they load.
    (new EnvFile(base_path('.env')))->set(['MAIL_DOMAIN' => $domain]);
    $this->call('config:clear');
    $this->info('Domain set to '.$domain.'. Mailboxes are now user@'.$domain.'.');
})->purpose('Use your own mail domain');
