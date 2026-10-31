<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** openmeru.com/docs and the cloud-hosting waitlist. */
class DocsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['updates.source' => true]);
    }

    public function test_docs_render_the_readme_with_a_section_menu(): void
    {
        $this->get('/docs')->assertOk()
            ->assertSee('<h2 id="how-it-works">', false)
            ->assertSee('href="#how-it-works"', false)
            ->assertDontSee('## ')
            ->assertDontSee('docs/screenshots/inbox.png')
            ->assertSee('buymeacoffee.com/weslleyharakawa');
    }

    public function test_waitlist_keeps_each_address_once(): void
    {
        $this->postJson('/waitlist', ['email' => 'Someone@Example.com'])->assertOk()->assertJsonPath('ok', true);
        $this->postJson('/waitlist', ['email' => 'someone@example.com'])->assertOk();
        $this->postJson('/waitlist', ['email' => 'not-an-email'])->assertStatus(422);

        $this->assertSame(1, DB::table('cloud_waitlist')->count());
        $this->assertSame('someone@example.com', DB::table('cloud_waitlist')->value('email'));
    }

    public function test_docs_and_waitlist_exist_only_on_the_source_site(): void
    {
        config(['updates.source' => false]);
        $this->get('/docs')->assertNotFound();
        $this->postJson('/waitlist', ['email' => 'a@b.co'])->assertNotFound();
    }
}
