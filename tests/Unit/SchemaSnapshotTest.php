<?php

namespace Tests\Unit;

use Eril\TblClass\Introspection\SchemaDiff;
use Eril\TblClass\Introspection\SchemaHasher;
use PHPUnit\Framework\TestCase;

final class SchemaSnapshotTest extends TestCase
{
    public function testHashIgnoresAssociativeKeyOrderRecursively(): void
    {
        $before = ['tables' => ['users' => ['columns' => ['id'], 'enums' => []]], 'database' => 'test'];
        $after = ['database' => 'test', 'tables' => ['users' => ['enums' => [], 'columns' => ['id']]]];
        self::assertSame(SchemaHasher::hash($before), SchemaHasher::hash($after));
    }

    public function testInvalidUtf8IsRejectedInsteadOfProducingAnEmptyHash(): void
    {
        $this->expectException(\JsonException::class);
        SchemaHasher::hash(['table' => "\xB1"]);
    }

    public function testEnumDiffIncludesOldAndNewValues(): void
    {
        $before = ['version' => 1, 'database' => 'test', 'foreignKeys' => [], 'generation' => [],
            'tables' => ['users' => ['columns' => ['status'], 'enums' => ['status' => ['active']]]]];
        $after = $before;
        $after['tables']['users']['enums']['status'][] = 'blocked';
        self::assertSame(['~ enum users.status: ["active"] -> ["active","blocked"]'], SchemaDiff::compare($before, $after));
    }
}
