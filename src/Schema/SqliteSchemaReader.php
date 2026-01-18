<?php

namespace Eril\TblClass\Schema;

use PDO;

class SqliteSchemaReader implements SchemaReaderInterface
{
    public function __construct(
        private PDO $pdo,
        private string $dbName
    ) {}

    public function getDatabaseName(): string
    {
        return $this->dbName;
    }

    public function getTables(): array
    {
        $stmt = $this->pdo->query("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table'
              AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getColumns(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info('$table')");

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    }

    /**
     * Extracts enum-like values from CHECK constraints.
     *
     * Example:
     * status TEXT CHECK(status IN ('active','inactive'))
     */
    public function getEnumColumns(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info('$table')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];

        foreach ($columns as $col) {
            if (
                isset($col['dflt_value']) &&
                preg_match(
                    "/CHECK\s*\\(\\s*{$col['name']}\\s+IN\\s*\\(([^)]+)\\)\\s*\\)/i",
                    $col['dflt_value'],
                    $matches
                )
            ) {
                $values = str_getcsv($matches[1], ',', "'");
                $result[$col['name']] = $values;
            }
        }

        return $result;
    }

    public function getForeignKeys(): array
    {
        $tables = $this->getTables();
        $fks = [];

        foreach ($tables as $table) {
            $stmt = $this->pdo->query("PRAGMA foreign_key_list('$table')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $fks[] = [
                    'from_table'  => $table,
                    'from_column' => $row['from'],
                    'to_table'    => $row['table'],
                    'to_column'   => $row['to'],
                ];
            }
        }

        return $fks;
    }
}
