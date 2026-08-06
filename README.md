# Luberius TailwindCSS

A PHP package for integrating TailwindCSS into your projects. This package provides an easy way to download and use the TailwindCSS executable in your PHP applications.

## Requirements

- PHP 8.0 or higher
- Composer

## Installation

You can install this package via Composer. Run the following command in your project directory:

```bash
composer require luberius/tailwindcss
```

## Usage

Here's a basic example of how to use the TailwindCSS package:

```php
use Luberius\TailwindCss\TailwindCss;

// Initialize TailwindCSS
$tailwind = new TailwindCss();

// Get the path to the TailwindCSS executable
$executablePath = $tailwind->getBinPath();

// Generate a watch command
$watchCommand = $tailwind->getWatchCommand('input.css', 'output.css');

// Use the watch command in your application
// For example, you might execute it using proc_open() or similar
```

### Tailwind CSS v4 Usage

When using with Tailwind CSS v4, your CSS file should use the new import syntax:

```css
/* input.css */
@import "tailwindcss";

/* Optional: Add custom theme */
@theme {
  --color-primary: #3f3cbb;
  --font-display: "Inter", sans-serif;
  --breakpoint-3xl: 1920px;
}
```

## How It Works

The package automatically:
1. Determines the correct Tailwind CSS executable for your OS and architecture
2. Downloads the latest version from GitHub releases
3. Caches the binary for fast subsequent usage
4. Installs to `vendor/bin` directory

### Supported Platforms

- macOS (ARM64, x86_64)
- Linux (x86_64, ARM64)
- Windows (x86_64)

### Version Management

The package reads the latest GitHub release metadata and installs the matching platform binary. Release tags and asset URLs are validated, and the downloaded file is verified against GitHub's SHA-256 digest before it is made executable. Binaries are cached locally and revalidated before reuse.

## Development

To set up the project for development:

1. Clone the repository
2. Run `composer install` to install dependencies
3. Run `composer test` to run the test suite

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

If you encounter any problems or have any questions, please open an issue on the GitHub repository.
