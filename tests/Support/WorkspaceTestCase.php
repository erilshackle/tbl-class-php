<?php

namespace Tests\Support;

use Eril\TblClass\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

abstract class WorkspaceTestCase extends TestCase
{
    protected string $directory;
    protected string $namespace;

    protected function setUp(): void
    {
        $id = bin2hex(random_bytes(8));
        $this->directory = sys_get_temp_dir() . '/tbl-tests-' . $id;
        $this->namespace = 'Fixture' . $id;
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    protected function write(string $relativePath, string $content): string
    {
        $file = $this->directory . '/' . $relativePath;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
        return $file;
    }

    protected function config(array $changes = []): Config
    {
        $data = array_replace_recursive([
            'database' => ['driver' => 'sqlite', 'name' => 'test', 'path' => ':memory:'],
            'output' => ['path' => $this->directory, 'namespace' => $this->namespace,
                'naming' => ['strategy' => 'full', 'overrides' => []]],
        ], $changes);
        return new Config($this->write('tblclass.yaml', Yaml::dump($data, 6)));
    }

    /** @return array{int, string} Exit status and combined console output. */
    protected function cli(array $arguments): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/tbl-class', ...$arguments], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1],
        ], $pipes, $this->directory);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        return [proc_close($process), $output];
    }
}
