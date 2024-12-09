<?php

spl_autoload_register(function ($class) {    
    $fileName = "src/TinyEnv.php";

    if (is_readable($fileName)) {
        require_once $fileName;
    } 
});

require_once __DIR__ . '/src/TinyEnv.php';
use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__ . '/../');
$env->load();

if (!function_exists('env')) {
    /**
     * env() function can be replace $_ENV[] to get environment variables in .env
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    function env($key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}

if (!function_exists('putenv')) {
    /**
     * TinyEnv::putenv()
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    function putenv($key, $value = null)
    {
        return TinyEnv::putenv($key, $value);
    }
}