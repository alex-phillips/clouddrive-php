# Changelog

All Notable changes to `clouddrive-php` will be documented in this file

## 0.1.1

### Added
- Indices to database tables for improved performance
- `composer.json` now has PHP version requirement
- `box.json` file added for packaging as `.phar`

### Fixed
- `config` command was not properly outputting `bool` values when reading an individual item
- `PHP` shebang path is now more universal in the `bin` file
