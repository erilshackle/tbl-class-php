<?php

namespace Eril\TblClass\Resolvers;

use Eril\TblClass\Traits\TableAliasGenerator;

class NamingResolver
{
    use TableAliasGenerator;

    private array $config;
    private ?TableAbbreviator $abbreviator = null;

    private const DICTIONARY_FILES = [
        'en' => 'common_tables_en.php',
        'pt' => 'common_tables_pt.php',
        'es' => 'common_tables_es.php',
    ];

    /**
     * @param array $naming ['strategy' => full|abbr|alias|upper, 'separator' => double|single]
     */
    public function __construct(array $naming = [])
    {
        $this->config = array_merge([
            'strategy'  => 'full',
            'separator' => 'double',
            'fk_prefix' => 'fk__',
            'join_prefix' => 'on__',
            'enum_prefix' => 'enum__',
        ], $naming);

        $this->bootDictionaries();
    }

    // ==========================================================
    // BOOTSTRAP DICTIONARIES
    // ==========================================================
    private function bootDictionaries(): void
    {
        if (in_array($this->config['strategy'], ['full', 'upper'])) {
            return;
        }

        $dictionary = $this->loadAllDictionaries();

        if (in_array($this->config['strategy'] , ['abbr','short'])) {
            $this->abbreviator = new TableAbbreviator($dictionary);
        }
        // alias já usa TableAliasGenerator
    }

    private function loadAllDictionaries(): array
    {
        $combined = [];

        foreach (self::DICTIONARY_FILES as $file) {
            $path = $this->findDictionaryPath($file);
            if ($path) {
                $data = include $path;
                if (is_array($data)) {
                    $combined = array_merge($combined, $data);
                }
            }
        }

        return $combined;
    }

    private function findDictionaryPath(string $filename): ?string
    {
        $paths = [
            dirname(__DIR__, 2) . "/data/{$filename}",
            getcwd() . "/data/{$filename}",
            dirname(__DIR__) . "/data/{$filename}",
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    // ==========================================================
    // API PÚBLICA
    // ==========================================================

    public function getTableConstName(string $table, bool $forceFull = false): string
    {
        $name = $forceFull ? $this->normalizeName($table) : $this->resolveTablePart($table);
        return $this->applyCasing($name);
    }

    public function getColumnConstName(string $table, string $column): string
    {
        $tablePart  = $this->resolveTablePart($table);
        $columnPart = $this->normalizeName($column);

        if ($this->isConcatenated()) {
            $tablePart = $this->concat($tablePart);
        }

        return $this->applyCasing($tablePart . $this->separator() . $columnPart);
    }

    public function getEnumConstName(string $table, string $value): string
    {
        $strategy = $this->config['strategy'] == 'short' ? 'full' : $this->config['strategy'];
        $tablePart = $this->resolveTablePart($table, $strategy);

        if ($this->isConcatenated()) {
            $tablePart = $this->concat($tablePart);
        }

        return $this->applyCasing($this->config['enum_prefix'] . $tablePart . $this->separator() . $this->normalizeEnumValue($value));
    }

    public function getForeignKeyConstName(string $fromTable, string $toTable): string
    {
        $strategy = $this->config['strategy'] == 'short' ? 'full' : $this->config['strategy'];
        $from = $this->resolveTablePart($fromTable, $strategy);
        $to   = $this->resolveTablePart($toTable, $strategy);

        if ($this->isConcatenated()) {
            $from = $this->concat($from);
            $to   = $this->concat($to);
        }

        return $this->applyCasing($this->config['fk_prefix'] . $from . $this->separator() . $to);
    }

    public function getOnJoinConstName(string $fromTable, string $toTable): string
    {
        $strategy = $this->config['strategy'] == 'short' ? 'full' : $this->config['strategy'];
        $from = $this->resolveTablePart($fromTable, $strategy);
        $to   = $this->resolveTablePart($toTable, $strategy);

        if ($this->isConcatenated()) {
            $from = $this->concat($from);
            $to   = $this->concat($to);
        }

        return $this->applyCasing($this->config['join_prefix'] . $from . $this->separator() . $to);
    }

    // ==========================================================
    // CORE LOGIC
    // ==========================================================

    private function resolveTablePart(string $table, $strategy = null): string
    {
        $strategy = $strategy ?? $this->config['strategy'];
        return match ($strategy) {
            'abbr', 'short'  => $this->abbreviateWithFallback($table),
            'alias' => $this->getTableAlias($table),
            'upper' => strtoupper($table),
            default => $this->normalizeName($table),
        };
    }

    private function abbreviateWithFallback(string $name): string
    {
        if (!$this->abbreviator) {
            return $this->normalizeName($name);
        }

        return $this->abbreviator->abbreviate($name) ?: $this->normalizeName($name);
    }

    // ==========================================================
    // HELPERS
    // ==========================================================

    private function separator(): string
    {
        return $this->config['separator'] === 'double' ? '__' : '_';
    }

    private function isConcatenated(): bool
    {
        return false; // mantido simples para v1
    }

    private function concat(string $name): string
    {
        return str_replace('_', '', $name);
    }

    private function normalizeName(string $name): string
    {
        return strtolower($name);
    }

    private function normalizeEnumValue(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]/', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        return strtolower(trim($value, '_')) ?: 'value';
    }

    private function applyCasing(string $name): string
    {
        return $this->config['strategy'] === 'upper' ? strtoupper($name) : strtolower($name);
    }

    // ==========================================================
    // STATE
    // ==========================================================

    public function reset(): void
    {
        $this->resetAliases();
    }

    public function getProfile(): array
    {
        return $this->config;
    }
}
