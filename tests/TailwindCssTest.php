<?php

namespace Syahril\TailwindCss\Tests;

use PHPUnit\Framework\TestCase;
use Syahril\TailwindCss\TailwindCss;

class TailwindCssTest extends TestCase
{
    public function testDownloadExecutable()
    {
        $tailwind = new TailwindCss();
        $binPath = $tailwind->getBinPath();
        $this->assertFileExists($binPath);
        $this->assertTrue(is_executable($binPath));
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
}
