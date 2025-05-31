<?php

namespace Datahihi1\TinyEnv;

use Exception;
use Datahihi1\TinyEnv\Validator;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    /** @var string[] */
    protected $rootDirs;
    /** @var array<string, mixed> */
    protected static $cache = [];
    /** @var bool */
    protected static $allowFileWrites = true;

    /**
     * Constructor to initialize the TinyEnv instance.
     *
     * @param string|string[] $rootDirs The root directory to load files from.
     * @param bool $fastLoad Whether to load the environment variables immediately.
     * @param array<int, string>|null $lazyPrefixes Array of prefixes for lazy loading.
     */
    public function __construct($rootDirs, bool $fastLoad = false, ?array $lazyPrefixes = null)
    {
        $this->rootDirs = (array)$rootDirs;
        if ($fastLoad && $lazyPrefixes === null) $this->load();
        elseif ($lazyPrefixes !== null) $this->lazy($lazyPrefixes);
    }

    /**
     * Set whether file writes are allowed.
     * 
     * Can be used to disable writing to .env files.
     *
     * @param bool $allow
     */
    public static function setAllowFileWrites(bool $allow): void
    {
        self::$allowFileWrites = $allow;
    }

    /**
     * Load environment variables from .env files in the specified root directories.
     * 
     * If the file does not exist or is not readable, an exception is thrown.
     *
     * @return self
     * @throws Exception If the .env file cannot be read.
     */
    public function load(): self
    {
        foreach ($this->rootDirs as $dir) $this->loadEnvFile($dir . DIRECTORY_SEPARATOR . '.env');
        return $this;
    }

    /**
     * Load environment variables lazily based on specified prefixes.
     * This method will only load variables that contain the specified prefixes.
     * If reset is true, it will unload existing variables before loading new ones.
     *
     * @param array<int, string> $prefixes Array of prefixes to filter environment variables.
     * @param bool $reset Whether to reset the existing environment variables before loading.
     * @return self
     * @throws Exception If the .env file cannot be read.
     */
    public function lazy(array $prefixes, bool $reset = false): self
    {
        if ($reset) $this->unload();
        foreach ($this->rootDirs as $dir) {
            $file = $dir . DIRECTORY_SEPARATOR . '.env';
            if (!is_file($file) || !is_readable($file)) throw new Exception("Cannot read: $file");
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) throw new Exception("Failed to read: $file");
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"");
                foreach ($prefixes as $prefix) {
                    // $prefix is always string due to PHPDoc, so remove is_string()
                    if (strpos($key, $prefix) !== false) {
                        $_ENV[$key] = $value;
                        self::$cache[$key] = $value;
                        break;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Load only specific environment variables by their keys.
     * If reset is true, it will unload existing variables before loading new ones.
     *
     * @param array<int, string>|string $keys The key or array of keys to load.
     * @param bool $reset Whether to reset the existing environment variables before loading.
     * @return self
     * @throws Exception If the .env file cannot be read.
     */
    public function only($keys, bool $reset = false): self
    {
        $keys = (array)$keys;
        if ($reset) $this->unload();
        foreach ($this->rootDirs as $dir) {
            $file = $dir . DIRECTORY_SEPARATOR . '.env';
            if (!is_file($file) || !is_readable($file)) throw new Exception("Cannot read: $file");
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) throw new Exception("Failed to read: $file");
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if (!in_array($key, $keys, true)) continue;
                $value = trim($value, " \t\n\r\0\x0B\"");
                $_ENV[$key] = $value;
                self::$cache[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Unload all environment variables loaded by TinyEnv.
     * This will clear the $_ENV array and the internal cache.
     *
     * @return self
     */
    public function unload(): self
    {
        foreach (self::$cache as $key => $_) unset($_ENV[$key]);
        self::$cache = [];
        return $this;
    }

    /**
     * Refresh the environment variables by unloading and then loading them again.
     * This is useful if the environment variables have changed and you want to reload them.
     *
     * @return self
     */
    public function refresh(): self
    {
        return $this->unload()->load();
    }

    /**
     * Load environment variables from a specific .env file.
     * This method reads the file line by line, ignoring comments and empty lines,
     * and populates the $_ENV array and the internal cache with the key-value pairs.
     *
     * @param string $file The path to the .env file.
     * @return bool True if the file was loaded successfully.
     * @throws Exception If the file cannot be read or is not readable.
     */
    protected function loadEnvFile(string $file): bool
    {
        if (!is_file($file) || !is_readable($file)) throw new Exception("Cannot read: $file");
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) throw new Exception("Failed to read: $file");
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"");
            $_ENV[$key] = $value;
            self::$cache[$key] = $value;
        }
        return true;
    }

    /**
     * Get the value of an environment variable by key.
     * If the key is null, the entire $_ENV array is returned.
     * Provides a default value if the key does not exist.
     *
     * @param string|null $key The key of the environment variable.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or the default value($default).
     */
    public static function env(?string $key = null, $default = null)
    {
        return $key === null ? $_ENV : (self::$cache[$key] ?? $_ENV[$key] ?? $default);
    }

    /**
     * Set or update an environment variable dynamically and persist it in available files.
     * Handles .env formats, creating files if necessary.
     * Ensure proper file permissions when writing to files.
     *
     * @param string $key The key of the environment variable to set.
     * @param mixed $value The value to set for the environment variable.
     * @return void
     * @throws Exception If the file is not writable or cannot be created.
     */
    public static function setenv(string $key, $value = null): void
    {
        $key = trim($key);
        if (!preg_match('/^[A-Z0-9_]+$/', $key)) throw new Exception("Invalid key: $key");
        if (is_bool($value)) {
            $fileValue = $value ? 'true' : 'false';
        } elseif (is_scalar($value) || $value === null) {
            $fileValue = trim((string)$value);
        } else {
            throw new Exception("Cannot cast value for '$key'");
        }
        $_ENV[$key] = $value;
        self::$cache[$key] = $value;
        if (!self::$allowFileWrites) return;
        $envFile = '.env';
        try {
            if (file_exists($envFile)) {
                if (!is_writable($envFile)) throw new Exception("Not writable: $envFile");
                $content = file_get_contents($envFile);
                if ($content === false) throw new Exception("Failed to read: $envFile");
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
                $content = preg_match($pattern, $content) ? preg_replace($pattern, "$key=$fileValue", $content) : $content . "\n$key=$fileValue";
                file_put_contents($envFile, $content, LOCK_EX);
            } elseif (is_writable(dirname($envFile))) {
                file_put_contents($envFile, "$key=$fileValue\n", LOCK_EX);
            } else throw new Exception("Cannot create: $envFile");
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    /**
     * Set a value in the internal cache.
     * This method is used to store values that are not necessarily environment variables.
     *
     * @param string $key The key to set in the cache.
     * @param mixed $value The value to set in the cache.
     */
    public static function setCache(string $key, $value): void
    {
        self::$cache[$key] = $value;
    }

    /**
     * Validate the environment variables using the provided rules.
     * If validation fails, an exception is thrown with the error messages.
     *
     * @param array<string, array<string>|string> $rules The validation rules.
     * @return void
     * @throws Exception If validation fails.
     */
    public static function validate(array $rules): void
    {
        Validator::validate($rules);
    }
}