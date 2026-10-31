<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** lang/*.json ship with openmeru.com; a running install shows its own MAIL_DOMAIN. */
class DomainTextsTest extends TestCase
{
    public function test_texts_show_the_installs_mail_domain(): void
    {
        config(['inbound.domain' => 'example.test']);
        Lang::setLoaded([]);

        $this->assertSame('Use an address outside @example.test.', __('forwardOwnDomain'));
        $this->assertStringContainsString('example.test', __('pushBlocked'));
        $this->assertStringNotContainsString('openmeru.com', __('pushBlocked'));
    }

    public function test_every_language_is_swapped(): void
    {
        config(['inbound.domain' => 'example.test']);
        foreach (glob(lang_path('*.json')) as $file) {
            $locale = basename($file, '.json');
            Lang::setLoaded([]);
            $text = __('forwardOwnDomain', [], $locale);
            $this->assertStringContainsString('example.test', $text, $locale);
            $this->assertStringNotContainsString('openmeru.com', $text, $locale);
        }
    }

    public function test_the_shipped_domain_is_left_alone_on_openmeru_itself(): void
    {
        config(['inbound.domain' => 'openmeru.com']);
        Lang::setLoaded([]);

        $this->assertSame('Use an address outside @openmeru.com.', __('forwardOwnDomain'));
    }

    public function test_php_group_translations_are_untouched(): void
    {
        config(['inbound.domain' => 'example.test']);
        Lang::setLoaded([]);

        // Framework texts (validation) go through the same loader and must still resolve.
        $this->assertNotSame('validation.required', __('validation.required'));
    }
}
