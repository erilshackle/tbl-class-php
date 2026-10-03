<?php

namespace Eril\TblClass\Generators;

use Eril\TblClass\Cli\CliPrinter;
use Eril\TblClass\Config;
use Eril\TblClass\GeneratorResult;
use Eril\TblClass\Introspection\GeneratedClassMetadata;
use Eril\TblClass\Introspection\SchemaHasher;
use Eril\TblClass\Introspection\SchemaDiff;
use Eril\TblClass\Resolvers\NamingResolver;
use Eril\TblClass\Schema\SchemaReaderInterface;
use RuntimeException;

abstract class Generator
{
    protected array $snapshot = [];
    public function __construct(
        protected SchemaReaderInterface $schema,
        protected Config $config,
        protected bool $checkMode = false
    ) {}

    final public function run(): GeneratorResult
    {
        try {
            $tables = $this->schema->getTables();
            sort($tables, SORT_STRING);
            if (empty($tables) && !$this->checkMode) {
                return GeneratorResult::error('No tables found in database');
            }

            $foreignKeys = $this->schema->getForeignKeys();
            sort($foreignKeys);
            $schemaData = $this->snapshot = $this->buildSchemaHashData($tables, $foreignKeys);
            $currentHash = SchemaHasher::hash($schemaData);

            if ($this->checkMode) {
                return $this->checkSchema($currentHash);
            }
            $this->ensureOutputDirectory();
            $this->generateContent($tables, $foreignKeys, $currentHash);
            return $this->writeOutput(count($tables), count($foreignKeys));
        } catch (RuntimeException $e) {
            return GeneratorResult::error($e->getMessage());
        }
    }

    abstract protected function generateContent(
        array $tables,
        array $relations = [],
        ?string $schemaHash = null
    );

    protected function ensureOutputDirectory(): void
    {
        $dir = $this->config->getOutputPath();

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create output directory: {$dir}");
        }
    }

    protected function buildSchemaHashData(array $tables, array $foreignKeys): array
    {
        $schemaData = [
            'version' => 1,
            'database' => $this->schema->getDatabaseName(),
            'tables' => [],
            'foreignKeys' => $foreignKeys,
            'generation' => [
                'generator.version' => '2.0.0',
                'output.column_helpers' => 3,
                'database.driver' => $this->config->getDriver(),
                'output.namespace' => $this->config->getOutputNamespace(),
                'output.naming' => (new NamingResolver($this->config->getNamingConfig()))->getProfile(),
            ],
        ];

        foreach ($tables as $table) {
            $columns = $this->schema->getColumns($table);
            sort($columns, SORT_STRING);
            $enums = $this->schema->getEnumColumns($table);
            ksort($enums);
            $schemaData['tables'][$table] = ['columns' => $columns, 'enums' => $enums];
        }

        ksort($schemaData['tables']);
        sort($schemaData['foreignKeys']);

        return $schemaData;
    }

    protected function checkSchema(string $currentHash): GeneratorResult
    {
        $outputFile = $this->config->getTblFile();
        $savedHash = GeneratedClassMetadata::extractSchemaHash($outputFile);

        if (!$savedHash) {
            return GeneratorResult::initialRequired();
        }

        $savedSnapshot = GeneratedClassMetadata::extractSnapshot($outputFile);
        if ($savedSnapshot === null) {
            return GeneratorResult::schemaChanged([
                'diff' => ['Previous generation has no snapshot. Run tbl-class generate once to enable detailed comparisons.'],
            ]);
        }
        if (SchemaHasher::hash($savedSnapshot) !== $savedHash) {
            return GeneratorResult::error('Generation snapshot does not match its hash. Run tbl-class generate.');
        }

        if ($savedHash !== $currentHash) {
            return GeneratorResult::schemaChanged(['diff' => SchemaDiff::compare($savedSnapshot, $this->snapshot)]);
        }

        return GeneratorResult::success('Schema is up to date');
    }

    protected function writeOutput(int $tableCount, int $fkCount): GeneratorResult
    {
        $tblDir = $this->config->getOutputPath();
        if (!is_dir($tblDir) && !mkdir($tblDir, 0755, true)) {
            return GeneratorResult::error("Cannot create Tbl output directory: {$tblDir}");
        }

        $tblFile = $tblDir . DIRECTORY_SEPARATOR . 'Tbl.php';
        $enumFile = $tblDir . DIRECTORY_SEPARATOR . 'TblEnum.php';

        $message = "Generated Tbl class:\n
    File: {$tblFile} - {$tableCount}\n";

        return GeneratorResult::success(
            $message,
            [
                'tblFile' => $tblFile,
                'enumFile' => $enumFile,
                'tables' => $tableCount,
                'foreignKeys' => $fkCount
            ]
        );
    }



    protected function printInstructions(): void
    {
        $file = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $this->config->getTblFile());

        CliPrinter::line('');
        CliPrinter::info("1. Add the generated classes to Composer autoload:");

        CliPrinter::line("  \"autoload\": {");
        CliPrinter::line("    \"files\": [");
        CliPrinter::line("      \"{$file}\"", 'bold');
        CliPrinter::line("    ]");
        CliPrinter::line("  }");
        // CliPrinter::line('');
        CliPrinter::info("2. Then run: composer dump-autoload");
    }
}
