<?php

use Datahihi1\TinyEnv\TinyEnv;

class TinyEnvFuzzTest extends \PHPUnit\Framework\TestCase
{
    private $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyenv_fuzz_' . uniqid();
        mkdir($this->tmpDir);
        // make randomness deterministic for reproducible runs
        mt_srand(12345);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . DIRECTORY_SEPARATOR . '.env');
        @rmdir($this->tmpDir);
    }

    private function writeEnv(array $lines): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . '.env';
        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
        return $path;
    }

    public function testFuzzLowLevel_noException()
    {
        // simple key=value pairs; ensure parser accepts many simple forms
        $lines = [];
        for ($i = 0; $i < 150; $i++) {
            $k = 'K' . $i;
            $v = (mt_rand(0, 1) ? (mt_rand(-1000, 1000)) : (mt_rand(0, 1) ? (mt_rand(0, 100) / 10) : bin2hex(random_bytes(3))));
            $lines[] = $k . '=' . $v;
        }
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);
        // should not throw for well-formed simple lines
        $env->load();

        // sample a few keys
        $this->assertNotNull(TinyEnv::env('K0'));
        $this->assertNotNull(TinyEnv::env('K10'));
        $this->assertNotNull(TinyEnv::env('K149'));
    }

    public function testFuzzLow_quotedValues()
    {
        // create keys with various quoted values
        $lines = [
            'NO_QUOTE=no quote value',
            "SINGLE_QUOTE='single quoted value'",
            'DOUBLE_QUOTE="double quoted value"',
            'EMPTY=""',
            "SPACED='   '"
        ];
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);
        $env->load();

        $this->assertSame('no quote value', TinyEnv::env('NO_QUOTE'));
        $this->assertSame('single quoted value', TinyEnv::env('SINGLE_QUOTE'));
        $this->assertSame('double quoted value', TinyEnv::env('DOUBLE_QUOTE'));
        $this->assertNull(TinyEnv::env('EMPTY'));
        $this->assertNull(TinyEnv::env('SPACED'));
    }
    
    public function testFuzzMedium_mailformedLinesHandled()
    {
        $lines = [
            'GOOD1=ok',
            'BADLINE',
            'GOOD2=also ok',
            'ANOTHERBAD==oops',
            'GOOD3="fine too"',
            'NOEQUALSIGNLINE',
            'GOOD4=lastgood'
        ];
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);
        $this->expectException(Exception::class);
        $env->load();
    }

    public function testFuzzMedium_variableSubstitution()
    {
        // create keys that reference earlier keys (valid substitutions)
        $lines = [
            'A=hello',
            'B=world',
            'C=${A}_${B}',
            'D=${C}_end',
            'NUM=42',
            'E=pre${NUM}post'
        ];
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);
        $env->load();

        $this->assertSame('hello', TinyEnv::env('A'));
        $this->assertSame('world', TinyEnv::env('B'));
        $this->assertSame('hello_world', TinyEnv::env('C'));
        $this->assertSame('hello_world_end', TinyEnv::env('D'));
        $this->assertSame('pre42post', TinyEnv::env('E'));
    }

    public function testFuzzHigh_dangerousRejected()
    {
        // include one dangerous pattern that should be rejected
        $lines = [
            'SAFE=ok',
            'EVIL=php://filter/convert.base64-encode/resource=php://input',
            'AFTER=ok2'
        ];
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);

        $thrown = false;
        try {
            $env->load();
        } catch (\Exception $e) {
            $thrown = true;
            $this->assertStringContainsString('rejected dangerous env value', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Dangerous value should cause an exception');
    }

    public function testFuzzMax_substitutionDepthExceeded()
    {
        // build a long chain longer than MAX_SUBSTITUTION_DEPTH (10)
        $chainLen = 12;
        $lines = [];
        for ($i = 0; $i < $chainLen; $i++) {
            $next = ($i + 1) % $chainLen;
            $lines[] = "V{$i}=\${V{$next}}";
        }
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);

        $this->expectException(\Exception::class);
        // message may be 'substitution depth exceeded' or recursive detection
        $env->load();
    }

    public function testFuzzCycle_detection()
    {
        // explicit small cycle A -> B -> C -> A
        $lines = [
            'VAR1=${VAR2}',
            'VAR2=${VAR3}',
            'VAR3=${VAR1}',
        ];
        $this->writeEnv($lines);

        $env = new TinyEnv($this->tmpDir);
        $this->expectException(Exception::class);
        $env->load();
    }
}