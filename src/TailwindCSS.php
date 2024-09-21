<?php

namespace Syahril\TailwindCss;

class TailwindCss
{
    private $binPath;
    private $binDir;

    public function __construct($binPath = null)
    {
        $this->binDir = $this->determineBinDirectory();
        $this->binPath = $binPath ?? $this->downloadExecutable();
    }

    private function determineBinDirectory()
    {
        // Check if we're in a Composer installation
        $vendorDir = $this->getComposerVendorDir();
        if ($vendorDir !== null) {
            return $vendorDir . '/bin';
        }

        // Fallback to a local 'bin' directory for development/testing
        return dirname(__DIR__) . '/bin';
    }

    private function getComposerVendorDir()
    {
        $dir = dirname(__DIR__);
        while (!file_exists($dir . '/vendor')) {
            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                return null; // We've reached the root directory without finding /vendor
            }
            $dir = $parentDir;
        }
        return $dir . '/vendor';
    }

    public function downloadExecutable()
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');
        $filename = $this->getExecutableFilename($os, $arch);

        $url = "https://github.com/tailwindlabs/tailwindcss/releases/latest/download/$filename";
        $binPath = $this->binDir . '/' . $filename;

        if (!file_exists($binPath)) {
            if (!is_dir($this->binDir)) {
                if (!$this->mkdir($this->binDir, 0755, true)) {
                    throw new \RuntimeException("Failed to create bin directory");
                }
            }

            $content = $this->fileGetContents($url);
            if ($content === false) {
                throw new \RuntimeException("Failed to download Tailwind CSS executable");
            }
            if ($this->filePutContents($binPath, $content) === false) {
                throw new \RuntimeException("Failed to write Tailwind CSS executable to disk");
            }
            chmod($binPath, 0755);
        }

        return $binPath;
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

    // These methods allow for easier mocking in tests
    protected function fileGetContents($url)
    {
        return file_get_contents($url);
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
