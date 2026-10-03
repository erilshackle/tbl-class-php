<?php

namespace Tests\Integration;

use Tests\Support\SqliteTestCase;

final class SqliteSchemaReaderTest extends SqliteTestCase
{
    public function testListsUserTablesInOrderWithoutSqliteInternalTables(): void
    {
        $this->pdo->exec('CREATE TABLE zebra (id INTEGER PRIMARY KEY AUTOINCREMENT); CREATE TABLE apple (id INTEGER);');
        self::assertSame(['apple', 'zebra'], $this->schema->getTables());
    }

    public function testReadsColumnsContainingQuotes(): void
    {
        $this->pdo->exec('CREATE TABLE "odd\'table" ("odd\'column" TEXT, id INTEGER)');
        self::assertSame(["odd'column", 'id'], $this->schema->getColumns("odd'table"));
    }

    public function testReadsForeignKeyEndpoints(): void
    {
        $this->createBlog();
        self::assertSame([['from_table' => 'posts', 'from_column' => 'user_id',
            'to_table' => 'users', 'to_column' => 'id']], $this->schema->getForeignKeys());
    }


}
