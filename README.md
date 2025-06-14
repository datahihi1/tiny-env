# TinyEnv

A simple environment variable loader for PHP applications. Designed for small projects, `TinyEnv` minimizes resource usage while ensuring stable performance. It loads environment variables from `.env` files and provides flexible methods to manage them.

---

### Installation and Setup

#### Install via Composer

Installation is straightforward with [Composer](https://getcomposer.org/):

```bash
composer require datahihi1/tiny-env
```

Or manually add it to your `composer.json`:

```json
{
    "require": {
        "datahihi1/tiny-env": "^1.0.6"
    }
}
```

Run `composer install` or `composer update` to download the package.

#### 1. `load()` - Basic Setup

After installing, include Composer's autoloader, create a `TinyEnv` instance, and load the environment variables:

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;

// Load from the current directory
$env = new TinyEnv(__DIR__);
$env->load();
```

#### 2. Fast Load Option

Use the `fastLoad` option in the constructor to load variables immediately:

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;

// Load immediately upon instantiation
$env = new TinyEnv(__DIR__, true);
```
#### 3. `lazy()` - Lazy Load Option

Load only variables matching specific prefixes:

```php
$env->lazy(['DB']); // Loads only variables starting with DB_
echo env('DB_HOST'); // Output: localhost
echo env('NAME', 'N/A'); // Output: N/A (NAME not loaded)
```

#### 4. `only()` - Load Only Specific Variables

Load only the specified environment variables by key (single or array):

```php
// Load only DB_HOST
$env->only('DB_HOST');

// Load only DB_HOST and DB_PORT
$env->only(['DB_HOST', 'DB_PORT']);

// After calling only(), only those variables are available in env()
echo env('DB_HOST'); // Output: localhost (if present in .env)
echo env('DB_PORT'); // Output: 3306 (if present in .env)
```

---

### Usage

`TinyEnv` provides simple methods and helper functions to work with environment variables. Below are examples based on a sample `.env` file:

```
NAME=TinyEnv
VERSION=1.0.6
DB_HOST=localhost
```

#### 1. `env()` - Retrieve Environment Variables

Use the global `env()` function to get environment variables:

```php

// Get a specific variable
echo env('NAME'); // Output: TinyEnv

// Get with a default value if the variable is not set
echo env('TESTER', 'Datahihi1'); // Output: Datahihi1 (if TESTER is not defined)

// Get all variables
print_r(env());
```

#### 2. `setenv()` - Set or Update Variables

Use `setenv()` to dynamically set or update environment variables. If file writing is enabled (default), it also updates the `.env` file:

```php

setenv('KEY', 'demo'); // Sets KEY=demo in $_ENV and .env file
echo env('KEY'); // Output: demo
```

**Note**: To disable file writing, use `TinyEnv::setAllowFileWrites(false)`.

#### 3. `validate_env()` - Validate Variables

Ensure variables meet specific rules (e.g., `required`, `int`, `bool`, `string`):

```php
validate_env([
    'VERSION' => 'required|string',
    'DB_PORT' => 'int' // Throws exception if DB_PORT is not an integer
]);
```

#### 4. `unload()` - Clear Variables

Remove all loaded environment variables from `$_ENV` and the internal cache:

```php
$env->load();
print_r(env()); // Shows all variables from .env
$env->unload();
print_r(env()); // Empty array
```

#### 5. `refresh()` - Reload Variables

Clear current variables and reload from the `.env` file:

```php
$env->refresh(); // Equivalent to $env->unload()->load()
print_r(env()); // Updated variables from .env
```

---

### Example `.env` File

Create a `.env` file in your project root:

```
NAME=TinyEnv
VERSION=1.0.6
DB_HOST=localhost
DB_PORT=3306
```

### Full Example

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;

// Initialize and load
$env = new TinyEnv(__DIR__);
$env->load();

// Access variables
echo env('NAME', 'Unknown'); // TinyEnv
echo env('NOT_FOUND', 'Default'); // Default

// Set a new variable
setenv('APP_DEBUG', true);

// Validate
validate_env(['APP_DEBUG' => 'bool']);

// Refresh
$env->refresh();
```

### Notes

- Ensure the `.env` file and its directory are readable/writable when using `setenv()`.
- Comments in `.env` files start with `#` and are ignored.
- Use uppercase letters, numbers, and underscores for variable names (e.g., `APP_KEY`).
---

### Variable Interpolation

TinyEnv supports variable interpolation within `.env` values. You can reference other variables using `${VAR_NAME}` syntax, and TinyEnv will automatically replace them with their corresponding values when loading:

```
DB_HOST=localhost
DB_PORT=3306
DB_URL=${DB_HOST}:${DB_PORT}
```

In this example, `DB_URL` will be set to `localhost:3306`. Interpolation works recursively and supports any variable defined earlier in the file or already loaded into the environment.

**Note:** If a referenced variable is not defined, it will be replaced with an empty string.