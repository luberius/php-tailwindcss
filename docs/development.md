# Development Guide

Guide for contributing to and developing the Luberius TailwindCSS library.

## Development Setup

### Prerequisites

- PHP 8.0 or higher
- Composer 2.x
- Git

### Initial Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/luberius/tailwindcss-php.git
   cd tailwindcss-php
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Verify installation**:
   ```bash
   composer test
   ```

You should see all tests passing.

## Project Structure

```
tailwindcss-php/
├── src/
│   └── TailwindCSS.php      # Main library class
├── tests/
│   └── TailwindCssTest.php  # Test suite
├── bin/                      # Composer binaries (gitignored)
├── vendor/                   # Dependencies (gitignored)
├── composer.json             # Package configuration
├── phpunit.xml              # PHPUnit configuration
└── README.md                # Project documentation
```

## Running Tests

### Standard Test Run

```bash
composer test
```

This runs PHPUnit with pretty printer formatting.

### With Coverage Report

```bash
composer test:coverage
```

Generates a text-based coverage report showing which lines are covered.

### Running Specific Tests

```bash
vendor/bin/phpunit --filter testGetOrDownloadExecutable
```

### Verbose Output

```bash
vendor/bin/phpunit --verbose
```

## Testing Guidelines

### Test Structure

Tests are organized in `/tests/TailwindCssTest.php` using PHPUnit.

**Test Categories**:

1. **Integration Tests**: Test real downloads and caching
   - `testGetOrDownloadExecutable()`
   - `testGetOrDownloadExecutableCache()`
   - `testCachePersistence()`

2. **Unit Tests**: Test isolated functionality
   - `testGetWatchCommand()`
   - `testCustomBinPath()`
   - `testOsArchCombinations()`

3. **Error Tests**: Test exception handling
   - `testUnsupportedOsArch()`
   - `testDownloadFailure()`
   - `testFileWriteFailure()`

### Writing New Tests

Example test structure:

```php
public function testNewFeature(): void
{
    // Arrange
    $tailwind = new TailwindCss(null, self::$testCacheDir);

    // Act
    $result = $tailwind->someMethod();

    // Assert
    $this->assertSomething($result);
}
```

### Mocking for Tests

The class provides protected methods for file operations that can be mocked:

```php
use PHPUnit\Framework\TestCase;

class TailwindCssTestableWrapper extends TailwindCss
{
    protected function fileGetContents(string $url, $context = null): string|false
    {
        return 'mocked content';
    }
}

// In test
public function testWithMock(): void
{
    $tailwind = new TailwindCssTestableWrapper();
    // Test behavior with mocked file operations
}
```

### Test Cache Management

Tests use a separate cache directory:

```php
private static string $testCacheDir = '/tmp/tailwindcss-test-cache';

protected function setUp(): void
{
    if (!is_dir(self::$testCacheDir)) {
        mkdir(self::$testCacheDir, 0755, true);
    }
}

public static function tearDownAfterClass(): void
{
    if (is_dir(self::$testCacheDir)) {
        // Clean up test cache
    }
}
```

## Coding Standards

### PSR-12 Compliance

This project follows PSR-12 coding standards.

**Key Rules**:
- 4 spaces for indentation (no tabs)
- Opening braces on same line for methods
- Space after control structure keywords
- Type hints for all parameters and return types

**Check Standards**:
```bash
./phpcs.phar src/ tests/
```

**Auto-Fix**:
```bash
./phpcbf.phar src/ tests/
```

### Code Style Examples

**Good**:
```php
public function getWatchCommand(string $inputCss, string $outputCss): array
{
    return [
        $this->binPath,
        '-i',
        $inputCss,
        '-o',
        $outputCss,
        '--watch',
    ];
}
```

**Bad** (missing space after `match`):
```php
return match([$os, $arch]) {  // Wrong
```

**Good**:
```php
return match ([$os, $arch]) {  // Correct
```

### Type Declarations

Always use type hints:

```php
// Good
public function getBinPath(): string
{
    return $this->binPath;
}

// Bad
public function getBinPath()
{
    return $this->binPath;
}
```

### Documentation Comments

Document public methods with PHPDoc:

```php
/**
 * Returns the path to the TailwindCSS executable.
 *
 * @return string The absolute path to the executable
 */
public function getBinPath(): string
{
    return $this->binPath;
}
```

## Git Workflow

### Branch Naming

- `feature/feature-name` - New features
- `fix/bug-description` - Bug fixes
- `docs/documentation-update` - Documentation changes
- `refactor/refactor-description` - Code refactoring

### Commit Messages

Follow conventional commit format:

```
type(scope): subject

body (optional)

footer (optional)
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `test`: Adding or updating tests
- `refactor`: Code refactoring
- `chore`: Maintenance tasks

**Examples**:
```bash
git commit -m "feat: add support for custom download URLs"
git commit -m "fix: cache validation checks file existence"
git commit -m "docs: add API reference documentation"
git commit -m "test: add tests for download failure scenarios"
```

### Pull Request Process

1. **Create feature branch**:
   ```bash
   git checkout -b feature/new-feature
   ```

2. **Make changes and test**:
   ```bash
   # Make code changes
   composer test
   ```

3. **Commit changes**:
   ```bash
   git add .
   git commit -m "feat: add new feature"
   ```

4. **Push to remote**:
   ```bash
   git push origin feature/new-feature
   ```

5. **Create Pull Request** on GitHub

### Pull Request Checklist

- [ ] All tests pass (`composer test`)
- [ ] Code follows PSR-12 standards
- [ ] New tests added for new features
- [ ] Documentation updated if needed
- [ ] Commit messages follow convention
- [ ] No unrelated changes included

## Debugging

### Enable Verbose Output

Modify the class temporarily to add debug output:

```php
private function downloadExecutable(): string
{
    $os = PHP_OS_FAMILY;
    $arch = php_uname('m');

    echo "OS: $os, Arch: $arch\n";  // Debug

    $filename = $this->getExecutableFilename($os, $arch);
    echo "Filename: $filename\n";  // Debug

    // Rest of method...
}
```

### Test Specific Platform

Mock platform detection for testing:

```php
class TailwindCssWithCustomPlatform extends TailwindCss
{
    private string $mockOs;
    private string $mockArch;

    public function __construct(string $os, string $arch)
    {
        $this->mockOs = $os;
        $this->mockArch = $arch;
        parent::__construct();
    }

    protected function getExecutableFilename(): string
    {
        return parent::getExecutableFilename($this->mockOs, $this->mockArch);
    }
}
```

### Cache Inspection

Check cache contents:

```php
$tailwind = new TailwindCss();
$cacheDir = $tailwind->getCacheDir();

echo "Cache directory: $cacheDir\n";
echo "Cache contents:\n";
print_r(scandir($cacheDir));
```

## Performance Optimization

### Benchmark Tests

Example performance test:

```php
public function testPerformance(): void
{
    $iterations = 100;

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $tailwind = new TailwindCss();
        $tailwind->getBinPath();
    }

    $duration = microtime(true) - $start;
    $avgTime = $duration / $iterations;

    $this->assertLessThan(0.1, $avgTime, 'Average execution time should be < 100ms');
}
```

### Cache Performance

The library uses Symfony FilesystemAdapter which is optimized for file-based caching:

- **First call**: Downloads executable (~2-5 MB, depends on internet speed)
- **Cached calls**: Reads from filesystem (<5 seconds as verified by tests)

## Troubleshooting Development Issues

### Tests Failing

**Issue**: Tests fail with "executable not found"

**Solution**: Clear test cache and re-run:
```bash
rm -rf /tmp/tailwindcss-test-cache
composer test
```

### Permission Errors

**Issue**: Permission denied errors during tests

**Solution**: Fix cache directory permissions:
```bash
chmod -R 755 /tmp/tailwindcss-test-cache
```

### Composer Issues

**Issue**: Dependency conflicts

**Solution**: Update composer.lock:
```bash
composer update
```

## Release Process

### Version Bumping

1. Update version in `composer.json` if needed
2. Update CHANGELOG.md with changes
3. Create git tag:
   ```bash
   git tag -a v1.0.0 -m "Release version 1.0.0"
   git push origin v1.0.0
   ```

### Publishing to Packagist

The package is automatically picked up by Packagist when you push tags to GitHub.

**Steps**:
1. Ensure tests pass
2. Create and push tag
3. Packagist auto-updates

## Contributing

### How to Contribute

1. **Fork the repository**
2. **Create feature branch** (`git checkout -b feature/amazing-feature`)
3. **Make changes** and add tests
4. **Run tests** (`composer test`)
5. **Commit changes** (`git commit -m 'feat: add amazing feature'`)
6. **Push to branch** (`git push origin feature/amazing-feature`)
7. **Open Pull Request**

### Code Review Process

All pull requests require:
- Passing tests
- Code review approval
- PSR-12 compliance
- No merge conflicts

## Support and Questions

- **Issues**: [GitHub Issues](https://github.com/luberius/tailwindcss-php/issues)
- **Email**: callmesyahril@gmail.com

## License

This project is licensed under the MIT License - see LICENSE file for details.
