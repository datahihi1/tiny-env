# TinyEnv

A lightweight .env loader for PHP projects.

Fast, Safe, Simple — designed for small to medium projects.

### Installation
```bash
composer require datahihi1/tiny-env:^1.0.14
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
$env->load(noFile:true);   // Load but skip checking .env file existence
```

#### 2. Fast load
```php
$env = new TinyEnv(__DIR__, true); // Load immediately
# $env->load(); // No need to call load() again
```

#### 3. Multiple .env files
```php
$env = new TinyEnv(__DIR__.'/to/path/env');
$env->envfiles(['.env', '.env.local', '.env.production']);
$env->load(); // Load from multiple files but .env is always first
```

TinyEnv always loads `.env` first, then any additional files in the order specified.

#### Populate Superglobals
```php
$env = new TinyEnv(__DIR__);
$env->populateSuperglobals(); // Enable superglobals population
$env->load();
```

Or use `fastLoad` which will always populate superglobals - **But not recommended for production**.

- Getting Values
```php
echo env('NAME');                // Get value
echo env('NOT_FOUND', 'backup'); // With default
print_r(env());                  // Get all (in .env file)
print_r(sysenv());               // Get all system variables
```

- Validation

Using [tiny-env-validator](https://github.com/datahihi1/tiny-env-validator.git)

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
>   - `"true", "yes", "on"` → `true`
>   - `"false", "no", "off"` → `false`
>   - `"123"` → `int`
>   - `"12.3"` → `float`
>   - `"null"` or empty → `null`
> - TinyEnv considers yes/no, on/off to be boolean values.