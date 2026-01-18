<?php

namespace Eril\TblClass\Introspection;

final class SchemaHasher
{
    public static function hash(array $schemaData): string
    {
        $schemaData = self::normalize($schemaData);
        return md5(json_encode($schemaData, JSON_UNESCAPED_UNICODE));
    }

    public static function fromSchema(array ...$parts): string
    {
        $data = self::normalize($parts);
        return md5(json_encode($data, JSON_UNESCAPED_UNICODE));
    }


    private static function normalize(array $data): array
    {
        ksort($data);

        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = self::normalize($value);
            }
        }
        return $data;
    }
}
