<?php

namespace Tests\Feature;

use App\Support\Locales;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

/** The project site at openmeru.com: translated landing, language links and crawler files. */
class LandingTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        // The root route is decided while the routes load, so the app is booted again as the source install.
        putenv('OPENMERU_SOURCE=true');
        $this->refreshApplication();
        $this->base = rtrim(url('/'), '/');
    }

    protected function tearDown(): void
    {
        putenv('OPENMERU_SOURCE');
        parent::tearDown();
    }

    public function test_landing_speaks_every_language(): void
    {
        $this->assertTrue(config('updates.source'));
        $this->get('/')->assertOk()
            ->assertSee('<html lang="en-GB"', false)
            ->assertSee(__('landing.h1a'))
            ->assertSee('rel="canonical" href="'.$this->base.'/"', false)
            ->assertSee('hreflang="pt-BR" href="'.$this->base.'/?lang=pt_BR"', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('id="langPick"', false);

        foreach (array_keys(Locales::ALL) as $code) {
            $this->withUnencryptedCookie(Locales::COOKIE, $code)->get('/')->assertOk()
                ->assertSee('<html lang="'.Locales::tag($code).'"', false)
                ->assertSee(trans('landing.h1a', [], $code))
                ->assertSee(e(trans('landing.title', [], $code)), false)
                ->assertDontSee('landing.');
        }
    }

    public function test_lang_query_picks_the_language_and_sticks(): void
    {
        $this->get('/?lang=de')->assertOk()
            ->assertSee(trans('landing.h1a', [], 'de'))
            ->assertCookie(Locales::COOKIE, 'de', false);
    }

    public function test_an_unknown_lang_query_is_ignored(): void
    {
        Cookie::flushQueuedCookies();
        $this->get('/?lang=xx')->assertOk()->assertSee(trans('landing.h1a', [], 'en'))->assertCookieMissing(Locales::COOKIE);
    }

    public function test_the_landing_texts_stay_out_of_the_webmail_script(): void
    {
        $this->assertArrayNotHasKey('landing.title', Locales::dictionary('en'));
        $this->assertArrayHasKey('language', Locales::dictionary('pt'));
    }

    public function test_crawler_files_on_the_source_site(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.$this->base.'/sitemap.xml')->assertSee('Disallow: /admin');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>'.$this->base.'/?lang=zh</loc>', false)
            ->assertSee('hreflang="ru-RU"', false)
            ->assertSee('<loc>'.$this->base.'/privacy</loc>', false);
        $this->get('/llms.txt')->assertOk()->assertSee('# OpenMeru Mail')->assertSee('curl -fsSL '.$this->base.'/install | bash');
    }

    public function test_other_installs_ask_not_to_be_indexed(): void
    {
        putenv('OPENMERU_SOURCE=false');
        $this->refreshApplication();

        $this->assertFalse(config('updates.source'));
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap');
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/llms.txt')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('id="langPick"', false);
    }
}
