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
        TinyEnv::setAllowFileWrites(true);
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

    public function testSetenvUpdatesEnvFile()
    {
        TinyEnv::setenv('APP_VERSION', '1.0.0');
        $env = new TinyEnv(__DIR__ . '/..');
        $env->load();

        $this->assertEquals('1.0.0', TinyEnv::env('APP_VERSION'));

        // Kiểm tra file .env đã được cập nhật
        $content = file_get_contents($this->envFile);
        $this->assertStringContainsString('APP_VERSION=1.0.0', $content);
    }

    public function testSetenvWithInvalidKeyThrowsException()
    {
        $this->expectException(Exception::class);
        TinyEnv::setenv('invalid key', 'value');
    }

    public function testMultipleEnvFilesOverride()
    {
        // Tạo thêm file .env.production để override giá trị
        $envProdFile = __DIR__ . '/../.env.production';
        file_put_contents($envProdFile, "APP_NAME=TinyEnvProd\nAPP_DEBUG=false\n");
        $env = new TinyEnv(__DIR__ . '/..');
        $env->envfiles(['.env', '.env.production'])->load();

        $this->assertEquals('TinyEnvProd', TinyEnv::env('APP_NAME'));
        $this->assertEquals('false', TinyEnv::env('APP_DEBUG'));

        // Dọn dẹp
        unlink($envProdFile);
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

    public function testSetenvWithBooleanValue()
    {
        TinyEnv::setenv('IS_ENTERPRISE', true);
        $env = new TinyEnv(__DIR__ . '/..');
        $env->load();

        $this->assertEquals('true', TinyEnv::env('IS_ENTERPRISE'));
    }

    public function testSetenvWithInvalidValueThrowsException()
    {
        $this->expectException(Exception::class);
        TinyEnv::setenv('APP_ARRAY', ['not', 'scalar']);
    }
}