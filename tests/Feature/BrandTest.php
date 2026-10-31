<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Brand::forget();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_default_brand_is_openmeru_mail(): void
    {
        $this->get('/')->assertOk()->assertSee('OpenMeru')->assertSee('MAIL')->assertSee('<svg class="logo-mark', false);
        $this->assertSame('OpenMeru Mail', Brand::name());
        $this->get('/brand/logo')->assertNotFound();
    }

    public function test_admin_sets_a_name_and_logo_that_show_on_the_sign_in_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->put('/admin/settings/brand', ['brand_name' => 'W8 Atlantic Mail', 'logo' => UploadedFile::fake()->image('logo.png', 128, 128)])
            ->assertRedirect()->assertSessionHasNoErrors();

        Brand::forget();
        $this->assertSame('W8 Atlantic Mail', Brand::name());
        $this->assertNotNull(Setting::get('brand_logo'));
        Storage::disk('local')->assertExists(Setting::get('brand_logo'));

        $page = $this->get('/')->assertOk();
        $page->assertSee('W8 Atlantic Mail')->assertDontSee('<svg class="logo-mark', false)->assertSee('/brand/logo', false);
        $this->get('/brand/logo')->assertOk();
    }

    public function test_removing_the_logo_restores_the_default_mark(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];
        $this->actingAs($admin)->withSession($session)->put('/admin/settings/brand', ['logo' => UploadedFile::fake()->image('logo.png')]);
        $path = Setting::get('brand_logo');

        $this->actingAs($admin)->withSession($session)->delete('/admin/settings/brand/logo')->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        Brand::forget();
        $this->assertNull(Brand::logoUrl());
        $this->get('/')->assertSee('<svg class="logo-mark', false);
    }

    public function test_only_images_are_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->from('/admin/settings')
            ->put('/admin/settings/brand', ['logo' => UploadedFile::fake()->create('evil.html', 10, 'text/html')])
            ->assertSessionHasErrors('logo');
        $this->assertNull(Setting::get('brand_logo'));
    }

    public function test_settings_page_has_the_side_menu_with_general_first(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->get('/admin/settings')->assertOk()
            ->assertSeeInOrder(['data-pane-link="general"', 'data-pane-link="smtp"', 'data-pane-link="push"', 'data-pane-link="translate"', 'data-pane-link="inbound"', 'data-pane-link="updates"'], false)
            ->assertSee('name="brand_name"', false);
    }
}
