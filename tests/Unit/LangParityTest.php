<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Every interface text exists in all ten languages: a key added to en.json must be translated everywhere. */
class LangParityTest extends TestCase
{
    public function test_every_language_has_every_key(): void
    {
        $dir = dirname(__DIR__, 2).'/lang';
        $en = json_decode((string) file_get_contents($dir.'/en.json'), true);
        $this->assertNotEmpty($en);

        $languages = ['pt', 'pt_BR', 'es', 'fr', 'de', 'it', 'nl', 'ru', 'zh'];
        foreach ($languages as $locale) {
            $other = json_decode((string) file_get_contents($dir.'/'.$locale.'.json'), true);
            $missing = array_values(array_diff(array_keys($en), array_keys($other)));
            $this->assertSame([], $missing, "lang/{$locale}.json is missing keys");
            $extra = array_values(array_diff(array_keys($other), array_keys($en)));
            $this->assertSame([], $extra, "lang/{$locale}.json has keys en.json lacks");
        }
    }
}
