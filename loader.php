<?php

// Kiểm tra nếu Composer autoload có sẵn sử dụng autoload của Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    // Nếu không có Composer, tự tạo autoloader
    spl_autoload_register(function ($class) {
        $prefix = 'Datahihi1\\TinyEnv\\';
        $baseDir = __DIR__ . '/src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

require_once __DIR__ . '/src/TinyEnv.php';
use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__ . '/../');
$env->load();

if (!function_exists('env')) {
    /**
     * Summary of env
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    function env($key = null, $default = null)
    {
        return TinyEnv::env($key, $default);
    }
}

// Định nghĩa hàm getenv() toàn cục nếu chưa tồn tại
if (!function_exists('getenv')) {

    function getenv($key = null, $default = null)
    {
        return TinyEnv::getenv($key, $default);
    }
}

// Định nghĩa hàm putenv() toàn cục nếu chưa tồn tại
if (!function_exists('putenv')) {
    function putenv($key, $value = null)
    {
        return TinyEnv::putenv($key, $value);
    }
}