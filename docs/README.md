# Luberius TailwindCSS Documentation

Welcome to the documentation for Luberius TailwindCSS - a PHP library that automatically downloads and manages the TailwindCSS CLI executable for your PHP projects.

## Overview

This library provides a seamless integration of TailwindCSS CLI into PHP applications without requiring Node.js or npm. It handles:

- **Automatic platform detection** for macOS, Linux, and Windows
- **Smart executable downloads** from official GitHub releases
- **Intelligent caching** to avoid redundant downloads
- **Simple PHP API** for TailwindCSS integration

## Quick Start

```php
use Luberius\TailwindCss\TailwindCss;

$tailwind = new TailwindCss();
$executablePath = $tailwind->getBinPath();
$watchCommand = $tailwind->getWatchCommand('input.css', 'output.css');
```

## Documentation Index

- **[Installation Guide](installation.md)** - How to install and set up the library
- **[Usage Guide](usage.md)** - Examples and common use cases
- **[API Reference](api-reference.md)** - Complete API documentation
- **[Development Guide](development.md)** - Contributing and development setup
- **[Architecture](architecture.md)** - Technical design and implementation details

## Features

### Platform Support

| Operating System | Architectures |
|-----------------|---------------|
| macOS (Darwin) | arm64 (Apple Silicon), x86_64 (Intel) |
| Linux | x86_64, aarch64 (ARM64) |
| Windows | x86_64 |

### Key Capabilities

- **Zero Configuration**: Works out of the box with automatic platform detection
- **Fast Caching**: Downloaded executables are cached in the system temp directory
- **Reliable Recovery**: Cache persists across application restarts
- **Progress Feedback**: Visual progress bar during downloads
- **Type Safety**: Built for PHP 8.0+ with modern language features

## Requirements

- PHP 8.0 or higher
- Internet connection (for first-time executable download)
- Supported operating system and architecture

## License

MIT License - see LICENSE file for details

## Support

- **Issues**: [GitHub Issues](https://github.com/luberius/tailwindcss-php/issues)
- **Author**: Syahril A P (callmesyahril@gmail.com)
