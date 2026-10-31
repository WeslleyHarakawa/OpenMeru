<?php

namespace App\Console\Commands;

use App\Mail\Outbox;
use App\Models\Setting;
use App\Models\User;
use App\Support\EnvFile;
use App\Support\MailDomain;
use App\Support\Push;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MissingAnswer extends RuntimeException {}

/**
 * One command that takes a fresh checkout to a working install: .env, keys,
 * database, domain, first admin, SMTP relay, caches. Safe to run again: it
 * keeps what is already set and only asks for what is missing.
 */
class InstallCommand extends Command
{
    protected $signature = 'openmeru:install
        {--domain= : Mail domain, mailboxes are user@domain}
        {--url= : Public URL of this install, e.g. https://mail.example.com}
        {--otp-email= : Outside inbox (your Gmail) that receives the admin sign-in codes}
        {--db-connection= : mysql (default) or sqlite}
        {--db-host= : Database host (default 127.0.0.1)}
        {--db-port= : Database port (default 3306)}
        {--db-name= : Database name}
        {--db-user= : Database user}
        {--admin-email= : First admin mailbox, e.g. admin@domain}
        {--admin-name= : Display name of the first admin}
        {--skip-admin : Do not create or reset an admin}
        {--skip-smtp : Do not ask for the Brevo SMTP relay}
        {--env-file= : Path of the .env to write (default: the app\'s .env)}';

    protected $description = 'Interactive installer: .env, database, domain, admin, SMTP and caches';

    private EnvFile $env;

    public function handle(): int
    {
        $this->env = new EnvFile($this->option('env-file') ?: base_path('.env'));
        $this->line('');
        $this->components->info('OpenMeru Mail installer');

        $this->prepareEnvFile();
        try {
            $this->askIdentity();
        } catch (MissingAnswer $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
        if (! $this->configureDatabase()) {
            return self::FAILURE;
        }
        $this->reloadConfig();
        $this->migrate();
        $this->pushKeys();
        if (! $this->option('skip-admin')) {
            $this->admin();
        }
        if (! $this->option('skip-smtp')) {
            $this->smtp();
        }
        $this->caches();
        $this->summary();

        return self::SUCCESS;
    }

    private function prepareEnvFile(): void
    {
        if (! $this->env->exists()) {
            copy(base_path('.env.example'), $this->env->path());
            $this->components->task('.env created from .env.example', fn () => true);
        }
        if (! $this->env->get('APP_KEY')) {
            $this->env->set(['APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')))]);
            $this->components->task('APP_KEY generated', fn () => true);
        }
        // The app may have booted before .env existed: the encrypter (push keys, SMTP
        // secrets) must use the key that is now in the file.
        config(['app.key' => $this->env->get('APP_KEY')]);
        $this->laravel->forgetInstance('encrypter');
        if (! $this->env->get('INBOUND_SECRET')) {
            $this->env->set(['INBOUND_SECRET' => bin2hex(random_bytes(24))]);
            $this->components->task('INBOUND_SECRET generated', fn () => true);
        }
        $this->env->set(['APP_ENV' => 'production', 'APP_DEBUG' => 'false']);
    }

    private function askIdentity(): void
    {
        $domain = mb_strtolower($this->value('domain', 'MAIL_DOMAIN', 'Mail domain (mailboxes will be user@domain)', fn ($v) => $v !== 'yourdomain.com' ? $v : null));
        while (! MailDomain::valid($domain)) {
            $this->components->error('Invalid domain: '.$domain);
            $domain = $this->mustAsk('Mail domain (mailboxes will be user@domain)');
        }

        $url = $this->value('url', 'APP_URL', 'Public URL of this install', fn ($v) => $v !== 'https://yourdomain.com' ? $v : null, 'https://'.$domain);
        $url = rtrim($url, '/');
        if (! preg_match('#^https?://#', $url)) {
            $url = 'https://'.$url;
        }

        $otp = $this->value('otp-email', 'ADMIN_OTP_EMAIL', 'Gmail that receives the admin sign-in codes', fn ($v) => $v !== 'you@gmail.com' ? $v : null);
        while (! filter_var($otp, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Invalid email: '.$otp);
            $otp = $this->mustAsk('Gmail that receives the admin sign-in codes');
        }

        $this->env->set([
            'MAIL_DOMAIN' => mb_strtolower($domain),
            'APP_URL' => $url,
            'ADMIN_OTP_EMAIL' => mb_strtolower($otp),
            'MAIL_FROM_ADDRESS' => 'hello@'.mb_strtolower($domain),
        ]);
        $this->components->twoColumnDetail('Domain', $domain);
        $this->components->twoColumnDetail('URL', $url);
        $this->components->twoColumnDetail('Admin codes go to', $otp);
    }

    private function configureDatabase(): bool
    {
        // Already reachable (a second run, or a pre-filled .env): nothing to ask.
        if (! $this->option('db-connection') && ! $this->option('db-name') && $this->databaseWorks()) {
            $this->components->task('Database connection', fn () => true);

            return true;
        }

        $connection = $this->option('db-connection') ?: ($this->input->isInteractive()
            ? $this->choice('Database', ['mysql', 'sqlite'], 0)
            : 'mysql');

        if ($connection === 'sqlite') {
            $path = database_path('database.sqlite');
            if (! is_file($path)) {
                touch($path);
            }
            $this->env->set(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path]);
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
        } else {
            $values = [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $this->option('db-host') ?: ($this->env->get('DB_HOST') ?: '127.0.0.1'),
                'DB_PORT' => $this->option('db-port') ?: ($this->env->get('DB_PORT') ?: '3306'),
                'DB_DATABASE' => $this->value('db-name', 'DB_DATABASE', 'Database name', fn ($v) => $v !== 'openmeru' || ! $this->input->isInteractive() ? $v : null, 'openmeru'),
                'DB_USERNAME' => $this->value('db-user', 'DB_USERNAME', 'Database user', fn ($v) => $v !== 'openmeru' || ! $this->input->isInteractive() ? $v : null, 'openmeru'),
            ];
            // The password is only ever typed, never passed on the command line.
            $password = $this->env->get('DB_PASSWORD');
            if ($this->input->isInteractive() && ($password === null || $password === '' || $this->confirm('Replace the saved database password?', false))) {
                $password = (string) $this->secret('Database password (not shown)');
            }
            $values['DB_PASSWORD'] = (string) $password;
            $this->env->set($values);
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $values['DB_HOST'],
                'database.connections.mysql.port' => $values['DB_PORT'],
                'database.connections.mysql.database' => $values['DB_DATABASE'],
                'database.connections.mysql.username' => $values['DB_USERNAME'],
                'database.connections.mysql.password' => $values['DB_PASSWORD'],
            ]);
        }

        DB::purge();
        if (! $this->databaseWorks($error)) {
            $this->components->error('Cannot connect to the database: '.$error);
            $this->line('  Fix the credentials (CloudPanel: Databases) and run this command again.');

            return false;
        }
        $this->components->task('Database connection', fn () => true);

        return true;
    }

    private function databaseWorks(?string &$error = null): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable $e) {
            $error = $e->getMessage();

            return false;
        }
    }

    private function reloadConfig(): void
    {
        // Values written to .env above must be live for the rest of this run.
        config([
            'inbound.domain' => $this->env->get('MAIL_DOMAIN'),
            'inbound.secret' => $this->env->get('INBOUND_SECRET'),
            'inbound.otp_email' => $this->env->get('ADMIN_OTP_EMAIL'),
            'app.url' => $this->env->get('APP_URL'),
        ]);
    }

    private function migrate(): void
    {
        $this->callSilently('migrate', ['--force' => true]);
        $this->components->task('Database tables', fn () => true);
    }

    private function pushKeys(): void
    {
        if (! Push::configured()) {
            Push::generateKeys();
            $this->components->task('Push notification keys', fn () => true);
        }
    }

    private function admin(): void
    {
        $domain = $this->env->get('MAIL_DOMAIN');
        $existing = User::where('is_admin', true)->where('is_demo', false)->pluck('email');
        $email = $this->option('admin-email');

        if (! $email) {
            if ($existing->isNotEmpty() && (! $this->input->isInteractive() || ! $this->confirm('Admin exists ('.$existing->implode(', ').'). Create or reset another?', false))) {
                return;
            }
            if (! $this->input->isInteractive()) {
                return;
            }
            $email = $this->ask('Admin mailbox', 'admin@'.$domain);
        }
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! str_ends_with($email, '@'.$domain)) {
            $this->components->error('The admin must be a mailbox on '.$domain.' (got '.$email.'). Skipped.');

            return;
        }
        $name = $this->option('admin-name') ?: ($this->input->isInteractive() ? $this->ask('Admin name', 'Administrator') : 'Administrator');

        $password = null;
        if ($this->input->isInteractive()) {
            do {
                $password = (string) $this->secret('Admin password (min. 8 characters, not shown)');
                $again = (string) $this->secret('Confirm password');
                if ($password !== $again) {
                    $this->components->error('The passwords do not match.');
                } elseif (mb_strlen($password) < 8) {
                    $this->components->error('At least 8 characters.');
                } else {
                    break;
                }
            } while (true);
        } else {
            // No terminal: a random password, shown once.
            $password = Str::password(16, symbols: false);
            $this->components->warn('Generated admin password (shown once): '.$password);
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'is_admin' => true, 'is_active' => true]
        );
        $this->components->task('Admin '.$email, fn () => true);
    }

    private function smtp(): void
    {
        if (Outbox::configured()) {
            $this->components->task('SMTP relay (already saved)', fn () => true);

            return;
        }
        if (! $this->input->isInteractive() || ! $this->confirm('Set up the Brevo SMTP relay now? (you can do it later in /admin → Settings)', true)) {
            return;
        }
        $host = $this->ask('SMTP host', 'smtp-relay.brevo.com');
        $port = $this->ask('SMTP port', '587');
        $username = $this->ask('SMTP login (from Brevo → SMTP & API)');
        $key = (string) $this->secret('SMTP key (not shown)');
        $from = $this->ask('System sender address', 'hello@'.$this->env->get('MAIL_DOMAIN'));
        if (! $username || ! $key) {
            $this->components->warn('SMTP skipped: login or key empty.');

            return;
        }
        foreach (['host' => $host, 'port' => $port, 'username' => $username, 'password' => $key] as $k => $v) {
            Setting::put('smtp_'.$k, trim((string) $v));
        }
        Setting::put('system_from', trim($from));
        $this->components->task('SMTP relay saved', fn () => true);
    }

    private function caches(): void
    {
        if ($this->laravel->environment('testing')) {
            return;
        }
        $this->callSilently('config:cache');
        $this->callSilently('route:cache');
        $this->callSilently('view:cache');
        $this->components->task('Caches', fn () => true);
    }

    private function summary(): void
    {
        $url = $this->env->get('APP_URL');
        $this->line('');
        $this->components->info('Done. What is left is outside this server:');
        $this->line('  1. Web root: point your site\'s document root at');
        $this->line('       '.base_path('public_root'));
        $this->line('     (nginx needs: try_files $uri $uri/ /index.php?$query_string;) and HTTPS.');
        $this->line('  2. Cloudflare → Workers: create "openmeru-mail-inbound" with cloudflare-email-worker.js and set');
        $this->line('       GMAIL          = '.$this->env->get('ADMIN_OTP_EMAIL').' (a verified destination address)');
        $this->line('       INBOUND_URL    = '.$url.'/hooks/inbound');
        $this->line('       INBOUND_SECRET = (secret) '.$this->env->get('INBOUND_SECRET'));
        $this->line('  3. Cloudflare → Email Routing → Routing rules: send each mailbox (or the catch-all) to that Worker.');
        $this->line('  4. Brevo: domain authenticated, SPF includes spf.brevo.com, SMTP key saved in /admin → Settings.');
        $this->line('');
        $this->line('  Sign in: '.$url.'/admin');
        $this->line('  Run this command again at any time; it keeps what is already set.');
        $this->line('');
    }

    /** Option, else current .env value (when the filter accepts it), else a question. */
    private function value(string $option, string $envKey, string $question, callable $acceptCurrent, ?string $default = null): string
    {
        if ($v = $this->option($option)) {
            return trim($v);
        }
        $current = $this->env->get($envKey);
        if ($current !== null && $current !== '' && $acceptCurrent($current) !== null) {
            return $current;
        }
        if (! $this->input->isInteractive()) {
            if ($default !== null) {
                return $default;
            }
            throw new MissingAnswer('Missing --'.$option.' (no terminal to ask).');
        }

        return $this->mustAsk($question, $default);
    }

    private function mustAsk(string $question, ?string $default = null): string
    {
        do {
            $answer = trim((string) $this->ask($question, $default));
        } while ($answer === '');

        return $answer;
    }
}
