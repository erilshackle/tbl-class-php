<?php

namespace Tests\Integration;

use Eril\TblClass\Independence\GeneratedModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WorkspaceTestCase;

final class GeneratedModelTest extends WorkspaceTestCase
{
    public static function legacyCalls(): array
    {
        return ['default table' => [[], 'users.id'], 'null alias' => [[null], 'users.id'],
            'empty alias' => [[''], 'users.id'], 'positional alias' => [['u'], 'u.id'],
            'named alias' => [['alias' => 'u'], 'u.id']];
    }

    #[DataProvider('legacyCalls')]
    public function testInterpretsExplicitLegacyHelpersWithoutLoadingThem(array $arguments, string $expected): void
    {
        $file = $this->write('legacy.php', <<<'PHP'
<?php
namespace Legacy;
final class Tbl
{
    public const users__id = 'id';
    public static function users__id(?string $alias = null): string
    {
        return ($alias === null || $alias === '' ? 'users' : $alias) . '.' . self::users__id;
    }
}
throw new \RuntimeException('This file must never be executed');
PHP);
        $model = new GeneratedModel($file);
        self::assertSame($expected, $model->call('users__id', $arguments));
        self::assertFalse(class_exists('Legacy\\Tbl', false));
    }
}
