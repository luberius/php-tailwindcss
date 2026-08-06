<?php

declare(strict_types=1);

namespace Luberius\TailwindCss\Tests;

use Luberius\TailwindCss\TailwindCss;

class FakeTailwindCss extends TailwindCss
{
    public $downloads = 0;
    public $failDownload = false;
    public $failRename = false;
    private $asset;
    private $version;

    public function __construct(array $asset, string $version)
    {
        $this->asset = $asset;
        $this->version = $version;
    }

    public function boot(string $cacheDir, string $binDir): void
    {
        parent::__construct(null, $cacheDir, $binDir);
    }

    public function prepare(string $cacheDir, string $binDir): void
    {
        parent::__construct($binDir . '/placeholder', $cacheDir, $binDir);
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function executableName(string $os, string $arch): string
    {
        return $this->getExecutableFilename($os, $arch);
    }

    protected function fetchLatestRelease()
    {
        return ['tag_name' => $this->version, 'assets' => [$this->asset]];
    }

    protected function streamDownload($url, $path)
    {
        unset($url);
        $this->downloads++;
        return !$this->failDownload && file_put_contents($path, 'fake-tailwind-binary') !== false;
    }

    protected function executeCommand(array $command, &$output, &$exitCode)
    {
        $exitCode = file_get_contents($command[0]) !== 'broken' ? 0 : 1;
        $output = ['tailwindcss v' . ltrim($this->version, 'v')];
    }

    protected function rename($source, $destination)
    {
        return !$this->failRename && parent::rename($source, $destination);
    }
}
