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

## Configuration

The package will automatically determine the correct executable for your operating system and architecture. The executable will be downloaded to the `vendor/bin` directory when installed via Composer.

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
