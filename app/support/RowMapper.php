<?php

declare(strict_types=1);

namespace app\support;

/**
 * Canonical database-row shape helper.
 *
 * The copied Vue frontend expects camelCase keys (`createTime`, `orgId`),
 * while the database returns UPPER_SNAKE columns (`CREATE_TIME`, `ORG_ID`).
 * Mismatched shapes are the single most recurring class of bug in this
 * refactor (see MT-001, MT-002, P-022 in docs/tasks). Use this one helper
 * instead of hand-rolling a per-service `camelKey()` copy so the mapping
 * cannot drift between services.
 *
 * Semantics are identical to the historical per-service `camelKey()`:
 * lowercase the key, then turn `_x` into `X`. `CREATE_TIME` => `createTime`.
 */
final class RowMapper
{
    /**
     * Convert a single UPPER_SNAKE / snake_case key to camelCase.
     */
    public static function camelKey(string $key): string
    {
        $key = strtolower($key);

        return preg_replace_callback(
            '/_([a-z0-9])/',
            static fn (array $matches): string => strtoupper($matches[1]),
            $key
        ) ?? $key;
    }

    /**
     * Convert every key of one row to camelCase, preserving values.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function toCamel(array $row): array
    {
        $mapped = [];
        foreach ($row as $key => $value) {
            $mapped[self::camelKey((string)$key)] = $value;
        }

        return $mapped;
    }

    /**
     * Convert a list of rows to camelCase keys.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function toCamelList(array $rows): array
    {
        return array_map(static fn (array $row): array => self::toCamel($row), $rows);
    }
}
