<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WorkspaceTestCase;

final class CliTest extends WorkspaceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Each subprocess gets a fresh in-memory database with this same schema.
        $connection = $this->write('connection.php', <<<'PHP'
<?php
final class TestConnection
{
    public static function open(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        return $pdo;
    }
}
PHP);
        $this->write('schema.sql', 'CREATE TABLE users (id INTEGER PRIMARY KEY);');
        $this->config(['include' => $connection, 'database' => ['connection' => 'TestConnection::open']]);
    }

    private function generate(): string
    {
        [$code, $output] = $this->cli(['generate']);
        self::assertSame(0, $code, $output);
        return $this->directory . '/Tbl/Tbl.php';
    }

    public function testGenerateWritesLoadablePhpFromCustomMemoryConnection(): void
    {
        $file = $this->generate();
        require $file;
        $class = $this->namespace . '\\Tbl\\Tbl';
        self::assertSame('users', $class::users);
    }

    public function testCheckReturnsZeroForUnchangedSchema(): void
    {
        $this->generate();
        [$code, $output] = $this->cli(['check', '--diff']);
        self::assertSame(0, $code, $output);
        self::assertStringContainsString('up to date', $output);
    }

    public static function checkCommands(): array
    {
        return ['command' => [['check', '--diff']], 'legacy flag' => [['--check', '--diff']]];
    }

    #[DataProvider('checkCommands')]
    public function testCheckDiffReturnsOneAndDoesNotWriteOnDrift(array $arguments): void
    {
        $file = $this->generate();
        $original = file_get_contents($file);
        $this->write('schema.sql', 'CREATE TABLE users (id INTEGER PRIMARY KEY, phone TEXT);');
        [$code, $output] = $this->cli($arguments);
        self::assertSame(1, $code, $output);
        self::assertStringContainsString('+ users.phone', $output);
        self::assertSame($original, file_get_contents($file));
    }

    public function testCheckReturnsTwoWhenOutputIsMissing(): void
    {
        [$code, $output] = $this->cli(['check', '--diff']);
        self::assertSame(2, $code, $output);
        self::assertStringContainsString('Initial generation required', $output);
    }

    public function testCheckReportsCorruptMetadataAsFailure(): void
    {
        $file = $this->generate();
        file_put_contents($file, str_replace('@generation-snapshot ', '@generation-snapshot corrupt', file_get_contents($file)));
        [$code, $output] = $this->cli(['check', '--diff']);
        self::assertSame(1, $code);
        self::assertStringContainsString('Check failed:', $output);
    }

    public static function invalidArguments(): array
    {
        return ['diff with generate' => [['generate', '--diff']], 'diff alone' => [['--diff']],
            'dry run with generate' => [['generate', '--dry-run']], 'missing directory' => [['independence']],
            'missing generated path' => [['independence', '.', '--generated']], 'unknown option' => [['--unknown']]];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsReturnTwo(array $arguments): void
    {
        [$code, $output] = $this->cli($arguments);
        self::assertSame(2, $code, $output);
    }

    public function testVersionReportsPackageVersion(): void
    {
        [$code, $output] = $this->cli(['--version']);
        self::assertSame(0, $code);
        self::assertStringContainsString('v2.0.0', $output);
    }

    public function testIndependencePreviewShowsConcreteReplacementWithoutBootstrap(): void
    {
        $this->generate();
        $file = $this->write('app/query.php', '<?php return \\' . $this->namespace . '\\Tbl\\Tbl::users;');
        $original = file_get_contents($file);
        $include = $this->write('forbidden.php', '<?php throw new RuntimeException("BOOTSTRAP EXECUTED");');
        $this->config(['include' => $include, 'database' => ['driver' => 'invalid']]);

        [$code, $output] = $this->cli(['independence', $this->directory . '/app', '--dry-run']);

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('1 replacements', $output);
        self::assertStringContainsString('query.php:', $output);
        self::assertStringContainsString(' -> ', $output);
        self::assertSame($original, file_get_contents($file));
    }

    public function testIndependenceReturnsOneForPendingReferences(): void
    {
        $file = $this->generate();
        $this->write('app/query.php', '<?php return \\' . $this->namespace . '\\Tbl\\Tbl::users($alias);');
        [$code, $output] = $this->cli(['independence', $this->directory . '/app', '--generated', $file]);
        self::assertSame(1, $code, $output);
        self::assertStringContainsString('1 unresolved references', $output);
    }

    public function testInvalidNamingFailsBeforeBootstrap(): void
    {
        $include = $this->write('forbidden.php', '<?php throw new RuntimeException("BOOTSTRAP EXECUTED");');
        $this->config(['include' => $include, 'output' => ['naming' => ['strategy' => 'abbr']]]);
        [$code, $output] = $this->cli(['generate']);
        self::assertSame(1, $code);
        self::assertStringContainsString('v2 naming.strategy', $output);
        self::assertStringNotContainsString('BOOTSTRAP EXECUTED', $output);
    }
}
