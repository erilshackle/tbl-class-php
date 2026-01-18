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
            SELECT c.column_name, t.typname AS enum_type
            FROM information_schema.columns c
            JOIN pg_type t ON c.udt_name = t.typname
            WHERE c.table_schema = 'public'
              AND c.table_name = ?
              AND t.typtype = 'e'
        ");
        $stmt->execute([$table]);

        $result = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $valStmt = $this->pdo->prepare("
                SELECT enumlabel
                FROM pg_enum
                WHERE enumtypid = (
                    SELECT oid FROM pg_type WHERE typname = ?
                )
                ORDER BY enumsortorder
            ");
            $valStmt->execute([$row['enum_type']]);

            $result[$row['column_name']] = $valStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return $result;
    }

    public function getForeignKeys(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                tc.table_name  AS from_table,
                kcu.column_name AS from_column,
                ccu.table_name AS to_table,
                ccu.column_name AS to_column
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
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
