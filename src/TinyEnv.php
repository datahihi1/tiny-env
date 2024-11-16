<?php

namespace Datahihi1\TinyEnv;

class TinyEnv
{
    protected $envFile = '.env';
    protected $exampleEnvFile = '.env.example';
    protected $additionalFiles = ['env.php', '.ini'];
    protected $rootDir;

    public function __construct($rootDir)
    {
        if (!is_dir($rootDir)) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid directory', $rootDir));
        }

        $this->rootDir = realpath($rootDir);
    }

    public function load()
    {
        // Load variables from all relevant files
        $this->loadEnvFiles($this->rootDir);
        $this->loadAdditionalFiles($this->rootDir);
    }

    protected function loadEnvFiles($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();

                if ($filename === $this->envFile || $filename === $this->exampleEnvFile) {
                    $this->loadFile($file->getRealPath(), $filename !== $this->exampleEnvFile); // Don't overwrite with .env.example
                }
            }
        }
    }

    protected function loadAdditionalFiles($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();

                if ($filename === 'env.php') {
                    $this->loadPhpEnvFile($file->getRealPath());
                } elseif ($this->endsWith($filename, '.env')) {
                    $this->loadFile($file->getRealPath());
                } elseif ($this->endsWith($filename, '.ini')) {
                    $this->loadIniFile($file->getRealPath());
                }
            }
        }
    }

    protected function loadFile($envFile, $overwrite = true)
    {
        if (!is_readable($envFile)) {
            error_log(sprintf('Warning: %s file is not readable.', $envFile));
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            }

            if ($overwrite || !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }

    protected function loadPhpEnvFile($filePath)
    {
        if (!is_readable($filePath)) {
            error_log(sprintf('Warning: %s file is not readable.', $filePath));
            return;
        }

        include $filePath;

        foreach (get_defined_constants(true)['user'] as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    protected function loadIniFile($filePath)
    {
        if (!is_readable($filePath)) {
            error_log(sprintf('Warning: %s file is not readable.', $filePath));
            return;
        }

        $data = parse_ini_file($filePath, true);
        foreach ($data as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $envKey = strtoupper($section . '_' . $key);
                    $_ENV[$envKey] = $value;
                }
            } else {
                $_ENV[$section] = $values;
            }
        }
    }

    public static function env($key = null, $default = null)
    {
        if ($key === null) {
            return $_ENV;
        }
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    public static function getenv($key = null, $default = null)
    {
        return self::env($key, $default);
    }

    public static function putenv($key, $value)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException("Environment variable key cannot be empty.");
        }

        $_ENV[$key] = $value;
        \putenv("{$key}={$value}");
    }

    private function endsWith($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
