<?php

namespace Eril\TblClass\Schema;

use PDO;

class MySqlSchemaReader implements SchemaReaderInterface
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
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
              AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");
        $stmt->execute([$this->dbName]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getColumns(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->execute([$this->dbName, $table]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Returns enum values indexed by column name.
     *
     * [
     *   'status' => ['active', 'pending', 'inactive']
     * ]
     */
    public function getEnumColumns(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND DATA_TYPE = 'enum'
        ");
        $stmt->execute([$this->dbName, $table]);

        $result = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $enumDef = substr($row['COLUMN_TYPE'], 5, -1); // enum(...)
            $values  = str_getcsv($enumDef, ',', "'");

            $result[$row['COLUMN_NAME']] = $values;
        }

        return $result;
    }

    public function getForeignKeys(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                TABLE_NAME AS from_table,
                COLUMN_NAME AS from_column,
                REFERENCED_TABLE_NAME AS to_table,
                REFERENCED_COLUMN_NAME AS to_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");
        $stmt->execute([$this->dbName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
