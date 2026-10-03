<?php

namespace Tests\Integration;

use Eril\TblClass\Independence\GeneratedModel;
use Eril\TblClass\Independence\IndependenceCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OutputProfiles;
use Tests\Support\SqliteTestCase;

final class GeneratedHelpersTest extends SqliteTestCase
{
    use OutputProfiles;

    private function generateProfile(array $profile): string
    {
        $this->pdo->exec('CREATE TABLE authentication (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES authentication(id));
            CREATE TABLE configuration (id INTEGER PRIMARY KEY, authentication_id INTEGER REFERENCES authentication(id));');
        return $this->loadOutput($this->config(['output' => ['naming' => [
            'strategy' => $profile['strategy'], 'overrides' => $profile['overrides'],
        ]]]));
    }

    #[DataProvider('outputProfiles')]
    public function testConstantsKeepDatabaseValues(array $profile): void
    {
        $class = $this->generateProfile($profile);
        self::assertSame('authentication', constant($class . '::' . $profile['table']));
        self::assertSame('id', constant($class . '::' . $profile['column']));
        self::assertSame('configuration', constant($class . '::' . $profile['otherTable']));
        self::assertSame('id', constant($class . '::' . $profile['otherColumn']));
        self::assertSame('authentication_id', constant($class . '::' . $profile['foreignKey']));
        self::assertSame('configuration.authentication_id = authentication.id', constant($class . '::' . $profile['join']));
        self::assertSame('authentication.parent_id = authentication.id', constant($class . '::' . $profile['selfJoin']));
    }

    #[DataProvider('outputProfiles')]
    public function testHelpersBuildAnExecutableJoin(array $profile): void
    {
        $class = $this->generateProfile($profile);
        ['table' => $table, 'column' => $column, 'otherTable' => $otherTable,
            'otherColumn' => $otherColumn, 'join' => $join] = $profile;
        $this->pdo->exec('INSERT INTO authentication VALUES (1, NULL), (2, 1); INSERT INTO configuration VALUES (10, 2)');

        $sql = 'SELECT ' . $class::$column('a') . ' AS account_id, ' . $class::$otherColumn('c')
            . ' AS setting_id FROM ' . $class::$table('a') . ' JOIN ' . $class::$otherTable('c')
            . ' ON ' . $class::$join('c', 'a');

        self::assertSame(['account_id' => 2, 'setting_id' => 10], $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC));
    }

    #[DataProvider('outputProfiles')]
    public function testHelpersBuildAnExecutableSelfJoin(array $profile): void
    {
        $class = $this->generateProfile($profile);
        ['table' => $table, 'column' => $column, 'selfJoin' => $selfJoin] = $profile;
        $this->pdo->exec('INSERT INTO authentication VALUES (1, NULL), (2, 1)');

        $sql = 'SELECT ' . $class::$column('child') . ' FROM ' . $class::$table('child')
            . ' JOIN ' . $class::$table('parent') . ' ON ' . $class::$selfJoin('child', 'parent');

        self::assertSame([2], $this->pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }

    #[DataProvider('outputProfiles')]
    public function testIndependenceUnderstandsEveryOutputProfile(array $profile): void
    {
        $class = $this->generateProfile($profile);
        ['table' => $table, 'column' => $column, 'foreignKey' => $fk,
            'join' => $join, 'selfJoin' => $selfJoin] = $profile;
        $file = $this->write('app/query.php', '<?php use ' . $class . ' as T; return [T::' . $table
            . ', T::' . $column . ', T::' . $column . '("a"), T::' . $fk . '("c"), T::'
            . $join . '("c", "a"), T::' . $selfJoin . '("child", "parent")];');

        $report = (new IndependenceCommand())->run($this->directory . '/app', $this->directory . '/Tbl/Tbl.php', false);

        self::assertSame(6, $report['replacements']);
        self::assertSame([], $report['pending']);
        self::assertSame(['authentication', 'id', 'a.id', 'c.authentication_id',
            'c.authentication_id = a.id', 'child.parent_id = parent.id'], require $file);
    }

    public static function helperCalls(): array
    {
        return [
            'table without alias' => ['users', [], 'users'],
            'table null alias' => ['users', [null], 'users'],
            'table empty alias' => ['users', [''], 'users'],
            'table positional alias' => ['users', ['u'], 'users AS u'],
            'table named alias' => ['users', ['alias' => 'u'], 'users AS u'],
            'column without alias' => ['users__id', [], 'id'],
            'column null alias' => ['users__id', [null], 'id'],
            'column empty alias' => ['users__id', [''], 'id'],
            'column positional alias' => ['users__id', ['u'], 'u.id'],
            'column named alias' => ['users__id', ['alias' => 'u'], 'u.id'],
            'column table qualifier' => ['users__id', ['users'], 'users.id'],
            'column zero alias' => ['users__id', ['0'], '0.id'],
            'foreign key without alias' => ['fk__posts__users', [], 'user_id'],
            'foreign key with alias' => ['fk__posts__users', ['p'], 'p.user_id'],
            'join without aliases' => ['on__posts__users', [], 'posts.user_id = users.id'],
            'join left alias' => ['on__posts__users', ['p'], 'p.user_id = users.id'],
            'join right alias' => ['on__posts__users', [null, 'u'], 'posts.user_id = u.id'],
            'join both aliases' => ['on__posts__users', ['p', 'u'], 'p.user_id = u.id'],
            'join null aliases' => ['on__posts__users', [null, null], 'posts.user_id = users.id'],
        ];
    }

    #[DataProvider('helperCalls')]
    public function testRuntimeHelperReturnsExpectedSql(string $method, array $arguments, string $expected): void
    {
        $this->createBlog();
        $class = $this->loadOutput();
        self::assertSame($expected, $class::$method(...$arguments));
    }

    #[DataProvider('helperCalls')]
    public function testStaticInterpreterReturnsExpectedSql(string $method, array $arguments, string $expected): void
    {
        $this->createBlog();
        $config = $this->generate();
        $model = new GeneratedModel($config->getTblFile());
        self::assertSame($expected, $model->call($method, $arguments));
    }

    public function testAliasesDoNotPersistBetweenCalls(): void
    {
        $this->createBlog();
        $class = $this->loadOutput();
        $class::users('u');
        self::assertSame('author.id', $class::users__id('author'));
        self::assertSame('editor.id', $class::users__id('editor'));
        self::assertSame('id', $class::users__id());
    }

    public function testHelpersAreAvailableWithoutForeignKeys(): void
    {
        $this->pdo->exec('CREATE TABLE users (id INTEGER)');
        $class = $this->loadOutput();
        self::assertSame('users AS u', $class::users('u'));
        self::assertSame('u.id', $class::users__id('u'));
        self::assertFalse(method_exists($class, 'users__id'));
    }

    public function testUnknownHelperThrows(): void
    {
        $this->createBlog();
        $class = $this->loadOutput();
        $this->expectException(\BadMethodCallException::class);
        $class::unknown_helper();
    }
}

