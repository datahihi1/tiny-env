<?php
require_once __DIR__ . "/src/helper/helpers.php";
require_once __DIR__ . '/src/TinyEnv.php';

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv([__DIR__.'/test',__DIR__]);
$env->populateSuperglobals(true);
$env->load();

echo gettype(env('DEMO', '1.2')) . "\n";
TinyEnv::env('MISSING', 'default');
var_dump($_ENV, env(), s_env());
if (env('APP_DEBUG')) {
    echo "Debug mode is ON\n";
}
else if (env('APP_DEBUG') === false) {
    echo "Debug mode is OFF\n";
}