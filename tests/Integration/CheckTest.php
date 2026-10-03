<?php

namespace Tests\Integration;

use Eril\TblClass\Generators\FileClassGenerator;
use Eril\TblClass\Introspection\GeneratedClassMetadata;
use Eril\TblClass\Introspection\SchemaHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SqliteTestCase;

final class CheckTest extends SqliteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createBlog();
    }

    public function testMissingOutputRequiresInitialGeneration(): void
    {
        $result = (new FileClassGenerator($this->schema, $this->config(), true))->run();
        self::assertTrue($result->isInitialRequired());
    }

    public function testUnchangedSchemaPasses(): void
    {
        $config = $this->generate();
        self::assertTrue((new FileClassGenerator($this->schema, $config, true))->run()->isSuccess());
    }

    public static function schemaChanges(): array
    {
        return [
            'added column' => ['ALTER TABLE users ADD COLUMN phone TEXT', '+ users.phone'],
            'removed foreign key' => ['DROP TABLE posts; CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER)', '- FK posts.user_id -> users.id'],
            'removed all tables' => ['DROP TABLE posts; DROP TABLE users', '- table users'],
        ];
    }

    #[DataProvider('schemaChanges')]
    public function testSchemaChangesAreReportedWithoutWriting(string $sql, string $expectedDiff): void
    {
        $config = $this->generate();
        $original = file_get_contents($config->getTblFile());
        $this->pdo->exec($sql);

        $result = (new FileClassGenerator($this->schema, $config, true))->run();

        self::assertTrue($result->isSchemaChanged());
        self::assertContains($expectedDiff, $result->getData()['diff']);
        self::assertSame($original, file_get_contents($config->getTblFile()));
    }

    public static function configurationChanges(): array
    {
        return ['strategy' => [['naming' => ['strategy' => 'short']]], 'namespace' => [['namespace' => 'Changed']]];
    }

    #[DataProvider('configurationChanges')]
    public function testConfigurationChangesRequireRegeneration(array $output): void
    {
        $this->generate();
        $config = $this->config(['output' => $output]);
        self::assertTrue((new FileClassGenerator($this->schema, $config, true))->run()->isSchemaChanged());
    }

    public function testColumnOrderDoesNotCauseDrift(): void
    {
        $config = $this->generate();
        $this->pdo->exec('DROP TABLE users; CREATE TABLE users (name TEXT, id INTEGER PRIMARY KEY)');
        self::assertTrue((new FileClassGenerator($this->schema, $config, true))->run()->isSuccess());
    }

    public function testLegacyFileExplainsMissingSnapshot(): void
    {
        $config = $this->config();
        $this->write('Tbl/Tbl.php', '<?php /** @schema-hash md5:' . str_repeat('a', 32) . ' */');
        $result = (new FileClassGenerator($this->schema, $config, true))->run();
        self::assertTrue($result->isSchemaChanged());
        self::assertStringContainsString('no snapshot', implode(' ', $result->getData()['diff']));
    }

    public function testCorruptSnapshotIsAnErrorRatherThanSchemaDrift(): void
    {
        $config = $this->generate();
        $source = file_get_contents($config->getTblFile());
        file_put_contents($config->getTblFile(), str_replace('@generation-snapshot ', '@generation-snapshot corrupt', $source));
        $result = (new FileClassGenerator($this->schema, $config, true))->run();
        self::assertFalse($result->isSuccess());
        self::assertFalse($result->isSchemaChanged());
    }

    public function testOlderHelperVersionRequiresRegeneration(): void
    {
        $config = $this->generate();
        $file = $config->getTblFile();
        $snapshot = GeneratedClassMetadata::extractSnapshot($file);
        unset($snapshot['generation']['output.column_helpers']);
        $source = preg_replace('/@generation-snapshot [A-Za-z0-9+\/=]+/', '@generation-snapshot ' . base64_encode(json_encode($snapshot)), file_get_contents($file));
        $source = preg_replace('/@schema-hash md5:[a-f0-9]{32}/', '@schema-hash md5:' . SchemaHasher::hash($snapshot), $source);
        file_put_contents($file, $source);
        self::assertTrue((new FileClassGenerator($this->schema, $config, true))->run()->isSchemaChanged());
    }
}
