# TinyEnv
A simple environment variable loader for PHP applications, used for small projects that don't use a lot of resources but still ensure stable performance. It's can load environment variable in .env, .env.example, .ini

### Install and Setup:

##### With PHP pure project:

For pure PHP projects, TinyEnv should be set to the following structure:

![Structure](https://datahihi1.id.vn/uploads/Screenshot_2025-01-18_121003.png)

NOTE: index.php is a small example to show how the package works.

To use `TinyEnv`, requires the library's loader file and create a new object of the library class to load it:

```php
require 'package/tiny-env/loader.php';
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv(__DIR__);
$env->load();
```

##### With project use Composer:

Installation is super-easy with [Composer](https://getcomposer.org/):

```bash
$ composer require datahihi1/tiny-env
```

or add it by hand to your `composer.json` file:

```json
    "require": {
      "datahihi1/tiny-env": "^1.0.0"
    }
```

Composer autoload requirements and create a new object of the library class and load it:

```php
require 'vendor/autoload.php';
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv(__DIR__);
$env->load();
```
NOTE: You can also load only the .env file with `$onlyEnvFile` set to ``true``

```php
$env = new TinyEnv(__DIR__,true); // load only .env
```

### Usage:

###### `env()`:

Here are some example environment variables:

```env
NAME=TinyEnv
VERSION=1.1.0
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

###### `setenv()`:

To set or update an environment variable, use the `setenv()` function:

```php
use function Datahihi1\TinyEnv\setenv;
setenv('KEY','ffyflaslj'); // will set or update environment variable in .env file
```