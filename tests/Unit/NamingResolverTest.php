<?php

namespace Tests\Unit;

use Eril\TblClass\Resolvers\NamingResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamingResolverTest extends TestCase
{
    public static function strategies(): array
    {
        return [
            'full lowercase' => ['full', 'authentication__id'],
            'full uppercase' => ['FULL', 'AUTHENTICATION__ID'],
            'short lowercase' => ['short', 'auth__id'],
            'short uppercase' => ['SHORT', 'AUTH__ID'],
        ];
    }

    #[DataProvider('strategies')]
    public function testStrategyDefinesAbbreviationAndCase(string $strategy, string $expected): void
    {
        $naming = new NamingResolver(['strategy' => $strategy]);
        self::assertSame($expected, $naming->getColumnConstName('authentication', 'id'));
        self::assertArrayNotHasKey('case', $naming->getProfile());
    }

    public static function invalidConfiguration(): iterable
    {
        foreach (['abbr', 'alias', 'upper', 'Full', 'Short', 'sHoRt', ' full', 'FULL ', '', null, 1] as $value) {
            yield 'strategy ' . json_encode($value) => [['strategy' => $value]];
        }
        yield 'removed case' => [['case' => 'upper']];
        yield 'removed separator' => [['separator' => 'single']];
        yield 'removed FK prefix' => [['fk_prefix' => 'f_']];
        yield 'invalid override' => [['overrides' => ['users' => 'bad-name']]];
    }

    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(array $config): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NamingResolver($config);
    }

    public function testOverridesApplyToColumnsAndRelationsButTableNamesStayFull(): void
    {
        $naming = new NamingResolver(['strategy' => 'short', 'overrides' => ['users' => 'usr', 'posts' => 'pst']]);
        self::assertSame('usr__id', $naming->getColumnConstName('users', 'id'));
        self::assertSame('fk__pst__usr', $naming->getForeignKeyConstName('posts', 'users'));
        self::assertSame('on__pst__usr', $naming->getOnJoinConstName('posts', 'users'));
        self::assertSame('users', $naming->getTableConstName('users', true));
    }

    public function testNamingDoesNotDependOnPreviouslyResolvedTables(): void
    {
        $naming = new NamingResolver(['strategy' => 'short']);
        $before = $naming->getColumnConstName('orders', 'id');
        $naming->getColumnConstName('other_orders', 'id');
        self::assertSame($before, $naming->getColumnConstName('orders', 'id'));
    }
}
