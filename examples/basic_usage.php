<?php
/**
 * Basic usage example - initialization, loading, and usage.
 */

require_once __DIR__ . '/../src/TinyEnv.php';
require_once __DIR__ . '/../src/helper/helpers.php';

use Datahihi1\TinyEnv\TinyEnv;

// 1. Initialize TinyEnv
$env = new TinyEnv(__DIR__ );
$env->envfiles(['.env.example']);
// 2. Load all variables
$env->load();

// 3. Get values
echo "DB Host: " . env('DB_HOST', 'localhost') . "\n";
echo "DB Port: " . env('DB_PORT', 3306) . "\n";
echo "App Debug: " . (env('APP_DEBUG', false) ? 'ON' : 'OFF') . "\n";

// 4. Get all
echo "\nAll environment variables:\n";
print_r(env());

