<?php

namespace Tests\Unit;

use App\Support\Brand;
use PHPUnit\Framework\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_the_php_limit_is_read_in_kilobytes(): void
    {
        $this->assertGreaterThan(0, Brand::phpUploadLimitKb());
        $this->assertSame(10240, Brand::neededUploadKb());
    }
}
