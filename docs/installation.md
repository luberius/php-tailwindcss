# Installation Guide

## System Requirements

Before installing, ensure your system meets these requirements:

- **PHP Version**: 8.0 or higher
- **Composer**: Latest stable version recommended
- **Operating System**: macOS, Linux, or Windows
- **Architecture**: x86_64 or ARM64/aarch64
- **Internet Connection**: Required for first-time download

## Installation via Composer

### Standard Installation

Install the package using Composer:

```bash
composer require luberius/tailwindcss
```

This will:
1. Download the library files to your `vendor/` directory
2. Register the PSR-4 autoloader
3. Make the library available in your project

### Development Installation

If you're contributing or want to run tests:

```bash
composer require --dev luberius/tailwindcss
```

## Post-Installation

### Automatic Setup

On first use, the library will automatically:

1. **Detect your platform**: Identifies your OS and CPU architecture
2. **Download TailwindCSS CLI**: Fetches the appropriate executable from GitHub
3. **Cache the executable**: Stores it in your system temp directory
4. **Set permissions**: Makes the executable runnable (Unix-based systems)

### Verify Installation

Create a simple PHP script to verify the installation:

```php
<?php

require 'vendor/autoload.php';

use Luberius\TailwindCss\TailwindCss;

$tailwind = new TailwindCss();
echo "TailwindCSS executable: " . $tailwind->getBinPath() . "\n";
```

Run it:

```bash
php verify.php
```

You should see output like:

```
TailwindCSS executable: /path/to/your/project/vendor/bin/tailwindcss-macos-arm64
```

## Manual Installation (Alternative)

If you prefer not to use Composer:

1. Clone the repository:
   ```bash
   git clone https://github.com/luberius/tailwindcss-php.git
   cd tailwindcss-php
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Include the autoloader in your project:
   ```php
   require '/path/to/tailwindcss-php/vendor/autoload.php';
   ```

## Configuration

### Default Configuration

The library uses sensible defaults:

- **Executable Location**: `vendor/bin/` (or package's `bin/` directory)
- **Cache Directory**: System temp directory + `/tailwindcss-cache`
- **Download Source**: Official TailwindCSS GitHub releases

### Custom Configuration

You can customize the installation paths:

```php
use Luberius\TailwindCss\TailwindCss;

// Custom executable path
$tailwind = new TailwindCss('/custom/path/to/tailwindcss');

// Custom cache directory
$tailwind = new TailwindCss(null, '/custom/cache/directory');

// Both custom
$tailwind = new TailwindCss(
    '/custom/path/to/tailwindcss',
    '/custom/cache/directory'
);
```

## Troubleshooting

### Unsupported Platform Error

**Error**: `RuntimeException: Unsupported OS/architecture combination`

**Solution**: Verify your platform is supported:

```bash
php -r "echo PHP_OS_FAMILY . '/' . php_uname('m');"
```

Supported combinations:
- Darwin/arm64, Darwin/x86_64
- Linux/x86_64, Linux/aarch64
- Windows/x86_64

### Download Failure

**Error**: `Failed to download TailwindCSS executable`

**Possible Causes**:
- No internet connection
- GitHub is temporarily unavailable
- Firewall blocking downloads

**Solutions**:
1. Check internet connectivity
2. Verify firewall settings allow downloads from github.com
3. Try again later if GitHub is experiencing issues

### Permission Denied

**Error**: Permission errors when creating cache directory or writing files

**Solution**: Ensure PHP has write permissions:

```bash
# Check temp directory permissions
ls -la $(php -r "echo sys_get_temp_dir();")

# Fix permissions if needed (Unix)
chmod 755 /path/to/temp/tailwindcss-cache
```

### Cache Issues

If experiencing cache-related problems, clear the cache:

```php
$tailwind = new TailwindCss();
$tailwind->clearCache();
```

Or manually:

```bash
rm -rf /tmp/tailwindcss-cache  # Unix/macOS
# Or check Windows temp directory
```

## Next Steps

- Read the [Usage Guide](usage.md) for examples
- Check the [API Reference](api-reference.md) for detailed method documentation
- Learn about [Development](development.md) if you want to contribute
