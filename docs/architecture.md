# Architecture Documentation

Technical architecture and design decisions for the Luberius TailwindCSS library.

## Overview

This library follows a single-class design pattern focused on simplicity and ease of use. The core `TailwindCss` class handles all aspects of TailwindCSS CLI integration.

## Design Goals

1. **Zero Configuration**: Works out of the box with sensible defaults
2. **Smart Caching**: Avoid redundant downloads
3. **Platform Agnostic**: Support major platforms and architectures
4. **Simple API**: Minimal learning curve for developers
5. **Testable**: Easy to mock and test

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                      User Application                        │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ new TailwindCss()
                         │ getBinPath()
                         │ getWatchCommand()
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   TailwindCss Class                          │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Public API                                           │  │
│  │  - getBinPath()                                       │  │
│  │  - getWatchCommand()                                  │  │
│  │  - getCacheDir()                                      │  │
│  │  - clearCache()                                       │  │
│  └───────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Core Logic                                           │  │
│  │  - getOrDownloadExecutable()                          │  │
│  │  - downloadExecutable()                               │  │
│  │  - getExecutableFilename()                            │  │
│  │  - findVendorDirectory()                              │  │
│  └───────────────────────────────────────────────────────┘  │
└────────┬─────────────────────────────────┬─────────────────┘
         │                                  │
         ▼                                  ▼
┌──────────────────────┐         ┌───────────────────────────┐
│  Symfony Cache       │         │  File System              │
│  (FilesystemAdapter) │         │  - vendor/bin/            │
│                      │         │  - /tmp/tailwindcss-cache │
│  - Cache metadata    │         │  - Downloads              │
│  - Cache validation  │         │  - Permissions            │
└──────────────────────┘         └───────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────────┐
│              GitHub TailwindCSS Releases                      │
│  https://github.com/tailwindlabs/tailwindcss/releases/latest  │
└──────────────────────────────────────────────────────────────┘
```

## Component Design

### 1. TailwindCss Class

**Responsibility**: Single point of entry for all TailwindCSS CLI operations

**Key Decisions**:
- **Single Class Design**: Simplifies API, no need for multiple classes
- **Auto-Initialization**: Constructor ensures executable is ready on instantiation
- **Stateful Design**: Stores paths and cache adapter for reuse

### 2. Platform Detection

**Implementation**:
```php
$os = PHP_OS_FAMILY;      // Darwin, Linux, Windows
$arch = php_uname('m');   // arm64, x86_64, aarch64
```

**Supported Matrix**:

| Platform | Detection Method | Executable Name |
|----------|------------------|-----------------|
| macOS Apple Silicon | Darwin + arm64 | `tailwindcss-macos-arm64` |
| macOS Intel | Darwin + x86_64 | `tailwindcss-macos-x64` |
| Linux x64 | Linux + x86_64 | `tailwindcss-linux-x64` |
| Linux ARM | Linux + aarch64 | `tailwindcss-linux-arm64` |
| Windows x64 | Windows + x86_64 | `tailwindcss-windows-x64.exe` |

**Design Choice**: Used PHP 8.0 `match` expression for clean, exhaustive mapping.

### 3. Caching Strategy

#### Cache Architecture

```
System Temp Directory
└── tailwindcss-cache/
    ├── tailwindcss-macos-arm64       (actual binary)
    ├── tailwindcss-macos-x64         (actual binary)
    └── [symfony cache metadata files]
```

#### Cache Flow

**First Request**:
```
1. Check Symfony cache → Miss
2. Download from GitHub
3. Write to vendor/bin/
4. Copy to cache directory
5. Save cache metadata
6. Return path
```

**Subsequent Requests**:
```
1. Check Symfony cache → Hit
2. Verify cache file exists
3. Copy from cache to vendor/bin/
4. Return path
Duration: <5 seconds
```

#### Cache Validation

**Two-Level Validation**:
```php
if ($cacheItem->isHit() && file_exists($cachePath)) {
    // Valid cache
} else {
    // Re-download
}
```

**Rationale**:
- `isHit()`: Checks cache metadata (fast)
- `file_exists()`: Verifies actual file (prevents broken references)

#### Why This Design?

**Problem**: Original design stored bin path in cache
```php
// Old (broken)
$cache->save('path', '/vendor/bin/tailwindcss');
// If binary deleted, cache points to nothing
```

**Solution**: Store separate cache copy
```php
// New (fixed)
$cachePath = $cacheDir . '/tailwindcss-macos-arm64';
$cache->save('executable', $cachePath);
// Cache directory persists, can restore binary
```

**Benefits**:
- Survives `vendor/bin` deletion (e.g., `composer install --no-dev`)
- Survives application uninstall/reinstall
- Shared across projects using same cache directory

### 4. Download Process

#### Download Architecture

```
┌─────────────────────────────────────────────────────┐
│ downloadExecutable()                                 │
│                                                      │
│  1. Detect platform (OS + Architecture)             │
│           ▼                                          │
│  2. Get executable filename                          │
│           ▼                                          │
│  3. Build GitHub download URL                        │
│           ▼                                          │
│  4. Create progress bar                              │
│           ▼                                          │
│  5. Download with progress callback                  │
│           ▼                                          │
│  6. Write to vendor/bin                              │
│           ▼                                          │
│  7. Set executable permissions (Unix)                │
│           ▼                                          │
│  8. Return path                                      │
└─────────────────────────────────────────────────────┘
```

#### Progress Reporting

Uses Symfony Console components:
```php
$output = new ConsoleOutput();
$progressBar = new ProgressBar($output);

stream_context_create([
    'http' => [
        'notification' => function ($code, ...) use ($progressBar) {
            // Update progress bar
        }
    ]
]);
```

**Benefits**:
- Visual feedback for long downloads (2-5 MB executables)
- Professional user experience
- Easy to test (can be mocked)

### 5. Vendor Directory Detection

#### Algorithm

```php
private function findVendorDirectory(): string
{
    $dir = __DIR__;

    while ($dir !== '/') {
        if (is_dir($dir . '/vendor/bin')) {
            return $dir . '/vendor/bin';
        }
        $dir = dirname($dir);
    }

    return __DIR__ . '/../bin';  // Fallback
}
```

#### Why Traverse Up?

**Use Case 1**: Library as Dependency
```
project/
  vendor/
    luberius/
      tailwindcss/
        src/
          TailwindCSS.php  ← Start here
  vendor/bin/              ← Find this
```

**Use Case 2**: Library Standalone
```
tailwindcss-php/
  src/
    TailwindCSS.php        ← Start here
  bin/                     ← Fallback to this
```

**Design Choice**: Ensures executables are always placed in correct, accessible location.

### 6. Error Handling

#### Exception Hierarchy

```
RuntimeException
  ├─ Unsupported platform
  ├─ Download failure
  └─ File write failure
```

#### Error Cases

**1. Unsupported Platform**:
```php
throw new \RuntimeException(
    'Unsupported OS/architecture combination: ' . $os . '/' . $arch
);
```

**When**: Running on FreeBSD, Solaris, or other unsupported OS

**2. Download Failure**:
```php
throw new \RuntimeException('Failed to download TailwindCSS executable');
```

**When**: Network error, GitHub down, firewall blocking

**3. File Write Failure**:
```php
throw new \RuntimeException('Failed to save TailwindCSS executable to ' . $binPath);
```

**When**: Permission denied, disk full, read-only filesystem

#### Error Handling Philosophy

- **Fail Fast**: Throw exceptions immediately on error
- **Clear Messages**: Include context (path, platform, etc.)
- **No Silent Failures**: Never return false or null on error
- **Recoverable**: User can clear cache and retry

## Design Patterns

### 1. Facade Pattern

The `TailwindCss` class acts as a facade to:
- Symfony Cache component
- File system operations
- GitHub API (via HTTP download)
- Platform detection APIs

**Benefit**: Users interact with simple API, complexity hidden.

### 2. Template Method Pattern

Protected methods allow testing without actual I/O:

```php
protected function fileGetContents(string $url, $context = null): string|false
{
    return file_get_contents($url, false, $context);
}
```

**Tests can override**:
```php
class TailwindCssTestableWrapper extends TailwindCss
{
    protected function fileGetContents(string $url, $context = null): string|false
    {
        return 'mock data';  // No actual download
    }
}
```

**Benefit**: Fast, isolated unit tests without network calls.

### 3. Strategy Pattern

Different executable naming strategies per platform:

```php
return match ([$os, $arch]) {
    ['Darwin', 'arm64'] => 'tailwindcss-macos-arm64',
    ['Darwin', 'x86_64'] => 'tailwindcss-macos-x64',
    ['Linux', 'x86_64'] => 'tailwindcss-linux-x64',
    ['Linux', 'aarch64'] => 'tailwindcss-linux-arm64',
    ['Windows', 'x86_64'] => 'tailwindcss-windows-x64.exe',
    default => throw new \RuntimeException(/* ... */),
};
```

**Benefit**: Clean, maintainable platform mapping.

## PHP 8.0+ Features

### 1. Match Expression

**Used in**: Platform detection

**Before (PHP 7.x with switch)**:
```php
switch ([$os, $arch]) {
    case ['Darwin', 'arm64']:
        return 'tailwindcss-macos-arm64';
    case ['Darwin', 'x86_64']:
        return 'tailwindcss-macos-x64';
    // ...
    default:
        throw new RuntimeException(/* ... */);
}
```

**After (PHP 8 with match)**:
```php
return match ([$os, $arch]) {
    ['Darwin', 'arm64'] => 'tailwindcss-macos-arm64',
    ['Darwin', 'x86_64'] => 'tailwindcss-macos-x64',
    // ...
    default => throw new RuntimeException(/* ... */),
};
```

**Benefits**:
- Exhaustive (must handle all cases or default)
- Returns value (no need for temporary variable)
- Stricter type comparison (===)
- More concise

### 2. Typed Properties

```php
private string $binPath;
private FilesystemAdapter $cache;
private string $cacheDir;
```

**Benefits**:
- Type safety at runtime
- No need for type checks in methods
- Better IDE autocompletion

### 3. Constructor Property Promotion

**Could be used** (not currently):
```php
public function __construct(
    private ?string $binPath = null,
    private ?string $cacheDir = null
) {
    // Properties auto-declared and assigned
}
```

## Dependency Choices

### Symfony Cache

**Why Symfony Cache?**
- Industry standard
- Well-tested and maintained
- Flexible (can swap adapters)
- PSR-6 compliant

**Alternatives Considered**:
- PSR-16 Simple Cache: Too simple, lacks features
- Custom cache: Reinventing the wheel
- No cache: Poor user experience

### Symfony Console

**Why Symfony Console?**
- Professional progress bars
- Cross-platform output handling
- Already familiar to PHP developers

**Alternatives Considered**:
- echo/print: Ugly, no progress
- Custom progress: Complex implementation
- Monolog: Overkill for simple progress

## Performance Considerations

### Download Performance

**Factors**:
- File size: ~2-5 MB (platform dependent)
- Network speed: User's internet connection
- GitHub CDN: Usually fast worldwide

**Optimization**:
- Progress bar provides feedback
- Cache eliminates re-downloads
- Single HTTP request (no chunking)

### Cache Performance

**Benchmarks** (from tests):
- First download: Variable (network dependent)
- Cache retrieval: <5 seconds (filesystem I/O)
- Multiple instances: Shared cache, no redundant downloads

**Optimization**:
- Filesystem adapter (fast for binaries)
- No serialization overhead (stores paths)
- Lazy initialization (only download when needed)

### Memory Performance

**Memory Usage**:
- Class instance: Minimal (~1 KB)
- Download buffer: Streams to disk, not memory
- Cache overhead: Symfony metadata only

**No Memory Leaks**:
- Downloads streamed directly to file
- No storing binary in memory
- Proper resource cleanup

## Security Considerations

### Download Security

**Current Implementation**:
- Downloads from official GitHub releases
- HTTPS enforced
- No user-controlled download URL

**Not Implemented** (potential enhancements):
- SHA-256 checksum verification
- GPG signature verification
- Configurable download mirrors

### File Permissions

**Unix/Linux/macOS**:
```php
chmod($binPath, 0755);  // rwxr-xr-x
```

**Why 0755?**
- Owner: Read, write, execute
- Group: Read, execute
- Others: Read, execute

**Not World-Writable**: Prevents tampering by other users.

### Cache Security

**Concerns**:
- Cache in world-readable temp directory
- Potential for cache poisoning

**Mitigations**:
- Validates cache file existence
- Can use custom cache directory for sensitive environments
- Cache directory permissions inherit from parent

### Input Validation

**Constructor Parameters**:
```php
public function __construct(?string $binPath = null, ?string $cacheDir = null)
```

**No Validation**: Trusts caller (library code, not user input)

**Watch Command**:
```php
public function getWatchCommand(string $inputCss, string $outputCss): array
```

**Returns Array**: Allows caller to use `escapeshellarg()` as needed.

## Testability

### Test Doubles

**Protected Methods** enable mocking:
```php
// Production
protected function fileGetContents(string $url, $context = null): string|false
{
    return file_get_contents($url, false, $context);
}

// Test
class MockTailwindCss extends TailwindCss
{
    protected function fileGetContents(string $url, $context = null): string|false
    {
        return 'fake binary data';
    }
}
```

### Integration Tests

**Real Downloads**:
- `testGetOrDownloadExecutable()`: Tests actual GitHub download
- `testGetOrDownloadExecutableCache()`: Tests caching persistence

**Benefits**:
- Catches real-world issues
- Validates GitHub API compatibility
- Ensures platform detection works

### Unit Tests

**Isolated Logic**:
- `testGetWatchCommand()`: Command generation
- `testOsArchCombinations()`: Platform mapping
- `testUnsupportedOsArch()`: Error handling

**Benefits**:
- Fast execution
- No network dependency
- Predictable results

## Future Enhancements

### Potential Improvements

1. **Version Pinning**:
   ```php
   $tailwind = new TailwindCss(version: '3.4.0');
   ```

2. **Checksum Verification**:
   ```php
   private function verifyChecksum(string $file, string $expectedHash): bool
   ```

3. **Multiple Download Mirrors**:
   ```php
   private array $mirrors = [
       'https://github.com/tailwindlabs/tailwindcss/releases/...',
       'https://mirror.example.com/tailwindcss/...',
   ];
   ```

4. **Event Hooks**:
   ```php
   $tailwind->onDownloadStart(function() { /* ... */ });
   $tailwind->onDownloadComplete(function() { /* ... */ });
   ```

5. **PSR-14 Event Dispatcher**:
   ```php
   $dispatcher->dispatch(new ExecutableDownloadedEvent($path));
   ```

### Backward Compatibility

All enhancements should:
- Maintain existing API
- Use optional parameters
- Not break current usage

## Comparison with Alternatives

### vs Node.js + npm

**This Library**:
- ✅ No Node.js required
- ✅ Pure PHP
- ✅ Auto-managed downloads
- ❌ Additional PHP dependency

**Node.js + npm**:
- ❌ Requires Node.js installation
- ✅ Official package
- ✅ Auto-updates via npm
- ❌ Extra runtime dependency

### vs Manual Download

**This Library**:
- ✅ Automatic
- ✅ Platform detection
- ✅ Caching
- ❌ Extra code

**Manual**:
- ❌ Manual process
- ❌ Platform-specific steps
- ❌ No caching
- ✅ No dependencies

## Conclusion

The architecture prioritizes:
1. **Simplicity**: Single class, clear API
2. **Reliability**: Caching, error handling
3. **Performance**: Fast cache, efficient downloads
4. **Maintainability**: Clean code, well-tested
5. **Extensibility**: Protected methods, configurable paths

The design balances ease of use with technical robustness, making it suitable for production PHP applications.
