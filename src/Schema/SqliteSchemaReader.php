<?php

namespace Eril\TblClass\Schema;

use PDO;

class SqliteSchemaReader implements SchemaReaderInterface
{
    public function __construct(
        private PDO $pdo,
        private string $dbPath
    ) {}

    public function getDatabaseName(): string
    {
        return basename($this->dbPath);
    }

    public function getTables(): array
    {
        $stmt = $this->pdo->query("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getColumns(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info('$table')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($columns, 'name');
    }

    public function getEnums(string $table): array
    {
        // SQLite não tem enum nativo, retorna vazio
        return [];
    }

    public function getForeignKeys(): array
    {
        $tables = $this->getTables();
        $fks = [];

        foreach ($tables as $table) {
            $stmt = $this->pdo->query("PRAGMA foreign_key_list('$table')");
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($list as $fk) {
                $fks[] = [
                    'from_table' => $table,
                    'from_column' => $fk['from'],
                    'to_table' => $fk['table'],
                    'to_column' => $fk['to'],
                ];
            }
        }

        return $fks;
    }
}
