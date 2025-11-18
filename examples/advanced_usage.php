<?php
/**
 * Advanced TinyEnv Usage Example
 * This script demonstrates advanced features of TinyEnv such as multiple directories,
 * specific key loading, and error handling.
 */

require_once __DIR__ . '/../src/TinyEnv.php';
require_once __DIR__ . '/../src/helper/helpers.php';

use Datahihi1\TinyEnv\TinyEnv;

echo "=== Advanced TinyEnv Usage ===\n\n";

// 1. Multiple directories
echo "1. Multiple Directories:\n";
$env = new TinyEnv([
    __DIR__ . '/../demo-env',
    __DIR__,
]);
$env->envfiles(['.env.example']);
$env->load();
echo "   Loaded from multiple directories\n\n";

// 2. Specific keys only
echo "2. Load Specific Keys Only:\n";
$env = new TinyEnv(__DIR__ );
$env->envfiles(['.env.example']);
$env->load(['DB_HOST', 'DB_PORT', 'APP_DEBUG']);
echo "   Only loaded: DB_HOST, DB_PORT, APP_DEBUG\n";
echo "   DB_HOST: " . env('DB_HOST') . "\n";
echo "   APP_NAME: " . (env('APP_NAME') ?? 'null (not loaded)') . "\n\n";

// 3. Force reload
echo "3. Force Reload:\n";
$env->load(); // First load
$env->load([], true); // Force reload
echo "   Reloaded all variables\n\n";

// 4. Multiple .env files
echo "4. Multiple .env Files:\n";
$env = new TinyEnv(__DIR__ . '/..');
$env->envfiles(['.env', '.env.local','.env.example']);
$env->load();
echo "   Loaded .env and .env.local (local overrides .env)\n\n";

// 5. Populate superglobals
echo "5. Populate Superglobals:\n";
$env = new TinyEnv(__DIR__ . '/..');
$env->populateSuperglobals(true);
$env->load(['APP_NAME']);
echo "   APP_NAME in \$_ENV: " . ($_ENV['APP_NAME'] ?? 'not set') . "\n\n";

// 6. Cache management
echo "6. Cache Management:\n";
TinyEnv::setCache('CUSTOM_KEY', 'custom_value');
echo "   Set custom cache: CUSTOM_KEY = " . env('CUSTOM_KEY') . "\n";
TinyEnv::clearCache('CUSTOM_KEY');
echo "   Cleared cache: CUSTOM_KEY = " . (env('CUSTOM_KEY') ?? 'null') . "\n\n";

// 7. System environment variables
echo "7. System Environment Variables:\n";
$path = sysenv('PATH');
echo "   PATH: " . (strlen($path) > 50 ? substr($path, 0, 50) . '...' : $path) . "\n\n";

// 8. Method chaining
echo "8. Method Chaining:\n";
$env = (new TinyEnv(__DIR__ . '/..'))
    ->populateSuperglobals(true)
    ->envfiles(['.env', '.env.local'])
    ->load(['APP_NAME', 'APP_DEBUG']);
echo "   Chained: populateSuperglobals() -> envfiles() -> load()\n\n";

// 9. Error handling
echo "9. Error Handling:\n";
try {
    $env = new TinyEnv('/nonexistent/path');
    $env->load();
} catch (RuntimeException $e) {
    echo "   Caught: " . $e->getMessage() . "\n";
}

