<?php
/**
 * Example: Type Parsing in TinyEnv
 * This script demonstrates how TinyEnv parses different types of environment variables.
 */

require_once __DIR__ . '/../src/TinyEnv.php';
require_once __DIR__ . '/../src/helper/helpers.php';

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__);
$env->envfiles(['.env.example']);
$env->load();

echo "=== Type Parsing Examples ===\n\n";

// Boolean
echo "Boolean values:\n";
$debug = env('APP_DEBUG');
echo "  APP_DEBUG: " . var_export($debug, true) . " (" . gettype($debug) . ")\n";

$enabled = env('FEATURE_ENABLED');
echo "  FEATURE_ENABLED: " . var_export($enabled, true) . " (" . gettype($enabled) . ")\n";

$disabled = env('FEATURE_DISABLED');
echo "  FEATURE_DISABLED: " . var_export($disabled, true) . " (" . gettype($disabled) . ")\n\n";

// Integer
echo "Integer values:\n";
$port = env('DB_PORT');
echo "  DB_PORT: " . var_export($port, true) . " (" . gettype($port) . ")\n";

$maxUsers = env('MAX_USERS');
echo "  MAX_USERS: " . var_export($maxUsers, true) . " (" . gettype($maxUsers) . ")\n\n";

// Float
echo "Float values:\n";
$price = env('PRICE');
echo "  PRICE: " . var_export($price, true) . " (" . gettype($price) . ")\n";

$rate = env('TAX_RATE');
echo "  TAX_RATE: " . var_export($rate, true) . " (" . gettype($rate) . ")\n\n";

// Null
echo "Null values:\n";
$optional = env('OPTIONAL_VAR');
echo "  OPTIONAL_VAR: " . var_export($optional, true) . " (" . gettype($optional) . ")\n\n";

// String (forced)
echo "String (forced with /value/):\n";
$numString = env('NUM_STRING');
echo "  NUM_STRING: " . var_export($numString, true) . " (" . gettype($numString) . ")\n";

$boolString = env('BOOL_STRING');
echo "  BOOL_STRING: " . var_export($boolString, true) . " (" . gettype($boolString) . ")\n\n";

// Regular string
echo "Regular string:\n";
$appName = env('APP_NAME');
echo "  APP_NAME: " . var_export($appName, true) . " (" . gettype($appName) . ")\n";

