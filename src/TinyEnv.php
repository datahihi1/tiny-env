<?php

namespace Datahihi1\TinyEnv;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    protected $rootDirs;
    protected $onlyEnvFile;

    /**
     * Constructor: Initializes the TinyEnv instance with the given root directories.
     *
     * @param string|array $rootDirs The root directory to load files from.
     * @param bool $onlyEnvFile Whether to load only `.env` files and skip `.ini` files.
     */
    public function __construct($rootDirs, $onlyEnvFile = false)
    {
        $this->rootDirs = is_array($rootDirs) ? $rootDirs : array($rootDirs);
        $this->onlyEnvFile = $onlyEnvFile;
    }

    /**
     * Main loader: Initializes the application by loading required files.
     * Specifically, it loads environment-specific configuration files 
     * and other supplementary files necessary for the application's operation.
     * If .env is not found, .env.example is used.
     *
     * @return void
     */
    public function load()
    {
        foreach ($this->rootDirs as $dir) {
            $envLoaded = $this->loadEnvFile($dir . DIRECTORY_SEPARATOR . '.env');

            if (!$envLoaded && !$this->onlyEnvFile) {
                $this->loadEnvFile($dir . DIRECTORY_SEPARATOR . '.env.example');
            }

            if (!$this->onlyEnvFile) {
                $this->loadIniFile($dir . DIRECTORY_SEPARATOR . '.ini');
            }
        }
    }

    /**
     * Parse a .env file and store the variables in the $_ENV array.
     * Skips comments and invalid lines.
     *
     * @param string $file Path to the .env file.
     * @return true
     */
    protected function loadEnvFile($file)
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"");

            $_ENV[$key] = $value;
        }
        return true;
    }

    /**
     * Parse a .ini file and store the variables in the $_ENV array.
     * Sectioned keys are converted to uppercase and prefixed.
     *
     * @param string $file Path to the .ini file.
     * @return true
     */
    protected function loadIniFile($file)
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        $data = parse_ini_file($file, true);
        foreach ($data as $section => $values) {
            foreach ((array) $values as $key => $value) {
                $envKey = strtoupper($section . '_' . $key);
                if (!isset($_ENV[$envKey])) {
                    $_ENV[$envKey] = $value;
                }
            }
        }
        return true;
    }

    /**
     * Retrieve an environment variable by key.
     * If the key is null, the entire $_ENV array is returned.
     * Provides a default value if the key does not exist.
     *
     * @param string|null $key The key of the environment variable.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or the default value(`$default`).
     */
    public static function env($key = null, $default = null)
    {
        if ($key === null) {
            return $_ENV;
        }
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * Set or update an environment variable dynamically and persist it in available files.
     * Handles .env formats, creating files if necessary.
     * Ensure proper file permissions when writing to files.
     *
     * @param string $key The key of the environment variable to set.
     * @param mixed $value The value to set for the environment variable.
     * @return void
     */
    public static function setenv($key, $value = null)
    {
        $key = trim($key);
        $value = trim($value);

        $_ENV[$key] = $value;

        $envFile = '.env';
        if (file_exists($envFile) && is_writable($envFile)) {
            $content = file_get_contents($envFile);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "$key=$value", $content);
            } else {
                $content .= "\n$key=$value";
            }

            file_put_contents($envFile, $content, LOCK_EX);
        }
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

if (!function_exists('setenv')) {
    /**
     * setenv() function to set or update environment variables from .env (will create if not exist).
     * @param mixed $key The environment variable key to set.
     * @param mixed $value The value to set for the environment variable.
     * @return void
     */
    function setenv($key, $value = null)
    {
        TinyEnv::setenv($key, $value);
    }
}