<?php

use Datahihi1\TinyEnv\TinyEnv;

class TinyEnvSecurityTest extends \PHPUnit\Framework\TestCase
{
    private $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyenv_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        // cleanup created .env
        @unlink($this->tmpDir . DIRECTORY_SEPARATOR . '.env');
        @rmdir($this->tmpDir);
    }

    public function testDangerousStreamWrapperRejected()
    {
        // create an env with a dangerous php:// payload
        $payload = "EVIL=php://filter/convert.base64-encode/resource=php://input" . PHP_EOL;
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . '.env', $payload);

        $env = new TinyEnv($this->tmpDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/rejected dangerous env value/');
        // use load() which will attempt to parse and should throw
        $env->load();
    }

    public function testRecursiveSubstitutionChainDetected()
    {
        // create an env that forms a cycle A -> B -> C -> A
        $data = "A=\${B}\nB=\${C}\nC=\${A}\n";
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . '.env', $data);

        $env = new TinyEnv($this->tmpDir);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/recursive variable substitution|substitution depth exceeded/');
        $env->load();
    }
}
