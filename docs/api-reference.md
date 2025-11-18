# API Reference

Complete API documentation for the `Luberius\TailwindCss\TailwindCss` class.

## Class: TailwindCss

**Namespace**: `Luberius\TailwindCss`

**Description**: Manages TailwindCSS CLI executable download, caching, and command generation.

**Location**: `src/TailwindCSS.php`

---

## Constructor

### `__construct(?string $binPath = null, ?string $cacheDir = null)`

Creates a new TailwindCss instance and ensures the executable is available.

**Parameters**:
- `$binPath` (string|null) - Optional custom path to TailwindCSS executable
- `$cacheDir` (string|null) - Optional custom cache directory path

**Throws**:
- `RuntimeException` - If platform is unsupported or download fails

**Example**:
```php
// Default configuration
$tailwind = new TailwindCss();

// Custom executable path
$tailwind = new TailwindCss('/path/to/tailwindcss');

// Custom cache directory
$tailwind = new TailwindCss(null, '/custom/cache');

// Both custom
$tailwind = new TailwindCss('/path/to/tailwindcss', '/custom/cache');
```

**Behavior**:
1. Determines vendor directory location
2. Sets up cache directory (default: `sys_get_temp_dir() . '/tailwindcss-cache'`)
3. Initializes Symfony FilesystemAdapter for caching
4. Calls `getOrDownloadExecutable()` to ensure executable is ready

---

## Public Methods

### `getBinPath(): string`

Returns the path to the TailwindCSS executable.

**Returns**: `string` - Absolute path to the executable

**Example**:
```php
$tailwind = new TailwindCss();
$path = $tailwind->getBinPath();
// Returns: /path/to/vendor/bin/tailwindcss-macos-arm64
```

**Location**: `src/TailwindCSS.php:133`

---

### `getWatchCommand(string $inputCss, string $outputCss): array`

Generates a TailwindCSS watch command array suitable for `proc_open()` or `Process` class.

**Parameters**:
- `$inputCss` (string) - Path to input CSS file
- `$outputCss` (string) - Path to output CSS file

**Returns**: `array` - Command array with executable and arguments

**Example**:
```php
$tailwind = new TailwindCss();
$command = $tailwind->getWatchCommand('input.css', 'output.css');

// Returns:
// [
//     '/path/to/tailwindcss',
//     '-i',
//     'input.css',
//     '-o',
//     'output.css',
//     '--watch'
// ]

// Use with proc_open
$process = proc_open($command, $descriptors, $pipes);

// Or with Symfony Process
use Symfony\Component\Process\Process;
$process = new Process($command);
$process->start();
```

**Location**: `src/TailwindCSS.php:142`

---

### `getCacheDir(): string`

Returns the cache directory path where downloaded executables are stored.

**Returns**: `string` - Absolute path to cache directory

**Example**:
```php
$tailwind = new TailwindCss();
$cacheDir = $tailwind->getCacheDir();
// Returns: /tmp/tailwindcss-cache (Unix)
// Or: C:\Users\Username\AppData\Local\Temp\tailwindcss-cache (Windows)
```

**Location**: `src/TailwindCSS.php:151`

---

### `clearCache(): void`

Clears all cached TailwindCSS executables.

**Returns**: `void`

**Example**:
```php
$tailwind = new TailwindCss();
$tailwind->clearCache();

// Cache is now empty, next call will re-download executable
$tailwind->getBinPath(); // Triggers download
```

**Behavior**:
- Calls `clear()` on the Symfony Cache adapter
- Removes all cached files from the cache directory
- Next executable retrieval will download from GitHub

**Use Cases**:
- Forcing a fresh download of the executable
- Troubleshooting cache corruption issues
- Clearing disk space

---

## Private/Protected Methods

These methods are used internally but documented for understanding and testing purposes.

### `getOrDownloadExecutable(): string`

**Visibility**: Private

**Description**: Main logic for retrieving or downloading the TailwindCSS executable.

**Returns**: `string` - Path to the executable

**Throws**: `RuntimeException` - If download or file operations fail

**Algorithm**:
1. Checks cache for existing executable
2. If cache hit and file exists: copies to bin directory, returns path
3. If cache miss: downloads fresh executable
4. Copies downloaded executable to cache
5. Saves cache metadata
6. Returns path to executable in bin directory

**Location**: `src/TailwindCSS.php:46-76`

---

### `downloadExecutable(): string`

**Visibility**: Private

**Description**: Downloads TailwindCSS executable from GitHub with progress bar.

**Returns**: `string` - Path to downloaded executable

**Throws**: `RuntimeException` - If download fails or file operations fail

**Behavior**:
1. Detects current platform OS and architecture
2. Determines correct executable filename
3. Creates ConsoleOutput with ProgressBar
4. Downloads from: `https://github.com/tailwindlabs/tailwindcss/releases/latest/download/{filename}`
5. Shows progress during download
6. Writes to bin directory
7. Sets executable permissions (chmod 0755 on Unix)
8. Returns path to downloaded file

**Location**: `src/TailwindCSS.php:78-114`

---

### `getExecutableFilename(string $os, string $arch): string`

**Visibility**: Private

**Description**: Maps OS and architecture to TailwindCSS executable filename.

**Parameters**:
- `$os` (string) - Operating system (Darwin, Linux, Windows)
- `$arch` (string) - CPU architecture (arm64, x86_64, aarch64)

**Returns**: `string` - Executable filename

**Throws**: `RuntimeException` - If OS/architecture combination is unsupported

**Supported Combinations**:

| OS | Architecture | Filename |
|----|--------------|----------|
| Darwin | arm64 | `tailwindcss-macos-arm64` |
| Darwin | x86_64 | `tailwindcss-macos-x64` |
| Linux | x86_64 | `tailwindcss-linux-x64` |
| Linux | aarch64 | `tailwindcss-linux-arm64` |
| Windows | x86_64 | `tailwindcss-windows-x64.exe` |

**Example**:
```php
$filename = $this->getExecutableFilename('Darwin', 'arm64');
// Returns: tailwindcss-macos-arm64

$filename = $this->getExecutableFilename('Linux', 'aarch64');
// Returns: tailwindcss-linux-arm64
```

**Location**: `src/TailwindCSS.php:116-126`

---

### `findVendorDirectory(): string`

**Visibility**: Private

**Description**: Finds the Composer vendor directory by traversing up the directory tree.

**Returns**: `string` - Path to vendor/bin directory

**Behavior**:
1. Starts from current class file location
2. Traverses up directory tree
3. Looks for `vendor/bin/` directory
4. Falls back to package's own `bin/` directory if not found

**Example**:
```php
$vendorBin = $this->findVendorDirectory();
// Returns: /path/to/project/vendor/bin
```

**Use Case**: Ensures executables are placed in the correct bin directory whether the package is used as a dependency or standalone.

**Location**: `src/TailwindCSS.php:128-131`

---

## Protected Methods (For Testing)

These methods wrap PHP built-in functions to allow mocking in unit tests.

### `fileGetContents(string $url, $context = null): string|false`

**Visibility**: Protected

**Description**: Wrapper for `file_get_contents()`

**Location**: `src/TailwindCSS.php:153-156`

---

### `filePutContents(string $filename, $data): int|false`

**Visibility**: Protected

**Description**: Wrapper for `file_put_contents()`

**Location**: `src/TailwindCSS.php:158-161`

---

### `mkdir(string $directory, int $permissions = 0755, bool $recursive = true): bool`

**Visibility**: Protected

**Description**: Wrapper for `mkdir()`

**Location**: `src/TailwindCSS.php:163-166`

---

## Exceptions

### RuntimeException

The class throws `RuntimeException` in these scenarios:

#### 1. Unsupported Platform

```php
throw new \RuntimeException('Unsupported OS/architecture combination: ' . $os . '/' . $arch);
```

**When**: OS or architecture is not in the supported combinations list

**Example Error**:
```
RuntimeException: Unsupported OS/architecture combination: FreeBSD/x86_64
```

#### 2. Download Failure

```php
throw new \RuntimeException('Failed to download TailwindCSS executable');
```

**When**: Network error or GitHub unavailable

**Example Error**:
```
RuntimeException: Failed to download TailwindCSS executable
```

#### 3. File Write Failure

```php
throw new \RuntimeException('Failed to save TailwindCSS executable to ' . $binPath);
```

**When**: Insufficient permissions or disk space

**Example Error**:
```
RuntimeException: Failed to save TailwindCSS executable to /vendor/bin/tailwindcss
```

---

## Constants and Properties

### Private Properties

```php
private string $binPath;           // Path to TailwindCSS executable
private FilesystemAdapter $cache;  // Symfony cache adapter
private string $cacheDir;          // Cache directory path
```

---

## Dependencies

### Required Dependencies

- **symfony/cache** (^5.4): Provides `FilesystemAdapter` for caching
- **symfony/console** (^5.4): Provides `ConsoleOutput` and `ProgressBar` for UI

### Usage in Code

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\ProgressBar;
```

---

## Platform Detection

The class uses PHP built-in functions for platform detection:

```php
$os = PHP_OS_FAMILY;           // Returns: Darwin, Linux, Windows
$arch = php_uname('m');        // Returns: arm64, x86_64, aarch64
```

---

## Cache Strategy

### Cache Key

```php
'tailwindcss_executable'
```

### Cache Storage

- **Location**: `{cache_dir}/tailwindcss-{os}-{arch}`
- **Metadata**: Stored via Symfony Cache
- **Persistence**: Survives application restarts

### Cache Validation

```php
if ($cacheItem->isHit() && file_exists($cachePath)) {
    // Use cached version
} else {
    // Download fresh version
}
```

Validates both cache metadata AND actual file existence.

---

## Version Information

- **PHP Requirement**: ^8.0
- **Uses PHP 8 Features**:
  - Match expressions
  - Typed properties
  - Null coalescing operator

---

## See Also

- [Usage Guide](usage.md) - Practical examples
- [Architecture](architecture.md) - Design decisions
- [Development Guide](development.md) - Testing and contributing
