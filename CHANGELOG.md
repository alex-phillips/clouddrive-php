# Changelog

All Notable changes to `clouddrive-php` will be documented in this file

## 0.1.1

### Added
- Indices to database tables for improved performance
- `composer.json` now has PHP version requirement
- `box.json` file added for packaging as `.phar`
- JSON output in CLI is now considered `verbose` and is not outputted unless desired
- 'Success' and 'failure' messages are colored accordingly
- A `callable` is now accepted to be passed into the `upload` command instead of a resource stream for writing

### Deprecated
- Passing in a resource stream into the `upload` method has been replaced by a callable (see 'added')

### Fixed
- `config` command was not properly outputting `bool` values when reading an individual item
- `PHP` shebang path is now more universal in the `bin` file
