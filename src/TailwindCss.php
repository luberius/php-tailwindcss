<?php

declare(strict_types=1);

namespace Luberius\TailwindCss;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class TailwindCss
{
    private const RELEASE_API = 'https://api.github.com/repos/tailwindlabs/tailwindcss/releases/latest';
    private const SEMVER = '/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
        . '(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D';

    private $binPath;
    private $binDir;
    private $cache;
    private $cacheDir;
    private $latestRelease;

    public function __construct($binPath = null, $cacheDir = null, $binDir = null)
    {
        $this->binDir = $binDir ?? $this->determineBinDirectory();
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/tailwindcss-cache';
        $this->ensureDirectory($this->binDir, 'bin');
        $this->ensureDirectory($this->cacheDir, 'cache');
        $this->cache = new FilesystemAdapter('', 0, $this->cacheDir);
        $this->binPath = $binPath ?? $this->resolveExecutablePath();
    }

    public function getOrDownloadExecutable()
    {
        $binaryName = $this->getExecutableFilename(PHP_OS_FAMILY, php_uname('m'));
        $version = $this->fetchLatestVersion();
        $asset = $this->getReleaseAssetMetadata($binaryName);
        $filename = $this->buildVersionedFilename($binaryName, $version);
        $binPath = $this->binDir . '/' . $filename;
        $cachePath = $this->cacheDir . '/' . $filename;
        $this->ensureDirectory($this->binDir, 'bin');
        $this->ensureDirectory($this->cacheDir, 'cache');

        $lock = $this->openLock($binPath . '.lock');
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Failed to lock Tailwind CSS executable');
            }
            if ($this->isValidBinary($binPath, $asset)) {
                return $this->binPath = $binPath;
            }
            $this->removeInvalidFile($binPath);

            $cacheItem = $this->cache->getItem(hash('sha256', $binPath));
            if ($cacheItem->isHit() && $this->isValidBinary($cachePath, $asset)) {
                $this->atomicCopy($cachePath, $binPath);
                $this->makeExecutable($binPath);
                $this->assertBinaryRunnable($binPath);
                return $this->binPath = $binPath;
            }
            $this->removeInvalidFile($cachePath);

            $this->downloadExecutable($asset, $binPath);
            $this->atomicCopy($binPath, $cachePath);
            $cacheItem->set($cachePath);
            if (!$this->cache->save($cacheItem)) {
                throw new \RuntimeException('Failed to save Tailwind CSS cache metadata');
            }
            return $this->binPath = $binPath;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function getInstalledVersion()
    {
        if (!$this->binPath || !is_file($this->binPath) || is_link($this->binPath)) {
            return null;
        }
        $version = $this->extractVersionFromPath($this->binPath);
        return $version ?? $this->detectVersionFromBinary($this->binPath);
    }

    public function getLatestVersion()
    {
        return $this->fetchLatestVersion();
    }

    public function isUpdateAvailable()
    {
        $installed = $this->getInstalledVersion();
        return $installed === null
            || version_compare(ltrim($installed, 'v'), ltrim($this->getLatestVersion(), 'v'), '<');
    }

    public function upgrade()
    {
        $this->binPath = null;
        return $this->getOrDownloadExecutable();
    }

    public function getWatchCommand($inputFile, $outputFile)
    {
        return [$this->binPath, '-i', $inputFile, '-o', $outputFile, '--watch'];
    }

    public function getBinPath()
    {
        return $this->binPath;
    }

    public function clearCache()
    {
        if (!$this->cache->clear()) {
            throw new \RuntimeException('Failed to clear Tailwind CSS cache');
        }
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    protected function getExecutableFilename($os, $arch)
    {
        return match ([$os, $arch]) {
            ['Darwin', 'arm64'] => 'tailwindcss-macos-arm64',
            ['Darwin', 'x86_64'] => 'tailwindcss-macos-x64',
            ['Linux', 'x86_64'] => 'tailwindcss-linux-x64',
            ['Linux', 'aarch64'] => 'tailwindcss-linux-arm64',
            ['Windows', 'x86_64'] => 'tailwindcss-windows-x64.exe',
            default => throw new \RuntimeException("Unsupported OS/architecture: $os $arch"),
        };
    }

    protected function fetchLatestRelease()
    {
        if ($this->latestRelease !== null) {
            return $this->latestRelease;
        }
        $context = stream_context_create([
            'http' => [
                'header' => "Accept: application/vnd.github+json\r\nUser-Agent: luberius-tailwindcss-php",
                'timeout' => 15,
            ],
        ]);
        $content = $this->fileGetContents(self::RELEASE_API, false, $context);
        $release = $content === false ? null : json_decode($content, true);
        if (!is_array($release)) {
            throw new \RuntimeException('Failed to fetch or parse latest Tailwind CSS release metadata');
        }
        return $this->latestRelease = $release;
    }

    protected function downloadExecutable($asset, $binPath)
    {
        $url = $asset['browser_download_url'] ?? null;
        if (!is_string($url) || !is_string($asset['name'] ?? null)) {
            throw new \RuntimeException('Invalid Tailwind CSS release asset metadata');
        }
        $this->assertAllowedDownloadUrl($url);
        $temporary = $this->createTemporaryFile($this->binDir, basename($binPath));
        if (!is_string($temporary) || is_link($temporary)) {
            throw new \RuntimeException('Failed to create a safe temporary download file');
        }
        try {
            if (!$this->streamDownload($url, $temporary)) {
                throw new \RuntimeException('Failed to download Tailwind CSS executable');
            }
            $this->assertFileDigest($temporary, $asset);
            $this->makeExecutable($temporary);
            $this->assertBinaryRunnable($temporary);
            $this->atomicMove($temporary, $binPath);
            $temporary = null;
        } finally {
            if ($temporary !== null && (file_exists($temporary) || is_link($temporary))) {
                $this->unlink($temporary);
            }
        }
    }

    protected function streamDownload($url, $path)
    {
        $source = @fopen($url, 'rb', false, stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
        ]));
        $destination = @fopen($path, 'wb');
        if ($source === false || $destination === false) {
            is_resource($source) && fclose($source);
            is_resource($destination) && fclose($destination);
            return false;
        }
        $copied = stream_copy_to_stream($source, $destination);
        return $copied !== false && fclose($source) && fclose($destination);
    }

    protected function executeCommand(array $command, &$output, &$exitCode)
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            $output = [];
            $exitCode = 1;
            return;
        }
        $text = stream_get_contents($pipes[1]) . "\n" . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $output = preg_split('/\R/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function fileGetContents($url, $useIncludePath = false, $context = null)
    {
        return file_get_contents($url, $useIncludePath, $context);
    }

    protected function mkdir($path, $mode = 0777, $recursive = false)
    {
        return mkdir($path, $mode, $recursive);
    }
    protected function copy($source, $destination)
    {
        return copy($source, $destination);
    }
    protected function rename($source, $destination)
    {
        return rename($source, $destination);
    }
    protected function chmod($path, $mode)
    {
        return chmod($path, $mode);
    }
    protected function unlink($path)
    {
        return unlink($path);
    }
    protected function createTemporaryFile($directory, $prefix)
    {
        return tempnam($directory, '.' . $prefix . '.tmp-');
    }

    private function determineBinDirectory()
    {
        $dir = dirname(__DIR__);
        while (!file_exists($dir . '/vendor')) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                return dirname(__DIR__) . '/bin';
            }
            $dir = $parent;
        }
        return $dir . '/vendor/bin';
    }

    private function resolveExecutablePath()
    {
        return $this->findInstalledBinary() ?? $this->getOrDownloadExecutable();
    }

    private function findInstalledBinary()
    {
        $name = $this->getExecutableFilename(PHP_OS_FAMILY, php_uname('m'));
        $base = str_ends_with($name, '.exe') ? substr($name, 0, -4) : $name;
        $extension = str_ends_with($name, '.exe') ? '\.exe' : '';
        $candidates = [];
        foreach (glob($this->binDir . '/' . $base . '*') ?: [] as $path) {
            if (!is_file($path) || is_link($path)) {
                continue;
            }
            $pattern = '/^' . preg_quote($base, '/')
                . '(?:-(v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?))?' . $extension . '$/D';
            if (preg_match($pattern, basename($path), $match)) {
                $candidates[] = ['path' => $path, 'version' => ltrim($match[1] ?? '0.0.0', 'v')];
            }
        }
        usort($candidates, fn ($a, $b) => version_compare($b['version'], $a['version']));
        foreach ($candidates as $candidate) {
            if ($this->isRunnable($candidate['path'])) {
                return $candidate['path'];
            }
        }
        return null;
    }

    private function fetchLatestVersion()
    {
        $tag = $this->fetchLatestRelease()['tag_name'] ?? null;
        if (!is_string($tag) || !preg_match(self::SEMVER, $tag)) {
            throw new \RuntimeException('Latest Tailwind CSS release tag is not a valid semantic version');
        }
        return $tag;
    }

    private function getReleaseAssetMetadata($binaryName)
    {
        $assets = $this->fetchLatestRelease()['assets'] ?? null;
        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (($asset['name'] ?? null) === $binaryName) {
                    return $asset;
                }
            }
        }
        throw new \RuntimeException('Could not find Tailwind CSS release asset for ' . $binaryName);
    }

    private function buildVersionedFilename($name, $version)
    {
        $suffix = '-' . ltrim($version, 'v');
        return str_ends_with($name, '.exe') ? substr($name, 0, -4) . $suffix . '.exe' : $name . $suffix;
    }

    private function extractVersionFromPath($path)
    {
        return preg_match('/-(v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)(?:\.exe)?$/D', basename($path), $match)
            ? $match[1] : null;
    }

    private function detectVersionFromBinary($path)
    {
        $output = [];
        $code = 1;
        $this->executeCommand([$path, '--help'], $output, $code);
        return $code === 0 && preg_match('/tailwindcss v(\d+\.\d+\.\d+)/i', implode("\n", $output), $match)
            ? $match[1] : null;
    }

    private function assertAllowedDownloadUrl($url)
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $hosts = ['github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];
        $invalid = ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || !in_array($host, $hosts, true);
        if (!$invalid && $host === 'github.com') {
            $pattern = '#^/tailwindlabs/tailwindcss/releases/download/[^/]+/[^/]+$#D';
            $invalid = !preg_match($pattern, $parts['path'] ?? '');
        }
        if ($invalid) {
            throw new \RuntimeException('Tailwind CSS release asset URL is not allowed');
        }
    }

    private function assertFileDigest($path, $asset)
    {
        $digest = $asset['digest'] ?? null;
        if (!is_string($digest) || !preg_match('/^sha256:([a-f0-9]{64})$/D', $digest, $match)) {
            throw new \RuntimeException('Tailwind CSS release asset digest is missing or unsupported');
        }
        $actual = hash_file('sha256', $path);
        if (!is_string($actual) || !hash_equals($match[1], $actual)) {
            throw new \RuntimeException('Tailwind CSS binary checksum verification failed');
        }
    }

    private function assertBinaryRunnable($path)
    {
        $output = [];
        $code = 1;
        $this->executeCommand([$path, '--help'], $output, $code);
        if ($code !== 0) {
            throw new \RuntimeException('Tailwind CSS binary failed runnable verification');
        }
    }

    private function isRunnable($path)
    {
        try {
            $this->assertBinaryRunnable($path);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    private function isValidBinary($path, $asset)
    {
        if (!is_file($path) || is_link($path)) {
            return false;
        }
        try {
            $this->assertFileDigest($path, $asset);
            return $this->isRunnable($path);
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    private function ensureDirectory($path, $label)
    {
        if (is_link($path) || (file_exists($path) && !is_dir($path))) {
            throw new \RuntimeException("Tailwind CSS $label path is not a safe directory");
        }
        if (!is_dir($path) && !$this->mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create $label directory");
        }
    }

    private function openLock($path)
    {
        if (is_link($path) || ($handle = @fopen($path, 'c')) === false) {
            throw new \RuntimeException('Failed to open safe Tailwind CSS download lock');
        }
        return $handle;
    }

    private function atomicCopy($source, $destination)
    {
        $temporary = $this->createTemporaryFile(dirname($destination), basename($destination));
        if (!is_string($temporary)) {
            throw new \RuntimeException('Failed to create a temporary file');
        }
        try {
            if (!$this->copy($source, $temporary)) {
                throw new \RuntimeException('Failed to copy Tailwind CSS executable');
            }
            $this->atomicMove($temporary, $destination);
            $temporary = null;
        } finally {
            if ($temporary !== null && file_exists($temporary)) {
                $this->unlink($temporary);
            }
        }
    }

    private function atomicMove($source, $destination)
    {
        if (is_link($destination) || !$this->rename($source, $destination)) {
            throw new \RuntimeException('Failed to atomically install Tailwind CSS executable');
        }
    }

    private function makeExecutable($path)
    {
        if (!$this->chmod($path, 0755)) {
            throw new \RuntimeException('Failed to make Tailwind CSS executable runnable');
        }
    }

    private function removeInvalidFile($path)
    {
        if ((file_exists($path) || is_link($path)) && !$this->unlink($path)) {
            throw new \RuntimeException('Failed to remove invalid Tailwind CSS executable');
        }
    }
}
