<?php

namespace Syahril\TailwindCss\Tests;

use PHPUnit\Framework\TestCase;
use Syahril\TailwindCss\TailwindCss;
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
    
    public function testCachePersistence()
    {
        // Clear cache and download
        $this->tailwind->clearCache();
        $firstBinPath = $this->tailwind->getOrDownloadExecutable();

        // Create a new instance with the same cache directory
        $newTailwind = new TailwindCss(null, self::$cacheDir);
        $secondBinPath = $newTailwind->getOrDownloadExecutable();

        $this->assertEquals($firstBinPath, $secondBinPath, "Cache should persist between instances");
    }
}
