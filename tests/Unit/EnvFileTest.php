<?php

namespace Tests\Unit;

use App\Support\EnvFile;
use PHPUnit\Framework\TestCase;

class EnvFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'env');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_reads_plain_quoted_and_missing_values(): void
    {
        file_put_contents($this->path, "A=1\nB=\"two words\"\nC='x'\n# D=comment\n");
        $env = new EnvFile($this->path);

        $this->assertSame('1', $env->get('A'));
        $this->assertSame('two words', $env->get('B'));
        $this->assertSame('x', $env->get('C'));
        $this->assertNull($env->get('D'));
        $this->assertNull($env->get('ZZ'));
    }

    public function test_replaces_in_place_and_appends_new_keys(): void
    {
        file_put_contents($this->path, "# head\nAPP_URL=https://old.test\nMAIL_DOMAIN=old.test\n");
        $env = new EnvFile($this->path);

        $env->set(['APP_URL' => 'https://new.test', 'INBOUND_SECRET' => 'abc']);

        $text = file_get_contents($this->path);
        $this->assertStringStartsWith("# head\nAPP_URL=https://new.test\nMAIL_DOMAIN=old.test\n", $text);
        $this->assertStringEndsWith("INBOUND_SECRET=abc\n", $text);
        $this->assertSame(1, substr_count($text, 'APP_URL='));
    }

    public function test_quotes_and_escapes_values_with_special_characters(): void
    {
        $env = new EnvFile($this->path);
        $password = 'p@ss "word" $1 \\ end';

        $env->set(['DB_PASSWORD' => $password, 'APP_NAME' => 'OpenMeru Mail', 'PLAIN' => 'a.b-c_d/e:f']);

        $this->assertSame($password, $env->get('DB_PASSWORD'));
        $this->assertSame('OpenMeru Mail', $env->get('APP_NAME'));
        $this->assertStringContainsString("PLAIN=a.b-c_d/e:f\n", file_get_contents($this->path));
    }

    public function test_does_not_match_keys_that_only_share_a_prefix(): void
    {
        file_put_contents($this->path, "DB_HOST=h\nDB_HOST_READ=r\n");
        $env = new EnvFile($this->path);

        $env->set(['DB_HOST' => 'new']);

        $this->assertSame('new', $env->get('DB_HOST'));
        $this->assertSame('r', $env->get('DB_HOST_READ'));
    }

    public function test_creates_the_file_when_missing(): void
    {
        unlink($this->path);
        $env = new EnvFile($this->path);

        $this->assertFalse($env->exists());
        $env->set(['A' => '1']);
        $this->assertTrue($env->exists());
        $this->assertSame('1', $env->get('A'));
    }
}
