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
     * Constructor: Initializes the TinyEnv instance with the given root directories.
     *
     * @param string|string[] $rootDirs The root directory to load files from.
     * @param bool $fastLoad Whether to load the environment variables immediately.
     * @param string[]|null $lazyPrefixes Array of prefixes for lazy loading.
     */
    public function __construct($rootDirs, bool $fastLoad = false, ?array $lazyPrefixes = null)
    {
        $this->rootDirs = is_array($rootDirs) ? $rootDirs : [$rootDirs];
        if ($fastLoad && $lazyPrefixes === null) {
            $this->load();
        } elseif ($lazyPrefixes !== null) {
            $this->lazy($lazyPrefixes);
        }
    }

    /**
     * Enable or disable file writing globally.
     *
     * @param bool $allow Whether to allow file writing.
     * @return void
     */
    public static function setAllowFileWrites(bool $allow): void
    {
        self::$allowFileWrites = $allow;
    }

    /**
     * Main loader: Loads environment variables from configuration files.
     * This method scans the specified root directories for `.env` files,
     * and loads their contents into the `$_ENV` array.
     *
     * @return self For method chaining
     */
    public function load(): self
    {
        foreach ($this->rootDirs as $dir) {
            $this->loadEnvFile($dir . DIRECTORY_SEPARATOR . '.env');
        }
        return $this;
    }

    /**
     * Lazy load specific environment variables based on prefixes.
     *
     * @param string[] $prefixes Array of prefixes to load (e.g., ['DB', 'KEY'])
     * @param bool $reset If true, clears existing variables before loading
     * @return self
     * @throws Exception If file operations fail
     */
    public function lazy(array $prefixes, bool $reset = false): self
    {
        if ($reset) {
            $this->unload();
        }

        foreach ($this->rootDirs as $dir) {
            $file = $dir . DIRECTORY_SEPARATOR . '.env';
            if (!is_file($file)) {
                throw new Exception("Environment file not found: $file");
            }
            if (!is_readable($file)) {
                throw new Exception("Environment file is not readable: $file");
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                throw new Exception("Failed to read environment file: $file");
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"");

                foreach ($prefixes as $prefix) {
                    if (strpos($key, $prefix) === 0) {
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
     * Unloads environment variables by clearing the $_ENV array and cache.
     *
     * @return self
     */
    public function unload(): self
    {
        foreach (self::$cache as $key => $value) {
            unset($_ENV[$key]);
        }
        self::$cache = [];
        return $this;
    }

    /**
     * Refreshes the environment variables by reloading the .env files and updating the cache.
     * @return self
     */
    public function refresh(): self
    {
        $this->unload();
        $this->load();
        return $this;
    }

    /**
     * Loads environment variables from a .env file into the $_ENV array.
     * This method reads the specified .env file, skipping comments and invalid lines,
     * and stores the key-value pairs as environment variables in the $_ENV array.
     *
     * @param string $file Path to the .env file.
     * @return bool True if the file was successfully loaded, false otherwise.
     * @throws Exception If the file is not found or not readable.
     */
    protected function loadEnvFile(string $file): bool
    {
        if (!is_file($file)) {
            throw new Exception("Environment file not found: $file");
        }
        if (!is_readable($file)) {
            throw new Exception("Environment file is not readable: $file");
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new Exception("Failed to read environment file: $file");
        }

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
    public static function env(?string $key = null, $default = null)
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
     * @throws Exception If the file is not writable or cannot be created.
     */
    public static function setenv(string $key, $value = null): void
    {
        $key = trim($key);
        $originalValue = $value;

        if (is_bool($value)) {
            $fileValue = $value ? 'true' : 'false';
        } elseif (is_scalar($value) || is_null($value)) {
            $fileValue = trim((string) $value);
        } else {
            throw new Exception("Cannot cast non-scalar value to string for key '$key'");
        }

        if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
            throw new Exception("Invalid environment variable key: $key");
        }

        $_ENV[$key] = $originalValue;
        self::$cache[$key] = $originalValue;

        if (!self::$allowFileWrites) {
            return;
        }

        $envFile = '.env';
        try {
            if (file_exists($envFile)) {
                if (!is_writable($envFile)) {
                    throw new Exception("Environment file is not writable: $envFile");
                }
                $content = file_get_contents($envFile);
                if ($content === false) {
                    throw new Exception("Failed to read environment file: $envFile");
                }
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, "$key=$fileValue", $content);
                } else {
                    $content .= "\n$key=$fileValue";
                }
                file_put_contents($envFile, $content, LOCK_EX);
            } elseif (is_writable(dirname($envFile))) {
                $content = "$key=$fileValue\n";
                file_put_contents($envFile, $content, LOCK_EX);
            } else {
                throw new Exception("Cannot create environment file: $envFile");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    /**
     * Set the value of a cached environment variable by key.
     *
     * @param string $key The key of the cached environment variable.
     * @param mixed $value The value to set.
     * @return void
     */
    public static function setCache(string $key, $value): void
    {
        self::$cache[$key] = $value;
    }

    /**
     * Validate environment variables against specified rules.
     *
     * @param array<string, string|string[]> $rules Array of rules, e.g., ['DB_PORT' => 'required|int|min:1']
     * @throws Exception If validation fails
     * @return void
     */
    public static function validate(array $rules): void
    {
        Validator::validate($rules);
    }
}

if (!function_exists('env')) {
    /**
     * Get the value of an environment variable by key.
     * If the key is null, the entire $_ENV array is returned.
     * Provides a default value if the key does not exist.
     *
     * @param string|null $key The key of the environment variable.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or the default value(`$default`).
     */
    function env(?string $key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}

if (!function_exists('setenv')) {
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
    function setenv(string $key, $value = null): void
    {
        TinyEnv::setenv($key, $value);
    }
}