<?php

namespace Datahihi1\TinyEnv;

use Exception;

/**
 * TinyEnv is a simple environment variable loader for PHP applications
 */
class TinyEnv
{
    /**
     * Loaded flag to check if envs have been loaded.
     *
     * @var bool
     */
    protected $loaded = false;
    /**
     * Root directories to search for .env files.
     *
     * @var string[]
     */
    protected $rootDirs;
    /**
     * Cached env values
     *
     * @var array<string, mixed>
     */
    protected static $cache = [];
    /**
     * Cached file lines to avoid re-reading files
     *
     * @var array<string, string[]>
     */
    protected static $fileLinesCache = [];
    /**
     * List of .env files to load, in order of priority.
     *
     * @var string[]
     */
    protected $envFiles = ['.env'];

    /**
     * By default do NOT write parsed values into PHP superglobals (e.g. $_ENV).
     * Writing into $_ENV can be abused by .env files to change runtime environment
     * (PATH, HOME, etc.). Libraries or applications that need the old behavior
     * can opt-in by calling ->populateSuperglobals(true).
     *
     * @var bool
     */
    protected $populateSuperglobals = false;

    /**
     * Maximum allowed recursive substitution depth to avoid DoS via long/cyclic chains.
     */
    private const MAX_SUBSTITUTION_DEPTH = 10;

    /**
     * Constructor to initialize the TinyEnv instance.
     *
     * @param string|string[] $rootDirs The root directory to load files from.
     * @param bool            $fastLoad Whether to load all the environment variables immediately.
     *      **Note:** Only .env files and enable populateSuperglobals - not recommended for production
     *
     * @throws Exception If the .env file cannot be read.
     */
    public function __construct($rootDirs, bool $fastLoad = false)
    {
        $this->rootDirs = (array) $rootDirs;
        if ($fastLoad) {
            $this->populateSuperglobals = true;
            $this->loadInternal([], true);
        }
    }

    /**
     * Opt-in to populate PHP superglobals (e.g. $_ENV) when env values are parsed.
     * Default is false for safety.
     *
     * @param bool $enable
     * @return $this
     */
    public function populateSuperglobals(bool $enable = true): self
    {
        $this->populateSuperglobals = $enable;
        return $this;
    }

    /**
     * Specify which env files to load (in order of priority, later files override earlier ones).
     *
     * @param  array<int, string> $files List of .env files to load, e.g., ['.env.local', '.env.production']
     * @return self
     */
    public function envfiles(array $files): self
    {
        $files = array_values(array_unique($files));
        if (($i = array_search('.env', $files, true)) !== false) {
            unset($files[$i]);
        }
        array_unshift($files, '.env');
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
     * @param  array<int, string>|string $specificKeys The key or array of keys to load. If empty, loads all.
     * @param  bool                      $forceReload  Whether to force reload even if already loaded.
     * @throws Exception If the .env file cannot be read.
     */
    public function load($specificKeys = [], bool $forceReload = false, bool $noFile = false): self
    {
        return $this->loadInternal($specificKeys, $forceReload, $noFile);
    }

    /**
     * Load env variables, with option to force reload.
     *
     * @param  array<int, string>|string $specificKeys
     * @param  bool                      $forceReload
     * @return self
     */
    protected function loadInternal($specificKeys = [], bool $forceReload = false, bool $noFile = false): self
    {
        if ($this->loaded && !$forceReload) {
            return $this;
        }
        if ($forceReload) {
            self::$fileLinesCache = [];
        }
        $specificKeys = (array) $specificKeys;
        $filter = !empty($specificKeys) ? $specificKeys : null;
        $found = false;
        foreach ($this->rootDirs as $dir) {
            foreach (array_reverse($this->envFiles) as $fileName) {
                $file = $dir . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($file) && is_readable($file)) {
                    $this->loadEnvFile($file, $filter);
                    $found = true;
                }
            }
        }
        if (!$found && !$noFile) {
            $listDirs = implode(", ", $this->rootDirs);
            $listFiles = implode(", ", $this->envFiles);
            throw new \RuntimeException(
                "No .env file found in directories: [$listDirs] with [$listFiles]"
            );
        }
        $this->loaded = true;
        return $this;
    }

    /**
     * Parse a line from .env and set $_ENV/cache if valid.
     *
     * Optionally filter by allowed keys.
     *
     * @param array<int, string>|null    $allowedKeys Optional array of allowed keys to filter.
     *                                             If null, all keys are allowed.
     * @param array<string, string>|null $rawMap      Map of raw key=>value from the .env file.
     *                                             Used for cross-line substitution.
     */
    private function parseAndSetEnvLine(string $line, ?array $allowedKeys = null, ?array $rawMap = null): void
    {
        $line = trim((string) $line);
        if ($line === '' || $line[0] === '#') {
            return;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            throw new Exception("Malformed .env line (missing '='): $line");
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = ltrim(substr($line, $eqPos + 1));
        $value = self::stripEnvComment($value);

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            throw new Exception("Malformed .env line (invalid key) in: $line");
        }

        $trimmedValueForCheck = ltrim(substr($line, $eqPos + 1));
        if ($trimmedValueForCheck !== '' && $trimmedValueForCheck[0] === '=') {
            if (
                !isset($trimmedValueForCheck[1])
                || ($trimmedValueForCheck[1] !== '"' && $trimmedValueForCheck[1] !== "'")
            ) {
                throw new Exception(
                    "Malformed .env line (unexpected '=' after key) in: $line"
                );
            }
        }

        if ($allowedKeys !== null && !in_array($key, $allowedKeys, true)) {
            return;
        }

        $value = trim($value, " \t\n\r\0\x0B\"");

        $forceString = false;
        if (strlen($value) >= 2 && $value[0] === '/' && substr($value, -1) === '/') {
            $value = substr($value, 1, -1);
            $forceString = true;
        }

        $visited = [$key];
        $allowedOps = ['', ':-', '-', '?', ':?'];

        $replacer = function (array $m) {
            return '';
        };
        $replacer = function (array $m) use (&$visited, $allowedOps, $rawMap, &$replacer): string {
            $var = isset($m[1]) && is_scalar($m[1]) ? (string) $m[1] : '';
            $op  = isset($m[2]) && is_scalar($m[2]) ? (string) $m[2] : '';
            $arg = isset($m[3]) && is_scalar($m[3]) ? (string) $m[3] : '';

            $msgOp = isset($m[0]) && is_scalar($m[0]) ? (string) $m[0] : '';
            if (!in_array($op, $allowedOps, true)) {
                throw new Exception("TinyEnv: invalid substitution operator '{$op}' in {$msgOp}");
            }

            if (in_array($var, $visited, true)) {
                $chain = implode(' -> ', array_merge($visited, [$var]));
                throw new Exception("TinyEnv: recursive variable substitution detected: {$chain}");
            }

            if (count($visited) >= self::MAX_SUBSTITUTION_DEPTH) {
                throw new Exception('TinyEnv: substitution depth exceeded ' . self::MAX_SUBSTITUTION_DEPTH);
            }

            $visited[] = $var;

            /**
             *
             *
             * @var string|int|null $env
             */
            $env = self::$cache[$var] ?? ($_ENV[$var] ?? null);

            if ($env === null && is_array($rawMap) && array_key_exists($var, $rawMap)) {
                $rawVal = $rawMap[$var];
                if (strpos((string)$rawVal, '${') !== false) {
                    $env = preg_replace_callback(
                        '/\${?([A-Z0-9_]+)(:?[-?])?([^}]*)}?/i',
                        $replacer,
                        (string)$rawVal
                    );
                } else {
                    $env = (string)$rawVal;
                }
            }

            switch ($op) {
                case ':-':
                    $resolved = ($env === null || $env === '') ? $arg : $env;
                    break;
                case '-':
                    $resolved = ($env === null) ? $arg : $env;
                    break;
                case '?':
                    if ($env === null || $env === '') {
                        throw new Exception("TinyEnv: missing required variable '{$var}' ({$arg})");
                    }
                    $resolved = $env;
                    break;
                case ':?':
                    if ($env === null) {
                        throw new Exception("TinyEnv: missing required variable '{$var}' ({$arg})");
                    }
                    $resolved = $env;
                    break;
                default:
                    $resolved = ($env !== null) ? $env : '';
            }

            $out = self::stringifyEnvValue($resolved);
            if (self::isDangerous($out)) {
                throw new Exception("TinyEnv: rejected dangerous env value in substitution: {$out}");
            }
            array_pop($visited);
            return $out;
        };

        $value = preg_replace_callback('/\${?([A-Z0-9_]+)(:?[-?])?([^}]*)}?/i', $replacer, $value);

        if (is_string($value) && self::isDangerous($value)) {
            throw new Exception("TinyEnv: rejected dangerous env value: {$value}");
        }

        $parsed = $forceString ? (string) $value : self::parseValue($value);
        self::$cache[$key] = $parsed;
        if ($this->populateSuperglobals) {
            $_ENV[$key] = $parsed;
        }
    }

    /**
     * Detect values that are likely to be used to exploit stream-wrappers, data wrappers
     * or other PHP wrappers which can lead to remote code execution when those values
     * are later used unsafely by an application.
     *
     * This is a conservative check and can be tuned if you need to allow specific schemes.
     *
     * @param string $value
     * @return bool
     */
    private static function isDangerous(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        return (bool) preg_match(
            '/php:\/\/|data:[^;]*;base64,|phar:|expect:|zip:|compress:|gopher:|file:\/\//i',
            $value
        );
    }

    /**
     * Remove inline comment (not in quotes) from env value.
     *
     * @param  string $value
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
     * Read a file with a shared lock and return lines similar to
     * file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES).
     *
     * @param  string $path
     * @param  bool   $ignoreEmptyLines
     * @return array<string>|false
     */
    private function readEnvFileLines(string $path, bool $ignoreEmptyLines = true, bool $useCache = false)
    {
        if ($useCache && isset(self::$fileLinesCache[$path])) {
            return self::$fileLinesCache[$path];
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        if (!flock($fh, LOCK_SH)) {
            fclose($fh);
            return false;
        }
        $lines = [];
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($ignoreEmptyLines && trim($line) === '') {
                continue;
            }
            $lines[] = $line;
        }

        flock($fh, LOCK_UN);
        fclose($fh);
        if ($useCache) {
            self::$fileLinesCache[$path] = $lines;
        }
        return $lines;
    }

    /**
     * Load environment variables from a specific .env file, optionally filtering by keys.
     *
     * @param  string                  $file   The path to the .env file.
     * @param  array<int, string>|null $filter Array of keys to load, or null to load all.
     * @return bool True if the file was loaded successfully.
     * @throws Exception If the file cannot be read or is not readable.
     */
    protected function loadEnvFile(string $file, ?array $filter = null): bool
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new Exception("Cannot read: $file");
        }
        $lines = $this->readEnvFileLines($file, false);
        if ($lines === false) {
            throw new Exception("Failed to read: $file");
        }

        $rawMap = [];
        foreach ($lines as $line) {
            $ln = rtrim($line, "\r\n");
            if ($ln === '' || preg_match('/^\s*#/', $ln)) {
                continue;
            }
            $stripped = self::stripEnvComment($ln);
            $parts = explode('=', $stripped, 2);
            if (count($parts) < 2) {
                continue;
            }
            $k = trim($parts[0]);
            $v = isset($parts[1]) ? ltrim($parts[1]) : '';
            $rawMap[$k] = $v;
        }

        foreach ($lines as $line) {
            $this->parseAndSetEnvLine($line, $filter, $rawMap);
        }
        return true;
    }

    /**
     * Convert a mixed value to string for env substitution without changing logic.
     *
     * @param  mixed $value
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
     *
     * @param  mixed $value
     * @return mixed
     */
    private static function parseValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === 'yes' || $lower === 'on') {
            return true;
        }
        if ($lower === 'false' || $lower === 'no' || $lower === 'off') {
            return false;
        }
        if ($lower === 'null' || $value === '') {
            return null;
        }
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
     * @param  string|null $key     The key of the environment variable.
     * @param  mixed       $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or the default value($default).
     */
    public static function env(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_ENV ?: self::$cache;
        }

        if (array_key_exists($key, self::$cache)) {
            return self::parseValue(self::$cache[$key]);
        }

        if (array_key_exists($key, $_ENV)) {
            return self::parseValue($_ENV[$key]);
        }

        if (func_num_args() > 1) {
            $parsedDefault = self::parseValue($default);
            $_ENV[$key] = $parsedDefault;
            self::$cache[$key] = $parsedDefault;
            return $parsedDefault;
        }

        return self::parseValue($default);
    }

    /**
     * Get a system environment variable as string or all system environment variables.
     *
     * **Note:** TinyEnv only allows getting system environment variables.
     * It does not support setting them.
     *
     * @param  string|null $key The key of the environment variable or system variable.
     * @return string|array<string, string> The variable value, or all variables if $key is null.
     */
    public static function sysenv(?string $key = null)
    {
        /**
         *
         *
         * @var array<string, string>
         */
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
     * @param string $key   The key to set in the cache.
     * @param mixed  $value The value to set in the cache.
     */
    public static function setCache(string $key, $value): void
    {
        self::$cache[$key] = $value;
    }

    /**
     * Clear the internal cache of env values and file lines.
     * 
     * @param string|null $key Optional key to clear specific cache entry.
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key !== null) {
            unset(self::$cache[$key]);
            unset(self::$fileLinesCache[$key]);
            return;
        }
        self::$cache = [];
        self::$fileLinesCache = [];
    }
}
