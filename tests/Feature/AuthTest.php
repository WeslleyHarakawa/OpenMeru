<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'email' => 'bob@example.test',
            'password' => 'correct horse',
        ]);
    }

    private function login(string $password, string $email = 'bob@example.test')
    {
        return $this->postJson('/login', ['email' => $email, 'password' => $password]);
    }

    public function test_valid_credentials_sign_in(): void
    {
        $user = $this->user();

        $this->login('correct horse')->assertOk()->assertJsonStructure(['redirect']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_email_is_case_insensitive(): void
    {
        $this->user();

        $this->login('correct horse', '  BOB@Example.test ')->assertOk();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user();

        $this->login('wrong')->assertStatus(422);
        $this->assertGuest();
    }

    public function test_disabled_mailbox_cannot_sign_in(): void
    {
        $this->user(['is_active' => false]);

        $this->login('correct horse')->assertStatus(422);
        $this->assertGuest();
    }

    public function test_demo_mailbox_cannot_sign_in_with_a_password(): void
    {
        $user = $this->user();
        $user->forceFill(['is_demo' => true])->save();

        $this->login('correct horse')->assertStatus(422);
        $this->assertGuest();
    }

    public function test_account_locks_after_repeated_failures_even_with_the_right_password(): void
    {
        $this->user();

        for ($i = 0; $i < 8; $i++) {
            $this->login('wrong')->assertStatus(422);
        }

        $this->login('correct horse')->assertStatus(429);
        $this->assertGuest();
    }

    public function test_lock_applies_to_the_account_whatever_the_ip(): void
    {
        $this->user();

        for ($i = 0; $i < 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.($i + 1)])->login('wrong');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->login('correct horse')
            ->assertStatus(429);
    }

    public function test_a_successful_sign_in_clears_earlier_failures(): void
    {
        // The per-IP throttle is not what is under test here.
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->user();

        for ($i = 0; $i < 6; $i++) {
            $this->login('wrong');
        }
        $this->login('correct horse')->assertOk();
        $this->post('/logout');

        // 12 failures in total would lock the account if the earlier ones still counted.
        for ($i = 0; $i < 6; $i++) {
            $this->login('wrong')->assertStatus(422);
        }
    }

    public function test_one_locked_account_does_not_lock_another(): void
    {
        $this->user();
        $this->user(['email' => 'carol@example.test']);

        for ($i = 0; $i < 8; $i++) {
            $this->login('wrong');
        }

        $this->login('correct horse', 'carol@example.test')->assertOk();
    }

    public function test_forgot_password_answers_the_same_for_unknown_accounts(): void
    {
        $this->user();

        $known = $this->postJson('/forgot', ['email' => 'bob@example.test']);
        $unknown = $this->postJson('/forgot', ['email' => 'nobody@example.test']);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
    }

    public function test_password_reset_signs_out_every_existing_session(): void
    {
        $user = $this->user();
        DB::table('sessions')->insert([
            'id' => 'stolen-session',
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'attacker',
            'payload' => '',
            'last_activity' => time(),
        ]);
        $token = Password::createToken($user);

        $this->postJson('/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand new pass',
            'password_confirmation' => 'brand new pass',
        ])->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->login('brand new pass')->assertOk();
    }

    public function test_reset_with_a_bad_token_changes_nothing(): void
    {
        $user = $this->user();

        $this->postJson('/reset', [
            'token' => 'bogus',
            'email' => $user->email,
            'password' => 'brand new pass',
            'password_confirmation' => 'brand new pass',
        ])->assertStatus(422);

        $this->login('correct horse')->assertOk();
    }

    public function test_signed_out_visitors_get_no_mailbox_api(): void
    {
        $this->getJson('/api/messages')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_a_signed_in_person_changes_the_password_without_typing_the_old_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)->putJson('/api/account/password', [
            'password' => 'brand new pass',
            'password_confirmation' => 'brand new pass',
        ])->assertOk();

        $this->post('/logout');
        $this->login('brand new pass')->assertOk();
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $user = $this->user();

        $this->actingAs($user)->putJson('/api/account/password', ['password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422);
        $this->actingAs($user)->putJson('/api/account/password', ['password' => 'brand new pass', 'password_confirmation' => 'other'])->assertStatus(422);
    }
}
