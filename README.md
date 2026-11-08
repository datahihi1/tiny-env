# TinyEnv

A lightweight .env loader for PHP projects.

Fast, Safe, Simple — designed for small to medium projects.

### Installation

```bash
composer require datahihi1/tiny-env:1.2.0
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
$env = new TinyEnv(__DIR__, true); // Load immediately from .env without populating superglobals unless you opt in
```

#### 3. Multiple .env files
```php
$env->envfiles(['.env', '.env.production', '.env.local']); // Load in order, pre-declaration file has the highest priority by default (.env > .env.production > .env.local)
$env->envfiles(['.env.production', '.env.local'], prioritizeEnv: true); // Prioritize .env file by loading it first, allows overwriting other files
```

#### Populate Superglobals
```php
$env = new TinyEnv(__DIR__); // By default, superglobals are NOT populated to avoid unintended side effects. Enable them explicitly:
$env->populateSuperglobals(); // Enable $_ENV population
$env->populateServerglobals(); // Enable $_SERVER population
$env->populateProcessEnv(); // Enable putenv()/getenv() population
$env->load();
```

`fastLoad` only performs the initial load immediately; it does not populate `$_ENV`, `$_SERVER`, or the process environment unless you opt in explicitly.

- Getting Values
```php
echo env('NAME');                // Get value
echo env('NOT_FOUND', 'backup'); // With default
print_r(env());                  // Get all (in .env file)
print_r(sysenv());               // Get all system variables
```

- Validation

Using [tiny-env-validator](https://github.com/datahihi1/tiny-env-validator.git)

- Encryption

Using [tiny-env-encryptor](https://github.com/datahihi1/tiny-env-encryptor.git)

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
DB_URL="localhost:3306"

USER="guest" (because USER_NAME is empty)

ALT_USER="" (because USER_NAME exists but empty)

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
