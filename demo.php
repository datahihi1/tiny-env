<?php
require_once __DIR__ . "/src/helper/helpers.php";
require_once __DIR__ . '/src/TinyEnv.php';

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__);
$env->populateSuperglobals();
$env->load(['APP_DEBUG', 'DEMO', 'PORT']);
var_dump($_ENV);

echo gettype(env('DEMO', '1.2')) . "\n";

// var_dump(sysenv());

if (env('APP_DEBUG')) {
    echo "Debug mode is ON\n";
}
else if (env('APP_DEBUG') === false) {
    echo "Debug mode is OFF\n";
}