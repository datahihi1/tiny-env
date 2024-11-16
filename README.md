# Tiny Env
A simple environment loader for PHP applications, used for small projects that don't use a lot of resources but still ensure stable performance.

## Installation

#### With PHP pure project:

For pure PHP projects, TinyEnv should be set to the following structure:

![Structure](https://datahihi1.id.vn/Screenshot_2024-11-15_161338.png)

#### With project use Composer:

Installation is super-easy via [Composer](https://getcomposer.org/):

```bash
$ composer require datahihi1/tiny-env
```

or add it by hand to your `composer.json` file.

## Usage

And usage, just need to request the library's autoload file:

```php
require __DIR__ . '/tiny-env/loader.php'; // or require 'tiny-env/loader.php';
use Datahihi1\TinyEnv\TinyEnv; // when use function in library: putenv(), getenv()
```

With projects use Composer, add:

```php
$env = new TinyEnv(__DIR__ . '/../');
$env->load();
```