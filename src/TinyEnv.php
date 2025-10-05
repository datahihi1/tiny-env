<?php
namespace Datahihi1\TinyEnv;

use Exception;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    /** @var bool */
    protected $loaded = false;
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
     * Specify which env files to load (in order of priority, later files override earlier ones).
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
     * - $env->load(); // Load all variables
     * - $env->load(['key1', 'key2']); // Load only specific keys
     * - $env->load([], true); // Force reload all variables
     *
     * @param array<int, string>|string $specificKeys The key or array of keys to load. If empty, loads all.
     * @param bool $forceReload Whether to force reload even if already loaded.
     * @throws Exception If the .env file cannot be read.
     */
    public function load($specificKeys = [], bool $forceReload = false): self
    {
        return $this->loadInternal($specificKeys, $forceReload);
    }

    /**
     * Load env variables, with option to force reload.
     * @param array<int, string>|string $specificKeys
     * @param bool $forceReload
     * @return self
     */
    protected function loadInternal($specificKeys = [], bool $forceReload = false): self
    {
        if ($this->loaded && !$forceReload)
            return $this;
        $specificKeys = (array) $specificKeys;
        $filter = !empty($specificKeys) ? $specificKeys : null;
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
            throw new \RuntimeException("No .env file found in any root directory: [" . implode(", ", $this->rootDirs) . "] with files [" . implode(", ", $this->envFiles) . "]");
        }
        $this->loaded = true;
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
        if ($line === '' || $line[0] === '#')
            return;
        $eqPos = strpos($line, '=');
        if ($eqPos === false)
            return;
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
                switch ($op) {
                    case ':-':
                        return self::stringifyEnvValue(($env === null || $env === '') ? $arg : $env);
                    case '-':
                        return self::stringifyEnvValue(($env === null) ? $arg : $env);
                    case '?':
                        if ($env === null || $env === '') {
                            throw new Exception("TinyEnv: missing required variable '$var' ($arg)");
                        }
                        return self::stringifyEnvValue($env);
                    case ':?':
                        if ($env === null) {
                            throw new Exception("TinyEnv: missing required variable '$var' ($arg)");
                        }
                        return self::stringifyEnvValue($env);
                    default:
                        return self::stringifyEnvValue(($env !== null) ? $env : '');
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
     * Convert a mixed value to string for env substitution without changing logic.
     * @param mixed $value
     * @return string
     */
    private static function stringifyEnvValue($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }
        return '';
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
     * Get a system environment variable as string or all system environment variables.
     * 
     * **Note:** TinyEnv only allows getting system environment variables.
     * It does not support setting them.
     *
     * @param string|null $key The key of the environment variable or system variable.
     * @return string|array<string, string> The variable value, or all variables if $key is null.
     */
    public static function sysenv(?string $key = null)
    {
        /** @var array<string, string> */
        static $sysenvCache = [];

        if ($key === null) {
            if (empty($sysenvCache)) {
                $sysenvCache = getenv() ?: [];
            }
            return $sysenvCache;
        }

        if (array_key_exists($key, $sysenvCache)) {
            return $sysenvCache[$key];
        }

        $val = getenv($key);
        $stringVal = ($val === false) ? '' : (string) $val;
        $sysenvCache[$key] = $stringVal;
        return $stringVal;
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