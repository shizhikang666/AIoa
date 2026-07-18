#!/usr/bin/env php
<?php

declare(strict_types=1);

use Oa\DatabaseMigration\DumpPolicy;
use Oa\DatabaseMigration\CommandRunner;
use Oa\DatabaseMigration\DatabaseManifest;
use Oa\DatabaseMigration\DetachedBytearrayPolicy;
use Oa\DatabaseMigration\DetachedOperationLogPolicy;
use Oa\DatabaseMigration\MigrationOptions;
use Oa\DatabaseMigration\MigrationRunner;
use Oa\DatabaseMigration\MigrationSafety;
use Oa\DatabaseMigration\ManifestStore;
use Oa\DatabaseMigration\MysqlProfile;
use Oa\DatabaseMigration\OrphanPolicy;
use Oa\DatabaseMigration\SchemaPolicy;

require __DIR__ . '/lib/oa-database-migration.php';
require __DIR__ . '/lib/installer-target.php';

$smokeChecks = 0;

function smoke_assert(bool $condition, string $message): void
{
    global $smokeChecks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $smokeChecks++;
}

function smoke_throws(callable $callback, string $message): void
{
    global $smokeChecks;
    try {
        $callback();
    } catch (Throwable) {
        $smokeChecks++;
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
        'generationExpression' => null,
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
    $emptyManifest = $temporary . DIRECTORY_SEPARATOR . 'empty-manifest';
    mkdir($emptyManifest, 0700, true);
    $manifestStore = new ManifestStore($emptyManifest);
    smoke_assert(is_dir($emptyManifest), 'empty manifest directory was rejected');
    smoke_throws(
        static fn () => $manifestStore->path('..'),
        'manifest path accepted a parent-directory child name'
    );
    unset($manifestStore);
    $nonemptyManifest = $temporary . DIRECTORY_SEPARATOR . 'nonempty-manifest';
    mkdir($nonemptyManifest, 0700, true);
    file_put_contents($nonemptyManifest . DIRECTORY_SEPARATOR . 'completed.json', '{}');
    smoke_throws(
        static fn () => new ManifestStore($nonemptyManifest),
        'non-empty manifest directory was accepted for a new migration run'
    );

    $sourceDefaults = $temporary . DIRECTORY_SEPARATOR . 'source.cnf';
    $targetDefaults = $temporary . DIRECTORY_SEPARATOR . 'target.cnf';
    file_put_contents($sourceDefaults, "[client]\nhost=old.invalid\nport=3306\nuser=readonly\npassword=\n");
    file_put_contents($targetDefaults, "[client]\nhost=new.invalid\nport=3306\nuser=migrator\npassword=\n");
    $sourceProfile = MysqlProfile::fromDefaultsFile($sourceDefaults);
    $targetProfile = MysqlProfile::fromDefaultsFile($targetDefaults);
    $loopbackDefaults = $temporary . DIRECTORY_SEPARATOR . 'loopback.cnf';
    file_put_contents($loopbackDefaults, "[client]\nhost=[::1]\nport=3306\nuser=migrator\npassword=\n");
    $loopbackProfile = MysqlProfile::fromDefaultsFile($loopbackDefaults);
    smoke_assert($loopbackProfile->childConnection()['remote'] === false, 'bracketed IPv6 loopback was treated as remote');

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
    [$detachedPredicate, $detachedParameters] = DetachedBytearrayPolicy::candidatePredicate(
        'oa2026',
        'b',
        ['excluded-process']
    );
    smoke_assert(
        str_contains(
            $detachedPredicate,
            "hp.PROC_INST_ID_ = NULLIF(TRIM(b.ROOT_PROC_INST_ID_), '')"
        ),
        'detached byte-array candidate predicate is missing its indexable process lookup'
    );
    smoke_assert(
        str_contains(
            $detachedPredicate,
            "BINARY TRIM(hp.PROC_INST_ID_) = BINARY NULLIF(TRIM(b.ROOT_PROC_INST_ID_), '')"
        ),
        'detached byte-array candidate predicate lost its byte-exact residual'
    );
    smoke_assert($detachedParameters === ['excluded-process'], 'detached exclusion parameters changed');
    $mysql57OperationLogColumns = [[
        'COLUMN_NAME' => 'USER_ID_',
        'ORDINAL_POSITION' => '15',
        'COLUMN_DEFAULT' => null,
        'IS_NULLABLE' => 'YES',
        'DATA_TYPE' => 'varchar',
        'COLUMN_TYPE' => 'varchar(255)',
        'CHARACTER_SET_NAME' => 'utf8',
        'COLLATION_NAME' => 'utf8_bin',
        'EXTRA' => '',
    ], [
        'COLUMN_NAME' => 'TIMESTAMP_',
        'ORDINAL_POSITION' => '16',
        'COLUMN_DEFAULT' => 'CURRENT_TIMESTAMP',
        'IS_NULLABLE' => 'NO',
        'DATA_TYPE' => 'timestamp',
        'COLUMN_TYPE' => 'timestamp',
        'CHARACTER_SET_NAME' => null,
        'COLLATION_NAME' => null,
        'EXTRA' => 'on update CURRENT_TIMESTAMP',
    ]];
    $mysql80OperationLogColumns = $mysql57OperationLogColumns;
    $mysql80OperationLogColumns[0]['CHARACTER_SET_NAME'] = 'utf8mb3';
    $mysql80OperationLogColumns[0]['COLLATION_NAME'] = 'utf8mb3_bin';
    $mysql80OperationLogColumns[1]['EXTRA'] = 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP';
    $normalizeOperationLogColumns = new ReflectionMethod(
        DetachedOperationLogPolicy::class,
        'normalizeTableStructureColumns'
    );
    $operationLogStructure = static function (
        array $columns,
        array $overrides = []
    ) use ($normalizeOperationLogColumns): array {
        return array_replace([
            'engine' => 'InnoDB',
            'columns' => $normalizeOperationLogColumns->invoke(null, $columns),
            'indexes' => [],
            'primaryKeyColumns' => ['ID_'],
        ], $overrides);
    };
    $operationLogPlan = static fn (array $columns, array $overrides = []): array => [
        'tableStructureSha256' => hash(
            'sha256',
            json_encode($operationLogStructure($columns, $overrides), JSON_THROW_ON_ERROR)
        ),
        'engineReferenceChecks' => [],
        'businessReferenceChecks' => [],
    ];
    DetachedOperationLogPolicy::assertSamePlan(
        $operationLogPlan($mysql80OperationLogColumns),
        $operationLogPlan($mysql57OperationLogColumns)
    );
    smoke_assert(
        $operationLogStructure($mysql80OperationLogColumns) === $operationLogStructure($mysql57OperationLogColumns),
        'detached operation-log structure did not normalize equivalent MySQL 5.7/8.0 metadata'
    );
    $parenthesizedTimestampDefaultColumns = $mysql80OperationLogColumns;
    $parenthesizedTimestampDefaultColumns[1]['COLUMN_DEFAULT'] = 'current_timestamp()';
    $normalizedParenthesizedTimestampColumns = $normalizeOperationLogColumns->invoke(
        null,
        $parenthesizedTimestampDefaultColumns
    );
    smoke_assert(
        ($normalizedParenthesizedTimestampColumns[1]['EXTRA'] ?? null) === 'on update current_timestamp',
        'detached operation-log structure did not recognize the optional CURRENT_TIMESTAMP parentheses'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[0]['COLLATION_NAME'] = 'utf8mb3_general_ci';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real collation change'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[0]['CHARACTER_SET_NAME'] = 'utf8mb4';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real character-set change'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[1]['EXTRA'] = 'DEFAULT_GENERATED_X on update CURRENT_TIMESTAMP';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure removed a non-marker DEFAULT_GENERATED_X token'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[0]['EXTRA'] = 'DEFAULT_GENERATED';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure removed DEFAULT_GENERATED from a non-TIMESTAMP_ column'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[0]['COLUMN_TYPE'] = 'varchar(512)';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real column-type change'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[0]['IS_NULLABLE'] = 'NO';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real nullability change'
    );
    $differentOperationLogColumns = $mysql80OperationLogColumns;
    $differentOperationLogColumns[0]['COLUMN_DEFAULT'] = 'system';
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($differentOperationLogColumns),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real default-value change'
    );
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($mysql80OperationLogColumns, ['engine' => 'MyISAM']),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real engine change'
    );
    smoke_throws(
        static fn () => DetachedOperationLogPolicy::assertSamePlan(
            $operationLogPlan($mysql80OperationLogColumns, [
                'indexes' => [[
                    'INDEX_NAME' => 'idx_user',
                    'NON_UNIQUE' => '1',
                    'SEQ_IN_INDEX' => '1',
                    'COLUMN_NAME' => 'USER_ID_',
                    'SUB_PART' => null,
                    'INDEX_TYPE' => 'BTREE',
                ]],
            ]),
            $operationLogPlan($mysql57OperationLogColumns)
        ),
        'detached operation-log structure normalized away a real index change'
    );
    $migrationLibrarySource = file_get_contents(__DIR__ . '/lib/oa-database-migration.php');
    smoke_assert(
        is_string($migrationLibrarySource)
        && !str_contains($migrationLibrarySource, 'WITH evidence AS')
        && !str_contains($migrationLibrarySource, 'WITH candidate_roots AS')
        && !str_contains($migrationLibrarySource, 'WITH candidates AS'),
        'detached byte-array audit reintroduced a MySQL 8-only common-table expression'
    );
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
        'biz_leave_application' => smoke_table(['ID' => smoke_column('int(11)')]),
    ];
    for ($index = 1; $index <= 119; $index++) {
        $sourceTables[sprintf('legacy_%03d', $index)] = smoke_table(['ID' => smoke_column('int(11)')]);
    }
    $sourceTables['legacy_002']['columns']['SECOND'] = array_merge(smoke_column('int(11)'), ['ordinal' => 2]);
    $sourceTables['legacy_004']['foreignKeys']['fk_equivalent_rule'] = [
        'referencedTable' => 'legacy_005',
        'updateRule' => 'NO ACTION',
        'deleteRule' => 'NO ACTION',
        'columns' => [['name' => 'ID', 'referenced' => 'ID']],
    ];
    $sourceTables['biz_leave_application']['indexes'] = [
        'PRIMARY' => [
            'unique' => true,
            'type' => 'BTREE',
            'columns' => [['name' => 'ID', 'prefix' => null, 'collation' => 'A']],
        ],
        'idx_legacy_leave' => [
            'unique' => false,
            'type' => 'BTREE',
            'columns' => [['name' => 'ID', 'prefix' => null, 'collation' => 'A']],
        ],
    ];
    $templateTables = $sourceTables;
    foreach ($templateTables as &$table) {
        $table['columns']['ID']['type'] = 'int';
    }
    unset($table);
    $sourceTables['legacy_001']['columns']['ID'] = array_merge(smoke_column('varchar(64)'), [
        'charset' => 'utf8',
        'collation' => 'utf8_bin',
    ]);
    $templateTables['legacy_001']['columns']['ID'] = array_merge(smoke_column('varchar(64)'), [
        'extra' => 'DEFAULT_GENERATED',
        'charset' => 'utf8mb3',
        'collation' => 'utf8mb3_bin',
    ]);
    $sourceTables['legacy_003']['collation'] = 'utf8_bin';
    $templateTables['legacy_003']['collation'] = 'utf8mb3_bin';
    $templateTables['legacy_004']['foreignKeys']['fk_equivalent_rule']['updateRule'] = 'RESTRICT';
    $templateTables['legacy_004']['foreignKeys']['fk_equivalent_rule']['deleteRule'] = 'RESTRICT';
    $templateTables['biz_leave_application']['indexes'] = [
        'idx_leave_after_sales_travel' => SchemaPolicy::EXPECTED_EXTRA_INDEXES[
            'biz_leave_application.idx_leave_after_sales_travel'
        ],
        'idx_legacy_leave' => $sourceTables['biz_leave_application']['indexes']['idx_legacy_leave'],
        'PRIMARY' => $sourceTables['biz_leave_application']['indexes']['PRIMARY'],
    ];
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
    SchemaPolicy::assertExpected($comparison, 121, 124, 122, 126);
    smoke_assert($comparison['valid'] === true, 'audited schema difference was rejected');
    smoke_assert($comparison['columnMismatches'] === [], 'MySQL utf8 aliases or generated-default metadata were not normalized');
    smoke_assert($comparison['indexMismatches'] === [], 'equivalent indexes with different map order were rejected');
    smoke_assert($comparison['foreignKeyMismatches'] === [], 'equivalent NO ACTION and RESTRICT foreign-key rules were rejected');
    smoke_assert(
        $comparison['extraIndexes'] === ['biz_leave_application.idx_leave_after_sales_travel'],
        'the audited new template index was not reported exactly'
    );
    $reorderedTemplateTables = $templateTables;
    $reorderedTemplateTables['biz_leave_application']['indexes'] = array_reverse(
        $reorderedTemplateTables['biz_leave_application']['indexes'],
        true
    );
    smoke_assert(
        DatabaseManifest::schemaHash($templateTables) === DatabaseManifest::schemaHash($reorderedTemplateTables),
        'raw schema hash changed when only an associative index map order changed'
    );
    $mutatedTemplateTables = $templateTables;
    $mutatedTemplateTables['biz_leave_application']['indexes']['idx_leave_after_sales_travel']['columns'] = array_reverse(
        $mutatedTemplateTables['biz_leave_application']['indexes']['idx_leave_after_sales_travel']['columns']
    );
    smoke_assert(
        DatabaseManifest::schemaHash($templateTables) !== DatabaseManifest::schemaHash($mutatedTemplateTables),
        'raw schema hash ignored ordered composite-index columns'
    );
    $mutatedTemplateTables = $templateTables;
    $mutatedTemplateTables['legacy_001']['columns']['ID']['ordinal'] = 99;
    smoke_assert(
        DatabaseManifest::schemaHash($templateTables) !== DatabaseManifest::schemaHash($mutatedTemplateTables),
        'raw schema hash ignored a column ordinal change'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_001']['columns']['UNREVIEWED'] = smoke_column('varchar(10)');
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'unexpected new field was accepted'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_001']['indexes']['UNREVIEWED'] = [
        'unique' => false,
        'type' => 'BTREE',
        'columns' => [['name' => 'ID', 'prefix' => null, 'collation' => 'A']],
    ];
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'unexpected new index was accepted'
    );
    $badTemplate = $templateTables;
    unset($badTemplate['biz_leave_application']['indexes']['idx_legacy_leave']);
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'missing legacy index was accepted'
    );
    $badTemplate = $templateTables;
    $badTemplate['biz_leave_application']['indexes']['idx_leave_after_sales_travel']['unique'] = true;
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'audited new index with the wrong definition was accepted'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_003']['collation'] = 'utf8mb4_bin';
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'a real table collation change was accepted as an utf8 alias'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_003']['engine'] = 'MyISAM';
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'a table engine change was accepted'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_004']['foreignKeys']['fk_equivalent_rule']['deleteRule'] = 'CASCADE';
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'a real foreign-key delete-rule change was accepted'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_002']['columns'] = [
        'SECOND' => $badTemplate['legacy_002']['columns']['SECOND'],
        'ID' => $badTemplate['legacy_002']['columns']['ID'],
    ];
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'a legacy common-column reorder was accepted'
    );
    $badTemplate = $templateTables;
    $badTemplate['legacy_001']['columns']['ID']['generationExpression'] = '1';
    smoke_assert(
        SchemaPolicy::compareSourceToTemplate(['tables' => $sourceTables], ['tables' => $badTemplate])['valid'] === false,
        'a generated-column expression change was accepted'
    );

    $pathProject = $temporary . DIRECTORY_SEPARATOR . 'path-policy-project';
    mkdir($pathProject . DIRECTORY_SEPARATOR . 'public', 0700, true);
    mkdir($pathProject . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backup', 0700, true);
    $runner = new MigrationRunner($pathProject);
    $safeManifestPath = new ReflectionMethod(MigrationRunner::class, 'safeManifestPath');
    $safeManifestPath->setAccessible(true);
    $previousPrivateManifest = getenv('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY');
    $invokeSafeManifestPath = static function (string $candidate) use (
        $safeManifestPath,
        $runner,
        $previousPrivateManifest
    ): mixed {
        putenv('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY=' . $candidate);
        try {
            return $safeManifestPath->invoke($runner, $candidate);
        } finally {
            if ($previousPrivateManifest === false) {
                putenv('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY');
            } else {
                putenv('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY=' . $previousPrivateManifest);
            }
        }
    };
    smoke_throws(
        static fn () => $invokeSafeManifestPath(
            $pathProject . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'nonexistent-audit'
        ),
        'public manifest guard was bypassed through an unresolved traversal path'
    );
    smoke_throws(
        static fn () => $invokeSafeManifestPath(
            $pathProject . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'private-audit'
        ),
        'manifest path outside runtime/backup was accepted'
    );
    smoke_throws(
        static fn () => $invokeSafeManifestPath(
            $temporary . DIRECTORY_SEPARATOR . 'external-private-audit'
        ),
        'external manifest path was accepted'
    );
    $allowedAuditPath = $invokeSafeManifestPath(
        $pathProject . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backup'
        . DIRECTORY_SEPARATOR . 'private-audit'
    );
    smoke_assert(
        str_contains(str_replace('\\', '/', (string)$allowedAuditPath), '/runtime/backup/private-audit'),
        'private runtime/backup manifest path was rejected or rewritten unexpectedly'
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
        'checks' => $smokeChecks,
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
