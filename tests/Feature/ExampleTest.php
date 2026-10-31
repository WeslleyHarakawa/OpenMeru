<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * An install is somebody's webmail: its root is the sign-in page and nothing
 * about the OpenMeru project shows. Only openmeru.com (updates.source) does.
 */
class ExampleTest extends TestCase
{
    public function test_an_install_opens_on_the_sign_in_page(): void
    {
        // Routes are registered at boot: the suite runs as a normal install (MAIL_DOMAIN=example.test).
        $this->get('/')->assertOk()->assertSee('Sign in');
        $this->get('/mail')->assertRedirect('/');
        $this->get('/webmail')->assertRedirect('/');
        $this->assertSame(url('/'), route('login'));
        foreach (['/install', '/version', '/demo', '/demo/admin'] as $projectPage) {
            $this->get($projectPage)->assertNotFound();
        }
    }

    public function test_signed_out_visitors_are_sent_to_the_root(): void
    {
        $this->get('/settings')->assertRedirect('/');
    }

    public function test_the_sign_in_page_does_not_advertise_the_project(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Open source')
            ->assertDontSee('openmeru.com/install')
            ->assertDontSee('Try the live demo');
    }

    public function test_openmeru_com_itself_keeps_the_project_pages(): void
    {
        // The root route is decided at boot, so only the source-only pages can be flipped here.
        config(['updates.source' => true]);

        $this->get('/install')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->get('/version')->assertOk()->assertJsonStructure(['version']);
    }

    public function test_source_is_openmeru_com_by_default(): void
    {
        // phpunit.xml sets MAIL_DOMAIN=example.test, so this suite runs as a plain install.
        $this->assertFalse(config('updates.source'));
    }
}
