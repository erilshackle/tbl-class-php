<?php

namespace Eril\TblClass\Introspection;

final class SchemaDiff
{
    public static function compare(array $before, array $after): array
    {
        $old = self::entries($before);
        $new = self::entries($after);
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys, SORT_STRING);
        $changes = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $old)) {
                $changes[] = '+ ' . $key;
            } elseif (!array_key_exists($key, $new)) {
                $changes[] = '- ' . $key;
            } elseif ($old[$key] !== $new[$key]) {
                $changes[] = '~ ' . $key . ': ' . self::display($old[$key]) . ' -> ' . self::display($new[$key]);
            }
        }
        return $changes;
    }

    private static function entries(array $snapshot): array
    {
        $entries = ['database' => $snapshot['database'], 'snapshot.version' => $snapshot['version']];
        foreach ($snapshot['tables'] as $table => $data) {
            $table = self::name((string) $table);
            $entries['table ' . $table] = true;
            foreach ($data['columns'] as $column) {
                $entries[$table . '.' . self::name($column)] = true;
            }
            foreach ($data['enums'] as $column => $values) {
                $entries['enum ' . $table . '.' . self::name((string) $column)] = $values;
            }
        }
        foreach ($snapshot['foreignKeys'] as $fk) {
            $entries['FK ' . self::name($fk['from_table']) . '.' . self::name($fk['from_column'])
                . ' -> ' . self::name($fk['to_table']) . '.' . self::name($fk['to_column'])] = true;
        }
        foreach ($snapshot['generation'] as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $option => $setting) {
                    $entries[$key . '.' . $option] = $setting;
                }
            } else {
                $entries[$key] = $value;
            }
        }
        return $entries;
    }

    private static function name(string $name): string
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name) ? $name : self::display($name);
    }

    private static function display(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
