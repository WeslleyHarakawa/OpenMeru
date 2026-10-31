<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Updates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Updates without git (release package) and the automatic run. */
class UpdatePackageTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
        config(['updates.url' => 'https://updates.test/version', 'updates.check' => true]);
        $this->tmp = sys_get_temp_dir().'/openmeru-pkg-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->rm($this->tmp);
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function write(string $path, string $content): void
    {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
    }

    private function rm(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    public function test_the_package_overwrites_code_but_never_the_installs_own_data(): void
    {
        $from = $this->tmp.'/release';
        $to = $this->tmp.'/install';
        $this->write($from.'/VERSION', "9.9.99\n");
        $this->write($from.'/app/New.php', 'new');
        $this->write($from.'/app/Same.php', 'same');
        $this->write($from.'/.env.example', 'example');
        $this->write($from.'/.env', 'SHIPPED=1');
        $this->write($from.'/storage/app/.gitignore', '*');
        $this->write($from.'/vendor/autoload.php', 'shipped');

        $this->write($to.'/VERSION', "1.0.00\n");
        $this->write($to.'/app/Same.php', 'same');
        $this->write($to.'/.env', 'APP_KEY=mine');
        $this->write($to.'/storage/app/private/secret.json', 'mine');
        $this->write($to.'/vendor/autoload.php', 'mine');

        $written = Updates::applyTree($from, $to);

        $this->assertSame(3, $written, 'VERSION, New.php and .env.example');
        $this->assertSame("9.9.99\n", file_get_contents($to.'/VERSION'));
        $this->assertSame('new', file_get_contents($to.'/app/New.php'));
        $this->assertSame('example', file_get_contents($to.'/.env.example'));
        $this->assertSame('APP_KEY=mine', file_get_contents($to.'/.env'));
        $this->assertSame('mine', file_get_contents($to.'/storage/app/private/secret.json'));
        $this->assertSame('mine', file_get_contents($to.'/vendor/autoload.php'));
        $this->assertFileDoesNotExist($to.'/storage/app/.gitignore');
    }

    public function test_check_carries_the_package_url(): void
    {
        Http::fake(['updates.test/*' => Http::response(['version' => '99.1.0', 'tarball' => 'https://example.test/release.tar.gz'])]);

        $this->assertSame('https://example.test/release.tar.gz', Updates::tarballUrl());
    }

    public function test_package_url_falls_back_to_the_repository(): void
    {
        Http::fake(['updates.test/*' => Http::response(['version' => '99.1.0'])]);

        $this->assertStringContainsString('github.com/WeslleyHarakawa/OpenMeru/archive', Updates::tarballUrl());
    }

    public function test_version_endpoint_names_the_package(): void
    {
        config(['updates.source' => true]);
        $this->getJson('/version')->assertOk()->assertJsonPath('tarball', 'https://github.com/WeslleyHarakawa/OpenMeru/archive/refs/heads/main.tar.gz');
    }

    public function test_automatic_updates_are_on_by_default_and_the_admin_can_turn_them_off(): void
    {
        $this->assertTrue(Updates::autoEnabled());

        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];
        $this->actingAs($admin)->withSession($session)->get('/admin/settings')->assertOk()
            ->assertSee('id="auto_update"', false)->assertSee('name="auto_update" value="1" checked', false);

        $this->actingAs($admin)->withSession($session)->put('/admin/settings/updates', [])->assertRedirect();
        $this->assertFalse(Updates::autoEnabled());

        $this->actingAs($admin)->withSession($session)->put('/admin/settings/updates', ['auto_update' => '1'])->assertRedirect();
        $this->assertTrue(Updates::autoEnabled());

        $bob = User::factory()->create(['email' => 'bob@example.test']);
        $this->actingAs($bob)->put('/admin/settings/updates', [])->assertForbidden();
    }

    public function test_the_automatic_run_waits_while_a_failure_is_on_screen(): void
    {
        Http::fake(['updates.test/*' => Http::response(['version' => '99.1.0'])]);
        Updates::writeStatus(['state' => 'failed', 'log' => 'FAILED: x']);

        $this->assertFalse(Updates::maybeAutoStart());
        $this->assertSame('failed', Updates::status()['state']);
    }

    public function test_a_run_that_died_is_reported_as_failed(): void
    {
        Updates::writeStatus(['state' => 'running', 'log' => "▶ Fetching\n", 'started_at' => now()->subHours(3)->toIso8601String()]);

        $status = Updates::status();
        $this->assertSame('failed', $status['state']);
        $this->assertStringContainsString('stopped without finishing', $status['log']);
        $this->assertFalse(Updates::isRunning());
    }

    public function test_auto_option_does_nothing_when_up_to_date(): void
    {
        Http::fake(['updates.test/*' => Http::response(['version' => \App\Support\Version::current()])]);

        $this->artisan('openmeru:update', ['--auto' => true])->expectsOutputToContain('Up to date')->assertExitCode(0);
        $this->assertSame('idle', Updates::status()['state']);
    }

    public function test_auto_option_does_nothing_when_turned_off(): void
    {
        Updates::setAutoEnabled(false);
        Http::fake();

        $this->artisan('openmeru:update', ['--auto' => true])->expectsOutputToContain('off')->assertExitCode(0);
        Http::assertNothingSent();
    }
}
