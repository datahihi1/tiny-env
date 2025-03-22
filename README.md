# TinyEnv

A simple environment variable loader for PHP applications, used for small projects that don't use a lot of resources but still ensure stable performance. It's can load environment variable in .env file.

### Install and Setup

Installation is super-easy with [Composer](https://getcomposer.org/):

```bash
composer require datahihi1/tiny-env
```

or add it by hand to your `composer.json` file:

```json
  "require": {
      "datahihi1/tiny-env": "^1.0.2"
  }
```

Composer autoload requirements and create a new object of the library class and load it:

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv(__DIR__);
$env->load();
```

Or you can use the fastLoad option in the constructor to load the environment variable immediately.

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv(__DIR__,true);
```

### Usage

###### `env()`

Here are some example environment variables:

```env
NAME=TinyEnv
VERSION=1.0.2
```

To get environment variables, use the `env()` function:

```php
use function Datahihi1\TinyEnv\env;
$env = env('NAME');
print_r($env); // Result: TinyEnv
```

If an environment variable is not declared, a default value can be assigned instead:

```php
use function Datahihi1\TinyEnv\env;
$env = env('TESTER','Datahihi1');
print_r($env); // Result: Datahihi1
```

**Hint**: You can also use the env() function to get all existing environment variables:

```php
print_r(env());
```

###### `setenv()`

To set or update an environment variable, use the `setenv()` function:

```php
use function Datahihi1\TinyEnv\setenv;
setenv('KEY','demo'); // will set or update environment variable in .env file
```

###### `unload()`

Unloads environment variables by clearing the $_ENV array and cache.

```php
print_r(env()); // Output is variable in .env file.
$env->unload();
print_r(env()); // Nothing output. Variable has been cleared
```