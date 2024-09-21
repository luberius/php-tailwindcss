<?php

namespace Syahril\TailwindCss\Tests;

use PHPUnit\Framework\TestCase;
use Syahril\TailwindCss\TailwindCss;
use ReflectionClass;

class TailwindCssTest extends TestCase
{
    private $tailwind;
    private $binDir;

    protected function setUp(): void
    {
        $this->tailwind = new TailwindCss();
        $reflection = new ReflectionClass(TailwindCss::class);
        $binDirProperty = $reflection->getProperty('binDir');
        $binDirProperty->setAccessible(true);
        $this->binDir = $binDirProperty->getValue($this->tailwind);
    }

    protected function tearDown(): void
    {
        // Clean up downloaded executables after tests
        array_map('unlink', glob("$this->binDir/*"));
        if (is_dir($this->binDir) && strpos($this->binDir, 'vendor') === false) {
            rmdir($this->binDir);
        }
    }

    public function testDownloadExecutable()
    {
        $binPath = $this->tailwind->getBinPath();
        $this->assertFileExists($binPath);
        $this->assertTrue(is_executable($binPath));
        $this->assertStringStartsWith($this->binDir, $binPath);
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

        $method->invoke($tailwind);
    }

    public function testFileWriteFailure()
    {
        $tailwind = $this->getMockBuilder(TailwindCss::class)
            ->setMethods(['filePutContents'])
            ->getMock();

        $tailwind->expects($this->once())
            ->method('filePutContents')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to write Tailwind CSS executable to disk");

        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('downloadExecutable');
        $method->setAccessible(true);

        $method->invoke($tailwind);
    }

    public function testBinDirectoryCreation()
    {
        // Remove the bin directory if it exists
        if (is_dir($this->binDir) && strpos($this->binDir, 'vendor') === false) {
            rmdir($this->binDir);
        }

        $this->tailwind->downloadExecutable();

        $this->assertTrue(is_dir($this->binDir));
        $this->assertTrue(is_readable($this->binDir));
        $this->assertTrue(is_writable($this->binDir));
    }

    public function testBinDirectoryCreationFailure()
    {
        $tailwind = $this->getMockBuilder(TailwindCss::class)
            ->setMethods(['mkdir'])
            ->getMock();

        $tailwind->expects($this->once())
            ->method('mkdir')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to create bin directory");

        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('downloadExecutable');
        $method->setAccessible(true);

        $method->invoke($tailwind);
    }

    public function testVendorDirectoryDetection()
    {
        $reflection = new ReflectionClass(TailwindCss::class);
        $method = $reflection->getMethod('getComposerVendorDir');
        $method->setAccessible(true);

        $vendorDir = $method->invoke($this->tailwind);
        $this->assertStringEndsWith('vendor', $vendorDir);
    }
}
