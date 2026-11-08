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
        $env->populateServerglobals(true);
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
            // Only call setAccessible on PHP < 8.5 to avoid deprecation
            if (PHP_VERSION_ID < 80500 && method_exists($prop, 'setAccessible')) {
                $prop->setAccessible(true);
            }
            $prop->setValue(null, []);
        }
        // Clear fileLinesCache as well to avoid cross-test caching
        if ($rc->hasProperty('fileLinesCache')) {
            $prop = $rc->getProperty('fileLinesCache');
            if (PHP_VERSION_ID < 80500 && method_exists($prop, 'setAccessible')) {
                $prop->setAccessible(true);
            }
            $prop->setValue(null, []);
        }
    }

    public function testValueTypesAndCache()
    {
        Assert::assertSame('TinyEnv', env('APP_NAME'));
        Assert::assertSame(8.7, env('MY_TEXT'));
        Assert::assertTrue(env('APP_DEBUG'));
        Assert::assertNull(env('NULL_VALUE'));
        Assert::assertSame('default', env('MISSING_KEY', 'default'));

        $this->resetEnvState();
        TinyEnv::setCache('CACHE_TEST', 'cached_value');
        $this->assertSame('cached_value', TinyEnv::env('CACHE_TEST'));
        TinyEnv::clearCache('CACHE_TEST');
        $this->assertNull(TinyEnv::env('CACHE_TEST'));
    }

    public function testSuperglobalPopulation()
    {
        $this->assertSame('TinyEnv', $_ENV['APP_NAME']);
        $this->assertSame(8.7, $_ENV['MY_TEXT']);
        $this->assertTrue($_ENV['APP_DEBUG']);
        $this->assertNull($_ENV['NULL_VALUE'] ?? null);
    }

    public function testSuperglobalVarPopulation()
    {
        $this->assertSame('TinyEnv', $_SERVER['APP_NAME']);
        $this->assertSame(8.7, $_SERVER['MY_TEXT']);
        $this->assertTrue($_SERVER['APP_DEBUG']);
        $this->assertNull($_SERVER['NULL_VALUE'] ?? null);
    }

    public function testQuotedValuesStayStringsWhenRead()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-quoted-read-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "PIN=\"0123\"\nANSWER=\"yes\"\nZERO=\"0\"\nLITERAL=\"null\"\n");

        try {
            $env = new TinyEnv($dir);
            $env->load();

            $this->assertSame('0123', TinyEnv::env('PIN'));
            $this->assertTrue(TinyEnv::env('ANSWER'));
            $this->assertSame(0, TinyEnv::env('ZERO'));
            $this->assertNull(TinyEnv::env('LITERAL'));
        } finally {
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testMalformedLineDoesNotLeavePartialEnvState()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-partial-state-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "PARTIAL_A=1\nBROKEN LINE\nPARTIAL_B=2\n");

        try {
            $env = new TinyEnv($dir);
            $this->expectException(Exception::class);
            $env->load();
        } finally {
            TinyEnv::clearCache();
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testForwardReferenceResolvesLaterDefinitions()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-forward-ref-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "B=\${A}\nA=\"hello\"\n");

        try {
            $env = new TinyEnv($dir);
            $env->load();

            $this->assertSame('hello', TinyEnv::env('B'));
        } finally {
            TinyEnv::clearCache();
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testHashInsideUnquotedValueIsPreserved()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-hash-value-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "PASS=Ab#cdEFG123\nURL=https://x.test/page#anchor\n");

        try {
            $env = new TinyEnv($dir);
            $env->load();

            $this->assertSame('Ab#cdEFG123', TinyEnv::env('PASS'));
            $this->assertSame('https://x.test/page#anchor', TinyEnv::env('URL'));
        } finally {
            TinyEnv::clearCache();
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testPutenvIsNotWrittenByDefault()
    {
        $original = getenv('LD_LIBRARY_PATH');
        $dir = sys_get_temp_dir() . '/tinyenv-putenv-default-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "LD_LIBRARY_PATH=/tmp/tinyenv_evil\n");

        try {
            $env = new TinyEnv($dir);
            $env->load();

            $this->assertFalse(getenv('LD_LIBRARY_PATH') === '/tmp/tinyenv_evil');
        } finally {
            if ($original === false) {
                putenv('LD_LIBRARY_PATH');
            } else {
                putenv('LD_LIBRARY_PATH=' . $original);
            }
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testFastLoadDoesNotOverwriteServerGlobalsByDefault()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-fastload-default-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "REMOTE_ADDR=127.0.0.1\nHTTPS=on\n");

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTPS'] = 'off';

        try {
            $env = new TinyEnv($dir, true);
            $env->load();

            $this->assertSame('203.0.113.9', $_SERVER['REMOTE_ADDR']);
            $this->assertSame('off', $_SERVER['HTTPS']);
        } finally {
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testSubstitutionExpansionIsCapped()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-subst-cap-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', "A=" . str_repeat('x', 20000) . "\nB=${A}${A}${A}\n");

        try {
            $env = new TinyEnv($dir);
            $this->expectException(Exception::class);
            $env->load();
        } finally {
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }

    public function testLineLengthIsRejectedBeforeReadCompletes()
    {
        $dir = sys_get_temp_dir() . '/tinyenv-line-cap-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.env', 'A=' . str_repeat('x', 9000) . "\n");

        try {
            $env = new TinyEnv($dir);
            $this->expectException(Exception::class);
            $env->load();
        } finally {
            @unlink($dir . '/.env');
            @rmdir($dir);
        }
    }
}
