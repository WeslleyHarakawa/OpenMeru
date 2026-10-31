<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_asks_for_the_password_when_it_is_left_out(): void
    {
        $this->artisan('openmeru:admin', ['email' => 'Info@Example.test', 'name' => 'Info'])
            ->expectsQuestion('Password (min. 8 characters, not shown)', 'a-long-password')
            ->expectsQuestion('Confirm password', 'a-long-password')
            ->assertSuccessful();

        $user = User::where('email', 'info@example.test')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
    }

    public function test_refuses_a_mismatched_confirmation(): void
    {
        $this->artisan('openmeru:admin', ['email' => 'info@example.test', 'name' => 'Info'])
            ->expectsQuestion('Password (min. 8 characters, not shown)', 'a-long-password')
            ->expectsQuestion('Confirm password', 'something-else')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_refuses_a_short_password(): void
    {
        $this->artisan('openmeru:admin', ['email' => 'info@example.test', 'name' => 'Info', 'password' => 'short'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_password_argument_still_works(): void
    {
        $this->artisan('openmeru:admin', ['email' => 'info@example.test', 'name' => 'Info', 'password' => 'a-long-password'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('a-long-password', User::firstOrFail()->password));
    }

    public function test_running_it_again_resets_the_password_of_the_same_admin(): void
    {
        $this->artisan('openmeru:admin', ['email' => 'info@example.test', 'name' => 'Info', 'password' => 'first-password'])->assertSuccessful();
        $this->artisan('openmeru:admin', ['email' => 'info@example.test', 'name' => 'Info', 'password' => 'second-password'])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('second-password', User::firstOrFail()->password));
    }
}
