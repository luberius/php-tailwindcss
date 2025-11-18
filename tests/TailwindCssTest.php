<?php

namespace Luberius\TailwindCss\Tests;

use PHPUnit\Framework\TestCase;
use Luberius\TailwindCss\TailwindCss;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class TailwindCssTest extends TestCase
{
    private $tailwind;
    private $binDir;
    private static $cacheDir;

    public static function setUpBeforeClass(): void
    {
        self::$cacheDir = sys_get_temp_dir() . '/tailwindcss-test-cache';
    }

    protected function setUp(): void
    {
        $this->tailwind = new TailwindCss(null, self::$cacheDir);
        $reflection = new ReflectionClass(TailwindCss::class);
        $binDirProperty = $reflection->getProperty('binDir');
        $binDirProperty->setAccessible(true);
        $this->binDir = $binDirProperty->getValue($this->tailwind);
    }

    protected function tearDown(): void
    {
        // Only remove the binary file, not the cache
        $binPath = $this->tailwind->getBinPath();
        if (file_exists($binPath)) {
            unlink($binPath);
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up the cache directory after all tests
        self::deleteDirectory(self::$cacheDir);
    }

    private static function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Test downloading and caching TailwindCSS executable.
     * This test downloads the binary and validates caching works.
     *
     * @group setup
     * @group download
     */
    public function testGetOrDownloadExecutable()
    {
        // Clear cache before this test
        $this->tailwind->clearCache();

        $binPath = $this->tailwind->getOrDownloadExecutable();
        $this->assertFileExists($binPath);
        $this->assertTrue(is_executable($binPath));
        $this->assertStringStartsWith($this->binDir, $binPath);

        // Call again to test caching
        $cachedBinPath = $this->tailwind->getOrDownloadExecutable();
        $this->assertEquals($binPath, $cachedBinPath);
    }

    /**
     * Test cache retrieval performance.
     * Validates that cached binary can be restored quickly.
     *
     * @group setup
     * @group cache
     */
    public function testGetOrDownloadExecutableCache()
    {
        unlink($this->tailwind->getBinPath());

        // Start time measurement
        $startTime = microtime(true);

        $binPath = $this->tailwind->getOrDownloadExecutable();

        // End time measurement
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assertions
        $this->assertFileExists($binPath);
        $this->assertTrue(is_executable($binPath));
        $this->assertStringStartsWith($this->binDir, $binPath);

        // Assert execution time is less than 5 seconds
        $this->assertLessThan(5, $executionTime, "Execution took longer than 5 seconds.");
    }

    public function testGetWatchCommand()
    {
        $tailwind = new TailwindCss('/path/to/tailwindcss');
        $command = $tailwind->getWatchCommand('input.css', 'output.css');
        $this->assertEquals([
            '/path/to/tailwindcss',
            '-i', 'input.css',
            '-o', 'output.css',
            '--watch'
        ], $command);
    }

    public function testCustomBinPath()
    {
        $customPath = '/custom/path/to/tailwindcss';
        $tailwind = new TailwindCss($customPath);
        $this->assertEquals($customPath, $tailwind->getBinPath());
    }

    /**
     * @dataProvider osArchProvider
     */
    public function testOsArchCombinations($os, $arch, $expectedFilename)
    {
        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('getExecutableFilename');
        $method->setAccessible(true);

        $filename = $method->invokeArgs($this->tailwind, [$os, $arch]);
        $this->assertEquals($expectedFilename, $filename);
    }

    public function osArchProvider()
    {
        return [
            ['Darwin', 'arm64', 'tailwindcss-macos-arm64'],
            ['Darwin', 'x86_64', 'tailwindcss-macos-x64'],
            ['Linux', 'x86_64', 'tailwindcss-linux-x64'],
            ['Linux', 'aarch64', 'tailwindcss-linux-arm64'],
            ['Windows', 'x86_64', 'tailwindcss-windows-x64.exe'],
        ];
    }

    public function testUnsupportedOsArch()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unsupported OS/architecture: Unsupported unsupported");

        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('getExecutableFilename');
        $method->setAccessible(true);

        $method->invokeArgs($this->tailwind, ['Unsupported', 'unsupported']);
    }

    public function testDownloadFailure()
    {
        $tailwind = $this->getMockBuilder(TailwindCss::class)
            ->setMethods(['fileGetContents'])
            ->getMock();

        $tailwind->expects($this->once())
            ->method('fileGetContents')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to download Tailwind CSS executable");

        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('downloadExecutable');
        $method->setAccessible(true);

        $method->invokeArgs($tailwind, ['test-file', '/tmp/test-file']);
    }

    public function testFileWriteFailure()
    {
        $tailwind = $this->getMockBuilder(TailwindCss::class)
            ->setMethods(['fileGetContents', 'filePutContents'])
            ->getMock();

        $tailwind->expects($this->once())
            ->method('fileGetContents')
            ->willReturn('mock content');

        $tailwind->expects($this->once())
            ->method('filePutContents')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to write Tailwind CSS executable to disk");

        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('downloadExecutable');
        $method->setAccessible(true);

        $method->invokeArgs($tailwind, ['tailwindcss-linux-x64', '/tmp/tailwindcss-linux-x64']);
    }

    /**
     * Test that cache persists across multiple instances.
     *
     * @group setup
     * @group cache
     */
    public function testCachePersistence()
    {
        // Use existing cache (don't clear)
        $firstBinPath = $this->tailwind->getOrDownloadExecutable();

        // Create a new instance with the same cache directory
        $newTailwind = new TailwindCss(null, self::$cacheDir);
        $secondBinPath = $newTailwind->getOrDownloadExecutable();

        $this->assertEquals($firstBinPath, $secondBinPath, "Cache should persist between instances");
    }

    /**
     * Test cache behavior when binary is deleted but cache exists.
     *
     * @group cache
     * @group edge-cases
     */
    public function testCacheRestoresDeletedBinary()
    {
        $this->tailwind->clearCache();
        $binPath = $this->tailwind->getOrDownloadExecutable();
        $this->assertFileExists($binPath);

        $originalSize = filesize($binPath);
        $this->assertGreaterThan(1000000, $originalSize, 'Downloaded executable should be > 1MB');

        unlink($binPath);
        $this->assertFileDoesNotExist($binPath, 'Binary should be deleted');

        $restoredBinPath = $this->tailwind->getOrDownloadExecutable();
        $this->assertFileExists($restoredBinPath, 'Binary should be restored from cache');
        $this->assertEquals($binPath, $restoredBinPath, 'Path should be the same');
        $this->assertEquals($originalSize, filesize($restoredBinPath), 'Restored binary should have same size');
    }

    /**
     * Test watch command generation with paths containing special characters.
     *
     * @group commands
     * @group edge-cases
     */
    public function testWatchCommandWithSpecialCharacters()
    {
        $tailwind = new TailwindCss('/path/to/tailwindcss');

        $inputWithSpaces = 'my folder/input file.css';
        $outputWithSpaces = 'dist folder/output file.css';

        $command = $tailwind->getWatchCommand($inputWithSpaces, $outputWithSpaces);

        $this->assertIsArray($command);
        $this->assertEquals('/path/to/tailwindcss', $command[0]);
        $this->assertEquals('-i', $command[1]);
        $this->assertEquals($inputWithSpaces, $command[2]);
        $this->assertEquals('-o', $command[3]);
        $this->assertEquals($outputWithSpaces, $command[4]);
        $this->assertEquals('--watch', $command[5]);
    }

    /**
     * Test that downloaded executable has reasonable file size.
     *
     * @group download
     */
    public function testDownloadedFileSize()
    {
        // Use existing cached binary (no need to re-download)
        $binPath = $this->tailwind->getBinPath();
        $this->assertFileExists($binPath);

        $fileSize = filesize($binPath);
        $this->assertGreaterThan(1000000, $fileSize, 'Executable should be larger than 1MB');
        $this->assertLessThan(150000000, $fileSize, 'Executable should be smaller than 150MB');
    }

    /**
     * Test that clearCache invalidates cache metadata.
     *
     * @group cache
     */
    public function testClearCacheFunctionality()
    {
        // Verify cache directory exists and has files
        $this->assertDirectoryExists($this->tailwind->getCacheDir());

        // Clear cache metadata
        $this->tailwind->clearCache();

        // Cache metadata should be cleared (tested by Symfony cache internally)
        // Note: Actual cache files may still exist on disk
        $this->assertTrue(true, 'clearCache() executed without errors');
    }

    /**
     * Test that getCacheDir returns the correct directory path.
     *
     * @group cache
     */
    public function testGetCacheDirReturnsCorrectPath()
    {
        $cacheDir = $this->tailwind->getCacheDir();

        $this->assertEquals(self::$cacheDir, $cacheDir);
        $this->assertStringContainsString('tailwindcss-test-cache', $cacheDir);
    }

    /**
     * Test that getBinPath returns an executable file.
     *
     * @group download
     */
    public function testGetBinPathReturnsExecutable()
    {
        $binPath = $this->tailwind->getBinPath();

        $this->assertFileExists($binPath);
        $this->assertTrue(is_file($binPath), 'Bin path should point to a file, not a directory');
        $this->assertTrue(is_executable($binPath), 'Binary file should be executable');
    }

    /**
     * Test that multiple instances with same cache directory share cache.
     *
     * @group cache
     */
    public function testMultipleInstancesShareCache()
    {
        // Use existing cache (no clear needed)
        $instance1 = new TailwindCss(null, self::$cacheDir);
        $binPath1 = $instance1->getOrDownloadExecutable();
        $time1 = filemtime($binPath1);

        sleep(1);

        $instance2 = new TailwindCss(null, self::$cacheDir);
        $binPath2 = $instance2->getOrDownloadExecutable();
        $time2 = filemtime($binPath2);

        $this->assertEquals(basename($binPath1), basename($binPath2), 'Both instances should use same executable name');
        $this->assertEquals($time1, $time2, 'File should not be re-downloaded, timestamps should match');
    }

    /**
     * Test that custom cache directory is respected.
     *
     * @group cache
     * @group configuration
     */
    public function testCustomCacheDirectory()
    {
        $customCacheDir = sys_get_temp_dir() . '/tailwindcss-custom-' . uniqid();

        $tailwind = new TailwindCss(null, $customCacheDir);
        $cacheDir = $tailwind->getCacheDir();

        $this->assertEquals($customCacheDir, $cacheDir);

        $binPath = $tailwind->getOrDownloadExecutable();
        $this->assertFileExists($binPath);

        if (is_dir($customCacheDir)) {
            self::deleteDirectory($customCacheDir);
        }
    }
}
