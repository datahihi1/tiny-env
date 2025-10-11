<?php
require_once (__DIR__ . '/vendor/autoload.php');

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__, true ); // Initialize TinyEnv with the current directory

var_dump(env());

echo gettype(env('DEMO', '1.2')) . "\n";

// var_dump(sysenv());

if (env('APP_DEBUG')) {
    echo "Debug mode is ON\n";
}
else if (env('APP_DEBUG') === false) {
    echo "Debug mode is OFF\n";
}