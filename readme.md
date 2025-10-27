# TinyEnv (minified version)

**Note:** This is a minified version of TinyEnv for projects that require a single-file solution. For the full-featured version with source code, tests, and documentation, please use the main repository: [datahihi1/tiny-env](https://github.com/datahihi1/tiny-env).

A lightweight .env loader for PHP projects.

⚡ Fast, 🛡️ Safe, 🎯 Simple — designed for small to medium projects.

### Installation
```bash
composer require datahihi1/tiny-env:dev-minify
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
All features are similar to the full version, you can refer to the main repository for detailed documentation.
