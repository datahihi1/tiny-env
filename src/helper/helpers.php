<?php

use Datahihi1\TinyEnv\TinyEnv;

if (!function_exists('env')) {
    /**
     * Get an env value by key or all($_ENV) if null.
     *
     * Returns $default if the key is not set.
     *
     * @param  string|null $key     The key of the environment variable.
     * @param  mixed       $default The default value if the key does not exist.
     * @return mixed The value or $default if the key is missing.
     */
    function env(?string $key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}

if (!function_exists('s_env')) {
    /**
     * Convenience function to get env value(s) as string(s).
     *
     * @param  string|null $key     The key of the environment variable.
     * @param  string      $default The default value if the key does not exist.
     * @return array<string>|string
     */
    function s_env(?string $key = null, $default = '')
    {
        $val = TinyEnv::env($key, $default);

        $toString = function ($v): string {
            if ($v === null) {
                return '';
            }
            if (is_scalar($v)) {
                return (string) $v;
            }
            if (is_object($v) && method_exists($v, '__toString')) {
                return (string) $v;
            }
            return '';
        };

        if ($key === null) {
            $out = [];
            foreach ((array) $val as $k => $v) {
                $out[(string) $k] = $toString($v);
            }
            return $out;
        }

        return $toString($val);
    }
}

if (!function_exists('sysenv')) {
    /**
     * Get a system environment variable as string, or all system env variables.
     *
     * @param  string|null $key The key of the environment variable or system variable.
     * @return array<string, string>|string The variable value, or all variables if $key is null
     */
    function sysenv(?string $key = null)
    {
        return TinyEnv::sysenv($key);
    }
}
