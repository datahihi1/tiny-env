# TinyEnv
A simple environment loader for PHP applications, used for small projects that don't use a lot of resources but still ensure stable performance. It's can load environment variable in .env, .env.example, .ini

## Installation and Usage

#### With PHP pure project:

##### Install:

For pure PHP projects, TinyEnv should be set to the following structure:

![Structure](https://datahihi1.id.vn/Screenshot_2024-12-20_225512.png)

NOTE: index.php is a small example to show how the package works.

##### Usage:

To use it (at index.php), requires the library's autoloader file (loader.php):

```php
require 'package/TinyEnv/loader.php';
```

And next, we need to create a new object of the library class and load it:

```php
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv(__DIR__); // only load .env at project/
$env->load();
```
or:

```php
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv([__DIR__,__DIR__.'/dir2']);
$env->load();
```
Note: It will load .env at project/ and project/dir2/ simultaneously, but will prioritize getting environment variables at the last directory (project/dir2/ ).

Here are some example environment variables:

```env
NAME=TinyEnv
VERSION=1.0.0
```

To get environment variables, use the env() function:

```php
$env = env('NAME');
print_r($env); // Result: TinyEnv
```

If an environment variable is not declared, a default value can be assigned instead:

```php
$env = env('TESTER','Datahihi1');
print_r($env); // Result: Datahihi1
```

#### With project use Composer:

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

##### Usage:

Composer autoload requirements:

```php
require 'vendor/autoload.php';
```

And need to create a new object of the library class and load it:

```php
use Datahihi1\TinyEnv\TinyEnv;
$env = new TinyEnv(__DIR__); // only load .env at project/
$env->load();
```

But now to use the env() function, you need to point directly at the library function:

###### With PHP 5.4 < 5.6 :
```php
$env = Datahihi1\TinyEnv\env('HOST','localhost');
```

###### With PHP 5.6 above :
```php
use function Datahihi1\TinyEnv\env;
$env = env('HOST','localhost');
```