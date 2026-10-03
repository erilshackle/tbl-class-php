<?php

namespace Tests\Integration;

use Eril\TblClass\Generators\FileClassGenerator;
use Eril\TblClass\Generators\PhpOutput;
use Eril\TblClass\Schema\SchemaReaderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SqliteTestCase;

final class GenerationTest extends SqliteTestCase
{
    public static function collisions(): array
    {
        return [
            'normalized table names' => ['CREATE TABLE "a-b" (id INTEGER); CREATE TABLE "a b" (id INTEGER)', []],
            'two relations to the same table' => ['CREATE TABLE edits (author INTEGER REFERENCES users(id), editor INTEGER REFERENCES users(id))', []],
            'same override prefix' => ['', ['output' => ['naming' => ['overrides' => ['users' => 'x', 'posts' => 'x']]]]],
        ];
    }

    #[DataProvider('collisions')]
    public function testCollisionPreservesExistingOutput(string $sql, array $changes): void
    {
        $this->createBlog();
        $config = $this->generate();
        $original = file_get_contents($config->getTblFile());
        if ($sql !== '') {
            $this->pdo->exec($sql);
        }

        $result = (new FileClassGenerator($this->schema, $this->config($changes)))->run();

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('Constant collision', $result->getMessage());
        self::assertSame($original, file_get_contents($config->getTblFile()));
    }

    public function testEmptyDatabasePreservesExistingOutput(): void
    {
        $this->createBlog();
        $config = $this->generate();
        $original = file_get_contents($config->getTblFile());
        $this->pdo->exec('DROP TABLE posts; DROP TABLE users');
        $result = (new FileClassGenerator($this->schema, $config))->run();
        self::assertFalse($result->isSuccess());
        self::assertSame($original, file_get_contents($config->getTblFile()));
    }

    public function testInvalidNamespacePreservesExistingOutput(): void
    {
        $this->createBlog();
        $config = $this->generate();
        $original = file_get_contents($config->getTblFile());
        $invalid = $this->config(['output' => ['namespace' => 'Invalid-namespace']]);
        $result = (new FileClassGenerator($this->schema, $invalid))->run();
        self::assertStringContainsString('Invalid output namespace', $result->getMessage());
        self::assertSame($original, file_get_contents($config->getTblFile()));
    }

    public function testUnusualSqlIdentifiersRoundTripThroughPhp(): void
    {
        $this->pdo->exec('CREATE TABLE "9 odd\'name*/" ("a\'b" TEXT); CREATE TABLE "class" (id INTEGER); CREATE TABLE audit__logs (id INTEGER)');
        $class = $this->loadOutput();
        self::assertSame("9 odd'name*/", $class::_9_odd_name__);
        self::assertSame("a'b", $class::_9_odd_name____a_b);
        self::assertSame('class AS c', $class::_class('c'));
        self::assertSame('audit__logs AS l', $class::audit__logs('l'));
    }

    public function testEnumMetadataCannotInjectPhp(): void
    {
        // SQLite has no native enum type: supply that part of the reader contract explicitly.
        $reader = $this->createStub(SchemaReaderInterface::class);
        $reader->method('getDatabaseName')->willReturn('test');
        $reader->method('getTables')->willReturn(['users']);
        $reader->method('getColumns')->willReturn(['status']);
        $reader->method('getForeignKeys')->willReturn([]);
        $reader->method('getEnumColumns')->willReturn(['status' => ['*/ ?> injected', "one\ntwo"]]);
        $config = $this->config();

        $result = (new FileClassGenerator($reader, $config))->run();

        self::assertTrue($result->isSuccess(), $result->getMessage());
        require $config->getTblFile();
        $class = $config->getOutputNamespace() . '\\Tbl';
        self::assertSame('status', $class::users__status);
        self::assertStringContainsString('injected', file_get_contents($config->getTblFile()));
    }

    public function testInvalidPhpDoesNotReplaceOutputOrLeaveTemporaryFiles(): void
    {
        $file = $this->write('output.php', '<?php return "original";');
        try {
            PhpOutput::write($file, '<?php class Broken { public const x = ; }');
            self::fail('Invalid PHP must be rejected before replacing the file.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Generated PHP is invalid', $error->getMessage());
        }
        self::assertSame('original', require $file);
        self::assertSame([], glob($this->directory . '/.tbl-*'));
    }
}
