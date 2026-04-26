# 01-11-2025

### Added
- Now I added changelog file to keep track of changes made to the project.

### Changed
- Improved code documentation and comments for better clarity.
- Formatted code according to PSR-12 coding standards.

# 02-11-2025

### Fixed
- Added maximum allowed recursive substitution depth to avoid DoS via long/cyclic chains.
- Added isDangerousValue() method to identify potentially dangerous values.

### Changed
- Added $populateSuperglobals parameter to the constructor of the main class to control whether superglobals should be populated.

1. Before versions dated 11-2-2025:
```php
$tinyenv = new \Datahihi10\TinyEnv\TinyEnv(__DIR__ . '/path/to/.env');
$tinyenv->load();

var_dump($_ENV); // Superglobals are populated
```

2. After versions dated 11-2-2025:
```php
$tinyenv = new \Datahihi10\TinyEnv\TinyEnv(__DIR__ . '/path/to/.env');
$tinyenv->load();
var_dump($_ENV); // Superglobals are NOT populated by default
```

**Use:**
```php
$tinyenv = new \Datahihi1\TinyEnv\TinyEnv(__DIR__ . '/path/to/.env');
$tinyenv->populateSuperglobals(); // Enable superglobals population
$tinyenv->load();
// or use fastLoad
$tinyenv = new \Datahihi1\TinyEnv\TinyEnv(__DIR__ . '/path/to/.env', true);
```

# 08-11-2025

### Added
- Added support `"/.../"` syntax to treat values as strings, even if they look like numbers or booleans.
- Added clearCache() method to clear the internal cache of loaded environment variables.

### Deprecated
- Deprecated lazy() method. It does not seem to have a practical use case.
- Deprecated safeLoad() method. Use load(noFile: true) instead. I want to optimize and simplify the codebase.

# 08-04-2026
### Added
- Added allowWrapperSchemes() method to opt-in allowing specific stream wrapper schemes (e.g. `phar`) in env values.

# 26-04-2026
### Changed

- Now, TinyEnv version 1.1.0 or higher requires PHP 8.0 or higher.
- Changed the priority order of .env files loaded by envfiles() method. To prioritize the .env file, set $prioritizeEnv to true and it will be loaded first and have the highest priority, allows overwriting other files . By default, $prioritizeEnv is false, and files are loaded in the order they are specified, the pre-declaration file will have the highest priority.

Example:

```php
$env->envfiles(['.env.production','.env', '.env.local']); // .env.production will override .env.local, and .env will override both
$env->envfiles(['.env.production','.env', '.env.local'], prioritizeEnv: true); // .env will be highest priority and override both .env.local and .env.production, while .env.production will override .env.local
$env->envfiles(['.env.production', '.env.local'], prioritizeEnv: true); // Although .env has been removed in the list, but .env will still be prioritized if it exists
```