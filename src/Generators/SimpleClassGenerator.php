<?php

namespace Eril\TblClass\Generators;

use Eril\TblClass\Config;
use Eril\TblClass\Resolvers\NamingResolver;
use Eril\TblClass\Schema\SchemaReaderInterface;
use RuntimeException;

class SimpleClassGenerator extends Generator
{
    private NamingResolver $namingResolver;

    public function __construct(
        SchemaReaderInterface $schema,
        Config $config,
        bool $checkMode = false
    ) {
        parent::__construct($schema, $config, $checkMode);
        $this->namingResolver = new NamingResolver($config->getNamingConfig());
    }

    /**
     * Gera apenas a classe Tbl contendo:
     * - constantes de tabelas
     * - constantes de foreign keys
     *
     * As colunas são documentadas exclusivamente nos comentários.
     */
    protected function generateContent(
        array $tables,
        array $relations = [],
        ?string $schemaHash = null
    ) {
        $tblDir = $this->config->getOutputPath();

        if (!is_dir($tblDir) && !mkdir($tblDir, 0755, true)) {
            throw new RuntimeException("Cannot create Tbl output directory: {$tblDir}");
        }

        $this->namingResolver->reset();

        $namespace = $this->config->getOutputNamespace();
        $tblFile   = $this->config->getTblFile();
        $header    = $this->generateHeader($schemaHash);

        $content  = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= $header;
        $content .= $this->generateTblClass($tables, $relations);

        file_put_contents($tblFile, $content);
    }

    private function generateHeader(?string $schemaHash): string
    {
        $time   = date('Y-m-d H:i:s');
        $dbName = $this->schema->getDatabaseName();

        return <<<HEADER
/**
 * Database schema mapping for "{$dbName}"
 *
 * This simplified mapping exposes only table and foreign key
 * constants. Column information is documented directly in
 * table-level PHPDoc blocks.
 *
 * @schema-hash md5:{$schemaHash}
 * @generated   {$time}
 *
 * ⚠ AUTO-GENERATED FILE
 * Any manual changes will be lost on regeneration.
 */

HEADER;
    }

    private function generateTblClass(array $tables, array $foreignKeys): string
    {
        $output = "final class Tbl\n{\n";

        // -------------------------------------------------
        // Tables (with column documentation)
        // -------------------------------------------------
        foreach ($tables as $table) {
            $columns = $this->schema->getColumns($table);
            if (empty($columns)) {
                continue;
            }

            $output .= "\n";
            $output .= "    /**\n";
            $output .= "     * table: {$table}\n";
            $output .= "     * columns:\n";

            foreach ($columns as $column) {
                $output .= "     *  - {$column}\n";
            }

            $output .= "     */\n";
            $output .= "    public const {$table} = '{$table}';\n";
        }

        // -------------------------------------------------
        // Foreign Keys (fk_)
        // -------------------------------------------------
        if (!empty($foreignKeys)) {
            $output .= "\n";
            foreach ($foreignKeys as $fk) {
                $fkConst = 'fk_' . $this->namingResolver->getForeignKeyConstName(
                    $fk['from_table'],
                    $fk['to_table']
                );

                $output .= "\n";
                $output .= "    /**\n";
                $output .= "     * FK: {$fk['from_table']} → {$fk['to_table']}.{$fk['to_column']}\n";
                $output .= "     */\n";
                $output .= "    public const {$fkConst} = '{$fk['from_column']}';\n";
            }
        }

        $output .= "}\n";

        return $output;
    }
}
