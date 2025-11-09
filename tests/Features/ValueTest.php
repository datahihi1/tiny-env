<?php

use PHPUnit\Framework\Assert;
use Datahihi1\TinyEnv\TinyEnv;

class ValueTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $envFile;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        // Direction to the .env file in the project root
        $keys = [
            'APP_NAME',
            'APP_DEBUG',
            'S_EVIL',
            'MY_IP',
            'MY_TEXT',
            'EMPTY_VALUE',
            'NULL_VALUE',
            'INTERPOLATED_VALUE',
            'DEFAULTED_VALUE',
            'USER_NAME',
            'USER',
            'ALT_USER',
            'DB_HOST',
            'PORT',
            'FLOAT_VALUE'
        ];
        $this->envFile = __DIR__ . '/../../.env';
        $env = new TinyEnv(__DIR__ . '/../../');
        $env->populateSuperglobals(true);
        $env->envfiles([".env"]);
        $env->load($keys);
    }

    protected function tearDown(): void
    {
        // Nothing
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

    public function testValueTypes()
    {
        // Test various value types from the .env file
        Assert::assertSame('TinyEnv', env('APP_NAME')); // string value
        Assert::assertSame(8.7, env('MY_TEXT'));
        Assert::assertTrue(env('APP_DEBUG'));
        Assert::assertFalse(env('FEATURE_ENABLED', false));
        Assert::assertNull(env('NULL_VALUE'));
        Assert::assertSame('default', env('MISSING_KEY', 'default'));

        // Test s_env function
        Assert::assertSame('TinyEnv', s_env('APP_NAME'));
        Assert::assertSame('8.7', s_env('MY_TEXT'));
        Assert::assertSame('1', s_env('APP_DEBUG'));
        Assert::assertSame('', s_env('NULL_VALUE'));
        Assert::assertSame('default', s_env('MISSING_KEY', 'default'));
    }

    public function testSuperglobalPopulation()
    {
        // Test that superglobals were populated
        $this->assertSame('TinyEnv', $_ENV['APP_NAME']);
        $this->assertSame(8.7, $_ENV['MY_TEXT']);
        $this->assertTrue($_ENV['APP_DEBUG']);
        $this->assertFalse($_ENV['FEATURE_ENABLED'] ?? false);
        $this->assertNull($_ENV['NULL_VALUE'] ?? null);
    }

    public function testCacheWithSetAndClear()
    {
        $this->resetEnvState();
        // Set a value
        TinyEnv::setCache('CACHE_TEST', 'cached_value');
        $this->assertSame('cached_value', TinyEnv::env('CACHE_TEST'));

        // Clear cache
        TinyEnv::clearCache('CACHE_TEST');
        $this->assertNull(TinyEnv::env('CACHE_TEST'));
    }
}
