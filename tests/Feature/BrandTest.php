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
        $this->get('/')->assertOk()->assertSee('OpenMeru')->assertSee('MAIL')->assertSee('<svg class="logo-mark', false)->assertDontSee('Brand::product');
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

        $this->actingAs($admin)->withSession($session)->put('/admin/settings/brand', ['remove_logo' => '1'])->assertRedirect();

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

    public function test_custom_colours_override_the_brand_variables_everywhere(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];

        $this->get('/')->assertOk()->assertDontSee('id="brand-vars"', false);

        $this->actingAs($admin)->withSession($session)
            ->put('/admin/settings/brand', ['brand_primary' => '#AA1122', 'brand_secondary' => '#331144'])
            ->assertRedirect()->assertSessionHasNoErrors();
        Brand::forget();

        $this->assertSame('#aa1122', Brand::primary());
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('--brand:#aa1122', false)->assertSee('--brand-dark:#331144', false)
            ->assertSee('<meta name="theme-color" content="#aa1122">', false);
        $this->actingAs($admin)->withSession($session)->get('/admin')->assertSee('--brand:#aa1122', false);

        $this->actingAs($admin)->withSession($session)
            ->put('/admin/settings/brand', ['brand_primary' => '#000000', 'brand_secondary' => '#000000', 'reset_colors' => '1'])
            ->assertRedirect();
        Brand::forget();
        $this->assertFalse(Brand::hasCustomColors());
        $this->get('/')->assertDontSee('id="brand-vars"', false);
    }

    public function test_default_colours_posted_back_do_not_count_as_custom(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])
            ->put('/admin/settings/brand', ['brand_primary' => Brand::DEFAULT_PRIMARY, 'brand_secondary' => Brand::DEFAULT_SECONDARY]);
        Brand::forget();

        $this->assertFalse(Brand::hasCustomColors());
    }

    public function test_bad_colours_are_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_verified' => $admin->id])->from('/admin/settings')
            ->put('/admin/settings/brand', ['brand_primary' => 'red'])
            ->assertSessionHasErrors('brand_primary');
    }

    public function test_sign_in_background_can_be_set_and_removed(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];

        $this->get('/brand/background')->assertNotFound();
        $this->actingAs($admin)->withSession($session)
            ->put('/admin/settings/brand', ['background' => UploadedFile::fake()->image('bg.jpg', 1200, 800)])
            ->assertRedirect()->assertSessionHasNoErrors();
        Brand::forget();

        $this->get('/brand/background')->assertOk();
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('/brand/background', false)->assertSee('brand has-bg', false);

        $this->actingAs($admin)->withSession($session)->put('/admin/settings/brand', ['remove_background' => '1'])->assertRedirect();
        Brand::forget();
        $this->get('/brand/background')->assertNotFound();
    }

    public function test_icon_and_favicon_show_up_in_the_right_places(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];

        $this->actingAs($admin)->withSession($session)->put('/admin/settings/brand', [
            'icon' => UploadedFile::fake()->image('icon.png', 192, 192),
            'favicon' => UploadedFile::fake()->image('fav.png', 32, 32),
        ])->assertRedirect()->assertSessionHasNoErrors();
        Brand::forget();

        $this->get('/brand/icon')->assertOk();
        $this->get('/brand/favicon')->assertOk();
        $this->get('/brand/nope')->assertNotFound();
        auth()->logout();
        $page = $this->get('/')->assertOk();
        $page->assertSee('<link rel="icon" href="'.url('/brand/favicon'), false)
            ->assertSee('<link rel="apple-touch-icon" href="'.url('/brand/icon'), false);
    }

    public function test_the_small_mark_prefers_the_icon_over_the_logo(): void
    {
        $admin = $this->admin();
        $session = ['admin_verified' => $admin->id];
        $this->actingAs($admin)->withSession($session)->put('/admin/settings/brand', [
            'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
            'icon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ]);
        Brand::forget();

        // The admin header is always OpenMeru's own mark; the client's icon shows in Settings → General.
        $this->actingAs($admin)->withSession($session)->get('/admin')->assertOk()->assertSee('<svg class="logo-mark', false)->assertDontSee('<img class="logo-mark', false);
        $this->actingAs($admin)->withSession($session)->get('/admin/settings')->assertOk()->assertSee('/brand/icon', false);
        // The sign-in card uses the big mark: the logo.
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('/brand/logo', false);
    }
}
