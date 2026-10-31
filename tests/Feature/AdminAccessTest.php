<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['email' => 'admin@example.test']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'bob@example.test']);
    }

    private function pending(User $admin, string $code = '123456'): array
    {
        return ['admin_otp' => [
            'user' => $admin->id,
            'hash' => Hash::make($code),
            'expires' => now()->addMinutes(10)->timestamp,
            'sent' => now()->timestamp,
            'attempts' => 0,
        ]];
    }

    public function test_a_signed_out_visitor_cannot_use_admin_actions(): void
    {
        $this->postJson('/admin/accounts', ['name' => 'x'])->assertUnauthorized();
        $this->deleteJson('/admin/accounts/1')->assertUnauthorized();
    }

    public function test_a_regular_mailbox_is_not_an_admin(): void
    {
        $bob = $this->member();
        $target = $this->member(['email' => 'carol@example.test']);

        $this->actingAs($bob)->get('/admin')->assertForbidden();
        $this->actingAs($bob)->postJson('/admin/accounts/'.$target->id.'/impersonate')->assertForbidden();
        $this->actingAs($bob)->deleteJson('/admin/accounts/'.$target->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_an_admin_password_alone_is_not_enough(): void
    {
        $admin = $this->admin();
        $target = $this->member();

        $this->actingAs($admin)->postJson('/admin/accounts', ['name' => 'x'])->assertForbidden();
        $this->actingAs($admin)->deleteJson('/admin/accounts/'.$target->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_a_verified_admin_can_use_the_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->get('/admin')->assertOk();
    }

    public function test_verification_does_not_carry_over_to_another_admin(): void
    {
        $first = $this->admin();
        $second = $this->admin(['email' => 'admin2@example.test']);

        $this->actingAs($second)->withSession(['admin_verified' => $first->id])
            ->postJson('/admin/accounts', ['name' => 'x'])->assertForbidden();
    }

    public function test_the_right_code_unlocks_the_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession($this->pending($admin))
            ->post('/admin/verify', ['code' => '123456'])
            ->assertRedirect('/admin');

        $this->assertSame($admin->id, session('admin_verified'));
    }

    public function test_a_wrong_code_does_not_unlock_the_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession($this->pending($admin))
            ->post('/admin/verify', ['code' => '000000'])
            ->assertRedirect('/admin')
            ->assertSessionHas('otp_error');

        $this->assertNull(session('admin_verified'));
    }

    public function test_an_expired_code_is_refused(): void
    {
        $admin = $this->admin();
        $pending = $this->pending($admin);
        $pending['admin_otp']['expires'] = now()->subMinute()->timestamp;

        $this->actingAs($admin)->withSession($pending)
            ->post('/admin/verify', ['code' => '123456'])
            ->assertSessionHas('otp_error');

        $this->assertNull(session('admin_verified'));
    }

    public function test_a_code_issued_to_another_admin_is_refused(): void
    {
        $first = $this->admin();
        $second = $this->admin(['email' => 'admin2@example.test']);

        $this->actingAs($second)->withSession($this->pending($first))
            ->post('/admin/verify', ['code' => '123456'])
            ->assertSessionHas('otp_error');

        $this->assertNull(session('admin_verified'));
    }

    public function test_a_regular_mailbox_cannot_submit_a_code(): void
    {
        $bob = $this->member();

        $this->actingAs($bob)->post('/admin/verify', ['code' => '123456'])->assertForbidden();
    }

    public function test_guessing_is_capped_and_survives_a_new_session(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin)->withSession($this->pending($admin))
                ->post('/admin/verify', ['code' => '00000'.$i]);
        }

        // A brand-new session with a fresh code, as after signing in again: the right code still fails.
        $this->flushSession();
        $this->actingAs($admin)->withSession($this->pending($admin))
            ->post('/admin/verify', ['code' => '123456'])
            ->assertSessionHas('otp_error');

        $this->assertNull(session('admin_verified'));
    }

    public function test_a_correct_code_resets_the_guess_counter(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin)->withSession($this->pending($admin))
                ->post('/admin/verify', ['code' => '00000'.$i]);
        }
        $this->actingAs($admin)->withSession($this->pending($admin))
            ->post('/admin/verify', ['code' => '123456']);
        $this->assertSame($admin->id, session('admin_verified'));

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin)->withSession($this->pending($admin))
                ->post('/admin/verify', ['code' => '00000'.$i]);
        }
        $this->actingAs($admin)->withSession($this->pending($admin))
            ->post('/admin/verify', ['code' => '123456']);
        $this->assertSame($admin->id, session('admin_verified'));
    }

    public function test_impersonation_opens_the_mailbox_and_drops_admin_verification(): void
    {
        $admin = $this->admin();
        $bob = $this->member();

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->post('/admin/accounts/'.$bob->id.'/impersonate')
            ->assertRedirect();

        $this->assertAuthenticatedAs($bob);
        $this->assertSame($admin->id, session('impersonator'));
        $this->assertNull(session('admin_verified'));
    }

    public function test_impersonation_refuses_self_disabled_and_demo_targets(): void
    {
        $admin = $this->admin();
        $off = $this->member(['is_active' => false]);
        $demo = $this->member(['email' => 'demo@example.test']);
        $demo->forceFill(['is_demo' => true])->save();
        $session = ['admin_verified' => $admin->id];

        $this->actingAs($admin)->withSession($session)
            ->post('/admin/accounts/'.$admin->id.'/impersonate')->assertStatus(422);
        $this->actingAs($admin)->withSession($session)
            ->post('/admin/accounts/'.$off->id.'/impersonate')->assertStatus(422);
        $this->actingAs($admin)->withSession($session)
            ->post('/admin/accounts/'.$demo->id.'/impersonate')->assertNotFound();
    }

    public function test_stop_impersonating_without_an_impersonator_does_nothing_special(): void
    {
        $bob = $this->member();

        $this->actingAs($bob)->post('/impersonate/stop')->assertRedirect(route('login'));
        $this->assertAuthenticatedAs($bob);
    }

    public function test_stop_impersonating_cannot_be_aimed_at_a_non_admin(): void
    {
        $bob = $this->member();
        $carol = $this->member(['email' => 'carol@example.test']);

        $this->actingAs($carol)->withSession(['impersonator' => $bob->id])
            ->post('/impersonate/stop')->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($carol);
    }

    public function test_stop_impersonating_returns_to_the_verified_admin(): void
    {
        $admin = $this->admin();
        $bob = $this->member();

        $this->actingAs($bob)->withSession(['impersonator' => $admin->id])
            ->post('/impersonate/stop')->assertRedirect(route('admin.accounts'));

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($admin->id, session('admin_verified'));
    }

    public function test_an_admin_cannot_delete_or_disable_themselves(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];

        $this->actingAs($admin)->withSession($session)
            ->delete('/admin/accounts/'.$admin->id)->assertStatus(422);

        $this->actingAs($admin)->withSession($session)
            ->put('/admin/accounts/'.$admin->id, ['name' => 'Me', 'is_admin' => 0, 'is_active' => 0]);
        $admin->refresh();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->is_active);
    }

    public function test_disabling_a_mailbox_signs_it_out(): void
    {
        $admin = $this->admin();
        $bob = $this->member();
        DB::table('sessions')->insert([
            'id' => 's1', 'user_id' => $bob->id, 'ip_address' => '203.0.113.1',
            'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->put('/admin/accounts/'.$bob->id, ['name' => 'Bob', 'is_active' => 0]);

        $this->assertFalse($bob->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $bob->id)->count());
    }

    public function test_the_system_sender_must_be_on_the_mail_domain(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];
        $base = ['smtp_host' => 'smtp-relay.brevo.com', 'smtp_port' => 587, 'smtp_username' => 'login@smtp-brevo.com'];

        $this->actingAs($admin)->withSession($session)->from('/admin/settings')
            ->put('/admin/settings', $base + ['system_from' => 'someone@gmail.com'])
            ->assertSessionHasErrors('system_from');
        $this->assertNull(\App\Models\Setting::get('system_from'));

        $this->actingAs($admin)->withSession($session)
            ->put('/admin/settings', $base + ['system_from' => 'info@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame('info@example.test', \App\Models\Setting::get('system_from'));
    }
}
