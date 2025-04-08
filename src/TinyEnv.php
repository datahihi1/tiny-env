<?php

namespace Datahihi1\TinyEnv;

use Exception;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    protected $rootDirs;
    protected static $cache = [];
    protected static $allowFileWrites = true;

    /**
     * Constructor: Initializes the TinyEnv instance with the given root directories.
     *
     * @param string|array $rootDirs The root directory to load files from.
     * @param bool $fastLoad Whether to load the environment variables immediately.
     * @param array|null $lazyPrefixes Array of prefixes for lazy loading.
     */
    public function __construct($rootDirs, $fastLoad = false, $lazyPrefixes = null)
    {
        $this->rootDirs = is_array($rootDirs) ? $rootDirs : array($rootDirs);
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
    public static function setAllowFileWrites($allow)
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
    public function load()
    {
        foreach ($this->rootDirs as $dir) {
            $this->loadEnvFile($dir . DIRECTORY_SEPARATOR . '.env');
        }
        return $this;
    }

    /**
     * Lazy load specific environment variables based on prefixes.
     *
     * @param array $prefixes Array of prefixes to load (e.g., ['DB', 'KEY'])
     * @param bool $reset If true, clears existing variables before loading
     * @return self For method chaining
     * @throws Exception If file operations fail
     */
    public function lazy(array $prefixes, $reset = false)
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
     * @return self For method chaining
     */
    public function unload()
    {
        foreach (self::$cache as $key => $value) {
            unset($_ENV[$key]);
        }
        self::$cache = [];
        return $this;
    }

    /**
     * Refreshes the environment variables by reloading the .env files and updating the cache.
     *
     * @return self For method chaining
     */
    public function refresh()
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
    protected function loadEnvFile($file)
    {
        if (!is_file($file)) {
            throw new Exception("Environment file not found: $file");
        }
        if (!is_readable($file)) {
            throw new Exception("Environment file is not readable: $file");
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
     * @throws Exception If the file is not writable or cannot be created.
     */
    public static function setenv($key, $value = null)
    {
        $key = trim($key);
        $originalValue = $value;
        $fileValue = is_bool($value) ? ($value ? 'true' : 'false') : trim((string) $value);

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
     * Validate environment variables against specified rules.
     *
     * @param array $rules Array of rules, e.g., ['DB_PORT' => 'required|int']
     * @throws Exception If validation fails
     */
    public static function validate(array $rules)
    {
        foreach ($rules as $key => $rule) {
            $value = self::env($key);
            $ruleParts = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($ruleParts as $part) {
                switch ($part) {
                    case 'required':
                        if ($value === null || $value === '') {
                            throw new Exception("Environment variable '$key' is required but missing or empty");
                        }
                        break;
                    case 'int':
                        if ($value !== null) {
                            if (is_int($value)) {
                            } elseif (is_numeric($value) && (int) $value == $value) {
                                $_ENV[$key] = (int) $value;
                                self::$cache[$key] = (int) $value;
                            } else {
                                throw new Exception("Environment variable '$key' must be an integer, got '" . var_export($value, true) . "'");
                            }
                        }
                        break;
                    case 'bool':
                        if ($value !== null) {
                            if (is_bool($value)) {
                            } elseif (in_array(strtolower((string) $value), ['true', 'false', '1', '0'])) {
                                $boolValue = in_array(strtolower((string) $value), ['true', '1']);
                                $_ENV[$key] = $boolValue;
                                self::$cache[$key] = $boolValue;
                            } else {
                                throw new Exception("Environment variable '$key' must be a boolean, got '" . var_export($value, true) . "'");
                            }
                        }
                        break;
                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            throw new Exception("Environment variable '$key' must be a string, got '" . var_export($value, true) . "'");
                        }
                        break;
                }
            }
        }
    }
}

if (!function_exists('env')) {
    function env($key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}

if (!function_exists('setenv')) {
    function setenv($key, $value = null)
    {
        TinyEnv::setenv($key, $value);
    }
}
