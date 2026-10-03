<?php

namespace Eril\TblClass\Generators\Traits;

trait JoinHelperTrait
{
    protected function generateJoinHelper(array $foreignKeys): string
    {
        if (empty($foreignKeys)) {
            return $this->generateCallStatic();
        }

        $out = "\n";
        $out .= "    /** JOIN helpers (auto-generated from foreign keys) */\n";

        foreach ($foreignKeys as $fk) {

            $constName = $this->naming->getOnJoinConstName(
                $fk['from_table'],
                $fk['to_table']
            );

            // $constName = "on__{$fkConst}";
            $expr      = "{$fk['from_table']}.{$fk['from_column']} = {$fk['to_table']}.{$fk['to_column']}";

            $out .= $this->php->constant($constName, $expr, "JOIN ON: {$expr}");
        }

        $out .= $this->generateCallStatic();

        return $out;
    }

    private function generateCallStatic(): string
    {
        return <<<PHP

    public static function __callStatic(string \$name, array \$args): string
    {
        if (!defined("self::\$name")) {
            throw new \\BadMethodCallException("Undefined Tbl helper: \$name");
        }
        \$value = constant("self::\$name");
        \$alias = \$args[0] ?? \$args['alias'] ?? null;
        // Table constants use their full normalized names, even with short naming.
        \$tableName = preg_replace('/[^a-zA-Z0-9_\\x80-\\xff]/', '_', \$value);
        if (\$tableName === '' || ctype_digit(\$tableName[0]) || strtolower(\$tableName) === 'class') {
            \$tableName = '_' . \$tableName;
        }
        if (strtolower(\$name) === strtolower(\$tableName)) {
            return \$alias === null || \$alias === '' ? \$value : "\$value AS \$alias";
        }
        if (str_starts_with(strtolower(\$name), 'on__') && defined("self::\$name")) {
            \$e = constant("self::\$name");
            [\$fA, \$tA] = \$args + [null, null];
            if (!\$fA && !\$tA) return \$e;
            [\$left, \$right] = explode('=', \$e, 2);
            [\$fromTable, \$fromCol] = array_map('trim', explode('.', \$left, 2));
            [\$toTable, \$toCol]     = array_map('trim', explode('.', \$right, 2));
            \$fromTable = \$fA ?? \$fromTable;
            \$toTable   = \$tA ?? \$toTable;
            return "{\$fromTable}.{\$fromCol} = {\$toTable}.{\$toCol}";
        }

        return \$alias === null || \$alias === '' ? \$value : "\$alias.\$value";
    }

PHP;
    }

    private static function callStatic(string $name, array $args): string
    {
        // JOIN helper: on__from__to('f','t')
        if (str_starts_with($name, 'on__') && defined("self::" . $name)) {
            $expr = constant("self::" . $name);

            // aliases: [from, to]
            [$fromAlias, $toAlias] = $args + [null, null];

            if (!$fromAlias && !$toAlias) {
                return $expr;
            }

            // replace table names by aliases
            [$from, $to] = explode('__', substr($name, 4), 2);

            if ($fromAlias) {
                $expr = str_replace($from . '.', $fromAlias . '.', $expr);
            }

            if ($toAlias) {
                $expr = str_replace($to . '.', $toAlias . '.', $expr);
            }

            return $expr;
        }

        // TABLE helper: Tbl::users('u') → "users AS u"
        if (defined("self::" . strtoupper($name))) {
            $alias = $args[0] ?? null;
            return $alias ? "$name AS $alias" : $name;
        }

        throw new \BadMethodCallException("Undefined Tbl helper: {\$name}");
    }
}
