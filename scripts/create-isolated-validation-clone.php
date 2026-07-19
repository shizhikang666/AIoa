#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Oa\IsolatedValidationClone;

use RuntimeException;
use Throwable;
use think\facade\Db;
use function Oa\IsolatedValidationParameters\databaseIdentifier as parameterDatabaseIdentifier;
use function Oa\IsolatedValidationParameters\expectedCount;
use function Oa\IsolatedValidationParameters\loopbackHost;
use function Oa\IsolatedValidationParameters\parseNamedOptions;
use function Oa\IsolatedValidationParameters\runDate;
use function Oa\IsolatedValidationParameters\runLabel;

require_once __DIR__ . '/lib/isolated-validation-parameters.php';

/** @return array<string, string> */
function parseOptions(array $argv): array
{
    $options = parseNamedOptions($argv, [
        'source-db',
        'target-db',
        'manifest-dir',
        'database-host',
        'run-label',
        'run-date',
        'expected-table-count',
        'expected-foreign-key-count',
        'confirm-create-target',
        'preflight-only',
    ], [
        'source-db',
        'target-db',
        'manifest-dir',
        'database-host',
        'run-label',
        'run-date',
        'expected-table-count',
        'expected-foreign-key-count',
    ]);
    if (isset($options['preflight-only']) && $options['preflight-only'] !== '1') {
        throw new RuntimeException('clone preflight-only option must be 1 when supplied');
    }
    sourceDatabase($options['source-db']);
    targetDatabase($options['target-db']);
    if (strcasecmp($options['source-db'], $options['target-db']) === 0) {
        throw new RuntimeException('clone target must differ from source database');
    }
    assertLoopbackHost($options['database-host']);
    runLabel($options['run-label']);
    runDate($options['run-date']);
    expectedCount($options['expected-table-count'], 'expected table count', false);
    expectedCount($options['expected-foreign-key-count'], 'expected foreign key count', true);
    if (!isset($options['preflight-only'])
        && (($options['confirm-create-target'] ?? '') === ''
            || !hash_equals($options['target-db'], $options['confirm-create-target']))
    ) {
        throw new RuntimeException('clone target creation requires exact explicit confirmation');
    }

    return $options;
}

function sourceDatabase(string $value): string
{
    return parameterDatabaseIdentifier($value, 'source');
}

function targetDatabase(string $value): string
{
    return parameterDatabaseIdentifier($value, 'target');
}

function quoteIdentifier(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        throw new RuntimeException('clone encountered an unsafe SQL identifier');
    }

    return '`' . $value . '`';
}

/** @param array<string, mixed> $row */
function showCreateTableDdl(array $row, string $table): string
{
    $ddl = '';
    foreach ($row as $column => $value) {
        $normalized = strtolower((string) preg_replace('/[^a-z]/i', '', (string) $column));
        if ($normalized === 'createtable') {
            $ddl = (string) $value;
            break;
        }
    }
    $expectedPrefix = 'CREATE TABLE ' . quoteIdentifier($table);
    if ($ddl === '' || !str_starts_with($ddl, $expectedPrefix)) {
        throw new RuntimeException('clone could not verify SHOW CREATE TABLE output');
    }

    return $ddl;
}

/** @param array<string, mixed> $row */
function targetCreateTableDdl(array $row, string $table, string $target): string
{
    $ddl = showCreateTableDdl($row, $table);
    if (preg_match('/\b(?:DATA|INDEX)\s+DIRECTORY\b|\bTABLESPACE\b/i', $ddl) === 1) {
        throw new RuntimeException('clone refuses external table storage options');
    }
    if (preg_match('/\bREFERENCES\s+`[^`]+`\s*\./i', $ddl) === 1) {
        throw new RuntimeException('clone refuses a schema-qualified foreign key reference');
    }

    $sourcePrefix = 'CREATE TABLE ' . quoteIdentifier($table);
    $targetPrefix = 'CREATE TABLE ' . quoteIdentifier($target) . '.' . quoteIdentifier($table);

    return $targetPrefix . substr($ddl, strlen($sourcePrefix));
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function normalizeForeignKeyDefinitions(array $rows, string $database): array
{
    $normalized = [];
    foreach ($rows as $row) {
        $referencedSchema = (string) ($row['referenced_table_schema'] ?? '');
        $uniqueSchema = (string) ($row['unique_constraint_schema'] ?? '');
        if (strcasecmp($referencedSchema, $database) !== 0 || strcasecmp($uniqueSchema, $database) !== 0) {
            throw new RuntimeException('clone refuses a cross-database foreign key');
        }
        $normalized[] = [
            'table_name' => (string) ($row['table_name'] ?? ''),
            'constraint_name' => (string) ($row['constraint_name'] ?? ''),
            'column_name' => (string) ($row['column_name'] ?? ''),
            'ordinal_position' => (int) ($row['ordinal_position'] ?? 0),
            'position_in_unique_constraint' => (int) ($row['position_in_unique_constraint'] ?? 0),
            'referenced_table_schema' => '<SELF>',
            'referenced_table_name' => (string) ($row['referenced_table_name'] ?? ''),
            'referenced_column_name' => (string) ($row['referenced_column_name'] ?? ''),
            'unique_constraint_schema' => '<SELF>',
            'unique_constraint_name' => (string) ($row['unique_constraint_name'] ?? ''),
            'match_option' => (string) ($row['match_option'] ?? ''),
            'update_rule' => (string) ($row['update_rule'] ?? ''),
            'delete_rule' => (string) ($row['delete_rule'] ?? ''),
        ];
    }
    usort($normalized, static function (array $left, array $right): int {
        foreach (['table_name', 'constraint_name'] as $field) {
            $comparison = strcmp((string) $left[$field], (string) $right[$field]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return ((int) $left['ordinal_position']) <=> ((int) $right['ordinal_position']);
    });

    return $normalized;
}

/** @return array<int, array<string, mixed>> */
function foreignKeyDefinitions(string $database): array
{
    $rows = Db::query(
        'SELECT k.TABLE_NAME AS table_name, k.CONSTRAINT_NAME AS constraint_name, '
        . 'k.COLUMN_NAME AS column_name, k.ORDINAL_POSITION AS ordinal_position, '
        . 'k.POSITION_IN_UNIQUE_CONSTRAINT AS position_in_unique_constraint, '
        . 'k.REFERENCED_TABLE_SCHEMA AS referenced_table_schema, '
        . 'k.REFERENCED_TABLE_NAME AS referenced_table_name, '
        . 'k.REFERENCED_COLUMN_NAME AS referenced_column_name, '
        . 'rc.UNIQUE_CONSTRAINT_SCHEMA AS unique_constraint_schema, '
        . 'rc.UNIQUE_CONSTRAINT_NAME AS unique_constraint_name, rc.MATCH_OPTION AS match_option, '
        . 'rc.UPDATE_RULE AS update_rule, rc.DELETE_RULE AS delete_rule '
        . 'FROM information_schema.KEY_COLUMN_USAGE k '
        . 'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc '
        . 'ON BINARY rc.CONSTRAINT_SCHEMA = BINARY k.CONSTRAINT_SCHEMA '
        . 'AND BINARY rc.TABLE_NAME = BINARY k.TABLE_NAME '
        . 'AND BINARY rc.CONSTRAINT_NAME = BINARY k.CONSTRAINT_NAME '
        . 'WHERE BINARY k.CONSTRAINT_SCHEMA = BINARY ? AND k.REFERENCED_TABLE_NAME IS NOT NULL '
        . 'ORDER BY BINARY k.TABLE_NAME, BINARY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
        [$database]
    );

    return normalizeForeignKeyDefinitions($rows, $database);
}

/** @param array<int, array<string, mixed>> $source @param array<int, array<string, mixed>> $target */
function assertForeignKeyDefinitions(array $source, array $target): void
{
    $encode = static fn (array $rows): string => json_encode(
        $rows,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    if ($encode($source) !== $encode($target)) {
        throw new RuntimeException('clone target foreign key definitions differ from the audited source');
    }
}

function tableChecksum(string $database, string $table): string
{
    $rows = Db::query('CHECKSUM TABLE ' . quoteIdentifier($database) . '.' . quoteIdentifier($table));
    if (count($rows) !== 1) {
        throw new RuntimeException('clone table checksum is unavailable');
    }
    foreach ($rows[0] as $column => $value) {
        if (strtolower((string) preg_replace('/[^a-z]/i', '', (string) $column)) === 'checksum'
            && $value !== null
            && $value !== ''
        ) {
            return (string) $value;
        }
    }

    throw new RuntimeException('clone table checksum is unavailable');
}

/** @return array{schemaSha256:string,tableCount:int,foreignKeyConstraintCount:int,nonTableObjectCount:int} */
function databaseStructureFingerprint(string $database): array
{
    quoteIdentifier($database);
    $schemaRows = Db::query(
        'SELECT DEFAULT_CHARACTER_SET_NAME AS charset_name, DEFAULT_COLLATION_NAME AS collation_name '
        . 'FROM information_schema.SCHEMATA WHERE BINARY SCHEMA_NAME=BINARY ?',
        [$database]
    );
    if (count($schemaRows) !== 1) {
        throw new RuntimeException('clone structure database is missing');
    }
    $tables = Db::query(
        "SELECT TABLE_NAME AS table_name,ENGINE AS engine_name,TABLE_COLLATION AS table_collation "
        . "FROM information_schema.TABLES WHERE BINARY TABLE_SCHEMA=BINARY ? "
        . "AND TABLE_TYPE='BASE TABLE' ORDER BY BINARY TABLE_NAME",
        [$database]
    );
    $tableDefinitions = [];
    foreach ($tables as $table) {
        $name = (string) ($table['table_name'] ?? '');
        $rows = Db::query('SHOW CREATE TABLE ' . quoteIdentifier($database) . '.' . quoteIdentifier($name));
        if (count($rows) !== 1) {
            throw new RuntimeException('clone structure table definition is unavailable');
        }
        $tableDefinitions[$name] = [
            'engine' => (string) ($table['engine_name'] ?? ''),
            'collation' => (string) ($table['table_collation'] ?? ''),
            'ddl' => showCreateTableDdl($rows[0], $name),
        ];
    }
    $nonTableCounts = [];
    foreach ([
        'views' => ['information_schema.VIEWS', 'TABLE_SCHEMA'],
        'triggers' => ['information_schema.TRIGGERS', 'TRIGGER_SCHEMA'],
        'routines' => ['information_schema.ROUTINES', 'ROUTINE_SCHEMA'],
        'events' => ['information_schema.EVENTS', 'EVENT_SCHEMA'],
    ] as $type => [$catalog, $schemaColumn]) {
        $rows = Db::query(
            "SELECT COUNT(*) AS aggregate FROM {$catalog} WHERE BINARY {$schemaColumn}=BINARY ?",
            [$database]
        );
        $nonTableCounts[$type] = (int) ($rows[0]['aggregate'] ?? -1);
    }
    $foreignKeys = foreignKeyDefinitions($database);
    $payload = [
        'charset' => (string) ($schemaRows[0]['charset_name'] ?? ''),
        'collation' => (string) ($schemaRows[0]['collation_name'] ?? ''),
        'tables' => $tableDefinitions,
        'foreignKeys' => $foreignKeys,
        'nonTableObjects' => $nonTableCounts,
    ];

    return [
        'schemaSha256' => databaseStructurePayloadSha256($payload),
        'tableCount' => count($tables),
        'foreignKeyConstraintCount' => count(array_unique(array_map(
            static fn (array $row): string => (string) $row['table_name'] . "\0" . (string) $row['constraint_name'],
            $foreignKeys
        ))),
        'nonTableObjectCount' => array_sum($nonTableCounts),
    ];
}

/** @param array<string, mixed> $payload */
function databaseStructurePayloadSha256(array $payload): string
{
    return hash('sha256', json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ));
}

/** @param null|callable(string):array<int, string>|false $resolver */
function assertLoopbackHost(string $host, ?callable $resolver = null): void
{
    loopbackHost($host, $resolver);
}

function manifestDirectory(string $projectRoot, string $value): string
{
    $runtimeRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'runtime');
    if ($runtimeRoot === false || is_link($runtimeRoot)) {
        throw new RuntimeException('private runtime root is unavailable');
    }

    $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);
    if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/', $candidate)) {
        $candidate = $projectRoot . DIRECTORY_SEPARATOR . $candidate;
    }
    $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
    $parent = realpath(dirname($candidate));
    if ($parent === false || is_link($parent)) {
        throw new RuntimeException('clone manifest parent is unavailable');
    }
    $runtimePrefix = rtrim(strtolower($runtimeRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $parentPath = rtrim(strtolower($parent), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($parentPath, $runtimePrefix)) {
        throw new RuntimeException('clone manifest directory must stay below runtime');
    }
    if (file_exists($candidate) || is_link($candidate)) {
        throw new RuntimeException('clone manifest directory already exists');
    }

    return $candidate;
}

/** @param array<string, mixed> $payload */
function writeJson(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('clone could not write its private evidence');
    }
}

function run(array $argv): int
{
    $projectRoot = dirname(__DIR__);
    $manifest = '';
    $target = '';
    $foreignKeyChecksDisabled = false;

    try {
        $options = parseOptions($argv);
        $source = sourceDatabase($options['source-db']);
        $target = targetDatabase($options['target-db']);
        $preflightOnly = ($options['preflight-only'] ?? '') === '1';
        $databaseHost = $options['database-host'];
        $validationRunLabel = runLabel($options['run-label']);
        $validationRunDate = runDate($options['run-date']);
        $expectedTableCount = expectedCount($options['expected-table-count'], 'expected table count', false);
        $expectedForeignKeyCount = expectedCount(
            $options['expected-foreign-key-count'],
            'expected foreign key count',
            true
        );
        $manifest = manifestDirectory($projectRoot, $options['manifest-dir']);
        if (!mkdir($manifest, 0700) && !is_dir($manifest)) {
            throw new RuntimeException('clone could not create its private manifest directory');
        }

        $loader = require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!$loader instanceof \Composer\Autoload\ClassLoader) {
            throw new RuntimeException('composer autoloader is unavailable');
        }
        $loader->setPsr4('app\\', [$projectRoot . DIRECTORY_SEPARATOR . 'app']);
        (new \think\App($projectRoot))->initialize();

        $connection = (array) config('database.connections.mysql', []);
        if (loopbackHost((string) ($connection['hostname'] ?? '')) !== loopbackHost($databaseHost)) {
            throw new RuntimeException('clone database host differs from the explicit invocation');
        }

        $sourceMeta = Db::query(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset_name, DEFAULT_COLLATION_NAME AS collation_name '
            . 'FROM information_schema.SCHEMATA WHERE BINARY SCHEMA_NAME = BINARY ?',
            [$source]
        );
        if (count($sourceMeta) !== 1) {
            throw new RuntimeException('clone source database is missing');
        }
        $targetCount = Db::query(
            'SELECT COUNT(*) AS aggregate FROM information_schema.SCHEMATA WHERE BINARY SCHEMA_NAME = BINARY ?',
            [$target]
        );
        if ((int) ($targetCount[0]['aggregate'] ?? -1) !== 0) {
            throw new RuntimeException('clone target database already exists');
        }
        foreach ([
            ['information_schema.VIEWS', 'TABLE_SCHEMA'],
            ['information_schema.TRIGGERS', 'TRIGGER_SCHEMA'],
            ['information_schema.ROUTINES', 'ROUTINE_SCHEMA'],
            ['information_schema.EVENTS', 'EVENT_SCHEMA'],
        ] as [$catalog, $schemaColumn]) {
            $objects = Db::query(
                "SELECT COUNT(*) AS aggregate FROM {$catalog} WHERE BINARY {$schemaColumn}=BINARY ?",
                [$source]
            );
            if ((int) ($objects[0]['aggregate'] ?? -1) !== 0) {
                throw new RuntimeException('clone refuses an unhandled non-table database object');
            }
        }

        $charset = (string) ($sourceMeta[0]['charset_name'] ?? '');
        $collation = (string) ($sourceMeta[0]['collation_name'] ?? '');
        quoteIdentifier($charset);
        quoteIdentifier($collation);

        $tables = Db::query(
            "SELECT TABLE_NAME AS table_name, ENGINE AS engine_name FROM information_schema.TABLES "
            . "WHERE BINARY TABLE_SCHEMA = BINARY ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY BINARY TABLE_NAME",
            [$source]
        );
        if (count($tables) !== $expectedTableCount) {
            throw new RuntimeException('clone source table count differs from the audited PHP template');
        }
        foreach ($tables as $table) {
            if (strcasecmp((string) ($table['engine_name'] ?? ''), 'InnoDB') !== 0) {
                throw new RuntimeException('clone refuses a non-InnoDB source table');
            }
            quoteIdentifier((string) ($table['table_name'] ?? ''));
        }
        $sourceForeignKeyConstraints = Db::query(
            'SELECT COUNT(*) AS aggregate FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE BINARY CONSTRAINT_SCHEMA = BINARY ?',
            [$source]
        );
        $sourceForeignKeyConstraintCount = (int) ($sourceForeignKeyConstraints[0]['aggregate'] ?? -1);
        if ($sourceForeignKeyConstraintCount !== $expectedForeignKeyCount) {
            throw new RuntimeException('clone source foreign key count differs from the audited PHP template');
        }
        $sourceForeignKeys = foreignKeyDefinitions($source);
        $createTableDdls = [];
        foreach ($tables as $table) {
            $name = (string) $table['table_name'];
            $sourceTable = quoteIdentifier($source) . '.' . quoteIdentifier($name);
            $showCreate = Db::query("SHOW CREATE TABLE {$sourceTable}");
            if (count($showCreate) !== 1) {
                throw new RuntimeException('clone could not read source table structure');
            }
            $createTableDdls[$name] = targetCreateTableDdl($showCreate[0], $name, $target);
        }
        $sourceStructureBefore = databaseStructureFingerprint($source);
        if ($sourceStructureBefore['tableCount'] !== $expectedTableCount
            || $sourceStructureBefore['foreignKeyConstraintCount'] !== $expectedForeignKeyCount
            || $sourceStructureBefore['nonTableObjectCount'] !== 0
        ) {
            throw new RuntimeException('clone source structure fingerprint is outside the audited boundary');
        }
        if ($preflightOnly) {
            writeJson($manifest . DIRECTORY_SEPARATOR . 'preflight-completed.json', [
                'status' => 'completed',
                'mode' => 'preflight-only',
                'sourceDatabase' => $source,
                'reservedTargetDatabase' => $target,
                'targetDatabaseCreated' => false,
                'runLabel' => $validationRunLabel,
                'runDate' => $validationRunDate,
                'databaseHost' => loopbackHost($databaseHost),
                'expectedTableCount' => $expectedTableCount,
                'expectedForeignKeyConstraintCount' => $expectedForeignKeyCount,
                'tableCount' => $expectedTableCount,
                'foreignKeyConstraintCount' => $sourceForeignKeyConstraintCount,
                'foreignKeyDefinitionsRead' => count($sourceForeignKeys),
                'tableDdlValidated' => true,
                'nonTableObjectsAbsent' => true,
                'structureHashAlgorithm' => 'show-create-structure-v1',
                'schemaSha256' => $sourceStructureBefore['schemaSha256'],
                'sourceWritesPerformed' => false,
                'completedAt' => gmdate(DATE_ATOM),
            ]);
            fwrite(STDOUT, json_encode([
                'status' => 'completed',
                'mode' => 'preflight-only',
                'runLabel' => $validationRunLabel,
                'runDate' => $validationRunDate,
                'tableCount' => $expectedTableCount,
                'foreignKeyConstraintCount' => $sourceForeignKeyConstraintCount,
                'tableDdlValidated' => true,
                'nonTableObjectsAbsent' => true,
                'structureHashAlgorithm' => 'show-create-structure-v1',
                'schemaSha256' => $sourceStructureBefore['schemaSha256'],
                'sourceWritesPerformed' => false,
                'targetDatabaseCreated' => false,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

            return 0;
        }

        $sourceBaselineRows = [];
        $sourceBaselineChecksums = [];
        foreach ($tables as $table) {
            $name = (string) $table['table_name'];
            $sourceTable = quoteIdentifier($source) . '.' . quoteIdentifier($name);
            $sourceRows = Db::query("SELECT COUNT(*) AS aggregate FROM {$sourceTable}");
            $sourceBaselineRows[$name] = (int) ($sourceRows[0]['aggregate'] ?? -1);
            $sourceBaselineChecksums[$name] = tableChecksum($source, $name);
        }

        Db::execute(
            'CREATE DATABASE ' . quoteIdentifier($target)
            . ' CHARACTER SET ' . quoteIdentifier($charset)
            . ' COLLATE ' . quoteIdentifier($collation)
        );
        Db::execute('SET FOREIGN_KEY_CHECKS = 0');
        $foreignKeyChecksDisabled = true;

        $rowCounts = [];
        $tableChecksums = [];
        foreach ($tables as $table) {
            $name = (string) $table['table_name'];
            $sourceTable = quoteIdentifier($source) . '.' . quoteIdentifier($name);
            $targetTable = quoteIdentifier($target) . '.' . quoteIdentifier($name);
            $sourceChecksum = $sourceBaselineChecksums[$name];
            Db::execute($createTableDdls[$name]);
            Db::execute("INSERT INTO {$targetTable} SELECT * FROM {$sourceTable}");
            $targetRows = Db::query("SELECT COUNT(*) AS aggregate FROM {$targetTable}");
            $expected = $sourceBaselineRows[$name];
            $actual = (int) ($targetRows[0]['aggregate'] ?? -2);
            if ($expected !== $actual) {
                throw new RuntimeException("clone row count mismatch for {$name}");
            }
            $targetChecksum = tableChecksum($target, $name);
            if (!hash_equals($sourceChecksum, $targetChecksum)) {
                throw new RuntimeException("clone content checksum mismatch for {$name}");
            }
            $rowCounts[$name] = $actual;
            $tableChecksums[$name] = $sourceChecksum;
        }

        Db::execute('SET FOREIGN_KEY_CHECKS = 1');
        $foreignKeyChecksDisabled = false;
        $finalTables = Db::query(
            "SELECT COUNT(*) AS aggregate FROM information_schema.TABLES "
            . "WHERE BINARY TABLE_SCHEMA = BINARY ? AND TABLE_TYPE = 'BASE TABLE'",
            [$target]
        );
        if ((int) ($finalTables[0]['aggregate'] ?? -1) !== $expectedTableCount) {
            throw new RuntimeException('clone target table count verification failed');
        }
        $targetForeignKeyConstraints = Db::query(
            'SELECT COUNT(*) AS aggregate FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE BINARY CONSTRAINT_SCHEMA = BINARY ?',
            [$target]
        );
        if ((int) ($targetForeignKeyConstraints[0]['aggregate'] ?? -1) !== $sourceForeignKeyConstraintCount) {
            throw new RuntimeException('clone target foreign key count verification failed');
        }
        assertForeignKeyDefinitions($sourceForeignKeys, foreignKeyDefinitions($target));
        foreach ($tables as $table) {
            $name = (string) $table['table_name'];
            $sourceTable = quoteIdentifier($source) . '.' . quoteIdentifier($name);
            $sourceRows = Db::query("SELECT COUNT(*) AS aggregate FROM {$sourceTable}");
            if ((int) ($sourceRows[0]['aggregate'] ?? -1) !== $sourceBaselineRows[$name]
                || !hash_equals($sourceBaselineChecksums[$name], tableChecksum($source, $name))
            ) {
                throw new RuntimeException('clone source changed during the consistency window');
            }
        }
        $sourceStructureAfter = databaseStructureFingerprint($source);
        $targetStructure = databaseStructureFingerprint($target);
        if ($sourceStructureAfter !== $sourceStructureBefore || $targetStructure !== $sourceStructureBefore) {
            throw new RuntimeException('clone structure changed during the consistency window');
        }

        writeJson($manifest . DIRECTORY_SEPARATOR . 'clone-completed.json', [
            'status' => 'completed',
            'sourceDatabase' => $source,
            'targetDatabase' => $target,
            'runLabel' => $validationRunLabel,
            'runDate' => $validationRunDate,
            'databaseHost' => loopbackHost($databaseHost),
            'expectedTableCount' => $expectedTableCount,
            'expectedForeignKeyConstraintCount' => $expectedForeignKeyCount,
            'tableCount' => $expectedTableCount,
            'foreignKeyConstraintCount' => $sourceForeignKeyConstraintCount,
            'foreignKeyDefinitionsMatch' => true,
            'contentChecksumsMatch' => true,
            'nonTableObjectsAbsent' => true,
            'structureHashAlgorithm' => 'show-create-structure-v1',
            'schemaSha256' => $sourceStructureBefore['schemaSha256'],
            'rowCounts' => $rowCounts,
            'tableChecksums' => $tableChecksums,
            'sourceConsistencyWindowPassed' => true,
            'sourceWritesPerformed' => false,
            'completedAt' => gmdate(DATE_ATOM),
        ]);
        fwrite(STDOUT, json_encode([
            'status' => 'completed',
            'runLabel' => $validationRunLabel,
            'runDate' => $validationRunDate,
            'tableCount' => $expectedTableCount,
            'foreignKeyConstraintCount' => $sourceForeignKeyConstraintCount,
            'foreignKeyDefinitionsMatch' => true,
            'contentChecksumsMatch' => true,
            'sourceConsistencyWindowPassed' => true,
            'nonTableObjectsAbsent' => true,
            'sourceWritesPerformed' => false,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

        return 0;
    } catch (Throwable $exception) {
        if ($foreignKeyChecksDisabled) {
            try {
                Db::execute('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Throwable) {
            }
        }
        if ($manifest !== '' && is_dir($manifest)) {
            try {
                writeJson($manifest . DIRECTORY_SEPARATOR . 'failed.json', [
                    'status' => 'failed',
                    'stage' => 'isolated-validation-clone',
                    'targetDatabase' => $target,
                    'targetMustNotBeReused' => true,
                    'message' => $exception->getMessage(),
                    'failedAt' => gmdate(DATE_ATOM),
                ]);
            } catch (Throwable) {
            }
        }
        fwrite(STDERR, 'isolated validation clone failed: ' . $exception->getMessage() . PHP_EOL);

        return 1;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(run($argv));
}
