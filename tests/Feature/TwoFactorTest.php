<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTwoFactor;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'bob@example.test', 'password' => 'correct horse']);
    }

    private function code(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    /** Enables 2FA for the user the way the UI does, returning the recovery codes. */
    private function enable(User $user): array
    {
        $this->actingAs($user)->postJson('/api/account/2fa/enable')->assertOk()->assertJsonStructure(['secret', 'qr']);
        $secret = (string) $user->fresh()->two_factor_secret;
        $codes = $this->actingAs($user)->postJson('/api/account/2fa/confirm', ['code' => $this->code($secret)])
            ->assertOk()->json('recovery_codes');
        $this->assertTrue(TwoFactor::enabled($user->fresh()));

        return $codes;
    }

    public function test_setup_needs_a_valid_code_before_anything_turns_on(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/api/account/2fa/enable')->assertOk()->assertSee('<svg', false);
        $this->actingAs($user)->postJson('/api/account/2fa/confirm', ['code' => '000000'])->assertStatus(422);
        $this->assertFalse(TwoFactor::enabled($user->fresh()));

        $codes = $this->enable($user);
        $this->assertCount(TwoFactor::RECOVERY_CODES, $codes);
    }

    public function test_sign_in_asks_for_the_code_and_blocks_everything_until_then(): void
    {
        $user = $this->user();
        $this->enable($user);
        $this->post('/logout');

        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct horse'])
            ->assertOk()->assertJsonPath('redirect', url('/two-factor'));
        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/messages')->assertForbidden()->assertJsonPath('message', 'two_factor');
        $this->get('/settings')->assertRedirect('/two-factor');
        $this->get('/two-factor')->assertOk();

        $this->postJson('/two-factor', ['code' => '000000'])->assertStatus(422);
        $this->postJson('/two-factor', ['code' => $this->code((string) $user->fresh()->two_factor_secret)])->assertOk();
        $this->getJson('/api/messages')->assertOk();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->user();
        $codes = $this->enable($user);
        $this->post('/logout');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct horse']);

        $this->postJson('/two-factor', ['code' => $codes[0]])->assertOk();
        $this->assertCount(TwoFactor::RECOVERY_CODES - 1, $user->fresh()->two_factor_recovery_codes);

        session()->forget(EnsureTwoFactor::SESSION_KEY);
        $this->postJson('/two-factor', ['code' => $codes[0]])->assertStatus(422);
    }

    public function test_turning_off_needs_a_code(): void
    {
        $user = $this->user();
        $this->enable($user);

        $this->actingAs($user)->postJson('/api/account/2fa/disable', ['code' => '000000'])->assertStatus(422);
        $this->assertTrue(TwoFactor::enabled($user->fresh()));

        $this->actingAs($user)->postJson('/api/account/2fa/disable', ['code' => $this->code((string) $user->fresh()->two_factor_secret)])->assertOk();
        $this->assertFalse(TwoFactor::enabled($user->fresh()));
    }

    public function test_when_the_admin_requires_it_every_account_must_set_it_up(): void
    {
        TwoFactor::setRequired(true);
        $user = $this->user();

        $this->actingAs($user)->getJson('/api/messages')->assertForbidden()->assertJsonPath('message', 'two_factor_setup');
        $this->actingAs($user)->get('/settings')->assertOk()->assertSee('twoFactorRequiredNotice', false);
        // The setup calls stay open.
        $this->actingAs($user)->postJson('/api/account/2fa/enable')->assertOk();

        $this->enable($user);
        $this->actingAs($user)->getJson('/api/messages')->assertOk();
    }

    public function test_admin_can_reset_a_mailbox_two_factor(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->forceFill(['is_admin' => true])->save();
        $user = $this->user();
        $this->enable($user);

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->post('/admin/accounts/'.$user->id.'/two-factor/reset')->assertRedirect();

        $this->assertFalse(TwoFactor::enabled($user->fresh()));
    }

    public function test_secrets_never_leave_the_server(): void
    {
        $user = $this->user();
        $this->enable($user);

        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonMissing(['two_factor_secret'])->assertJsonMissing(['two_factor_recovery_codes']);
    }
}
