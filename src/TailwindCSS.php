<?php

namespace Syahril\TailwindCss;

class TailwindCss
{
    private $binPath;

    public function __construct($binPath = null)
    {
        $this->binPath = $binPath ?? $this->downloadExecutable();
    }

    public function downloadExecutable()
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');
        $filename = match([$os, $arch]) {
            ['Darwin', 'arm64'] => 'tailwindcss-macos-arm64',
            ['Darwin', 'x86_64'] => 'tailwindcss-macos-x64',
            ['Linux', 'x86_64'] => 'tailwindcss-linux-x64',
            ['Linux', 'aarch64'] => 'tailwindcss-linux-arm64',
            ['Windows', 'x86_64'] => 'tailwindcss-windows-x64.exe',
            default => throw new \RuntimeException("Unsupported OS/architecture: $os $arch"),
        };

        $url = "https://github.com/tailwindlabs/tailwindcss/releases/latest/download/$filename";
        $binPath = sys_get_temp_dir() . '/' . $filename;

        if (!file_exists($binPath)) {
            $content = file_get_contents($url);
            if ($content === false) {
                throw new \RuntimeException("Failed to download Tailwind CSS executable");
            }
            file_put_contents($binPath, $content);
            chmod($binPath, 0755);
        }

        return $binPath;
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
}
