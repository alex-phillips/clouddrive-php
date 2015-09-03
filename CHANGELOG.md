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
- Added config value to allow duplicate file uploads in different locations (suppress dedup check in API)
- Added support of the `ls` command with a direct file node to just display its information (pass `-a` flag to show its child assets)
- Added `link` command to generate pre-authenticated temp links to share files
- Added config value to suppress trashed nodes in `ls` output
- Method in the `Node` class `inTrash` returns if the node's status is in the trash or not
- Ability to now download FOLDER nodes

### Deprecated
- Passing in a resource stream into the `upload` method has been replaced by a callable (see 'added')

### Fixed
- `config` command was not properly outputting `bool` values when reading an individual item
- `PHP` shebang path is now more universal in the `bin` file
- Error messages now to through `STDERR`
- MD5 queries return an array since multiple files can have the same MD5 with duplicate uploads enabled
