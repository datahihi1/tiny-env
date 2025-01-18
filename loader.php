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