<?php

namespace Eril\TblClass\Schema;

use PDO;

class PgSqlSchemaReader implements SchemaReaderInterface
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
            SELECT tablename
            FROM pg_catalog.pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getColumns(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getEnums(string $table): array
    {
        // PostgreSQL enums são tipos separados
        $stmt = $this->pdo->prepare("
            SELECT c.column_name, t.typname as enum_type
            FROM information_schema.columns c
            JOIN pg_type t ON c.udt_name = t.typname
            WHERE c.table_schema = 'public'
              AND c.table_name = ?
              AND t.typtype = 'e'
        ");
        $stmt->execute([$table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $constants = [];
        foreach ($rows as $row) {
            $columnName = $row['column_name'];
            $enumType = $row['enum_type'];

            $valStmt = $this->pdo->prepare("SELECT enumlabel FROM pg_enum WHERE enumtypid = (SELECT oid FROM pg_type WHERE typname = ?)");
            $valStmt->execute([$enumType]);
            $values = $valStmt->fetchAll(PDO::FETCH_COLUMN);

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
                tc.table_name as from_table,
                kcu.column_name as from_column,
                ccu.table_name as to_table,
                ccu.column_name as to_column
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = 'public'
            ORDER BY tc.table_name, kcu.column_name
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
