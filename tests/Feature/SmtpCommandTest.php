<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_the_settings_with_the_password_as_an_argument(): void
    {
        $this->artisan('openmeru:smtp', [
            'host' => 'smtp.example.test', 'port' => '587', 'username' => 'user@example.test',
            'password' => 'arg-secret', 'from' => 'info@example.test',
        ])->assertSuccessful();

        $this->assertSame('smtp.example.test', Setting::get('smtp_host'));
        $this->assertSame('587', Setting::get('smtp_port'));
        $this->assertSame('arg-secret', Setting::get('smtp_password'));
        $this->assertSame('info@example.test', Setting::get('system_from'));
    }

    public function test_a_dash_asks_for_the_password_without_echo(): void
    {
        $this->artisan('openmeru:smtp', [
            'host' => 'smtp.example.test', 'port' => '587', 'username' => 'user@example.test',
            'password' => '-', 'from' => 'info@example.test',
        ])
            ->expectsQuestion('SMTP password / key (not shown)', 'typed-secret')
            ->assertSuccessful();

        $this->assertSame('typed-secret', Setting::get('smtp_password'));
    }

    public function test_the_password_is_stored_encrypted(): void
    {
        $this->artisan('openmeru:smtp', [
            'host' => 'h', 'port' => '587', 'username' => 'u', 'password' => 'plain-secret', 'from' => 'a@example.test',
        ])->assertSuccessful();

        $this->assertNotSame('plain-secret', Setting::find('smtp_password')->value);
    }

    public function test_an_empty_typed_password_saves_nothing(): void
    {
        $this->artisan('openmeru:smtp', [
            'host' => 'h', 'port' => '587', 'username' => 'u', 'password' => '-', 'from' => 'a@example.test',
        ])
            ->expectsQuestion('SMTP password / key (not shown)', '')
            ->assertFailed();

        $this->assertNull(Setting::find('smtp_host'));
    }
}
