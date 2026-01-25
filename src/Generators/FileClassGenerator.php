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

    private const SEPARATOR = " ";
    private NamingResolver $naming;

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

        $namespace = $this->config->getOutputNamespace();
        $tblFile   = $this->config->getTblFile();

        $content  = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= $this->generateHeader($schemaHash);
        $content .= $this->generateTblClass($tables, $relations);

        if (file_put_contents($tblFile, $content) === false) {
            throw new RuntimeException("Failed to write Tbl file: {$tblFile}");
        }
    }

    /**
     * Generates the file header containing schema metadata.
     */
    private function generateHeader(?string $schemaHash): string
    {
        $time   = date('Y-m-d H:i:s');
        $dbName = $this->schema->getDatabaseName();

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
            $columns = $this->schema->getColumns($table);
            if (empty($columns)) {
                continue;
            }

            $columnEnums = $this->schema->getEnumColumns($table);

            $tableConst = $this->naming->getTableConstName($table, true);

            $out .= "\n";
            $out .= "    /** TABLE: `{$table}` */" . self::SEPARATOR;
            $out .= "    public const {$tableConst} = '{$table}';\n\n";

            foreach ($columns as $column) {
                $colConst = $this->naming->getColumnConstName($table, $column);

                if (isset($columnEnums[$column])) {

                    $values = implode(' | ', array_map(
                        fn($v) => "`{$v}`",
                        $columnEnums[$column]
                    ));

                    $out .= "    /** ENUM {$column} → {$values} */" . self::SEPARATOR;
                } else {
                    $out .= "    /** COLUMN `{$column}` */" . self::SEPARATOR;
                }

                $out .= "    public const {$colConst} = '{$column}';\n"  . self::SEPARATOR;
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
                $out .= "    /** FK: `{$fk['from_table']}.{$fk['from_column']}` → `{$fk['to_table']}.{$fk['to_column']}` */" . self::SEPARATOR;
                $out .= "    public const {$fkConst} = '{$fk['from_column']}';" . self::SEPARATOR;
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
