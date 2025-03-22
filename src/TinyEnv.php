<?php

namespace Datahihi1\TinyEnv;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    protected $rootDirs;
    protected static $cache = [];

    /**
     * Constructor: Initializes the TinyEnv instance with the given root directories.
     *
     * @param string|array $rootDirs The root directory to load files from.
     * @param bool $fastLoad Whether to load the environment variables immediately.
     */
    public function __construct($rootDirs, $fastLoad = false)
    {
        $this->rootDirs = is_array($rootDirs) ? $rootDirs : array($rootDirs);
        if ($fastLoad) {
            $this->load();
        }
    }

    /**
     * Main loader: Loads environment variables from configuration files.
     * This method scans the specified root directories for `.env` files,
     * and loads their contents into the `$_ENV` array.
     *
     * @return void
     */
    public function load()
    {
        foreach ($this->rootDirs as $dir) {
            $this->loadEnvFile($dir . DIRECTORY_SEPARATOR . '.env');
        }
    }

    /**
     * Unloads environment variables by clearing the $_ENV array and cache.
     *
     * @return void
     */
    public function unload()
    {
        foreach (self::$cache as $key => $value) {
            unset($_ENV[$key]);
        }
        self::$cache = [];
    }

    /**
     * Loads environment variables from a .env file into the $_ENV array.
     * This method reads the specified .env file, skipping comments and invalid lines,
     * and stores the key-value pairs as environment variables in the $_ENV array.
     *
     * @param string $file Path to the .env file.
     * @return bool True if the file was successfully loaded, false otherwise.
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

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"");

            $_ENV[$key] = $value;
            self::$cache[$key] = $value;
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
        return self::$cache[$key] ?? $_ENV[$key] ?? $default;
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
        self::$cache[$key] = $value;

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
        } elseif (!file_exists($envFile) && is_writable(dirname($envFile))) {
            $content = "$key=$value\n";
            file_put_contents($envFile, $content, LOCK_EX);
        }
    }
}

if (!function_exists('env')) {
    /**
     * env() function for accessing environment variables from .env files.
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
