# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive documentation in `/docs` directory
- LICENSE file (MIT)
- .gitattributes for composer package optimization
- .editorconfig for consistent coding style
- phpcs.xml for PSR-12 code style checking
- Enhanced test suite with 8 additional test cases covering edge cases
- Test groups for better test organization (@group annotations)
- composer.json metadata (keywords, homepage, support URLs)

### Changed
- Improved test documentation with detailed docblocks
- Updated composer.json with archive excludes configuration
- Set minimum-stability to "stable" in composer.json

### Fixed
- Cache file validation to check file existence (commit f663292)
- Cache persistence across application restarts

## [1.0.0] - 2024-09-22

### Added
- Initial release
- Automatic TailwindCSS CLI download for PHP projects
- Platform detection for macOS (Intel/Apple Silicon), Linux (x64/ARM64), Windows
- Smart caching system using Symfony Cache
- Progress bar for downloads using Symfony Console
- PHPUnit test suite with 11 test methods
- Support for custom binary paths and cache directories
- Watch command generation for TailwindCSS compilation

### Fixed
- Vendor directory detection for proper binary placement
- Changed vendor name to "luberius"
- Added download logging and caching functionality

## Release Notes

### Version 1.0.0 (September 22, 2024)

First stable release of Luberius TailwindCSS - a PHP library for automatic TailwindCSS CLI management.

**Features:**
- Zero-configuration setup
- Automatic platform detection
- Fast caching (< 5 seconds retrieval)
- Comprehensive test coverage
- PSR-4 autoloading
- MIT License

**Requirements:**
- PHP 8.0 or higher
- Symfony Cache ^5.4
- Symfony Console ^5.4

**Installation:**
```bash
composer require luberius/tailwindcss
```

**Quick Start:**
```php
use Luberius\TailwindCss\TailwindCss;

$tailwind = new TailwindCss();
$executablePath = $tailwind->getBinPath();
```

---

[Unreleased]: https://github.com/luberius/tailwindcss-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/luberius/tailwindcss-php/releases/tag/v1.0.0
