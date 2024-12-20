<?php
spl_autoload_register(function ($class) {    
    $baseDir = __DIR__ . '/src/';
    $relativeClass = str_replace('Datahihi1\\TinyEnv\\', '', $class);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

if (!class_exists('Datahihi1\\TinyEnv\\TinyEnv')) {
    die('TinyEnv class not found. Please check your autoloader.');
}

use Datahihi1\TinyEnv\TinyEnv;

if (!function_exists('env')) {
    /**
     * env() function for accessing environment variables from .env, .env.example, .ini files.
     * @param string|null $key The environment variable key.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The value of the environment variable or $default.
     */
    function env($key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}
