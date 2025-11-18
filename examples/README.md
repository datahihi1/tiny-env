# Example for using TinyEnv

This document provides examples of how to use TinyEnv in various scenarios. Each example demonstrates different features and functionalities of the TinyEnv library.

## Examples

### 1. `basic_usage.php`
Basic usage example - initialization, loading, and usage.

```bash
php examples/basic_usage.php
```

### 2. `database_config.php`
Database configuration with TinyEnv.

```bash
php examples/database_config.php
```

### 3. `multi_environment.php`
Setup multi-environment (local, staging, production).

```bash
APP_ENV=local php examples/multi_environment.php
APP_ENV=production php examples/multi_environment.php
```

### 4. `variable_interpolation.php`
Example of variable interpolation.

```bash
php examples/variable_interpolation.php
```

### 5. `type_parsing.php`
Example of automatic type parsing.

```bash
php examples/type_parsing.php
```

### 6. `advanced_usage.php`
Example of advanced usage.

```bash
php examples/advanced_usage.php
```

## Requirements

All examples require a `.env` file in the root directory of the project.

Example `.env`:
```env
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=myapp
DB_USER=root
DB_PASS=secret

# App
APP_NAME=TinyEnv Demo
APP_DEBUG=true
APP_URL=http://localhost

# Variables for interpolation
DB_URL=${DB_HOST}:${DB_PORT}
USER_NAME=
USER=${USER_NAME:-guest}
ALT_USER=${USER_NAME-guest}

# Type examples
FEATURE_ENABLED=yes
FEATURE_DISABLED=no
MAX_USERS=100
PRICE=99.99
TAX_RATE=0.15
OPTIONAL_VAR=
NUM_STRING="/123/"
BOOL_STRING="/true/"

# API
API_BASE_URL=https://api.example.com
API_VERSION=v1
API_FULL_URL=${API_BASE_URL}/${API_VERSION}
```

## Run All Examples

```bash
for file in examples/*.php; do
    echo "=== Running $file ==="
    php "$file"
    echo ""
done
```

