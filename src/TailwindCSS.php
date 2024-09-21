<?php

namespace Syahril\TailwindCss;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class TailwindCss
{
    private $binPath;
    private $binDir;
    private $cache;
    private $cacheDir;

    public function __construct($binPath = null, $cacheDir = null)
    {
        $this->binDir = $this->determineBinDirectory();
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/tailwindcss-cache';
        $this->cache = new FilesystemAdapter('', 0, $this->cacheDir);
        $this->binPath = $binPath ?? $this->getOrDownloadExecutable();
    }

    private function determineBinDirectory()
    {
        $vendorDir = $this->getComposerVendorDir();
        if ($vendorDir !== null) {
            return $vendorDir . '/bin';
        }
        return dirname(__DIR__) . '/bin';
    }

    private function getComposerVendorDir()
    {
        $dir = dirname(__DIR__);
        while (!file_exists($dir . '/vendor')) {
            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                return null;
            }
            $dir = $parentDir;
        }
        return $dir . '/vendor';
    }

    public function getOrDownloadExecutable()
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');
        $filename = $this->getExecutableFilename($os, $arch);
        $binPath = $this->binDir . '/' . $filename;

        $cacheItem = $this->cache->getItem(md5($binPath));
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        if (!file_exists($binPath)) {
            $this->downloadExecutable($filename, $binPath);
        }

        $cacheItem->set($binPath);
        $this->cache->save($cacheItem);

        return $binPath;
    }

    private function downloadExecutable($filename, $binPath)
    {
        $url = "https://github.com/tailwindlabs/tailwindcss/releases/latest/download/$filename";

        if (!is_dir($this->binDir)) {
            if (!$this->mkdir($this->binDir, 0755, true)) {
                throw new \RuntimeException("Failed to create bin directory");
            }
        }

        $output = new ConsoleOutput();
        $output->writeln("Downloading Tailwind CSS executable...");
        $progressBar = new ProgressBar($output);
        $progressBar->start();

        $context = stream_context_create([], [
            'notification' => function ($notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax) use ($progressBar) {
                if ($notificationCode == STREAM_NOTIFY_PROGRESS) {
                    $progressBar->setProgress($bytesTransferred);
                }
            }
        ]);

        $content = $this->fileGetContents($url, false, $context);
        if ($content === false) {
            throw new \RuntimeException("Failed to download Tailwind CSS executable");
        }

        $progressBar->finish();
        $output->writeln("");

        if ($this->filePutContents($binPath, $content) === false) {
            throw new \RuntimeException("Failed to write Tailwind CSS executable to disk");
        }

        chmod($binPath, 0755);
    }

    protected function getExecutableFilename($os, $arch)
    {
        return match([$os, $arch]) {
            ['Darwin', 'arm64'] => 'tailwindcss-macos-arm64',
            ['Darwin', 'x86_64'] => 'tailwindcss-macos-x64',
            ['Linux', 'x86_64'] => 'tailwindcss-linux-x64',
            ['Linux', 'aarch64'] => 'tailwindcss-linux-arm64',
            ['Windows', 'x86_64'] => 'tailwindcss-windows-x64.exe',
            default => throw new \RuntimeException("Unsupported OS/architecture: $os $arch"),
        };
    }

    public function getWatchCommand($inputFile, $outputFile)
    {
        return [
            $this->binPath,
            '-i', $inputFile,
            '-o', $outputFile,
            '--watch'
        ];
    }

    public function getBinPath()
    {
        return $this->binPath;
    }

    public function clearCache()
    {
        $this->cache->clear();
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    protected function fileGetContents($url, $useIncludePath = false, $context = null)
    {
        return file_get_contents($url, $useIncludePath, $context);
    }

    protected function filePutContents($path, $content)
    {
        return file_put_contents($path, $content);
    }

    protected function mkdir($path, $mode = 0777, $recursive = false)
    {
        return mkdir($path, $mode, $recursive);
    }
}
