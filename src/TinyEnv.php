<?php

namespace Datahihi1\TinyEnv;

/**
 * TinyEnv is a simple environment loader for PHP applications
 */
class TinyEnv
{
    protected $envFile = '.env';
    protected $exampleEnvFile = '.env.example';
    protected $iniFiles = '.ini';
    protected $rootDirs = [];

    /**
     * Constructor: Initializes the TinyEnv instance with the given root directories.
     *
     * @param string|array $rootDirs The root directory (or directories) to load files from.
     * @throws \InvalidArgumentException If the provided directories are invalid.
     */
    public function __construct($rootDirs)
    {
        if (!is_array($rootDirs)) {
            $rootDirs = [$rootDirs];
        }

        foreach ($rootDirs as $dir) {
            if (!is_dir($dir)) {
                throw new \InvalidArgumentException(sprintf('%s is not a valid directory', $dir));
            }
        }

        $this->rootDirs = array_unique(array_map('realpath', $rootDirs));
    }

    /**
     * Main loader: Initializes the application by loading required files.
     * Specifically, it loads environment-specific configuration files 
     * and other supplementary files necessary for the application's operation.
     * 
     * @return void
     */
    public function load()
    {
        $loadedEnv = false;

        foreach ($this->rootDirs as $dir) {
            if (!$loadedEnv) {
                $loadedEnv = $this->loadEnvFiles($dir);
            }
            $this->loadAdditionalFiles($dir);
        }
    }

    /**
     * Loads `.env` and `.env.example` files from the specified directory.
     *
     * @param string $dir The directory to load files from.
     * @return bool True if `.env` file was loaded successfully, false otherwise.
     */
    protected function loadEnvFiles($dir)
    {
        $envFiles = [$this->envFile, $this->exampleEnvFile];
        foreach ($envFiles as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                $this->loadFile($filePath, $file === $this->envFile);

                if ($file === $this->envFile) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Scans the directory for `.env` and `.ini` files and loads them into the environment.
     *
     * @param string $dir The directory to scan and load files from.
     */
    protected function loadAdditionalFiles($dir)
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_file($filePath)) {
                if ($this->endsWith($file, '.env')) {
                    $this->loadFile($filePath);
                } elseif ($this->endsWith($file, '.ini')) {
                    $this->loadIniFile($filePath);
                }
            }
        }
    }

    /**
     * Reads and processes a `.env` file line-by-line, adding variables to `$_ENV`.
     *
     * @param string $envFile The path to the `.env` file.
     * @param bool $overwrite Whether to overwrite existing variables in `$_ENV`.
     */
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

    /**
     * Reads and processes a `.ini` file, converting its sections into environment variables.
     *
     * @param string $filePath The path to the `.ini` file.
     */
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
                    if (!isset($_ENV[$envKey])) {
                        $_ENV[$envKey] = $value;
                    }
                }
            } else {
                if (!isset($_ENV[$section])) {
                    $_ENV[$section] = $values;
                }
            }
        }
    }

    /**
     * Checks if a string ends with a specified substring.
     *
     * @param string $haystack The string to check.
     * @param string $needle The substring to look for.
     * @return bool True if `$haystack` ends with `$needle`, false otherwise.
     */
    private function endsWith($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Retrieves an environment variable's value or all variables if no key is provided.
     *
     * @param string|null $key The environment variable key.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or `$default`.
     */
    public static function env($key = null, $default = null)
    {
        if ($key === null) {
            return $_ENV;
        }
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }
}

if (!function_exists('env')) {
    /**
     * env() function for accessing environment variables from .env, .env.example, .ini files.
     * @param string|null $key The environment variable key.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or $default.
     */
    function env($key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}
