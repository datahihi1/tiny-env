<?php
/**
 * Example: Database Configuration
 * This script demonstrates how to use TinyEnv to load database configuration
 */

require_once __DIR__ . '/../src/TinyEnv.php';
require_once __DIR__ . '/../src/helper/helpers.php';

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__);
$env->envfiles(['.env.example']);
$env->load(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS']);

// Tạo database config
$dbConfig = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 3306),
    'database' => env('DB_NAME'),
    'username' => env('DB_USER'),
    'password' => env('DB_PASS'),
];

// Validate required fields
$required = ['host', 'database', 'username', 'password'];
foreach ($required as $field) {
    if (empty($dbConfig[$field])) {
        die("Error: Database {$field} is required\n");
    }
}

// Tạo DSN
$dsn = sprintf(
    "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['database']
);

echo "Database Configuration:\n";
echo "DSN: {$dsn}\n";
echo "Username: {$dbConfig['username']}\n";
echo "Password: " . str_repeat('*', strlen($dbConfig['password'])) . "\n";

// Có thể sử dụng với PDO:
// try {
//     $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
//     echo "Connection successful!\n";
// } catch (PDOException $e) {
//     die("Connection failed: " . $e->getMessage() . "\n");
// }

