<?php

use PHPUnit\Framework\Assert;
use Datahihi1\TinyEnv\TinyEnv;

class TinyEnvTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $envFile;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        // Direction to the .env file in the project root
        $this->envFile = __DIR__ . '/../.env';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        // Do not auto-delete .env file after tests
    }

    protected function resetEnvState(): void
    {
        // Clear any keys that tests may have added to $_ENV and TinyEnv cache
        foreach (array_keys($_ENV) as $k) {
            unset($_ENV[$k]);
        }
        // Reset TinyEnv internal cache via reflection (no direct API)
        $rc = new \ReflectionClass(TinyEnv::class);
        if ($rc->hasProperty('cache')) {
            $prop = $rc->getProperty('cache');
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }
        // Clear fileLinesCache as well to avoid cross-test caching
        if ($rc->hasProperty('fileLinesCache')) {
            $prop = $rc->getProperty('fileLinesCache');
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }
        // Ensure .env is loaded fresh in next test
    }

    public function testEnvfilesPriorityAndFastLoad()
    {
        $this->resetEnvState();
        // envfiles order: later files override earlier ones. We pass production
        // and .env; production should override values.
        $env = new TinyEnv(__DIR__ . '/..');
        $env->envfiles(['.env.production', '.env']);
        // use safeLoad here so tests that expect a successful load are not
        // interrupted by the recursion detection test data (VAR1<->VAR2)
        $env->safeLoad();
        // fastLoad true triggers load in constructor; app name should come from production
        // always prioritize .env , .env.production is also considered as override
        $this->assertEquals('TinyEnv', TinyEnv::env('APP_NAME'));
        $this->assertTrue(TinyEnv::env('APP_DEBUG'));
    }

    public function testLoadWithSpecificKeys()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        // Load only MY_TEXT
        $env->load(['MY_TEXT']);
        $this->assertSame(8.7, TinyEnv::env('MY_TEXT'));
        // APP_NAME should not be loaded
        $this->assertNull(TinyEnv::env('APP_NAME'));
    }

    public function testRecursiveSubstitutionThrows()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        $this->expectException(Exception::class);
        if (method_exists($this, 'expectExceptionMessageMatches')) {
            $this->expectExceptionMessageMatches('/recursive variable substitution/');
        } else {
            $this->expectExceptionMessage('recursive variable substitution');
        }
        $env->load();
    }

    public function testMalformedLinesLoadVsSafeLoad()
    {
        $this->resetEnvState();
        // Create a temp dir with a malformed .env
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyenv_test_' . uniqid();
        mkdir($tmp);
        $bad = "OK=1\nBADLINE\nANOTHER=2\nBAD==x\n";
        file_put_contents($tmp . DIRECTORY_SEPARATOR . '.env', $bad);
        try {
            $env = new TinyEnv($tmp);
            // load should throw on malformed lines
            $this->expectException(Exception::class);
            $env->load();
        } finally {
            // safeLoad should not throw
            $env2 = new TinyEnv($tmp);
            $env2->safeLoad();
            $this->assertSame(1, TinyEnv::env('OK'));
            // cleanup
            @unlink($tmp . DIRECTORY_SEPARATOR . '.env');
            @rmdir($tmp);
        }
    }

    public function testLazyCaching()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        // first lazy() should populate internal fileLinesCache
        $env->lazy(['APP']);
        $rc = new \ReflectionClass(TinyEnv::class);
        $prop = $rc->getProperty('fileLinesCache');
        $prop->setAccessible(true);
        $cache = $prop->getValue();
        $this->assertNotEmpty($cache);
        // calling lazy again should still work and the cache remains
        $env->lazy(['MY']);
        $this->assertNotEmpty($prop->getValue());
    }

    public function testSetCacheAndEnvGetter()
    {
        $this->resetEnvState();
        TinyEnv::setCache('X_TEST', '42');
        $this->assertSame(42, TinyEnv::env('X_TEST'));
    }

    public function testLazyLoadWithPrefixAndPersistence()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        $env->lazy(['APP']);

        // lazy loads only keys with APP prefix
        Assert::assertEquals('TinyEnv', TinyEnv::env('APP_NAME'));
        Assert::assertTrue(TinyEnv::env('APP_DEBUG'));

        // Non-APP keys should not be present unless requested
        Assert::assertNull(TinyEnv::env('MY_IP'));
    }

    public function testSafeLoadDoesNotThrowOnMissingFile()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        // Rename .env to simulate missing file
        $bak = $this->envFile . '.bak';
        $renamed = false;
        try {
            if (is_file($this->envFile)) {
                $renamed = @rename($this->envFile, $bak);
            }
            // safeLoad should not throw
            $this->expectNotToPerformAssertions();
            $env->safeLoad();
        } finally {
            if ($renamed && is_file($bak)) {
                @rename($bak, $this->envFile);
            }
        }
    }

    public function testSysenvWrapperReturnsString()
    {
        $this->resetEnvState();
        // sysenv should return string even if getenv returns false
        $val = TinyEnv::sysenv('NON_EXISTENT_SYS_VAR');
        Assert::assertIsString($val);
    }

    /**
     * @return void|null
     */
    public function testLoadAndGetEnv()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        // use safeLoad to tolerate the recursive entries in the fixture
        $env->safeLoad();

        // Basic string and boolean (note: parseValue converts 'true' string to boolean true)
        Assert::assertEquals('TinyEnv', TinyEnv::env('APP_NAME'));
        Assert::assertTrue(TinyEnv::env('APP_DEBUG'));

        // Not exist returns null by default
        Assert::assertNull(TinyEnv::env('NOT_EXIST'));

        // Passing default persists the default into $_ENV/cache per current implementation
        $val = TinyEnv::env('NOT_EXIST', 'default');
        Assert::assertEquals('default', $val);
        // Subsequent read should return the persisted value (and not the fallback)
        Assert::assertEquals('default', TinyEnv::env('NOT_EXIST'));

        // Numeric parsing: MY_TEXT in .env is 8.7 -> float
        Assert::assertSame(8.7, TinyEnv::env('MY_TEXT'));

        // Empty value is parsed to null
        Assert::assertNull(TinyEnv::env('EMPTY_VALUE'));

        // Explicit "null" is parsed to null
        Assert::assertNull(TinyEnv::env('NULL_VALUE'));

        // Interpolated value uses MY_IP
        Assert::assertEquals('127.0.0.1_suffix', TinyEnv::env('INTERPOLATED_VALUE'));

        // Defaulted value using ${UNDEFINED_VAR:-yes}
        Assert::assertEquals('yes', TinyEnv::env('DEFAULTED_VALUE'));
    }

    /**
     * @return void
     */
    public function testLoadThrowExceptionOnMissingFile()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        // Expect RuntimeException when .env is missing
        $this->expectException(\RuntimeException::class);

        $bak = $this->envFile . '.bak';
        $renamed = false;
        try {
            // Simulate missing .env by renaming it temporarily (if it exists)
            if (is_file($this->envFile)) {
                $renamed = @rename($this->envFile, $bak);
            }
            // Execute load() to trigger Exception due to missing .env
            $env->load();
        } finally {
            // Restore .env if it was renamed
            if ($renamed && is_file($bak)) {
                @rename($bak, $this->envFile);
            }
        }
    }
}
