<?php

namespace Eril\TblClass\Cli;

final class CliPrinter
{
    
    private static array $colors = [
        'reset'   => "\033[0m",
        'red'     => "\033[31m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'blue'    => "\033[34m",
        'magenta' => "\033[35m",
        'cyan'    => "\033[36m",
        'white'   => "\033[37m",
        'bold'    => "\033[1m",
        'dim'     => "\033[2m",
    ];

    private static array $icons = [
        'success' => '✓',
        'error'   => '✗',
        'warning' => '!',
        'info'    => '•',
        'arrow_r' => '→',
        'arrow_l' => '←',
        'check'   => '✓',
        'cross'   => '✗',
        'circle'  => '○',
        'square'  => '■',
        'dash'    => '─',
        'pipe'    => '│',
        'corner_tl' => '┌',
        'corner_tr' => '┐',
        'corner_bl' => '└',
        'corner_br' => '┘',
        'tee_t'   => '┬',
        'tee_b'   => '┴',
        'tee_l'   => '├',
        'tee_r'   => '┤',
        'cross_c' => '┼',
    ];

    /* ---------- Core ---------- */

    public static function out(string $text, ?string $color = null): void
    {
        $code = self::$colors[$color] ?? '';
        echo $code . $text . self::$colors['reset'];
    }

    public static function line(string $text = '', ?string $color = null): void
    {
        self::out($text . PHP_EOL, $color);
    }



    /* ---------- Títulos e Seções ---------- */

    public static function title(string $text): void
    {
        self::line('');
        $line = str_repeat(self::$icons['dash'], strlen($text) + 4);
        self::line(self::$icons['corner_tl'] . $line . self::$icons['corner_tr']);
        self::line(self::$icons['pipe'] . '  ' . $text . '  ' . self::$icons['pipe']);
        self::line(self::$icons['corner_bl'] . $line . self::$icons['corner_br']);
        self::line('');
    }

    public static function section(string $text): void
    {
        self::line('');
        self::line('┌ ' . $text, 'bold');
        self::line('└' . str_repeat('─', strlen($text) + 1));
    }

    public static function subsection(string $text): void
    {
        self::line('├ ' . $text, 'cyan');
    }

    /* ---------- Status Messages ---------- */

    public static function success(string $text): void
    {
        self::line(self::$icons['success'] . ' ' . $text, 'green');
    }

    public static function error(string $text): void
    {
        self::line(self::$icons['error'] . ' ' . $text, 'red');
    }

    public static function warn(string $text): void
    {
        self::line(self::$icons['warning'] . ' ' . $text, 'yellow');
    }

    public static function info(string $text): void
    {
        self::line(self::$icons['info'] . ' ' . $text, 'cyan');
    }

    /* ---------- Icon Messages ---------- */

    public static function successIcon(string $text): void
    {
        self::line(self::$icons['check'] . ' ' . $text, 'green');
    }

    public static function errorIcon(string $text): void
    {
        self::line(self::$icons['cross'] . ' ' . $text, 'red');
    }

    public static function warnIcon(string $text): void
    {
        self::line(self::$icons['warning'] . ' ' . $text, 'yellow');
    }

    public static function infoIcon(string $text): void
    {
        self::line(self::$icons['info'] . ' ' . $text, 'cyan');
    }

    /* ---------- Formatting ---------- */

    public static function bold(string $text): string
    {
        return self::$colors['bold'] . $text . self::$colors['reset'];
    }

    public static function dim(string $text): string
    {
        return self::$colors['dim'] . $text . self::$colors['reset'];
    }

    public static function code(string $text): void
    {
        self::out('`' . $text . '`', 'cyan');
    }

    /* ---------- Progress / Status ---------- */

    public static function progress(string $text): void
    {
        self::out('↻ ' . $text . ' ', 'cyan');
    }

    public static function done(): void
    {
        self::line(' done', 'green');
    }

    public static function step(string $text, int $step, int $total): void
    {
        self::out("[{$step}/{$total}] ", 'dim');
        self::line($text);
    }

    /* ---------- Lists ---------- */

    public static function listItem(string $text, int $level = 0): void
    {
        $indent = str_repeat('  ', $level);
        self::line($indent . '• ' . $text);
    }

    public static function listNumbered(string $text, int $number): void
    {
        self::line("{$number}. " . $text);
    }

    /* ---------- Tables ---------- */

    public static function table(array $headers, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // Calculate column widths
        $columnWidths = [];
        $allRows = array_merge([$headers], $rows);

        foreach ($allRows as $row) {
            foreach ($row as $colIndex => $cell) {
                $width = strlen($cell);
                if (!isset($columnWidths[$colIndex]) || $width > $columnWidths[$colIndex]) {
                    $columnWidths[$colIndex] = $width;
                }
            }
        }

        // Print top border
        $topLine = self::$icons['corner_tl'];
        foreach ($columnWidths as $width) {
            $topLine .= str_repeat(self::$icons['dash'], $width + 2) . self::$icons['tee_t'];
        }
        $topLine = substr($topLine, 0, -1) . self::$icons['corner_tr'];
        self::line($topLine, 'dim');

        // Print header
        $headerLine = self::$icons['pipe'];
        foreach ($headers as $colIndex => $header) {
            $width = $columnWidths[$colIndex];
            $headerLine .= ' ' . str_pad($header, $width) . ' ' . self::$icons['pipe'];
        }
        self::line($headerLine, 'bold');

        // Print separator
        $sepLine = self::$icons['tee_l'];
        foreach ($columnWidths as $width) {
            $sepLine .= str_repeat(self::$icons['dash'], $width + 2) . self::$icons['cross_c'];
        }
        $sepLine = substr($sepLine, 0, -1) . self::$icons['tee_r'];
        self::line($sepLine, 'dim');

        // Print rows
        foreach ($rows as $row) {
            $rowLine = self::$icons['pipe'];
            foreach ($row as $colIndex => $cell) {
                $width = $columnWidths[$colIndex];
                $rowLine .= ' ' . str_pad($cell, $width) . ' ' . self::$icons['pipe'];
            }
            self::line($rowLine);
        }

        // Print bottom border
        $bottomLine = self::$icons['corner_bl'];
        foreach ($columnWidths as $width) {
            $bottomLine .= str_repeat(self::$icons['dash'], $width + 2) . self::$icons['tee_b'];
        }
        $bottomLine = substr($bottomLine, 0, -1) . self::$icons['corner_br'];
        self::line($bottomLine, 'dim');
        self::line('');
    }

    /* ---------- Helpers ---------- */

    public static function hr(): void
    {
        self::line(str_repeat('─', 60), 'dim');
    }

    public static function spacer(): void
    {
        self::line('');
    }

    public static function keyValue(string $key, string $value, ?string $color = null): void
    {
        self::out($key . ': ', 'dim');
        self::line($value, $color);
    }
}
