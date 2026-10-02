<?php

namespace Eril\TblClass\Generators;

use RuntimeException;

/** Builds PHP literals and replaces generated files only after a successful lint. */
final class PhpOutput
{
    private array $constants = [];

    public static function identifier(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\x80-\xff]/', '_', $name);
        if ($name === '' || ctype_digit($name[0]) || strtolower($name) === 'class') {
            $name = '_' . $name;
        }
        return $name;
    }

    public static function comment(string $text): string
    {
        return str_replace(['*/', "\r", "\n", "\0"], ['* /', ' ', ' ', ''], $text);
    }

    public function constant(string $name, string $value, string $description): string
    {
        if (isset($this->constants[$name])) {
            throw new RuntimeException("Constant collision '{$name}': {$this->constants[$name]} / {$description}. Adjust the schema or naming configuration.");
        }
        $this->constants[$name] = $description;
        return '    /** ' . self::comment($description) . " */\n"
            . "    public const {$name} = " . var_export($value, true) . ";\n";
    }

    public static function write(string $file, string $content): void
    {
        $temporary = tempnam(dirname($file), '.tbl-');
        if ($temporary === false) {
            throw new RuntimeException("Cannot create temporary output for: {$file}");
        }
        try {
            if (file_put_contents($temporary, $content) !== strlen($content)) {
                throw new RuntimeException("Failed to write temporary output for: {$file}");
            }
            if (!function_exists('proc_open')) {
                throw new RuntimeException('PHP lint requires proc_open; the previous output was preserved.');
            }
            $process = proc_open([PHP_BINARY, '-n', '-l', $temporary], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1],
            ], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start PHP lint.');
            }
            fclose($pipes[0]);
            $diagnostic = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException('Generated PHP is invalid: ' . trim($diagnostic));
            }
            $mode = is_file($file) ? fileperms($file) & 0777 : 0666 & ~umask();
            if (!chmod($temporary, $mode) || !rename($temporary, $file)) {
                throw new RuntimeException("Cannot replace generated file: {$file}");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
