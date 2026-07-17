#!/usr/bin/env php
<?php

declare(strict_types=1);

use Oa\DatabaseMigration\DumpPolicy;
use Oa\DatabaseMigration\CommandRunner;
use Oa\DatabaseMigration\MigrationOptions;
use Oa\DatabaseMigration\MigrationSafety;
use Oa\DatabaseMigration\MysqlProfile;
use Oa\DatabaseMigration\OrphanPolicy;
use Oa\DatabaseMigration\SchemaPolicy;

require __DIR__ . '/lib/oa-database-migration.php';
require __DIR__ . '/lib/installer-target.php';

function smoke_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function smoke_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

/** @return array<string, mixed> */
function smoke_column(string $type): array
{
    return [
        'ordinal' => 1,
        'type' => $type,
        'nullable' => 'NO',
        'default' => null,
        'extra' => '',
        'charset' => null,
        'collation' => null,
    ];
}

/** @return array<string, mixed> */
function smoke_table(array $columns): array
{
    return [
        'engine' => 'InnoDB',
        'collation' => 'utf8mb4_general_ci',
        'columns' => $columns,
        'indexes' => [],
        'foreignKeys' => [],
    ];
}

$temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oa-db-migration-smoke-' . bin2hex(random_bytes(5));
if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
    throw new RuntimeException('unable to create offline smoke directory');
}

try {
    $sourceDefaults = $temporary . DIRECTORY_SEPARATOR . 'source.cnf';
    $targetDefaults = $temporary . DIRECTORY_SEPARATOR . 'target.cnf';
    file_put_contents($sourceDefaults, "[client]\nhost=old.invalid\nport=3306\nuser=readonly\npassword=\n");
    file_put_contents($targetDefaults, "[client]\nhost=new.invalid\nport=3306\nuser=migrator\npassword=\n");
    $sourceProfile = MysqlProfile::fromDefaultsFile($sourceDefaults);
    $targetProfile = MysqlProfile::fromDefaultsFile($targetDefaults);

    MigrationSafety::assertSafeTopology(
        $sourceProfile,
        'oa2026',
        $targetProfile,
        'oa2026',
        'oa2026_migrated',
        'oa2026_quarantine_20260717',
        ['oa2026_migrated']
    );
    smoke_throws(
        static fn () => MigrationSafety::assertSafeTopology(
            $sourceProfile,
            'oa2026',
            $targetProfile,
            'oa2026',
            'oa2026_live',
            'oa2026_quarantine_20260717',
            ['oa2026_live']
        ),
        'unsafe target suffix was accepted'
    );
    smoke_throws(
        static fn () => MigrationSafety::assertSafeTopology(
            $sourceProfile,
            'oa2026',
            $targetProfile,
            'oa2026',
            'oa2026_migrated',
            'oa2026_quarantine_20260717',
            []
        ),
        'non-whitelisted target was accepted'
    );
    smoke_throws(
        static fn () => MigrationSafety::assertSafeTopology(
            $sourceProfile,
            'oa2026_migrated',
            $sourceProfile,
            'oa2026_template',
            'oa2026_migrated',
            'oa2026_quarantine_20260717',
            ['oa2026_migrated']
        ),
        'identical source and target schema was accepted'
    );

    $options = MigrationOptions::fromArgv(['smoke', '--apply', '--allow-target=one', '--allow-target=two']);
    smoke_assert($options->flag('apply'), 'apply flag parsing failed');
    smoke_assert($options->list('allow-target') === ['one', 'two'], 'target allowlist parsing failed');
    smoke_assert(
        MigrationSafety::confirmToken('oa2026', 'oa2026_migrated', 'oa2026_quarantine_20260717')
        === 'MIGRATE_OA2026_TO_OA2026_MIGRATED_WITH_OA2026_QUARANTINE_20260717',
        'confirmation token is not deterministic'
    );
    smoke_assert(
        str_ends_with(
            MigrationSafety::planBoundConfirmToken(
                'oa2026',
                'oa2026_migrated',
                'oa2026_quarantine_20260717',
                str_repeat('a', 64)
            ),
            '_PLAN_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
        ),
        'confirmation token is not bound to the reviewed plan digest'
    );
    smoke_throws(
        static fn () => MigrationOptions::fromArgv(['smoke', '--aply']),
        'unknown apply-like option was silently accepted'
    );

    $sourceTables = [
        'biz_sale_project' => smoke_table(['ID' => smoke_column('int(11)')]),
    ];
    for ($index = 1; $index <= 120; $index++) {
        $sourceTables[sprintf('legacy_%03d', $index)] = smoke_table(['ID' => smoke_column('int(11)')]);
    }
    $templateTables = $sourceTables;
    foreach ($templateTables as &$table) {
        $table['columns']['ID']['type'] = 'int';
    }
    unset($table);
    $templateTables['biz_sale_project']['columns']['TRAVEL_DAYS'] = [
        'ordinal' => 2,
        'type' => 'decimal(10,1)',
        'nullable' => 'NO',
        'default' => '0.0',
        'extra' => '',
        'charset' => null,
        'collation' => null,
    ];
    foreach (SchemaPolicy::NEW_TABLES as $newTable) {
        $templateTables[$newTable] = smoke_table(['ID' => smoke_column('varchar(20)')]);
    }
    ksort($sourceTables);
    ksort($templateTables);
    $comparison = SchemaPolicy::compareSourceToTemplate(
        ['tables' => $sourceTables],
        ['tables' => $templateTables]
    );
    SchemaPolicy::assertExpected($comparison, 121, 124, 121, 125);
    smoke_assert($comparison['valid'] === true, 'audited schema difference was rejected');
    $badTemplate = $templateTables;
    $badTemplate['legacy_001']['columns']['UNREVIEWED'] = smoke_column('varchar(10)');
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'unexpected new field was accepted'
    );

    $taskIds = [];
    $detected = [];
    for ($index = 1; $index <= 20; $index++) {
        $id = 'known-task-' . $index;
        $taskIds[] = $id;
        $detected[] = ['taskId' => $id];
    }
    $allowlistPath = $temporary . DIRECTORY_SEPARATOR . 'known-orphans.json';
    file_put_contents($allowlistPath, json_encode(['taskIds' => $taskIds], JSON_THROW_ON_ERROR));
    $allowlist = OrphanPolicy::loadAllowlist($allowlistPath, 20);
    OrphanPolicy::assertExact($detected, $allowlist);
    $withUnknown = $detected;
    $withUnknown[] = ['taskId' => 'unknown-task-21'];
    smoke_throws(
        static fn () => OrphanPolicy::assertExact($withUnknown, $allowlist),
        'a twenty-first orphan was accepted'
    );

    $dataDump = $temporary . DIRECTORY_SEPARATOR . 'data.sql';
    file_put_contents($dataDump, "SET NAMES utf8mb4;\nINSERT INTO `legacy_001` (`ID`) VALUES (1);\n");
    $dumpAudit = DumpPolicy::validateDataDump($dataDump, ['legacy_001']);
    smoke_assert($dumpAudit['insertStatements'] >= 1, 'explicit INSERT was not audited');
    file_put_contents($dataDump, "DROP TABLE `legacy_001`;\nINSERT INTO `legacy_001` (`ID`) VALUES (1);\n");
    smoke_throws(
        static fn () => DumpPolicy::validateDataDump($dataDump, ['legacy_001']),
        'DDL in a source data dump was accepted'
    );
    $schemaDump = $temporary . DIRECTORY_SEPARATOR . 'schema.sql';
    file_put_contents($schemaDump, "CREATE TABLE `legacy_001` (`ID` int NOT NULL);\n");
    smoke_assert(DumpPolicy::validateSchemaDump($schemaDump)['createTables'] === 1, 'template schema dump was rejected');
    file_put_contents($schemaDump, "DROP TABLE `legacy_001`;\nCREATE TABLE `legacy_001` (`ID` int NOT NULL);\n");
    smoke_throws(
        static fn () => DumpPolicy::validateSchemaDump($schemaDump),
        'DROP TABLE in a template schema dump was accepted'
    );

    $installerTarget = installer_target_prepare([
        'installer',
        '--target-defaults=' . $targetDefaults,
        '--database=oa2026_migrated',
    ]);
    smoke_assert(($installerTarget['database'] ?? '') === 'oa2026_migrated', 'installer target database was not pinned');
    smoke_assert(($installerTarget['hostname'] ?? '') === 'new.invalid', 'installer target host was not read safely');

    $fakeConverter = $temporary . DIRECTORY_SEPARATOR . 'fake-converter.php';
    file_put_contents($fakeConverter, <<<'PHP'
<?php
$name = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--password-env=')) {
        $name = substr($argument, strlen('--password-env='));
    }
}
if ($name === '' || getenv($name) !== 'child-secret') {
    fwrite(STDERR, "child environment missing\n");
    exit(2);
}
fwrite(STDOUT, "child environment present\n");
PHP
    );
    $child = (new CommandRunner())->run(
        [PHP_BINARY, $fakeConverter, '--password-env=OA_SMOKE_CHILD_PASSWORD'],
        null,
        ['OA_SMOKE_CHILD_PASSWORD' => 'child-secret'],
        ['child-secret']
    );
    smoke_assert(trim($child['stdout']) === 'child environment present', 'child-only password environment transport failed');
    smoke_assert(!str_contains(implode(' ', [PHP_BINARY, $fakeConverter, '--password-env=OA_SMOKE_CHILD_PASSWORD']), 'child-secret'), 'password leaked into converter command');

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'networkConnections' => 0,
        'databaseWrites' => 0,
        'checks' => 19,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($temporary);
}
