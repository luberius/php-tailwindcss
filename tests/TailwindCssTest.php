<?php

declare(strict_types=1);

namespace Luberius\TailwindCss\Tests;

use Luberius\TailwindCss\TailwindCss;
use PHPUnit\Framework\TestCase;

class TailwindCssTest extends TestCase
{
    private $root;
    private $binDir;
    private $cacheDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tailwindcss-php-' . bin2hex(random_bytes(6));
        $this->binDir = $this->root . '/bin';
        $this->cacheDir = $this->root . '/cache';
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);
    }

    public function testDownloadsValidAssetWithoutNetworkAccess(): void
    {
        $tailwind = $this->manager();
        $path = $tailwind->getBinPath();
        self::assertFileExists($path);
        self::assertSame('fake-tailwind-binary', file_get_contents($path));
        self::assertSame(1, $tailwind->downloads);
        self::assertSame('4.1.12', $tailwind->getInstalledVersion());
    }

    public function testRejectsInvalidChecksumAndCleansTemporaryFile(): void
    {
        $tailwind = $this->manager(['digest' => 'sha256:' . str_repeat('0', 64)], false);
        try {
            $tailwind->getOrDownloadExecutable();
            self::fail('Expected checksum failure');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('checksum', $exception->getMessage());
        }
        self::assertSame([], glob($this->binDir . '/.*.tmp-*') ?: []);
    }

    /** @dataProvider invalidVersionProvider */
    public function testRejectsInvalidReleaseVersions(string $version): void
    {
        $tailwind = $this->manager([], false, $version);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('valid semantic version');
        $tailwind->getLatestVersion();
    }

    public function invalidVersionProvider(): array
    {
        return [['../../evil'], ['v4'], ['4.1'], ['01.2.3'], ['4.1.2/asset']];
    }

    /** @dataProvider disallowedUrlProvider */
    public function testRejectsDisallowedDownloadUrls(string $url): void
    {
        $tailwind = $this->manager(['browser_download_url' => $url], false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL is not allowed');
        $tailwind->getOrDownloadExecutable();
    }

    public function disallowedUrlProvider(): array
    {
        return [
            ['http://github.com/tailwindlabs/tailwindcss/releases/download/v4.1.12/file'],
            ['https://example.com/file'],
            ['https://github.com/other/repo/releases/download/v1/file'],
            ['https://user@github.com/tailwindlabs/tailwindcss/releases/download/v4.1.12/file'],
        ];
    }

    public function testFailedDownloadLeavesNoPartialBinary(): void
    {
        $tailwind = $this->manager([], false);
        $tailwind->failDownload = true;
        try {
            $tailwind->getOrDownloadExecutable();
            self::fail('Expected download failure');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('download', $exception->getMessage());
        }
        self::assertSame([], glob($this->binDir . '/.*.tmp-*') ?: []);
        self::assertFileDoesNotExist($this->binDir . '/' . $this->platformBinaryName() . '-4.1.12');
    }

    public function testFailedAtomicMoveCleansTemporaryFile(): void
    {
        $tailwind = $this->manager([], false);
        $tailwind->failRename = true;
        try {
            $tailwind->getOrDownloadExecutable();
            self::fail('Expected write failure');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('atomically install', $exception->getMessage());
        }
        self::assertSame([], glob($this->binDir . '/.*.tmp-*') ?: []);
    }

    public function testCacheRestoresDeletedBinaryWithoutDownloading(): void
    {
        $first = $this->manager();
        $path = $first->getBinPath();
        unlink($path);
        $second = $this->manager();
        self::assertSame(0, $second->downloads);
        self::assertFileExists($second->getBinPath());
    }

    public function testInvalidCachedBinaryIsDiscardedAndRedownloaded(): void
    {
        $first = $this->manager();
        $path = $first->getBinPath();
        unlink($path);
        file_put_contents($this->cacheDir . '/' . basename($path), 'tampered');
        $second = $this->manager();
        self::assertSame(1, $second->downloads);
        self::assertSame('fake-tailwind-binary', file_get_contents($second->getBinPath()));
    }

    public function testUpgradeSelectsLatestVersion(): void
    {
        $tailwind = $this->manager();
        $tailwind->setVersion('4.2.0');
        self::assertTrue($tailwind->isUpdateAvailable());
        self::assertStringEndsWith('-4.2.0', $tailwind->upgrade());
        self::assertSame(2, $tailwind->downloads);
    }

    public function testFindsHighestRunnableInstalledVersion(): void
    {
        mkdir($this->binDir, 0755, true);
        file_put_contents($this->versionedPlatformBinary('4.0.1'), 'old');
        file_put_contents($this->versionedPlatformBinary('4.2.0'), 'new');
        $tailwind = $this->manager([], true);
        self::assertStringEndsWith('-4.2.0', $tailwind->getBinPath());
        self::assertSame(0, $tailwind->downloads);
    }

    public function testIgnoresInstalledBinaryThatIsNotRunnable(): void
    {
        mkdir($this->binDir, 0755, true);
        file_put_contents($this->versionedPlatformBinary('9.0.0'), 'broken');
        $tailwind = $this->manager([], true);
        self::assertStringEndsWith('-4.1.12', $tailwind->getBinPath());
        self::assertSame(1, $tailwind->downloads);
    }

    /** @dataProvider platformProvider */
    public function testSupportedPlatforms(string $os, string $arch, string $expected): void
    {
        $tailwind = $this->manager([], false);
        self::assertSame($expected, $tailwind->executableName($os, $arch));
    }

    public function platformProvider(): array
    {
        return [
            ['Darwin', 'arm64', 'tailwindcss-macos-arm64'],
            ['Darwin', 'x86_64', 'tailwindcss-macos-x64'],
            ['Linux', 'x86_64', 'tailwindcss-linux-x64'],
            ['Linux', 'aarch64', 'tailwindcss-linux-arm64'],
            ['Windows', 'x86_64', 'tailwindcss-windows-x64.exe'],
        ];
    }

    public function testUnsupportedPlatformFails(): void
    {
        $tailwind = $this->manager([], false);
        $this->expectException(\RuntimeException::class);
        $tailwind->executableName('Plan9', 'mips');
    }

    public function testWatchCommandRemainsArgumentSafe(): void
    {
        $tailwind = new TailwindCss('/path with spaces/tailwindcss', $this->cacheDir, $this->binDir);
        self::assertSame(
            ['/path with spaces/tailwindcss', '-i', 'input file.css', '-o', 'output file.css', '--watch'],
            $tailwind->getWatchCommand('input file.css', 'output file.css')
        );
    }

    private function manager(
        array $assetOverrides = [],
        bool $construct = true,
        string $version = 'v4.1.12'
    ): FakeTailwindCss {
        $asset = array_merge([
            'name' => $this->platformBinaryName(),
            'browser_download_url' => 'https://github.com/tailwindlabs/tailwindcss/'
                . 'releases/download/v4.1.12/tailwindcss-linux-x64',
            'digest' => 'sha256:' . hash('sha256', 'fake-tailwind-binary'),
        ], $assetOverrides);
        $tailwind = new FakeTailwindCss($asset, $version);
        if ($construct) {
            $tailwind->boot($this->cacheDir, $this->binDir);
        } else {
            $tailwind->prepare($this->cacheDir, $this->binDir);
        }
        return $tailwind;
    }

    private function platformBinaryName(): string
    {
        $names = [
            'Darwin-arm64' => 'tailwindcss-macos-arm64',
            'Darwin-x86_64' => 'tailwindcss-macos-x64',
            'Linux-x86_64' => 'tailwindcss-linux-x64',
            'Linux-aarch64' => 'tailwindcss-linux-arm64',
            'Windows-x86_64' => 'tailwindcss-windows-x64.exe',
        ];
        return $names[PHP_OS_FAMILY . '-' . php_uname('m')];
    }

    private function versionedPlatformBinary(string $version): string
    {
        $name = $this->platformBinaryName();
        return $this->binDir . '/' . (str_ends_with($name, '.exe')
            ? substr($name, 0, -4) . '-' . $version . '.exe'
            : $name . '-' . $version);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (array_diff(scandir($directory), ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
