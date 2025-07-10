<?php
use Datahihi1\TinyEnv\TinyEnv;

if (!function_exists('env')) {
    /**
     * Get the value of an environment variable by key.
     * 
     * If the key is null, the entire $_ENV array is returned.
     * 
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
     * 
     * Handles .env formats, creating files if necessary.
     * 
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

if(!function_exists('validate_env')) {
    /**
     * Validate the environment variables using the provided rules.
     * 
     * If validation fails, an exception is thrown with the error messages.
     *
     * @param array<string, array<string>|string> $rules The validation rules.
     * @return void
     * @throws Exception If validation fails.
     */
    function validate_env(array $rules): void
    {
        TinyEnv::validate($rules);
    }
}