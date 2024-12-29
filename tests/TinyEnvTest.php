<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Datahihi1\TinyEnv\TinyEnv;

class TinyEnvTest extends TestCase
{
    private $testDir;

    protected function setUp(): void
    {
        // Clear $_ENV to start fresh for each test
        $_ENV = [];

        // Create a temporary directory for testing
        $this->testDir = sys_get_temp_dir() . '/tinyenv_test';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        // Create test files
        file_put_contents($this->testDir . '/.env', "APP_ENV=testing\nAPP_DEBUG=true");
        file_put_contents($this->testDir . '/.env.example', "APP_ENV=example\nAPP_DEBUG=false");
        file_put_contents($this->testDir . '/config.ini', "[database]\nhost=localhost\nport=3306");
    }

    protected function tearDown(): void
    {
        // Clean up the test directory recursively
        $this->deleteDirectory($this->testDir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
    
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
    
        rmdir($dir);
    }
    
    public function testLoadEnvFiles(): void
    {
        $tinyEnv = new TinyEnv($this->testDir);
        $tinyEnv->load();

        $this->assertSame('testing', $_ENV['APP_ENV']);
        $this->assertSame('true', $_ENV['APP_DEBUG']);
    }

    public function testLoadAdditionalFiles(): void
    {
        $tinyEnv = new TinyEnv($this->testDir);
        $tinyEnv->load();

        $this->assertSame('localhost', $_ENV['DATABASE_HOST']);
        $this->assertSame('3306', $_ENV['DATABASE_PORT']);
    }

    public function testEnvFunction(): void
    {
        $tinyEnv = new TinyEnv($this->testDir);
        $tinyEnv->load();

        $this->assertSame('testing', TinyEnv::env('APP_ENV'));
        $this->assertSame('true', TinyEnv::env('APP_DEBUG'));
        $this->assertNull(TinyEnv::env('NON_EXISTENT_KEY'));
        $this->assertSame('default', TinyEnv::env('NON_EXISTENT_KEY', 'default'));
    }

    public function testInvalidDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TinyEnv('/nonexistent/path');
    }

    public function testMalformedEnvFile(): void
    {
        file_put_contents($this->testDir . '/malformed.env', "MALFORMED LINE\nVALID_KEY=valid_value");

        $tinyEnv = new TinyEnv($this->testDir);
        $tinyEnv->load();

        $this->assertSame('valid_value', $_ENV['VALID_KEY']);
        $this->assertArrayNotHasKey('MALFORMED LINE', $_ENV);
    }

    public function testEmptyDirectory(): void
    {
        // Clear $_ENV to ensure it doesn't retain values from previous tests
        $_ENV = [];

        $emptyDir = $this->testDir . '/empty';
        mkdir($emptyDir);

        $tinyEnv = new TinyEnv($emptyDir);
        $tinyEnv->load();

        $this->assertEmpty($_ENV);

        rmdir($emptyDir);
    }

    public function testDuplicateKeys(): void
    {
        file_put_contents($this->testDir . '/override.env', "APP_ENV=production");

        $tinyEnv = new TinyEnv($this->testDir);
        $tinyEnv->load();

        $this->assertSame('production', $_ENV['APP_ENV']);
    }
}
