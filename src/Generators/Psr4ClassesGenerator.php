<?php

namespace Eril\TblClass\Generators;

use Eril\TblClass\Config;
use Eril\TblClass\Resolvers\NamingResolver;
use Eril\TblClass\Schema\SchemaReaderInterface;
use RuntimeException;

class Psr4ClassesGenerator extends Generator
{
    private NamingResolver $namingResolver;
    private array $aliases = [];
    private array $enumConstants = [];

    public function __construct(
        SchemaReaderInterface $schema,
        Config $config,
        bool $checkMode = false
    ) {
        parent::__construct($schema, $config, $checkMode);
        $this->namingResolver = new NamingResolver($config->getNamingProfileConfig());
    }

    /**
     * Gera os arquivos Tbl.php, TblEnum.php e TblFk.php na pasta Tbl do output path
     */
    protected function generateContent(array $tables, array $relations = [], ?string $schemaHash = null)
    {
        // Pasta Tbl dentro do output path
        $tblDir = $this->config->getOutputPath();
        if (!is_dir($tblDir) && !mkdir($tblDir, 0755, true)) {
            throw new RuntimeException("Cannot create Tbl output directory: {$tblDir}");
        }

        $this->namingResolver->reset();
        $this->aliases = [];
        $this->enumConstants = [];

        $namespace = $this->config->getOutputNamespace();
        $tblFile = $this->config->getTblFile();
        $enumFile = $this->config->getOutputPath() . "Tbe.php";
        $tkFile = $this->config->getOutputPath() . "Tbk.php";

        $header = $this->generateHeader($schemaHash);

        // -------------------
        // Tbl.php → tabelas + colunas + FK
        // -------------------
        $tblContent = "<?php\n\nnamespace {$namespace};\n\n" . $header;
        $tblContent .= $this->generateTblClass($tables, $relations);
        file_put_contents($tblFile, $tblContent);

        // -------------------
        // TblEnum.php → enums
        // -------------------
        $enumContent = "<?php\n\nnamespace {$namespace};\n\n" . $header;
        $enumContent .= $this->generateTblEnumClass($tables);
        file_put_contents($enumFile, $enumContent);

        // -------------------
        // TblFk.php → FK
        // -------------------
        $fkContent = "<?php\n\nnamespace {$namespace};\n\n" . $header;
        $fkContent .= $this->generateTblFkClass($relations);
        file_put_contents($tkFile, $fkContent);

        // -------------------
        // TblName.php → Stable full table names
        // -------------------
        $tblNameFile = "<?php\n\nnamespace {$namespace};\n\n" . $header;
        $tblNameFile .= $this->generateTblNameClass($relations);
        file_put_contents($tkFile, $tblNameFile);
    }

    private function generateHeader(?string $schemaHash): string
    {
        $time = date('Y-m-d H:i:s');
        $dbName = $this->schema->getDatabaseName();

        return <<<HEADER
/**
 * Database schema mapping for "{$dbName}"
 *
 * This file is generated from the live database schema and
 * provides a stable, type-safe reference to tables, columns,
 * foreign keys and enum values.
 *
 * @schema-hash md5:{$schemaHash}
 * @generated   {$time}
 * @tool        tbl-class
 *
 * ⚠ AUTO-GENERATED FILE
 * Any manual changes will be lost on regeneration.
 */

HEADER;
    }

    private function generateTblClass(array $tables, array $foreignKeys = []): string
    {
        $output = "final class Tbl\n{\n";

        // -------------------
        // Tabelas + colunas
        // -------------------
        foreach ($tables as $table) {
            $columns = $this->schema->getColumns($table);
            if (empty($columns)) continue;

            $tableConst = $this->namingResolver->getTableConstName($table);

            $output .= "\n    /** table: {$table} */\n";
            $output .= "    public const {$table} = '{$table}';\n";
            // if($table != $tableConst){
            //     $output .= "    public const {$table} = '{$table}';\n";
            // }

            foreach ($columns as $column) {
                $columnConst = $this->namingResolver->getColumnConstName($table, $column);
                $output .= "    /** `{$table}`.`{$column}` */ public const {$columnConst} = '{$column}';\n";
            }
        }

        // -------------------
        // Foreign keys na Tbl (prefixo fk_)
        // -------------------
        foreach ($foreignKeys as $fk) {
            $constName = $this->namingResolver->getForeignKeyConstName($fk['from_table'], $fk['to_table']);
            $fkConstName = $constName;

            $output .= "\n    /** FK: {$fk['from_table']} → {$fk['to_table']}.{$fk['to_column']} */\n";
            $output .= "    public const {$fkConstName} = '{$fk['from_column']}';\n";
        }

        $output .= "}\n";
        return $output;
    }

    private function generateTblFkClass(array $foreignKeys): string
    {
        $output = "final class TblFk\n{\n";

        if (empty($foreignKeys)) {
            $output .= "    // No foreign keys found in the schema\n";
            $output .= "}\n";
            return $output;
        }

        foreach ($foreignKeys as $fk) {
            $fkConst = $this->namingResolver->getForeignKeyConstName(
                $fk['from_table'],
                $fk['to_table']
            );
            $comment = "    /** {$fk['from_table']} → {$fk['to_table']}.{$fk['to_column']} */\n";
            $output .= $comment . "    public const {$fkConst} = '{$fk['from_column']}';\n";
        }

        $output .= "}\n";
        return $output;
    }

    private function generateTblEnumClass(array $tables): string
    {
        $output = "final class TblEnum\n{\n";
        $hasEnums = false;

        foreach ($tables as $table) {
            $enums = $this->schema->getEnumColumns($table);
            $tableConst = $this->namingResolver->getTableConstName($table);
            if (empty($enums)) continue;

            $hasEnums = true;
            foreach ($enums as $column => $values) {
                foreach ($values as $value) {
                    $constantName = $this->namingResolver->getEnumConstName($table, $value);
                    $output .= "    /** ENUM `{$table}`.`{$column}` = '{$value}' */\n";
                    $output .= "    public const {$constantName} = '{$value}';\n";
                }
                $this->enumConstants[] = $constantName;
            }
        }

        if (!$hasEnums) {
            $output .= "    // No enum columns found in the schema\n";
        }

        $output .= "}\n";
        return $output;
    }

    /**
     * Gera uma classe TblName contendo apenas nomes das tabelas (full, strategy-independent)
     */
    private function generateTblNameClass(array $tables): string
    {

        $output = "final class TblName\n{\n";

        foreach ($tables as $table) {
            $output .= "    /** table: {$table} */\n";
            $output .= "    public const {$table} = '{$table}';\n";
        }

        $output .= "}\n";

        return $output;
    }
}
