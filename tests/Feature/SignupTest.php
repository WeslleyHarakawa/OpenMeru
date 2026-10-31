<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_sign_up_is_closed_until_the_admin_opens_it(): void
    {
        $this->get('/signup')->assertNotFound();
        $this->postJson('/signup', ['name' => 'Ann', 'local' => 'ann', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.url('/signup').'"', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_opens_sign_up_from_security_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->put('/admin/settings/security', ['allow_signup' => '1'])->assertRedirect();

        $this->assertSame('1', Setting::get('allow_signup'));
        auth()->logout();
        $this->get('/signup')->assertOk()->assertSee('@example.test');
        $this->get('/')->assertOk()->assertSee('href="'.url('/signup').'"', false);
    }

    public function test_a_person_creates_a_mailbox_and_is_signed_in(): void
    {
        Setting::put('allow_signup', '1');

        $this->postJson('/signup', ['name' => 'Ann Lee', 'local' => 'Ann.Lee', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'recovery_email' => 'ann@gmail.test'])
            ->assertOk()->assertJsonPath('email', 'ann.lee@example.test');

        $user = User::where('email', 'ann.lee@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertSame('ann@gmail.test', $user->recovery_email);
    }

    public function test_taken_and_bad_addresses_are_refused(): void
    {
        Setting::put('allow_signup', '1');
        User::factory()->create(['email' => 'ann@example.test']);

        $this->postJson('/signup', ['name' => 'Ann', 'local' => 'ann', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])->assertStatus(422)->assertJsonPath('message', 'taken');
        $this->postJson('/signup', ['name' => 'Ann', 'local' => 'ann lee', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])->assertStatus(422);
        $this->postJson('/signup', ['name' => 'Ann', 'local' => 'ann2', 'password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422);
        $this->postJson('/signup', ['name' => 'Ann', 'local' => 'ann3', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'recovery_email' => 'other@example.test'])->assertStatus(422);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_sending_settings_keep_the_chosen_provider(): void
    {
        $admin = $this->admin();
        $base = ['smtp_host' => 'mail.smtp2go.com', 'smtp_port' => 587, 'smtp_username' => 'user', 'system_from' => 'info@example.test'];

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->put('/admin/settings', $base + ['smtp_provider' => 'smtp2go'])->assertSessionHasNoErrors();
        $this->assertSame('smtp2go', \App\Mail\Outbox::provider());

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->from('/admin/settings')
            ->put('/admin/settings', $base + ['smtp_provider' => 'bogus'])->assertSessionHasErrors('smtp_provider');

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->get('/admin/settings')->assertOk()
            ->assertSee('name="smtp_provider"', false)->assertSee('SMTP2GO')->assertSee('Postmark');
    }
}
