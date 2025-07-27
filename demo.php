<?php
require_once (__DIR__ . '/vendor/autoload.php');

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__); // Initialize TinyEnv with the current directory
$env->envfiles(['.env', '.env.local']); // Load environment variables from .env and .env.local files
$env->setAllowFileWrites(true); // Allow writing to .env files
$env->setFileCache('env.cache.php')->secureByGitignore(); // Set the cache file name and ensure it's ignored by Git
// $env->load(); // Load environment variables
// $env->saveCacheToFile(); // Save cache
$env->loadCacheFromFile(); // Read cache

print_r(env());