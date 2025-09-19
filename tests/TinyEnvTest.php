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

    /**
     * @return void|null
     */
    public function testLoadAndGetEnv()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        $env->load();

        Assert::assertEquals('TinyEnvTest', TinyEnv::env('APP_NAME'));
        Assert::assertEquals('true', TinyEnv::env('APP_DEBUG'));
        Assert::assertNull(TinyEnv::env('NOT_EXIST'));
        Assert::assertEquals('default', TinyEnv::env('NOT_EXIST', 'default'));
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

    /**
     * @return void
     */
    public function testLazyLoadWithPrefix()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        $env->lazy(['APP']);

        Assert::assertEquals('TinyEnvTest', TinyEnv::env('APP_NAME'));
        Assert::assertEquals('true', TinyEnv::env('APP_DEBUG'));
        Assert::assertNull(TinyEnv::env('NOT_EXIST'));
    }

    /**
     * @return void
     */
    public function testSafeLoadDoesNotThrowOnMissingFile()
    {
        $env = new TinyEnv(__DIR__ . '/..');
        // Đổi tên file .env sang .env.bak để giả lập file không tồn tại
        rename($this->envFile, $this->envFile . '.bak');

        // Đảm bảo không xóa file .env
        $this->expectNotToPerformAssertions();
        $env->safeLoad();
        // Đổi lại tên file .env.bak về .env
        rename($this->envFile . '.bak', $this->envFile);
    }
}