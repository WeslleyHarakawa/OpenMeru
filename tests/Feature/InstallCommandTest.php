<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\EnvFile;
use App\Support\Push;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The suite's database is an in-memory SQLite that is already reachable, so
 * the installer skips the database questions; the .env it writes is a temp file.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envPath = tempnam(sys_get_temp_dir(), 'omenv');
        unlink($this->envPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);
        parent::tearDown();
    }

    private function env(): EnvFile
    {
        return new EnvFile($this->envPath);
    }

    public function test_non_interactive_install_writes_env_keys_and_admin(): void
    {
        $this->artisan('openmeru:install', [
            '--domain' => 'Example.Test',
            '--url' => 'mail.example.test/',
            '--otp-email' => 'Owner@gmail.test',
            '--admin-email' => 'admin@example.test',
            '--admin-name' => 'Owner',
            '--skip-smtp' => true,
            '--env-file' => $this->envPath,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $env = $this->env();
        $this->assertSame('example.test', $env->get('MAIL_DOMAIN'));
        $this->assertSame('https://mail.example.test', $env->get('APP_URL'));
        $this->assertSame('owner@gmail.test', $env->get('ADMIN_OTP_EMAIL'));
        $this->assertSame('production', $env->get('APP_ENV'));
        $this->assertSame('false', $env->get('APP_DEBUG'));
        $this->assertStringStartsWith('base64:', $env->get('APP_KEY'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $env->get('INBOUND_SECRET'));

        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Push::configured());
    }

    public function test_running_again_keeps_existing_key_and_secret(): void
    {
        $args = [
            '--domain' => 'example.test', '--otp-email' => 'o@gmail.test',
            '--skip-admin' => true, '--skip-smtp' => true,
            '--env-file' => $this->envPath, '--no-interaction' => true,
        ];
        $this->artisan('openmeru:install', $args)->assertSuccessful();
        $key = $this->env()->get('APP_KEY');
        $secret = $this->env()->get('INBOUND_SECRET');

        $this->artisan('openmeru:install', $args)->assertSuccessful();

        $this->assertSame($key, $this->env()->get('APP_KEY'));
        $this->assertSame($secret, $this->env()->get('INBOUND_SECRET'));
    }

    public function test_interactive_install_asks_for_the_admin_password_without_echo(): void
    {
        $this->artisan('openmeru:install', [
            '--domain' => 'example.test', '--url' => 'https://mail.example.test',
            '--otp-email' => 'o@gmail.test', '--admin-email' => 'info@example.test', '--admin-name' => 'Info',
            '--skip-smtp' => true, '--env-file' => $this->envPath,
        ])
            ->expectsQuestion('Admin password (min. 8 characters, not shown)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->expectsQuestion('Admin password (min. 8 characters, not shown)', 'a-long-password')
            ->expectsQuestion('Confirm password', 'a-long-password')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('a-long-password', User::where('email', 'info@example.test')->firstOrFail()->password));
    }

    public function test_interactive_install_can_save_the_smtp_relay(): void
    {
        $this->artisan('openmeru:install', [
            '--domain' => 'example.test', '--url' => 'https://mail.example.test',
            '--otp-email' => 'o@gmail.test', '--skip-admin' => true, '--env-file' => $this->envPath,
        ])
            ->expectsConfirmation('Set up the Brevo SMTP relay now? (you can do it later in /admin → Settings)', 'yes')
            ->expectsQuestion('SMTP host', 'smtp-relay.brevo.com')
            ->expectsQuestion('SMTP port', '587')
            ->expectsQuestion('SMTP login (from Brevo → SMTP & API)', 'login@smtp-brevo.com')
            ->expectsQuestion('SMTP key (not shown)', 'xsmtpsib-key')
            ->expectsQuestion('System sender address', 'info@example.test')
            ->assertSuccessful();

        $this->assertSame('smtp-relay.brevo.com', Setting::get('smtp_host'));
        $this->assertSame('xsmtpsib-key', Setting::get('smtp_password'));
        $this->assertSame('info@example.test', Setting::get('system_from'));
    }

    public function test_refuses_an_admin_outside_the_mail_domain(): void
    {
        $this->artisan('openmeru:install', [
            '--domain' => 'example.test', '--otp-email' => 'o@gmail.test',
            '--admin-email' => 'admin@other.test', '--admin-name' => 'X',
            '--skip-smtp' => true, '--env-file' => $this->envPath, '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_fails_without_a_domain_when_it_cannot_ask(): void
    {
        $this->artisan('openmeru:install', [
            '--otp-email' => 'o@gmail.test', '--skip-admin' => true, '--skip-smtp' => true,
            '--env-file' => $this->envPath, '--no-interaction' => true,
        ])->assertFailed();
    }
}
