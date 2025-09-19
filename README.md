# TinyEnv

**Note:** This branch is for testing purposes only. For production use, please refer to the [main branch](https://github.com/datahihi1/tiny-env.git)

A lightweight .env loader for PHP projects.

⚡ Fast, 🛡️ Safe, 🎯 Simple — designed for small to medium projects.

### Installation
```bash
composer require datahihi1/tiny-env:dev-test
```

### Quick Start
```php
require 'vendor/autoload.php';

use Datahihi1\TinyEnv\TinyEnv;

$env = new TinyEnv(__DIR__);
$env->load();

echo env('DB_HOST', 'localhost');
```

.env file:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
```

### Features
#### 1. load() – Standard load
```php
$env->load();              // Load all
$env->load(['DB_HOST']);   // Load specific keys
```

#### 2. Fast load
```php
$env = new TinyEnv(__DIR__, true); // Load immediately
```

#### 3. Lazy load
```php
$env->lazy(['DB']); // Load only DB_* variables
```

#### 4. Safe load
```php
$env->safeLoad(); // Ignore missing/unreadable .env files
```

#### 5. Multiple .env files
```php
$env->envfiles(['.env', '.env.local', '.env.production']);
```

- Getting Values
```php
echo env('NAME');                // Get value
echo env('NOT_FOUND', 'backup'); // With default
print_r(env());                  // Get all (in .env file)
print_r(sysenv());               // Get all system variables
```

- Validation
```php
validate_env([
  'VERSION' => 'required|string',
  'DB_PORT' => 'int',
  'APP_DEBUG' => 'bool'
]);
```

### Variable Interpolation

TinyEnv supports shell-style interpolation inside .env values:

```env
DB_HOST=localhost
DB_PORT=3306
DB_URL=${DB_HOST}:${DB_PORT}

USER_NAME=
USER=${USER_NAME:-guest}   # default if unset or empty
ALT_USER=${USER_NAME-guest} # default if unset only
REQUIRED=${MISSING?Missing variable MISSING}
```

Result:
```bash
DB_URL = "localhost:3306"

USER = "guest" (because USER_NAME is empty)

ALT_USER = "" (because USER_NAME exists but empty)

REQUIRED → throws Exception
```

**Notes**
>
> - Comments start with `#`.
> - Variable names: `A-Z`, `0-9`, `_`.
> - Values are auto-parsed into correct types:
>   - `"true"` → `true`
>   - `"false"` → `false`
>   - `"123"` → `int`
>   - `"12.3"` → `float`
>   - `"null"` or empty → `null`

### Run Tests

#### PHPUnit:

1. With PHPUnit installed globally:
```bash
phpunit --colors=always tests
```

2. With PHPUnit via Composer:
```bash
vendor/bin/phpunit --colors=always tests
```

3. With PHPUnit via PHAR:
```bash
/path/to/phpunit.phar --colors=always tests
```

#### PHPStan:

1. With PHPStan installed globally:

```bash
phpstan analyse src --level=max
```

2. With PHPStan via Composer:
```bash
vendor/bin/phpstan analyse src --level=max
```

3. With PHPStan via PHAR:
```bash
/path/to/phpstan.phar analyse src --level=max
```