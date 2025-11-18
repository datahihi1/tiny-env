<?php
/**
 * Multi-Environment Example
 * This script demonstrates how to use TinyEnv to manage multiple environment configurations.
 */

require_once __DIR__ . '/../src/TinyEnv.php';
require_once __DIR__ . '/../src/helper/helpers.php';

use Datahihi1\TinyEnv\TinyEnv;

// Detect environment (can be from system env, CLI argument, etc.)
$environment = getenv('APP_ENV') ?: 'example';

echo "Current environment: {$environment}\n\n";

// Initialize with multiple .env files
$env = new TinyEnv(__DIR__);
$env->envfiles(['.env', ".env.{$environment}"]);
$env->load();

// Load config
$config = [
    'app_name' => env('APP_NAME', 'MyApp'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'api_key' => env('API_KEY'),
    'log_level' => env('LOG_LEVEL', 'info'),
];

echo "Application Configuration:\n";
echo "Name: {$config['app_name']}\n";
echo "Debug: " . ($config['debug'] ? 'ON' : 'OFF') . "\n";
echo "URL: {$config['url']}\n";
echo "API Key: " . ($config['api_key'] ? str_repeat('*', 10) : 'Not set') . "\n";
echo "Log Level: {$config['log_level']}\n";

