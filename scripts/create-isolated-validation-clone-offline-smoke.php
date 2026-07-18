#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/create-isolated-validation-clone.php';

use function Oa\IsolatedValidationClone\assertLoopbackHost;
use function Oa\IsolatedValidationClone\assertForeignKeyDefinitions;
use function Oa\IsolatedValidationClone\normalizeForeignKeyDefinitions;
use function Oa\IsolatedValidationClone\parseOptions;
use function Oa\IsolatedValidationClone\quoteIdentifier;
use function Oa\IsolatedValidationClone\showCreateTableDdl;
use function Oa\IsolatedValidationClone\sourceDatabase;
use function Oa\IsolatedValidationClone\targetCreateTableDdl;
use function Oa\IsolatedValidationClone\targetDatabase;

function clone_smoke_expect_failure(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('expected validation clone policy failure was not raised');
}

$options = parseOptions([
    'clone',
    '--source-db=oa2026_rehearsal_r6_20260718_r10_migrated',
    '--target-db=oa2026_r10_validation_20260718_abcdef01',
    '--manifest-dir=runtime/isolated-r10-validation-20260718_abcdef01',
]);
if (count($options) !== 3) {
    throw new RuntimeException('clone option parser lost a required option');
}
$preflightOptions = parseOptions([
    'clone',
    '--source-db=oa2026_rehearsal_r6_20260718_r10_migrated',
    '--target-db=oa2026_r10_validation_20260718_abcdef02',
    '--manifest-dir=runtime/isolated-r10-validation-20260718_abcdef02',
    '--preflight-only=1',
]);
if (($preflightOptions['preflight-only'] ?? '') !== '1') {
    throw new RuntimeException('clone option parser lost preflight-only mode');
}

sourceDatabase($options['source-db']);
targetDatabase($options['target-db']);
assertLoopbackHost('127.0.0.1');
assertLoopbackHost('localhost');
assertLoopbackHost('::1');
assertLoopbackHost('local-db', static fn (string $host): array => $host === 'local-db' ? ['127.0.0.2'] : []);
if (quoteIdentifier('act_ru_task') !== '`act_ru_task`') {
    throw new RuntimeException('safe SQL identifier quoting changed');
}
$ddl = "CREATE TABLE `act_ru_task` (`ID_` varchar(64) NOT NULL COMMENT 'act_ru_task', `note` varchar(64) DEFAULT 'act_ru_task') ENGINE=InnoDB";
if (showCreateTableDdl(['Table' => 'act_ru_task', 'Create Table' => $ddl], 'act_ru_task') !== $ddl) {
    throw new RuntimeException('SHOW CREATE TABLE extraction changed');
}
$targetDdl = targetCreateTableDdl(
    ['Table' => 'act_ru_task', 'Create Table' => $ddl],
    'act_ru_task',
    'oa2026_r10_validation_20260718_abcdef01'
);
if (!str_starts_with($targetDdl, 'CREATE TABLE `oa2026_r10_validation_20260718_abcdef01`.`act_ru_task`')
    || substr_count($targetDdl, 'act_ru_task') !== 3
) {
    throw new RuntimeException('target DDL did not replace only the CREATE TABLE prefix');
}
$foreignKeyRows = [[
    'table_name' => 'child',
    'constraint_name' => 'fk_child_parent',
    'column_name' => 'parent_id',
    'ordinal_position' => 1,
    'position_in_unique_constraint' => 1,
    'referenced_table_schema' => 'source_db',
    'referenced_table_name' => 'parent',
    'referenced_column_name' => 'id',
    'unique_constraint_schema' => 'source_db',
    'unique_constraint_name' => 'PRIMARY',
    'match_option' => 'NONE',
    'update_rule' => 'RESTRICT',
    'delete_rule' => 'RESTRICT',
], [
    'table_name' => 'child',
    'constraint_name' => 'fk_child_parent',
    'column_name' => 'parent_type',
    'ordinal_position' => 2,
    'position_in_unique_constraint' => 2,
    'referenced_table_schema' => 'source_db',
    'referenced_table_name' => 'parent',
    'referenced_column_name' => 'type',
    'unique_constraint_schema' => 'source_db',
    'unique_constraint_name' => 'uk_parent_id_type',
    'match_option' => 'NONE',
    'update_rule' => 'CASCADE',
    'delete_rule' => 'RESTRICT',
]];
$foreignKeys = normalizeForeignKeyDefinitions($foreignKeyRows, 'source_db');
$reversedForeignKeys = normalizeForeignKeyDefinitions(array_reverse($foreignKeyRows), 'source_db');
assertForeignKeyDefinitions($foreignKeys, $reversedForeignKeys);

clone_smoke_expect_failure(static fn () => parseOptions(['clone', '--source-db=x']));
clone_smoke_expect_failure(static fn () => parseOptions([
    'clone',
    '--source-db=oa2026_rehearsal_r6_20260718_r10_migrated',
    '--target-db=oa2026_r10_validation_20260718_abcdef01',
    '--manifest-dir=runtime/x',
    '--preflight-only=0',
]));
clone_smoke_expect_failure(static fn () => parseOptions([
    'clone',
    '--source-db=oa2026_rehearsal_r6_20260718_r10_migrated',
    '--target-db=oa2026_r10_validation_20260718_abcdef01',
    '--manifest-dir=runtime/x',
    '--unknown=1',
]));
clone_smoke_expect_failure(static fn () => sourceDatabase('oa2026'));
clone_smoke_expect_failure(static fn () => sourceDatabase('oa2026_rehearsal_r10_migrated;DROP'));
clone_smoke_expect_failure(static fn () => targetDatabase('oa2026_r10_validation_latest'));
clone_smoke_expect_failure(static fn () => targetDatabase('oa2026_r10_validation_20260718_abcdef01_extra'));
clone_smoke_expect_failure(static fn () => assertLoopbackHost('120.24.76.240'));
clone_smoke_expect_failure(static fn () => assertLoopbackHost(
    'remote-db',
    static fn (string $host): array => $host === 'remote-db' ? ['192.168.1.10'] : []
));
clone_smoke_expect_failure(static fn () => assertLoopbackHost(
    'mixed-db',
    static fn (string $host): array => $host === 'mixed-db' ? ['127.0.0.1', '192.168.1.10'] : []
));
clone_smoke_expect_failure(static fn () => assertLoopbackHost('missing-db', static fn (): array => []));
clone_smoke_expect_failure(static fn () => quoteIdentifier('act_ru_task;DROP'));
clone_smoke_expect_failure(static fn () => showCreateTableDdl(
    ['Create Table' => 'CREATE TABLE `other` (`id` int)'],
    'act_ru_task'
));
clone_smoke_expect_failure(static fn () => targetCreateTableDdl(
    ['Create Table' => 'CREATE TABLE `act_ru_task` (`id` int) DATA DIRECTORY="C:/outside"'],
    'act_ru_task',
    'oa2026_r10_validation_20260718_abcdef01'
));
clone_smoke_expect_failure(static fn () => targetCreateTableDdl(
    ['Create Table' => 'CREATE TABLE `act_ru_task` (`id` int, FOREIGN KEY (`id`) REFERENCES `source_db`.`parent` (`id`))'],
    'act_ru_task',
    'oa2026_r10_validation_20260718_abcdef01'
));
clone_smoke_expect_failure(static fn () => normalizeForeignKeyDefinitions(
    [array_merge($foreignKeyRows[0], ['referenced_table_schema' => 'other_db'])],
    'source_db'
));
clone_smoke_expect_failure(static fn () => assertForeignKeyDefinitions(
    $foreignKeys,
    [array_merge($foreignKeys[0], ['delete_rule' => 'CASCADE'])]
));

echo "isolated validation clone offline smoke passed\n";
