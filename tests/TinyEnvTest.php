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
        // Đường dẫn tới file .env ở thư mục gốc dự án
        $this->envFile = __DIR__ . '/../.env';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        // Không tự động xóa file .env sau khi test
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
            $prop->setValue([]);
        }
        // Ensure .env is loaded fresh in next test
    }

    public function testLazyLoadWithPrefixAndPersistence()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        $env->lazy(['APP']);

        // lazy loads only keys with APP prefix
        Assert::assertEquals('TinyEnvTest', TinyEnv::env('APP_NAME'));
        Assert::assertTrue(TinyEnv::env('APP_DEBUG'));

        // Non-APP keys should not be present unless requested
        Assert::assertNull(TinyEnv::env('MY_IP'));
    }

    public function testSafeLoadDoesNotThrowOnMissingFile()
    {
        $this->resetEnvState();
        $env = new TinyEnv(__DIR__ . '/..');
        // Đổi tên file .env sang .env.bak để giả lập file không tồn tại
        $bak = $this->envFile . '.bak';
        $renamed = false;
        try {
            if (is_file($this->envFile)) {
                $renamed = @rename($this->envFile, $bak);
            }
            // Đảm bảo không ném
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
        $env->load();

        // Basic string and boolean (note: parseValue converts 'true' string to boolean true)
        Assert::assertEquals('TinyEnvTest', TinyEnv::env('APP_NAME'));
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
        // Kỳ vọng ném Exception khi không tìm thấy bất kỳ file .env nào
        $this->expectException(\RuntimeException::class);

        $bak = $this->envFile . '.bak';
        $renamed = false;
        try {
            // Giả lập file .env không tồn tại bằng cách đổi tên nó tạm thời (nếu tồn tại)
            if (is_file($this->envFile)) {
                $renamed = @rename($this->envFile, $bak);
            }
            // Thực thi load() để kích hoạt Exception do không tìm thấy .env
            $env->load();
        } finally {
            // Khôi phục lại file .env nếu đã đổi tên
            if ($renamed && is_file($bak)) {
                @rename($bak, $this->envFile);
            }
        }
    }
}