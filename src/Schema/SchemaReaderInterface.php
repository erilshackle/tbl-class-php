<?php

namespace Eril\TblClass\Schema;

interface SchemaReaderInterface
{
    public function getDatabaseName(): string;
    public function getTables(): array;
    public function getColumns(string $table): array;
    public function getEnumColumns(string $table): array;
    public function getForeignKeys(): array;
}
