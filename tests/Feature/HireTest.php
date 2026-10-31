<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "Install it for me" orders from the landing. */
class HireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['updates.source' => true]);
    }

    public function test_an_order_is_stored(): void
    {
        $this->postJson('/hire', ['name' => 'Ana', 'email' => 'Ana@Example.com', 'domain' => 'Example.com', 'panel' => 'cloudpanel', 'notes' => 'Two mailboxes'])
            ->assertOk()->assertJsonPath('ok', true);

        $row = DB::table('install_requests')->first();
        $this->assertSame('ana@example.com', $row->email);
        $this->assertSame('example.com', $row->domain);
        $this->assertSame('new', $row->status);

        $this->postJson('/hire', ['name' => '', 'email' => 'nope'])->assertStatus(422);
    }

    public function test_the_form_only_exists_on_the_source_site(): void
    {
        config(['updates.source' => false]);
        $this->postJson('/hire', ['name' => 'Ana', 'email' => 'ana@example.com'])->assertNotFound();
    }
}
