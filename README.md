# TinyEnv

**Note:** This branch is for testing purposes only. For production use, please refer to the [main branch](https://github.com/datahihi1/tiny-env.git)

A lightweight .env loader for PHP projects.

Fast, Safe, Simple — designed for small to medium projects.

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
$env->load();                           // Load all
$env->load(specificKeys: ['DB_HOST']);  // Load specific keys
$env->load([], forceReload: true);      // Force reload (overwrite existing values)
$env->load([], noFile: true);           // Load without requiring .env file to exist
```

#### 2. Fast load
```php
$env = new TinyEnv(__DIR__, true); // Load immediately and populate $_SERVER|$_ENV but only .env and not recommended for production
```

#### 3. Multiple .env files
```php
$env->envfiles(['.env', '.env.local', '.env.production']); // Load in order, later files override earlier ones, not work with fastLoad
```

#### Populate Superglobals
```php
$env = new TinyEnv(__DIR__); // By default, superglobals are NOT populated to avoid unintended side effects. You can enable it explicitly:
$env->populateSuperglobals(); // Enable superglobals population
$env->populateServerglobals(); // Enable server globals population
$env->load();
```

Or use `fastLoad` which will always populate superglobals - **But not recommended for production**.

- Getting Values
```php
echo env('NAME');                // Get value
echo env('NOT_FOUND', 'backup'); // With default
print_r(env());                  // Get all (in .env file)
print_r(s_env());                // Get all converted to string
print_r(sysenv());               // Get all system variables
```

- Validation

Using [tiny-env-validator](https://github.com/datahihi1/tiny-env-validator.git)

### Examples

Examples are located in the `examples` directory for quick reference.

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
> - Spaces around `=` still valid but not recommended.
> - Values are auto-parsed into correct types:
>   - `"true", "yes", "on"` → `true`
>   - `"false", "no", "off"` → `false`
>   - `"123"` → `int`
>   - `"12.3"` → `float` or `double`
>   - `"null"` or empty → `null`
> - TinyEnv considers yes/no, on/off to be boolean values.
> - Use `"/value/"` to force string type.

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