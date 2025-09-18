<?php

use PHPUnit\Framework\TestCase;
use Datahihi1\TinyEnv\TinyEnv;

class TinyEnvTest extends TestCase
{
    private $envFile;

    protected function setUp(): void
    {
        // Create a mock .env file for testing
        $this->envFile = __DIR__ . '/../.env';
        file_put_contents($this->envFile, "APP_NAME=TinyEnvTest\nAPP_DEBUG=true\n");
    }

    protected function tearDown(): void
    {
        // Xóa file .env sau khi test
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }
    }

    public function testLoadAndGetEnv()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        $env->load();

        $this->assertEquals('TinyEnvTest', TinyEnv::env('APP_NAME'));
        $this->assertEquals('true', TinyEnv::env('APP_DEBUG'));
        $this->assertNull(TinyEnv::env('NOT_EXIST'));
        $this->assertEquals('default', TinyEnv::env('NOT_EXIST', 'default'));
    }

    public function testLoadAndGetEnvProduction()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        $env->envfiles(['.env.production']);
        $env->load();

        $this->assertEquals('TinyEnvProd', TinyEnv::env('APP_NAME'));
        $this->assertEquals(false, TinyEnv::env('APP_DEBUG'));
        $this->assertNull(TinyEnv::env('NOT_EXIST'));
        $this->assertEquals('default', TinyEnv::env('NOT_EXIST', 'default'));
    }

    public function testMultipleEnvFilesOverride()
    {
        // Tạo thêm file .env.production để override giá trị
        $envProdFile = __DIR__ . '/../.env.production';
        file_put_contents($envProdFile, "APP_NAME=TinyEnvProd\nAPP_DEBUG=false\n");
        $env = new TinyEnv(__DIR__ . '/..');
        $env->envfiles(['.env', '.env.production'])->load();

        $this->assertEquals('TinyEnvProd', TinyEnv::env('APP_NAME'));
        $this->assertEquals(false, TinyEnv::env('APP_DEBUG'));
    }

    public function testLazyLoadWithPrefix()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        $env->lazy(['APP']);

        $this->assertEquals('TinyEnvTest', TinyEnv::env('APP_NAME'));
        $this->assertEquals('true', TinyEnv::env('APP_DEBUG'));
    }

    public function testSafeLoadDoesNotThrowOnMissingFile()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        // Xóa file .env để test safeLoad
        if (file_exists($this->envFile)) unlink($this->envFile);

        $this->expectNotToPerformAssertions();
        $env->safeLoad();
    }
}