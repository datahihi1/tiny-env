<?php

use Datahihi1\TinyEnv\TinyEnv;

class FileChangeDuringReadTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test helper subclass which replays TinyEnv::loadEnvFile logic but
     * replaces the target file after performing fstat($fh) and before stat($file)
     * to simulate a file change detected by loadEnvFile.
     */
    public function testFileChangeDetected()
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinyenv_race_' . uniqid();
        mkdir($tmpDir);
        $file = $tmpDir . DIRECTORY_SEPARATOR . '.env';
        file_put_contents($file, "A=1\n");

        $env = new class($tmpDir) extends TinyEnv {
            public function simulateRace(string $file, ?array $filter = null): bool
            {
                $fh = @fopen($file, 'rb');
                if ($fh === false) {
                    throw new \Exception("Cannot read file");
                }
                if (!flock($fh, LOCK_SH)) {
                    fclose($fh);
                    throw new \Exception("Cannot lock file");
                }

                $stat = @fstat($fh);
                if ($stat === false) {
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    throw new \Exception("File changed during read");
                }

                @unlink($file);
                @file_put_contents($file, str_repeat('X', 128) . "\n");

                clearstatcache(true, $file);
                $statPath = @stat($file);
                if ($statPath === false) {
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    throw new \Exception("File changed during read");
                }

                $same = false;
                $statIno = $stat['ino'] ?? 0;
                $statDev = $stat['dev'] ?? 0;
                $pathIno = $statPath['ino'] ?? 0;
                $pathDev = $statPath['dev'] ?? 0;
                if ($statIno !== 0 && $pathIno !== 0) {
                    $same = ($statIno === $pathIno && $statDev === $pathDev);
                }
                if (!$same) {
                    $same = ($stat['size'] === $statPath['size'] && $stat['mtime'] === $statPath['mtime']);
                }

                if (!$same) {
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    throw new \Exception("File changed during read");
                }

                flock($fh, LOCK_UN);
                fclose($fh);
                return true;
            }
        };

        $thrown = false;
        try {
            call_user_func([$env, 'simulateRace'], $file);
        } catch (\Exception $e) {
            $thrown = true;
            $this->assertStringContainsString('File changed during read', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected exception when file is modified during read');

        @unlink($file);
        @rmdir($tmpDir);
    }
}
