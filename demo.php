<?php

require_once 'src/TinyEnv.php';
require_once 'src/helper/helpers.php';

$env = new \Datahihi1\TinyEnv\TinyEnv(__DIR__, true);
$env->load();

print_r(env('USER'));