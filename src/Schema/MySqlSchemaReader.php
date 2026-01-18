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

    public function getEnums(string $table): array
    {
        $sql = "SELECT 
                    COLUMN_NAME,
                    COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ? 
                    AND DATA_TYPE = 'enum'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$table]);
        $enums = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $constants = [];
        foreach ($enums as $enum) {
            $columnName = $enum['COLUMN_NAME'];
            $enumString = $enum['COLUMN_TYPE'];
            $enumString = substr($enumString, 5, -1);
            $values = str_getcsv($enumString, ",", "'");

            foreach ($values as $value) {
                $constantName = strtolower($columnName . '_' . $value);
                $constantName = preg_replace('/[^a-z0-9_]/', '_', $constantName);
                $constants[$constantName] = $value;
            }
        }

        return $constants;
    }

    public function getForeignKeys(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                TABLE_NAME as from_table,
                COLUMN_NAME as from_column,
                REFERENCED_TABLE_NAME as to_table,
                REFERENCED_COLUMN_NAME as to_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");
        $stmt->execute([$this->dbName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
