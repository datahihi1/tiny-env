<?php

use Datahihi1\TinyEnv\TinyEnv;

class IntMaxTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Ensure cache is clean between tests
        TinyEnv::clearCache(null);
    }

    public function testIntMaxParsesToInt()
    {
        $max = (string) PHP_INT_MAX;
        $val = TinyEnv::env('INT_MAX_TEST', $max);
        $this->assertIsInt($val);
        $this->assertSame(PHP_INT_MAX, $val);
    }

    public function testTooLargeIntRemainsString()
    {
        $too = (string) PHP_INT_MAX . '0';
        $val = TinyEnv::env('INT_TOO_LARGE', $too);
        $this->assertIsString($val);
        $this->assertSame($too, $val);
    }
}
