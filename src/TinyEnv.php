<?php
namespace Datahihi1\TinyEnv;

use Exception;

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
    /** @var string[] */
    protected $envFiles = ['.env'];

    /**
     * Constructor to initialize the TinyEnv instance.
     *
     * @param string|string[] $rootDirs The root directory to load files from.
     * @param bool $fastLoad Whether to load all the environment variables immediately.
     * 
     * @throws Exception If the .env file cannot be read.
     */
    public function __construct($rootDirs, bool $fastLoad = false)
    {
        $this->rootDirs = (array)$rootDirs;
        if ($fastLoad) $this->load();
    }

    /**
     * Set whether to allow writing to .env files.
     *
     * @param bool $allow Whether to allow writing to .env files.
     */
    public static function setAllowFileWrites(bool $allow = false): void
    {
        self::$allowFileWrites = $allow;
    }

    /**
     * Specify which env files to load (in order of priority, later files override earlier ones).
     *
     * @param array<int, string> $files List of .env files to load, e.g., ['.env.local', '.env.production']
     * @return self
     */
    public function envfiles(array $files): self
    {
        // Always ensure .env is first if present, and no duplicates
        $files = array_unique($files);
        if (($i = array_search('.env', $files, true)) !== false) {
            unset($files[$i]);
            array_unshift($files, '.env');
        }
        $this->envFiles = array_values($files);
        return $this;
    }

    /**
     * Load environment variables from .env files in the specified root directories.
     *
     * Usage:
     * 
     *   $env->load(); // Load all variables
     * 
     *   $env->load(['key1', 'key2']); // Load only specific keys
     *
     * @param array<int, string>|string $specificKeys The key or array of keys to load. If empty, loads all.
     * @throws Exception If the .env file cannot be read.
     */
    public function load($specificKeys = []): self
    {
        $specificKeys = (array)$specificKeys;
        $filter = count($specificKeys) > 0 ? $specificKeys : null;
        foreach ($this->rootDirs as $dir) {
            foreach ($this->envFiles as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($file) && is_readable($file)) {
                    $this->loadEnvFile($file, $filter);
                }
            }
        }
        return $this;
    }

    /**
     * Load environment variables lazily based on specified prefixes.
     * 
     * @param array<int, string> $prefixes Array of prefixes to filter environment variables.
     * @throws Exception If the .env file cannot be read.
     */
    public function lazy(array $prefixes): self
    {
        foreach ($this->rootDirs as $dir) {
            foreach ($this->envFiles as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (!is_file($file) || !is_readable($file)) continue;
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false) continue;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"");
                    foreach ($prefixes as $prefix) {
                        if (strpos($key, $prefix) !== false) {
                            $_ENV[$key] = $value;
                            self::$cache[$key] = $value;
                            break;
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Load environment variables from .env files in the specified root directories, but do not throw if file is missing or unreadable.
     * 
     * This method does not check for file permissions or attempt to write to any file.
     *
     * @param array<int, string>|string $specificKeys The key or array of keys to load. If empty, loads all.
     */
    public function safeLoad($specificKeys = []): self
    {
        $specificKeys = (array)$specificKeys;
        $filter = count($specificKeys) > 0 ? $specificKeys : null;
        foreach ($this->rootDirs as $dir) {
            foreach ($this->envFiles as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (!is_file($file) || !is_readable($file)) continue;
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false) continue;
                foreach ($lines as $line) {
                    $this->parseAndSetEnvLine($line, $filter);
                }
            }
        }
        return $this;
    }
    
    /**
     * Parse a line from .env and set $_ENV/cache if valid.
     * 
     * Optionally filter by allowed keys.
     *
     * @param string $line
     * @param array<int, string>|null $allowedKeys
     */
    private function parseAndSetEnvLine(string $line, ?array $allowedKeys = null): void
    {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) return;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($allowedKeys !== null && !in_array($key, $allowedKeys, true)) return;
        $value = trim($value, " \t\n\r\0\x0B\"");

        $value = preg_replace_callback('/\${?([A-Z0-9_]+)(:-([^}]+))?}?/i',function (array $matches): string {
                $var = $matches[1];
                $default = $matches[3] ?? '';
                $env = $_ENV[$var] ?? (self::$cache[$var] ?? null);
                return is_string($env) ? $env : $default;
            },$value
        );

        $_ENV[$key] = $value;
        self::$cache[$key] = $value;
    }

    /**
     * Load environment variables from a specific .env file, optionally filtering by keys.
     *
     * @param string $file The path to the .env file.
     * @param array<int, string>|null $filter Array of keys to load, or null to load all.
     * @return bool True if the file was loaded successfully.
     * @throws Exception If the file cannot be read or is not readable.
     */
    protected function loadEnvFile(string $file, ?array $filter = null): bool
    {
        if (!is_file($file) || !is_readable($file)) throw new Exception("Cannot read: $file");
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) throw new Exception("Failed to read: $file");
        foreach ($lines as $line) {
            $this->parseAndSetEnvLine($line, $filter);
        }
        return true;
    }

    /**
     * Get an environment variable by key, or all if key is null.
     * 
     * Returns $default if not found.
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
     *
     * @param string $key The key of the environment variable to set.
     * @param mixed $value The value to set for the environment variable.
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
            throw $e;
        }
    }

    /**
     * Cache a value by key (not limited to env data).
     *
     * @param string $key The key to set in the cache.
     * @param mixed $value The value to set in the cache.
     */
    public static function setCache(string $key, $value): void
    {
        self::$cache[$key] = $value;
    }
}