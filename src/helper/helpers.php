<?php
use Datahihi1\TinyEnv\TinyEnv;
use Datahihi1\TinyEnv\Validator;

if (!function_exists('env')) {
    /**
     * Get an env value by key or all($_ENV) if null.
     *
     * Returns $default if the key is not set.
     *
     * @param string|null $key The key of the environment variable.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value or $default if the key is missing.
     */
    function env(?string $key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}

if (!function_exists('validate_env')) {
    /**
     * Validate the environment variables using the provided rules.
     *
     * @param array<string, array<string>|string> $rules The validation rules.
     * @throws Exception If validation fails.
     */
    function validate_env(array $rules): void
    {
        Validator::validate($rules);
    }
}

if (!function_exists('sysenv')) {
    /**
     * Get a system environment variable as string, or all system env variables.
     *
     * @param string|null $key The key of the environment variable or system variable.
     * @return array<string, string>|string The variable value, or all variables if $key is null
     */
    function sysenv(?string $key = null)
    {
        return TinyEnv::sysenv($key);
    }
}