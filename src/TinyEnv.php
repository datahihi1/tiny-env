<?php

namespace Datahihi1\TinyEnv;

use Exception;

use function array_key_exists;
use function chr;
use function count;
use function func_num_args;
use function in_array;
use function is_array;
use function is_bool;
use function is_file;
use function is_scalar;
use function is_string;
use function ord;
use function strlen;

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
     * Cached env values, namespaced by source file to reduce cross-instance poisoning.
     * Structure: [ filePath => [ key => value, ... ], '__global__' => [ ... ] ]
     * Top-level non-array values come from setCache($key, $value) or env($key, $default).
     *
     * @var array<string, array<string, mixed>|mixed>
     */
    protected static $cache = [];
    /**
     * List of .env files to load, in order of priority. `.env` is always loaded first and cannot be removed.
     *
     * @var string[]
     */
    protected $envFiles = ['.env'];

    /**
     * Current env file being processed (used to namespace writes to the cache).
     *
     * @var string
     */
    protected $currentEnvFile = '__global__';

    /**
     * Whether to write parsed env values into PHP superglobals (e.g. $_ENV). Default is false for safety.
     *
     * @var bool
     */
    protected $populateSuperglobals = false;

    /**
     * Whether to write parsed env values into $_SERVER superglobal. Default is false for safety.
     * 
     * @var bool
     */
    protected $populateServerglobals = false;

    /**
     * Allowlist of stream wrapper schemes that are otherwise considered dangerous
     * (e.g. "phar"). Empty by default for safety.
     *
     * @var array<int, string>
     */
    protected $allowedWrapperSchemes = [];

    /**
     * Maximum allowed recursive substitution depth to avoid DoS via long/cyclic chains.
     */
    private const MAX_SUBSTITUTION_DEPTH = 10;
    /**
     * Maximum number of lines per file
     */
    private const MAX_LINES = 10000;
    /**
     * Maximum line length
     */
    private const MAX_LINE_LENGTH = 8192;

    /**
     * Constructor to initialize the TinyEnv instance.
     *
     * @param string|string[] $rootDirs The root directory to load files from.
     * @param bool            $fastLoad Whether to load all the environment variables immediately.
     *      **Note:** Only `.env` and enable populateSuperglobals|populateServerglobals - not recommended for production.
     * 
     * @throws Exception If the .env file cannot be read.
     */
    public function __construct($rootDirs, bool $fastLoad = false)
    {
        $this->rootDirs = (array) $rootDirs;
        if ($fastLoad) {
            $this->populateSuperglobals = true;
            $this->populateServerglobals = true;
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
     * Opt-in to populate PHP superglobals (e.g. $_SERVER) when env values are parsed.
     * Default is false for safety.
     *
     * @param bool $enable
     * @return $this
     */
    public function populateServerglobals(bool $enable = true): self
    {
        $this->populateServerglobals = $enable;
        return $this;
    }

    /**
     * Specify which env files to load (in order of priority, later files override earlier ones).
     * 
     * `.env` is always loaded first and cannot be removed.
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
     * Allow specific stream wrapper schemes inside env values (e.g. "phar").
     *
     * This is opt-in because allowing wrappers can become dangerous if your app later
     * passes env values into file/stream APIs without validation.
     *
     * @param  array<int, string> $schemes
     * @return self
     */
    public function allowWrapperSchemes(array $schemes): self
    {
        $out = [];
        foreach ($schemes as $s) {
            if (!is_scalar($s)) {
                continue;
            }
            $s = strtolower(trim((string) $s));
            $s = rtrim($s, ':/');
            if ($s === '') {
                continue;
            }
            $out[] = $s;
        }
        $this->allowedWrapperSchemes = array_values(array_unique($out));
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
            self::$cache = [];
        }
        $specificKeys = (array) $specificKeys;
        $filter = !empty($specificKeys) ? $specificKeys : null;
        $found = false;
        foreach ($this->rootDirs as $dir) {
            $realDir = realpath($dir);
            if ($realDir === false) {
                continue;
            }
            foreach (array_reverse($this->envFiles) as $fileName) {
                if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
                    continue;
                }
                if (!preg_match('/^\.env(\.\w+)?$/', $fileName)) {
                    continue;
                }
                $file = $realDir . DIRECTORY_SEPARATOR . $fileName;
                $realFile = realpath($file);
                if ($realFile === false) {
                    continue;
                }

                $normRealDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $normRealFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realFile);

                $startsWith = DIRECTORY_SEPARATOR === '\\'
                    ? strcasecmp(substr($normRealFile, 0, strlen($normRealDir)), $normRealDir) === 0
                    : substr($normRealFile, 0, strlen($normRealDir)) === $normRealDir;


                if (!$startsWith) {
                    continue;
                }
                if (is_file($realFile) && is_readable($realFile)) {
                    $this->loadEnvFile($realFile, $filter);
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
            throw new Exception('Malformed .env line (missing "=")');
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = ltrim(substr($line, $eqPos + 1));
        $value = self::stripEnvComment($value);

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key) || strlen($key) > 255) {
            throw new Exception('Malformed .env line - invalid key');
        }

        $trimmedValueForCheck = ltrim(substr($line, $eqPos + 1));
        if ($trimmedValueForCheck !== '' && $trimmedValueForCheck[0] === '=') {
            if (
                !isset($trimmedValueForCheck[1])
                || ($trimmedValueForCheck[1] !== '"' && $trimmedValueForCheck[1] !== "'")
            ) {
                throw new Exception('Malformed .env line - invalid value');
            }
        }

        if ($allowedKeys !== null && !in_array($key, $allowedKeys, true)) {
            return;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

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

            if ($arg !== '' && !preg_match('/^[A-Z0-9_\s-]*$/i', $arg)) {
                throw new Exception("TinyEnv detected invalid characters in substitution argument");
            }

            $msgOp = isset($m[0]) && is_scalar($m[0]) ? (string) $m[0] : '';
            if (!in_array($op, $allowedOps, true)) {
                throw new Exception("TinyEnv detected invalid substitution operator");
            }

            if (in_array($var, $visited, true)) {
                throw new Exception("TinyEnv detected recursive variable substitution for {$var}");
            }

            if (count($visited) >= self::MAX_SUBSTITUTION_DEPTH) {
                throw new Exception('TinyEnv detected substitution depth exceeded');
            }

            $visited[] = $var;

            $env = $this->getCachedValue($var);
            if ($env === null && array_key_exists($var, $_ENV)) {
                $env = (string) $_ENV[$var];
            }
            if ($env === null && is_array($rawMap) && array_key_exists($var, $rawMap)) {
                $rawVal = $rawMap[$var];
                $env = strpos((string)$rawVal, '${') !== false
                    ? preg_replace_callback(
                        '/\${?([A-Z0-9_]+)(:?[-?])?([^}]*)}?/i',
                        $replacer,
                        (string)$rawVal
                    )
                    : (string) $rawVal;
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
                        throw new Exception("TinyEnv detected missing required variable: {$var}");
                    }
                    $resolved = $env;
                    break;
                case ':?':
                    if ($env === null) {
                        $msg = $arg !== '' ? $arg : "TinyEnv detected missing required variable: {$var}";
                        throw new Exception($msg);
                    }
                    $resolved = $env;
                    break;
                default:
                    $resolved = ($env !== null) ? $env : '';
            }

            $out = self::stringifyEnvValue($resolved);
            if ($this->isDangerous($out)) {
                throw new Exception("TinyEnv detected dangerous environment value: {$out}");
            }
            array_pop($visited);
            return $out;
        };

        $value = preg_replace_callback('/\$\{([A-Z0-9_]+)(:?[-?])?([^}]*)\}/i', $replacer, $value);

        if (is_string($value) && $this->isDangerous($value)) {
            throw new Exception("TinyEnv rejected dangerous env value");
        }

        $parsed = $forceString ? (string) $value : self::parseValue($value);
        $ns = $this->currentEnvFile;
        $nsCache = (isset(self::$cache[$ns]) && is_array(self::$cache[$ns])) ? self::$cache[$ns] : [];
        $nsCache[$key] = $parsed;
        self::$cache[$ns] = $nsCache;
        if ($this->populateSuperglobals) {
            $_ENV[$key] = $parsed;
        }
        if ($this->populateServerglobals) {
            $_SERVER[$key] = $parsed;
        }
        if ($parsed === null) {
            putenv($key);
        } else {
            putenv($key . '=' . (is_bool($parsed) ? ($parsed ? 'true' : 'false') : self::stringifyEnvValue($parsed)));
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
    private function isDangerous(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $decoded = urldecode($value);
        $doubleDecoded = urldecode($decoded);
        $cleaned = preg_replace('/[\s\x00]/', '', strtolower($value));
        $cleanedDecoded = preg_replace('/[\s\x00]/', '', strtolower($decoded));
        $cleanedDoubleDecoded = preg_replace('/[\s\x00]/', '', strtolower($doubleDecoded));

        if (!empty($this->allowedWrapperSchemes)) {
            foreach ($this->allowedWrapperSchemes as $scheme) {
                $prefix = $scheme . ':';
                if (
                    strpos((string) $cleaned, $prefix) === 0 ||
                    strpos((string) $cleanedDecoded, $prefix) === 0 ||
                    strpos((string) $cleanedDoubleDecoded, $prefix) === 0
                ) {
                    return false;
                }
            }
        }

        $patterns = [
            '/php:\/\//',
            '/data:[^;]*;base64,/',
            '/phar:/',
            '/expect:/',
            '/zip:/',
            '/compress:/',
            '/gopher:/',
            '/file:\/\//',
            '/ogg:/',
            '/rar:/',
            '/zlib:/',
            '/glob:/',
            '/ssh2:/',
            '/ftp:/',
            '/ftps:/',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match($pattern, (string) $cleaned) ||
                preg_match($pattern, (string) $cleanedDecoded) ||
                preg_match($pattern, (string) $cleanedDoubleDecoded)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve a cached value by name, preferring the current file namespace,
     * then the global namespace, then other namespaces.
     *
     * @param string $var
     * @return string|null
     */
    private function getCachedValue(string $var): ?string
    {
        $ns = $this->currentEnvFile;
        $nsEntry = self::$cache[$ns] ?? null;
        if (is_array($nsEntry) && array_key_exists($var, $nsEntry)) {
            return (string) $nsEntry[$var];
        }
        $globalEntry = self::$cache['__global__'] ?? null;
        if (is_array($globalEntry) && array_key_exists($var, $globalEntry)) {
            return (string) $globalEntry[$var];
        }
        foreach (self::$cache as $file => $map) {
            if ($file === $ns || $file === '__global__') {
                continue;
            }
            if (is_array($map) && array_key_exists($var, $map)) {
                return (string) $map[$var];
            }
        }
        return null;
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
            if ($c === '\\') {
                $i++;
                continue;
            }

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
     * @param  string                  $file   The path to the .env file.
     * @param  array<int, string>|null $filter Array of keys to load, or null to load all.
     * @return bool True if the file was loaded successfully.
     * @throws Exception If the file cannot be read or is not readable.
     */
    protected function loadEnvFile(string $file, ?array $filter = null): bool
    {
        $fh = @fopen($file, 'rb');
        if ($fh === false) {
            throw new Exception("Cannot read file");
        }
        if (!flock($fh, LOCK_SH)) {
            fclose($fh);
            throw new Exception("Cannot lock file");
        }
        $stat = @fstat($fh);
        if ($stat === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new Exception("File changed during read");
        }

        clearstatcache(true, $file);
        $statPath = @stat($file);
        if ($statPath === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new Exception("File changed during read");
        }

        $same = false;

        $statIno = $stat['ino'];
        $statDev = $stat['dev'];
        $pathIno = $statPath['ino'];
        $pathDev = $statPath['dev'];
        if ($statIno !== 0 && $pathIno !== 0) {
            $same = ($statIno === $pathIno && $statDev === $pathDev);
        }

        if (!$same) {
            $same = ($stat['size'] === $statPath['size'] && $stat['mtime'] === $statPath['mtime']);
        }

        if (!$same) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new Exception("File changed during read");
        }
        $this->currentEnvFile = $file;
        $lines = [];
        $lineCount = 0;
        while (($line = fgets($fh)) !== false) {
            if (strlen($line) > self::MAX_LINE_LENGTH) {
                flock($fh, LOCK_UN);
                fclose($fh);
                throw new Exception("Line too long");
            }
            $line = rtrim($line, "\r\n");
            if (trim($line) === '') {
                continue;
            }
            $lines[] = $line;
            $lineCount++;
            if ($lineCount > self::MAX_LINES) {
                flock($fh, LOCK_UN);
                fclose($fh);
                throw new Exception("Too many lines");
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);

        if (empty($lines)) {
            return true;
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

        $this->currentEnvFile = '__global__';

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
        if (preg_match('/^-?\d+$/', $value)) {
            if (!self::intFits($value)) {
                return $value;
            }
            return (int) $value;
        }
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }
        return $value;
    }

    /**
     * Check whether a decimal integer string fits in the platform integer range
     * without causing overflow. Uses BCMath if available, else string-length
     * and lexicographic comparisons.
     *
     * @param string $value Decimal integer string, may be negative
     * @return bool True if it fits in PHP int range
     */
    private static function intFits(string $value): bool
    {
        $neg = ($value[0] === '-');
        $abs = $neg ? substr($value, 1) : $value;
        $max = (string) PHP_INT_MAX;

        if (function_exists('bccomp') && function_exists('bcadd')) {
            if ($neg) {
                $limit = bcadd($max, '1');
                return bccomp($abs, $limit) <= 0;
            }
            return bccomp($abs, $max) <= 0;
        }

        $maxLen = strlen($max);
        $absLen = strlen($abs);
        if ($absLen < $maxLen) {
            return true;
        }
        if ($absLen > $maxLen + ($neg ? 1 : 0)) {
            return false;
        }

        if ($neg) {
            $limit = self::stringAddOne($max);
            if (strlen($abs) < strlen($limit)) {
                return true;
            }
            if (strlen($abs) > strlen($limit)) {
                return false;
            }
            return strcmp($abs, $limit) <= 0;
        }

        if ($absLen > $maxLen) {
            return false;
        }
        return strcmp($abs, $max) <= 0;
    }

    /**
     * Add one to a non-negative decimal integer string. Returns result string.
     * Simple implementation avoids bcmath/gmp dependency.
     *
     * @param string $s
     * @return string
     */
    private static function stringAddOne(string $s): string
    {
        $i = strlen($s) - 1;
        $carry = 1;
        $res = '';
        while ($i >= 0) {
            $digit = ord($s[$i]) - 48;
            $sum = $digit + $carry;
            $carry = intdiv($sum, 10);
            $digit = $sum % 10;
            $res = chr(48 + $digit) . $res;
            $i--;
        }
        if ($carry) {
            $res = '1' . $res;
        }
        return ltrim($res, '0') === '' ? '0' : ltrim($res, '0');
    }

    /**
     * Merge all cache namespaces into one flat key => value map (later namespace overwrites).
     *
     * @return array<mixed>
     */
    private static function flattenCache(): array
    {
        $flat = [];
        foreach (self::$cache as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry as $k => $v) {
                $flat[$k] = $v;
            }
        }
        return $flat;
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
            if (!empty($_ENV)) {
                return $_ENV;
            }
            return self::flattenCache();
        }

        if (array_key_exists($key, self::$cache) && !is_array(self::$cache[$key])) {
            return self::parseValue(self::$cache[$key]);
        }

        foreach (self::$cache as $ns => $map) {
            if (is_array($map) && array_key_exists($key, $map)) {
                return self::parseValue($map[$key]);
            }
        }

        if (array_key_exists($key, $_ENV)) {
            return self::parseValue($_ENV[$key]);
        }

        if (func_num_args() > 1) {
            $parsedDefault = self::parseValue($default);
            self::$cache[$key] = $parsedDefault;
            return $parsedDefault;
        }

        return self::parseValue($default);
    }

    /**
     * Get a system environment variable as string or all system environment variables.
     *
     * **Note:** Get only, never set them.
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
     * Clear the internal cache of env values.
     *
     * @param string|null $key Optional key to clear (namespace or key set via setCache/env default).
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key !== null) {
            unset(self::$cache[$key]);
            return;
        }
        self::$cache = [];
    }
}
