<?php

namespace Eril\TblClass\Introspection;

final class GeneratedClassMetadata
{
    public static function extractSchemaHash(string $file): ?string
    {
        return self::extractTag($file, 'schema-hash');
    }

    public static function extractFileHash(string $file): ?string
    {
        return self::extractTag($file, 'file-hash');
    }
      

    /**
     * Check whether a generated file was modified after creation.
     *
     * Strategy:
     * 1) Prefer embedded @file-hash comparison
     * 2) Fallback to file timestamps (mtime > ctime)
     */
    public static function isFileModified(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }

        // --- Primary: hash-based integrity check
        $embeddedHash = self::extractFileHash($file);
        if ($embeddedHash) {
            $currentHash = md5(self::getFileContentWithoutMetadata($file));
            return $embeddedHash !== $currentHash;
        }

        // --- Fallback: timestamp-based check
        $mtime = filemtime($file);
        $ctime = filectime($file);

        if ($mtime === false || $ctime === false) {
            // Unable to determine → assume modified
            return true;
        }

        return $mtime > $ctime;
    }

    // ===================== INTERNAL HELPERS =====================

    private static function extractTag(string $file, string $tag): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $fh = fopen($file, 'r');
        if (!$fh) {
            return null;
        }

        for ($i = 0; $i < 40 && ($line = fgets($fh)); $i++) {
            if (preg_match(
                '/@' . preg_quote($tag, '/') . '\s+md5:([a-f0-9]{32})/i',
                $line,
                $m
            )) {
                fclose($fh);
                return $m[1];
            }
        }

        fclose($fh);
        return null;
    }

    /**
     * Remove metadata lines before hashing content
     */
    private static function getFileContentWithoutMetadata(string $file): string
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!$lines) {
            return '';
        }

        $filtered = array_filter($lines, function ($line) {
            return !str_contains($line, '@schema-hash')
                && !str_contains($line, '@file-hash');
        });

        return implode("\n", $filtered);
    }
}
