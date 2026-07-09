<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Closure;

/**
 * Ready-made anonymization helpers on top of the transform hooks.
 *
 * columnMap() builds a row-transform hook from a per-table column map, and the
 * value helpers (fixed, mask, hash, email) cover the common GDPR-sanitization
 * cases without pulling in a faker-style dependency. All value helpers keep
 * NULL values as NULL so nullability semantics survive anonymization.
 *
 * hash() and email() are deterministic (same input, same output), so
 * uniqueness constraints and joins on the anonymized values keep working.
 * Deterministic output of low-entropy data (names, phone numbers) can be
 * re-identified by hashing candidate values — pass a secret salt to prevent
 * that.
 */
final class Anonymizer
{
    /**
     * Build a hook for Mysqldump::setTransformTableRowHook() from a map of
     * table => column => value transformer.
     *
     * Tables and columns not present in the map pass through untouched, as do
     * mapped columns that do not exist in a row. Each transformer is called as
     * function(mixed $value, array $row): mixed with the untransformed row.
     *
     * @param array<string, array<string, callable>> $map table => column => transformer
     */
    public static function columnMap(array $map): Closure
    {
        return function (string $tableName, array $row) use ($map): array {
            foreach ($map[$tableName] ?? [] as $column => $transform) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $transform($row[$column], $row);
                }
            }

            return $row;
        };
    }

    /**
     * Replace every non-NULL value with a constant.
     */
    public static function fixed(mixed $value): Closure
    {
        return fn (mixed $current): mixed => $current === null ? null : $value;
    }

    /**
     * Mask a string keeping its length and optionally a leading prefix,
     * e.g. mask(2) turns "0401234567" into "04********".
     */
    public static function mask(int $keepPrefix = 0, string $maskChar = '*'): Closure
    {
        return function (mixed $current) use ($keepPrefix, $maskChar): mixed {
            if ($current === null) {
                return null;
            }

            $value = (string) $current;
            // ext-mbstring is not a dependency; split UTF-8 with PCRE and
            // fall back to bytes for non-UTF-8 data
            $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

            if ($chars === false) {
                $chars = str_split($value);
            }

            $prefix = implode('', array_slice($chars, 0, max(0, $keepPrefix)));

            return $prefix . str_repeat($maskChar, max(0, count($chars) - $keepPrefix));
        };
    }

    /**
     * Replace a value with a deterministic SHA-256 hex digest, preserving
     * uniqueness and joins across tables. Pass a secret salt when the input
     * values are guessable.
     */
    public static function hash(string $salt = ''): Closure
    {
        return fn (mixed $current): mixed => $current === null
            ? null
            : hash('sha256', $salt . (string) $current);
    }

    /**
     * Replace a value with a deterministic, syntactically valid email address
     * such as "user-1a2b3c4d5e6f@example.com". Uniqueness is preserved, so
     * unique indexes on email columns keep working.
     */
    public static function email(string $domain = 'example.com', string $salt = ''): Closure
    {
        return fn (mixed $current): mixed => $current === null
            ? null
            : sprintf('user-%s@%s', substr(hash('sha256', $salt . (string) $current), 0, 12), $domain);
    }
}
