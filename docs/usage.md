# Usage Guide

This guide covers common use cases and examples for using the Luberius TailwindCSS library.

## Basic Usage

### Getting the Executable

The most basic usage is to get the path to the TailwindCSS executable:

```php
use Luberius\TailwindCss\TailwindCss;

$tailwind = new TailwindCss();
$executablePath = $tailwind->getBinPath();

echo "TailwindCSS CLI is located at: $executablePath\n";
```

### Generating Watch Commands

To compile and watch CSS files:

```php
$tailwind = new TailwindCss();
$command = $tailwind->getWatchCommand('input.css', 'output.css');

// $command is an array: ['/path/to/tailwindcss', '-i', 'input.css', '-o', 'output.css', '--watch']
```

## Common Use Cases

### 1. Build Process Integration

Integrate TailwindCSS compilation into your build process:

```php
use Luberius\TailwindCss\TailwindCss;

class AssetBuilder
{
    private TailwindCss $tailwind;

    public function __construct()
    {
        $this->tailwind = new TailwindCss();
    }

    public function buildCSS(): void
    {
        $executable = $this->tailwind->getBinPath();
        $inputFile = 'resources/css/app.css';
        $outputFile = 'public/css/app.css';

        $command = sprintf(
            '%s -i %s -o %s --minify',
            escapeshellarg($executable),
            escapeshellarg($inputFile),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('CSS build failed: ' . implode("\n", $output));
        }

        echo "CSS built successfully!\n";
    }
}

// Usage
$builder = new AssetBuilder();
$builder->buildCSS();
```

### 2. Development Watcher

Create a development watcher script:

```php
use Luberius\TailwindCss\TailwindCss;

$tailwind = new TailwindCss();
$command = $tailwind->getWatchCommand(
    'resources/css/app.css',
    'public/css/app.css'
);

echo "Starting TailwindCSS watcher...\n";
echo "Press Ctrl+C to stop\n\n";

$process = proc_open(
    $command,
    [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ],
    $pipes
);

if (is_resource($process)) {
    while ($line = fgets($pipes[1])) {
        echo $line;
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}
```

### 3. Programmatic Compilation

Compile CSS files programmatically:

```php
use Luberius\TailwindCss\TailwindCss;

class CSSCompiler
{
    private TailwindCss $tailwind;

    public function __construct()
    {
        $this->tailwind = new TailwindCss();
    }

    public function compile(string $input, string $output, array $options = []): bool
    {
        $executable = $this->tailwind->getBinPath();

        $args = [
            escapeshellarg($executable),
            '-i', escapeshellarg($input),
            '-o', escapeshellarg($output)
        ];

        if ($options['minify'] ?? false) {
            $args[] = '--minify';
        }

        if ($options['config'] ?? null) {
            $args[] = '-c';
            $args[] = escapeshellarg($options['config']);
        }

        $command = implode(' ', $args);
        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }
}

// Usage
$compiler = new CSSCompiler();
$success = $compiler->compile(
    'input.css',
    'output.css',
    ['minify' => true, 'config' => 'tailwind.config.js']
);

if ($success) {
    echo "Compilation successful!\n";
} else {
    echo "Compilation failed!\n";
}
```

### 4. Laravel Integration

Example integration with Laravel:

```php
// app/Services/TailwindService.php
namespace App\Services;

use Luberius\TailwindCss\TailwindCss;

class TailwindService
{
    private TailwindCss $tailwind;

    public function __construct()
    {
        $this->tailwind = new TailwindCss();
    }

    public function compile(): void
    {
        $inputPath = resource_path('css/app.css');
        $outputPath = public_path('css/app.css');

        $executable = $this->tailwind->getBinPath();

        $command = sprintf(
            '%s -i %s -o %s --minify',
            escapeshellarg($executable),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            logger()->error('TailwindCSS compilation failed', [
                'output' => $output,
                'exitCode' => $exitCode
            ]);
            throw new \RuntimeException('CSS compilation failed');
        }

        logger()->info('TailwindCSS compiled successfully');
    }
}

// app/Console/Commands/CompileCss.php
namespace App\Console\Commands;

use App\Services\TailwindService;
use Illuminate\Console\Command;

class CompileCss extends Command
{
    protected $signature = 'css:compile';
    protected $description = 'Compile TailwindCSS';

    public function handle(TailwindService $tailwind): void
    {
        $this->info('Compiling CSS...');
        $tailwind->compile();
        $this->info('CSS compiled successfully!');
    }
}
```

### 5. Symfony Integration

Example integration with Symfony:

```php
// src/Service/TailwindCompiler.php
namespace App\Service;

use Luberius\TailwindCss\TailwindCss;
use Symfony\Component\Process\Process;

class TailwindCompiler
{
    private TailwindCss $tailwind;
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->tailwind = new TailwindCss();
        $this->projectDir = $projectDir;
    }

    public function watch(): Process
    {
        $command = $this->tailwind->getWatchCommand(
            $this->projectDir . '/assets/css/app.css',
            $this->projectDir . '/public/css/app.css'
        );

        $process = new Process($command);
        $process->setTimeout(null);
        $process->start();

        return $process;
    }

    public function build(): void
    {
        $executable = $this->tailwind->getBinPath();

        $process = new Process([
            $executable,
            '-i', $this->projectDir . '/assets/css/app.css',
            '-o', $this->projectDir . '/public/css/app.css',
            '--minify'
        ]);

        $process->mustRun();
    }
}
```

## Advanced Usage

### Custom Executable Path

Use a pre-downloaded TailwindCSS executable:

```php
$tailwind = new TailwindCss('/path/to/custom/tailwindcss');
$executablePath = $tailwind->getBinPath();
```

### Custom Cache Directory

Specify a custom cache location:

```php
$tailwind = new TailwindCss(null, '/var/cache/tailwindcss');
```

### Cache Management

Clear the cache when needed:

```php
$tailwind = new TailwindCss();

// Clear cache
$tailwind->clearCache();

// Get cache directory
$cacheDir = $tailwind->getCacheDir();
echo "Cache directory: $cacheDir\n";
```

### Error Handling

Proper error handling for production use:

```php
use Luberius\TailwindCss\TailwindCss;

try {
    $tailwind = new TailwindCss();
    $executable = $tailwind->getBinPath();

    // Use the executable...

} catch (\RuntimeException $e) {
    // Handle platform not supported
    if (str_contains($e->getMessage(), 'Unsupported OS')) {
        error_log('TailwindCSS: Platform not supported - ' . $e->getMessage());
        // Fallback to alternative CSS processing
    }

    // Handle download failures
    if (str_contains($e->getMessage(), 'Failed to download')) {
        error_log('TailwindCSS: Download failed - ' . $e->getMessage());
        // Retry or use cached version
    }

    throw $e;
}
```

## Best Practices

### 1. Singleton Pattern

Create a single instance and reuse it:

```php
class TailwindFactory
{
    private static ?TailwindCss $instance = null;

    public static function getInstance(): TailwindCss
    {
        if (self::$instance === null) {
            self::$instance = new TailwindCss();
        }

        return self::$instance;
    }
}

// Usage
$tailwind = TailwindFactory::getInstance();
```

### 2. Environment-Specific Behavior

Different behavior for development vs production:

```php
$tailwind = new TailwindCss();
$executable = $tailwind->getBinPath();

$args = [
    $executable,
    '-i', 'input.css',
    '-o', 'output.css'
];

if (getenv('APP_ENV') === 'production') {
    $args[] = '--minify';
} else {
    $args[] = '--watch';
}
```

### 3. Logging

Add logging for debugging:

```php
use Psr\Log\LoggerInterface;
use Luberius\TailwindCss\TailwindCss;

class TailwindWrapper
{
    private TailwindCss $tailwind;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->tailwind = new TailwindCss();

        $this->logger->info('TailwindCSS initialized', [
            'executable' => $this->tailwind->getBinPath(),
            'cache_dir' => $this->tailwind->getCacheDir()
        ]);
    }
}
```

## Next Steps

- Check the [API Reference](api-reference.md) for detailed method documentation
- Learn about [Architecture](architecture.md) for understanding internals
- See [Development Guide](development.md) for contributing
