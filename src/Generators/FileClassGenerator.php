<?php

namespace Eril\TblClass\Generators;

use Eril\TblClass\Config;
use Eril\TblClass\Generators\Traits\JoinHelperTrait;
use Eril\TblClass\Resolvers\NamingResolver;
use Eril\TblClass\Schema\SchemaReaderInterface;
use RuntimeException;

/**
 * Generates schema constants based on the current database structure.
 *
 * Produces a single public entry-point class (Tbl) exposing:
 * - table names
 * - column names
 * - foreign key columns
 *
 * Enum columns are documented inline as metadata.
 */
class FileClassGenerator extends Generator
{
    use JoinHelperTrait;

    private NamingResolver $naming;
    private PhpOutput $php;

    public function __construct(
        SchemaReaderInterface $schema,
        Config $config,
        bool $checkMode = false
    ) {
        parent::__construct($schema, $config, $checkMode);
        $this->naming = new NamingResolver($config->getNamingConfig());
    }

    /**
     * Entry point for content generation.
     */
    protected function generateContent(
        array $tables,
        array $relations = [],
        ?string $schemaHash = null
    ): void {
        $this->naming->reset();
        $this->php = new PhpOutput();

        $namespace = $this->config->getOutputNamespace();
        $tblFile   = $this->config->getTblFile();
        foreach (explode('\\', $namespace) as $part) {
            if ($part === '' || PhpOutput::identifier($part) !== $part) {
                throw new RuntimeException('Invalid output namespace: ' . $namespace);
            }
        }

        $content  = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= $this->generateHeader($schemaHash);
        $content .= $this->generateTblClass($tables, $relations);

        PhpOutput::write($tblFile, $content);
    }

    /**
     * Generates the file header containing schema metadata.
     */
    private function generateHeader(?string $schemaHash): string
    {
        $time   = date('Y-m-d H:i:s');
        $dbName = PhpOutput::comment($this->schema->getDatabaseName());
        $snapshot = base64_encode(json_encode($this->snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return <<<HEADER
/**
 * Database schema mapping for "{$dbName}"
 *
 * Provides a stable reference layer for:
 * - tables
 * - columns
 * - foreign keys
 *
 * @schema-hash md5:{$schemaHash}
 * @generation-snapshot {$snapshot}
 * @generated   {$time}
 *
 * ⚠ AUTO-GENERATED FILE
 * Any manual changes will be lost on regeneration.
 */

HEADER;
    }

    /**
     * Builds the Tbl class.
     */
    private function generateTblClass(array $tables, array $foreignKeys): string
    {
        $out = "final class Tbl\n{\n";

        // --------------------------------------------------
        // Tables & Columns
        // --------------------------------------------------
        foreach ($tables as $table) {
            $columns = $this->snapshot['tables'][$table]['columns'];
            if (empty($columns)) {
                continue;
            }

            $columnEnums = $this->snapshot['tables'][$table]['enums'];

            $tableConst = $this->naming->getTableConstName($table, true);

            $out .= "\n";
            $out .= $this->php->constant($tableConst, $table, "TABLE: `{$table}`");

            foreach ($columns as $column) {
                $colConst = $this->naming->getColumnConstName($table, $column);

                if (isset($columnEnums[$column])) {

                    $values = implode(' | ', array_map(
                        fn($v) => "`{$v}`",
                        $columnEnums[$column]
                    ));

                    $description = "ENUM {$table}.{$column}: {$values}";
                } else {
                    $description = "COLUMN `{$table}.{$column}`";
                }

                $out .= $this->php->constant($colConst, $column, $description);
            }
        }

        // --------------------------------------------------
        // Foreign Keys
        // --------------------------------------------------
        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $fkConst = $this->naming->getForeignKeyConstName(
                    $fk['from_table'],
                    $fk['to_table']
                );

                $out .= "\n";
                $out .= $this->php->constant($fkConst, $fk['from_column'],
                    "FK: {$fk['from_table']}.{$fk['from_column']} -> {$fk['to_table']}.{$fk['to_column']}");
            }
        }

        // --------------------------------------------------
        // Helpers
        // --------------------------------------------------
        $out .= $this->generateJoinHelper($foreignKeys);

        $out .= "\n}\n";

        return $out;
    }

    /**
     * Generates the Tbk class (unused).
     */
    private function generateTbkClass(array $foreignKeys): string
    {
        return "final class Tbk {}\n";
    }

    /**
     * Generates the Tbe class (unused).
     */
    private function generateTbeClass(array $tables): string
    {
        return "final class Tbe {}\n";
    }
}
