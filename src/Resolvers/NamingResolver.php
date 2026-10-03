<?php

namespace Eril\TblClass\Resolvers;

use Eril\TblClass\Generators\PhpOutput;
use InvalidArgumentException;

/** v2 naming: stable prefixes independent of other schema tables. */
class NamingResolver
{
    private array $config;
    private ?TableAbbreviator $abbreviator = null;

    public function __construct(array $naming = [])
    {
        if (array_key_exists('case', $naming)) {
            throw new InvalidArgumentException('naming.case was removed. Use strategy: full, FULL, short or SHORT.');
        }
        $unknown = array_diff(array_keys($naming), ['strategy', 'overrides']);
        if ($unknown) {
            throw new InvalidArgumentException('v2 naming supports only strategy and overrides. Remove: ' . implode(', ', $unknown));
        }
        $this->config = array_replace(['strategy' => 'full', 'overrides' => []], $naming);
        if (!in_array($this->config['strategy'], ['full', 'FULL', 'short', 'SHORT'], true)) {
            throw new InvalidArgumentException('v2 naming.strategy must be exactly full, FULL, short or SHORT. Migrate abbr to short, upper to FULL, and alias to explicit overrides. Mixed casing is not supported.');
        }
        if (!is_array($this->config['overrides'])) {
            throw new InvalidArgumentException('naming.overrides must map table names to prefixes.');
        }
        foreach ($this->config['overrides'] as $table => $prefix) {
            if (!is_string($prefix) || $prefix === '' || PhpOutput::identifier($prefix) !== $prefix) {
                throw new InvalidArgumentException("Invalid naming override for table {$table}.");
            }
        }
        if (strtolower($this->config['strategy']) === 'short') {
            $dictionary = [];
            foreach (['en', 'pt', 'es'] as $language) {
                $dictionary = array_merge($dictionary, require dirname(__DIR__, 2) . "/data/common_tables_{$language}.php");
            }
            $this->abbreviator = new TableAbbreviator($dictionary);
        }
    }

    public function getTableConstName(string $table, bool $forceFull = false): string
    {
        return $this->applyCasing($forceFull ? $table : $this->tablePart($table));
    }

    public function getColumnConstName(string $table, string $column): string
    {
        return $this->applyCasing($this->tablePart($table) . '__' . $column);
    }

    public function getEnumConstName(string $table, string $value): string
    {
        $value = trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $value), '_') ?: 'value';
        return $this->applyCasing('enum__' . $this->tablePart($table) . '__' . $value);
    }

    public function getForeignKeyConstName(string $fromTable, string $toTable): string
    {
        return $this->relation('fk__', $fromTable, $toTable);
    }

    public function getOnJoinConstName(string $fromTable, string $toTable): string
    {
        return $this->relation('on__', $fromTable, $toTable);
    }

    private function relation(string $prefix, string $from, string $to): string
    {
        return $this->applyCasing($prefix . $this->tablePart($from) . '__' . $this->tablePart($to));
    }

    private function tablePart(string $table): string
    {
        return $this->config['overrides'][$table] ?? ($this->abbreviator?->abbreviate($table) ?: $table);
    }

    private function applyCasing(string $name): string
    {
        $name = PhpOutput::identifier($name);
        return in_array($this->config['strategy'], ['FULL', 'SHORT'], true) ? strtoupper($name) : strtolower($name);
    }

    public function reset(): void {}

    public function getProfile(): array
    {
        return $this->config;
    }
}
