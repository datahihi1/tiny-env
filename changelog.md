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