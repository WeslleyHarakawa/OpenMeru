<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Updates;
use App\Support\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
        config(['updates.url' => 'https://updates.test/version', 'updates.check' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_version_endpoint_tells_the_running_version(): void
    {
        config(['updates.source' => true]);
        $this->getJson('/version')
            ->assertOk()
            ->assertJsonPath('version', Version::current())
            ->assertJsonStructure(['version', 'commit', 'released_at', 'repo', 'url', 'install']);

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d{2}$/', Version::current());
    }

    public function test_bump_and_compare(): void
    {
        $this->assertSame('1.1.10', Version::bump('1.1.09'));
        $this->assertSame('1.2.00', Version::bump('1.1.99'));
        $this->assertSame('2.0.00', Version::bump('1.99.99'));
        $this->assertSame('1.2.00', Version::bump('1.1.09', 'minor'));
        $this->assertSame('2.0.00', Version::bump('v1.1.09', 'major'));
        $this->assertSame('1.1.09', Version::bump('1.1.8'));
        $this->assertTrue(Version::newerAvailable(Version::bump(Version::current())));
        $this->assertFalse(Version::newerAvailable('1.1.08'));
        $this->assertTrue(Version::newerAvailable('99.0.00'));
        $this->assertFalse(Version::newerAvailable(Version::current()));
        $this->assertFalse(Version::newerAvailable('0.0.1'));
    }

    public function test_check_sees_a_newer_version_and_caches_it(): void
    {
        Http::fake(['updates.test/*' => Http::response(['version' => '99.1.0', 'url' => 'https://example.test/changes'])]);

        $check = Updates::check();
        $this->assertTrue($check['available']);
        $this->assertSame('99.1.0', $check['latest']);
        $this->assertSame('https://example.test/changes', $check['url']);

        Updates::check();
        Http::assertSentCount(1);
    }

    public function test_check_handles_a_dead_endpoint_without_breaking_the_admin(): void
    {
        Http::fake(['updates.test/*' => Http::response('', 500)]);

        $check = Updates::check();
        $this->assertFalse($check['available']);
        $this->assertNotNull($check['error']);

        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->get('/admin')->assertOk();
    }

    public function test_check_can_be_turned_off(): void
    {
        config(['updates.check' => false]);
        Http::fake();

        $this->assertFalse(Updates::check()['available']);
        Http::assertNothingSent();
    }

    public function test_admin_sees_the_banner_only_when_an_update_is_out(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];

        // The first fake registered keeps answering, so one sequence serves both requests.
        Http::fake(['updates.test/*' => Http::sequence()
            ->push(['version' => Version::current()])
            ->push(['version' => '99.1.0'])]);
        $this->actingAs($admin)->withSession($session)->get('/admin')->assertOk()->assertDontSee('id="updateBanner"', false);

        Cache::flush();
        $this->actingAs($admin)->withSession($session)->get('/admin')->assertOk()
            ->assertSee('id="updateBanner"', false)
            ->assertSee('99.1.0')
            ->assertSee('curl -fsSL https://updates.test/install | bash');
    }

    public function test_update_endpoints_are_for_verified_admins_only(): void
    {
        $bob = User::factory()->create(['email' => 'bob@example.test']);
        $admin = $this->admin();

        $this->getJson('/admin/update')->assertUnauthorized();
        $this->postJson('/admin/update')->assertUnauthorized();
        $this->actingAs($bob)->postJson('/admin/update')->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/update')->assertForbidden();
    }

    public function test_status_reports_the_background_run(): void
    {
        $admin = $this->admin();
        Http::fake(['updates.test/*' => Http::response(['version' => '99.1.0'])]);
        Updates::writeStatus(['state' => 'running', 'log' => "▶ Fetching\n", 'started_at' => now()->toIso8601String()]);

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->getJson('/admin/update')
            ->assertOk()
            ->assertJsonPath('state', 'running')
            ->assertJsonPath('check.latest', '99.1.0');
        $this->assertTrue(Updates::isRunning());

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->post('/admin/update/dismiss')->assertRedirect();
        $this->assertSame('idle', Updates::status()['state']);
    }

    public function test_a_stale_running_flag_is_not_a_running_update(): void
    {
        Updates::writeStatus(['state' => 'running', 'started_at' => now()->subHours(3)->toIso8601String()]);

        $this->assertFalse(Updates::isRunning());
    }

    public function test_starting_without_a_git_checkout_points_to_the_terminal(): void
    {
        $admin = $this->admin();

        // The test tree is not self-updatable (testing environment), so the button must not run anything.
        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->postJson('/admin/update')
            ->assertStatus(422)
            ->assertJsonPath('command', 'curl -fsSL https://updates.test/install | bash');
        $this->assertSame('idle', Updates::status()['state']);
    }

    public function test_demo_admin_cannot_start_an_update(): void
    {
        $demo = $this->admin();
        $demo->forceFill(['is_demo' => true])->save();

        $this->actingAs($demo)->withSession(['admin_verified' => $demo->id])->postJson('/admin/update')->assertForbidden();
    }

    public function test_a_finished_update_is_told_once_without_the_log(): void
    {
        $admin = $this->admin();
        Http::fake(['updates.test/*' => Http::response(['version' => Version::current()])]);
        Updates::writeStatus(['state' => 'done', 'log' => "▶ Fetching the latest code
Saved to /home/x/backup.sql.gz
", 'to' => '9.9.9']);

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->get('/admin')->assertOk()
            ->assertSee('Updated to 9.9.9.')
            ->assertDontSee('Fetching the latest code')
            ->assertDontSee('backup.sql.gz');

        $this->assertSame('idle', Updates::status()['state']);
        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->get('/admin')->assertOk()->assertDontSee('Updated to 9.9.9.');
    }
}
