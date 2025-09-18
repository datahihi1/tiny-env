<?php
namespace Datahihi1\TinyEnv;

use Exception;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    /** @var bool */
    protected static $loaded = false;
    /** @var string[] */
    protected $rootDirs;
    /** @var array<string, mixed> */
    protected static $cache = [];
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
        $this->rootDirs = (array) $rootDirs;
        if ($fastLoad)
            $this->loadInternal([], true);
    }

    /**
     * Specify which env files to load.
     * 
     * .env is always loaded first if present.
     * 
     * **Note**: This method must be called before load() to take effect. The order of files matters, later files override earlier ones.
     *
     * @param array<int, string> $files List of .env files to load, e.g., ['.env.local', '.env.production']
     * @return self
     */
    public function envfiles(array $files): self
    {
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
        return $this->loadInternal($specificKeys);
    }

    /**
     * Load env variables, with option to force reload.
     * @param array<int, string>|string $specificKeys
     * @param bool $forceReload
     * @return self
     */
    protected function loadInternal($specificKeys = [], bool $forceReload = false): self
    {
        // Always reset cache and loaded state before loading new env files
        self::$cache = [];
        self::$loaded = false;
        $specificKeys = (array) $specificKeys;
        $filter = count($specificKeys) > 0 ? $specificKeys : null;
        $found = false;
        foreach ($this->rootDirs as $dir) {
            foreach ($this->envFiles as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($file) && is_readable($file)) {
                    $this->loadEnvFile($file, $filter);
                    $found = true;
                }
            }
        }
        if (!$found) {
            throw new Exception("No .env file found in any root directory: [" . implode(", ", $this->rootDirs) . "] with files [" . implode(", ", $this->envFiles) . "]");
        }
        self::$loaded = true;
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
        $prefixes = array_filter(array_map('strval', $prefixes));
        if (empty($prefixes))
            return $this;
        foreach ($this->rootDirs as $dir) {
            foreach ($this->envFiles as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (!is_file($file) || !is_readable($file))
                    continue;
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false)
                    continue;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false)
                        continue;
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    foreach ($prefixes as $prefix) {
                        if (stripos($key, $prefix) === 0) {
                            if (!array_key_exists($key, $_ENV)) {
                                $value = trim($value, " \t\n\r\0\x0B\"");
                                $_ENV[$key] = $value;
                                self::$cache[$key] = $value;
                            }
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
        $specificKeys = (array) $specificKeys;
        $filter = count($specificKeys) > 0 ? $specificKeys : null;
        foreach ($this->rootDirs as $dir) {
            foreach ($this->envFiles as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (!is_file($file) || !is_readable($file))
                    continue;
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false)
                    continue;
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
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false)
            return;
        $eqPos = strpos($line, '=');
        $key = trim(substr($line, 0, $eqPos));
        $value = ltrim(substr($line, $eqPos + 1));
        $value = self::stripEnvComment($value);

        if ($allowedKeys !== null && !in_array($key, $allowedKeys, true))
            return;

        $value = trim($value, " \t\n\r\0\x0B\"");
        $value = preg_replace_callback(
            '/\${?([A-Z0-9_]+)(:?[-?])?([^}]*)}?/i',
            function (array $m): string {
                $var = $m[1];
                $op = $m[2];
                $arg = $m[3];
                $env = $_ENV[$var] ?? (self::$cache[$var] ?? null);
                $toString = static function($v): string {
                    if (is_string($v) || is_numeric($v)) {
                        return (string)$v;
                    }
                    return '';
                };
                switch ($op) {
                    case ':-':
                        return $toString(($env === null || $env === '') ? $arg : $env);
                    case '-':
                        return $toString(($env === null) ? $arg : $env);
                    case '?':
                        if ($env === null || $env === '') {
                            throw new Exception("TinyEnv: missing required variable '$var' ($arg)");
                        }
                        return $toString($env);
                    case ':?':
                        if ($env === null) {
                            throw new Exception("TinyEnv: missing required variable '$var' ($arg)");
                        }
                        return $toString($env);
                    default:
                        return $toString(($env !== null) ? $env : '');
                }
            },
            $value
        );

        $parsed = self::parseValue($value);
        $_ENV[$key] = $parsed;
        self::$cache[$key] = $parsed;
    }

    /**
     * Remove inline comment (not in quotes) from env value.
     * @param string $value
     * @return string
     */
    private static function stripEnvComment(string $value): string
    {
        $len = strlen($value);
        $inSingle = false;
        $inDouble = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $value[$i];
            if ($c === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($c === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($c === '#' && !$inSingle && !$inDouble) {
                return rtrim(substr($value, 0, $i));
            }
        }
        return $value;
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
        if (!is_file($file) || !is_readable($file))
            throw new Exception("Cannot read: $file");
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false)
            throw new Exception("Failed to read: $file");
        foreach ($lines as $line) {
            $this->parseAndSetEnvLine($line, $filter);
        }
        return true;
    }

    /**
     * Parse env value to correct type: bool, int, float, null, string
     * @param mixed $value
     * @return mixed
     */
    private static function parseValue($value)
    {
        if (!is_string($value))
            return $value;
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === 'yes' || $lower === 'on')
            return true;
        if ($lower === 'false' || $lower === 'no' || $lower === 'off')
            return false;
        if ($lower === 'null' || $value === '')
            return null;
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        return $value;
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
        if ($key === null)
            return $_ENV;
        $val = self::$cache[$key] ?? $_ENV[$key] ?? $default;
        return self::parseValue($val);
    }

    /**
     * Get or set a system environment variable (like getenv/putenv), with cache for performance.
     *
     * @param string|null $key
     * @param string|null $value If null, get; else set the env var
     * @return string|false|null Returns value if get, or true/false if set
     */
    public static function sysenv(?string $key = null, ?string $value = null)
    {
        /** @var array<string, string|false|null> */
        static $sysenvCache = [];
        if ($key === null) {
            return null;
        }
        if (func_num_args() === 1) {
            if (array_key_exists($key, $sysenvCache)) {
                return is_string($sysenvCache[$key]) || $sysenvCache[$key] === false ? $sysenvCache[$key] : null;
            }
            $val = getenv($key);
            $sysenvCache[$key] = $val;
            return $sysenvCache[$key] ?? null;

        }
        $ok = putenv("{$key}={$value}");
        if ($ok) {
            $sysenvCache[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        return $ok ? $value : false;
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