<?php
/**
 * Example: Variable Interpolation in TinyEnv
 * This demonstrates how to use variable interpolation features in TinyEnv.
 */

require_once __DIR__ . '/../src/TinyEnv.php';
require_once __DIR__ . '/../src/helper/helpers.php';

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__);
$env->envfiles(['.env.example']);
$env->load(); // Force reload to ensure all variables are set

echo "=== Variable Interpolation Examples ===\n\n";

// 1. Basic interpolation
echo "1. Basic Interpolation:\n";
echo "   DB_URL: " . env('DB_URL') . "\n";
echo "   (Should be: DB_HOST:DB_PORT)\n\n";

// 2. Default values with :-
echo "2. Default with :- (if unset or empty):\n";
echo "   USER: " . env('USER') . "\n";
echo "   (Should use default if USER_NAME is empty)\n\n";

// 3. Default values with -
echo "3. Default with - (if unset only):\n";
echo "   ALT_USER: " . env('ALT_USER') . "\n";
echo "   (Should be empty if USER_NAME exists but empty)\n\n";

// 4. Nested interpolation
echo "4. Nested Interpolation:\n";
echo "   API_FULL_URL: " . env('API_FULL_URL') . "\n";
echo "   (Should combine BASE_URL and API_VERSION)\n\n";

// 5. Required variable (will throw exception if missing)
echo "5. Required Variable:\n";
try {
    echo "   REQUIRED_VAR: " . env('REQUIRED_VAR') . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

