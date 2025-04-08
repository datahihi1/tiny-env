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
        "datahihi1/tiny-env": "^1.0.4"
    }
}
```

Run `composer install` or `composer update` to download the package.

#### Basic Setup

After installing, include Composer's autoloader, create a `TinyEnv` instance, and load the environment variables:

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;

// Load from the current directory
$env = new TinyEnv(__DIR__);
$env->load();
```

#### Fast Load Option

Use the `fastLoad` option in the constructor to load variables immediately:

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;

// Load immediately upon instantiation
$env = new TinyEnv(__DIR__, true);
```
#### Lazy Load Option

Load only variables matching specific prefixes:

```php
$env->lazy(['DB']); // Loads only variables starting with DB_
echo env('DB_HOST'); // Output: localhost
echo env('NAME', 'N/A'); // Output: N/A (NAME not loaded)
```

---

### Usage

`TinyEnv` provides simple methods and helper functions to work with environment variables. Below are examples based on a sample `.env` file:

```
NAME=TinyEnv
VERSION=1.0.4
DB_HOST=localhost
```

#### 1. `env()` - Retrieve Environment Variables

Use the global `env()` function to get environment variables:

```php
use function Datahihi1\TinyEnv\env;

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
use function Datahihi1\TinyEnv\setenv;

setenv('KEY', 'demo'); // Sets KEY=demo in $_ENV and .env file
echo env('KEY'); // Output: demo
```

**Note**: To disable file writing, use `TinyEnv::setAllowFileWrites(false)`.

#### 3. `validate()` - Validate Variables

Ensure variables meet specific rules (e.g., `required`, `int`, `bool`, `string`):

```php
TinyEnv::validate([
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
VERSION=1.0.4
DB_HOST=localhost
DB_PORT=3306
```

### Full Example

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;
use function Datahihi1\TinyEnv\env;
use function Datahihi1\TinyEnv\setenv;

// Initialize and load
$env = new TinyEnv(__DIR__);
$env->load();

// Access variables
echo env('NAME', 'Unknown'); // TinyEnv
echo env('NOT_FOUND', 'Default'); // Default

// Set a new variable
setenv('APP_DEBUG', true);

// Validate
TinyEnv::validate(['APP_DEBUG' => 'bool']);

// Refresh
$env->refresh();
```

### Notes

- Ensure the `.env` file and its directory are readable/writable when using `setenv()`.
- Comments in `.env` files start with `#` and are ignored.
- Use uppercase letters, numbers, and underscores for variable names (e.g., `APP_KEY`).
