#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\support\LegacyFileSource;

const OA_LEGACY_FILE_MIGRATION_LIBRARY_ONLY = true;

require __DIR__ . '/migrate-legacy-files.php';
require_once dirname(__DIR__) . '/app/support/LegacyFileSource.php';

$checks = 0;

function legacy_file_smoke_assert(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function legacy_file_smoke_throws(callable $callback, string $message): void
{
    global $checks;
    try {
        $callback();
    } catch (Throwable) {
        $checks++;
        return;
    }
    throw new RuntimeException($message);
}

final class LegacyFileOfflineUpdateProbe
{
    public int $calls = 0;

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        $this->calls++;
        return count($values) > 0 ? 2 : 0;
    }
}

$temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'legacy-file-migration-smoke-' . bin2hex(random_bytes(5));
if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
    throw new RuntimeException('cannot create offline smoke directory');
}

try {
    $placeholderDatabase = 'candidate_database_migrated';
    $targetRoot = $temporary . DIRECTORY_SEPARATOR . 'target-root';
    mkdir($targetRoot, 0700, true);
    $dryRun = parseLegacyFileMigrationOptions([
        'smoke',
        '--database=' . $placeholderDatabase,
        '--confirm-database=' . $placeholderDatabase,
        '--target-root=' . $targetRoot,
        '--confirm-target-root=' . $targetRoot,
        '--source-root=' . $temporary,
    ]);
    legacy_file_smoke_assert(
        assertDatabaseCliBinding($dryRun) === $placeholderDatabase,
        'matching migrated database binding was rejected'
    );
    legacy_file_smoke_assert(
        assertTargetRootCliBinding($dryRun) === cliPath($targetRoot),
        'matching target-root binding was rejected'
    );
    assertApplyCliBinding($dryRun, false);
    legacy_file_smoke_assert(!isset($dryRun['apply']), 'default mode was not dry-run');

    legacy_file_smoke_throws(
        static fn () => assertDatabaseCliBinding(['database' => $placeholderDatabase]),
        'missing confirm-database was accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertDatabaseCliBinding([
            'database' => $placeholderDatabase,
            'confirm-database' => 'different_database_migrated',
        ]),
        'mismatched database confirmation was accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertDatabaseCliBinding([
            'database' => 'candidate_database',
            'confirm-database' => 'candidate_database',
        ]),
        'database without _migrated suffix was accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertTargetRootCliBinding(['target-root' => $targetRoot]),
        'missing target-root confirmation was accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertTargetRootCliBinding([
            'target-root' => $targetRoot,
            'confirm-target-root' => $targetRoot . '-other',
        ]),
        'mismatched target-root confirmation was accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertApplyCliBinding(['apply' => true], true),
        'apply was accepted without independent confirmation and approved dry-run evidence'
    );
    legacy_file_smoke_throws(
        static fn () => assertApplyCliBinding([LEGACY_FILE_APPLY_CONFIRMATION_OPTION => true], false),
        'apply confirmation was accepted without apply mode'
    );
    $approvedOptions = [
        'apply' => true,
        LEGACY_FILE_APPLY_CONFIRMATION_OPTION => true,
        'approved-dry-run-manifest' => 'approved.jsonl',
        'approved-dry-run-manifest-sha256' => str_repeat('a', 64),
    ];
    assertApplyCliBinding($approvedOptions, true);
    legacy_file_smoke_assert(true, 'approved apply option gate failed');
    legacy_file_smoke_throws(
        static fn () => assertApplyCliBinding([
            'apply' => true,
            LEGACY_FILE_APPLY_CONFIRMATION_OPTION => true,
            'approved-dry-run-manifest' => 'approved.jsonl',
        ], true),
        'apply was accepted without approved dry-run SHA-256'
    );

    legacy_file_smoke_throws(
        static fn () => parseLegacyFileMigrationOptions(['smoke', '--apply', '--apply']),
        'duplicate options were accepted'
    );
    legacy_file_smoke_throws(
        static fn () => parseLegacyFileMigrationOptions(['smoke', '--unknown']),
        'unknown options were accepted'
    );
    legacy_file_smoke_throws(static fn () => parseNonNegativeLimit('-1'), 'negative limit was accepted');
    legacy_file_smoke_assert(parseNonNegativeLimit('0') === 0, 'zero limit was rejected');
    legacy_file_smoke_assert(parseNonNegativeLimit('25') === 25, 'positive limit parsing failed');

    $layoutRoot = $temporary . DIRECTORY_SEPARATOR . 'layout-root';
    mkdir($layoutRoot, 0700, true);
    $layoutRoot = cliPath((string)realpath($layoutRoot));
    $row = static fn (string $id, string $storagePath): array => [
        'ID' => $id,
        'BUCKET' => 'bucket',
        'STORAGE_PATH' => $storagePath,
        'OBJ_NAME' => $id . '.bin',
        'CREATE_TIME' => '2026-01-01 00:00:00',
    ];
    $zeroWriteSummary = initialMigrationSummary(true);
    $zeroWriteProbe = new LegacyFileOfflineUpdateProbe();
    legacy_file_smoke_throws(
        static fn () => buildSafeTargetLayout([
            $row('duplicate-a', '/legacy/bucket/same.bin'),
            $row('duplicate-b', '/legacy/bucket/same.bin'),
        ], $layoutRoot),
        'duplicate canonical target paths were accepted'
    );
    legacy_file_smoke_assert(
        $zeroWriteProbe->calls === 0
            && $zeroWriteSummary['filesCopied'] === 0
            && $zeroWriteSummary['databaseWriteStatements'] === 0
            && $zeroWriteSummary['databaseRowsAffected'] === 0,
        'duplicate target rejection performed a copy or database update'
    );
    legacy_file_smoke_throws(
        static fn () => buildSafeTargetLayout([
            $row('prefix-parent', '/legacy/bucket/parent'),
            $row('prefix-child', '/legacy/bucket/parent/child.bin'),
        ], $layoutRoot),
        'planned file-versus-directory prefix conflict was accepted'
    );
    legacy_file_smoke_assert(
        array_values(array_diff(scandir($layoutRoot) ?: [], ['.', '..'])) === [],
        'target layout audit wrote into the target root'
    );
    $dryLayout = buildSafeTargetLayout([
        $row('dry-layout', '/legacy/bucket/new/nested/file.bin'),
    ], $layoutRoot);
    legacy_file_smoke_assert(
        count($dryLayout) === 1
            && !file_exists($layoutRoot . DIRECTORY_SEPARATOR . 'bucket'),
        'dry-run target planning created target directories'
    );
    mkdir($layoutRoot . DIRECTORY_SEPARATOR . 'bucket', 0700);
    file_put_contents($layoutRoot . DIRECTORY_SEPARATOR . 'bucket' . DIRECTORY_SEPARATOR . 'blocked', 'file');
    legacy_file_smoke_throws(
        static fn () => buildSafeTargetLayout([
            $row('blocked-child', '/legacy/bucket/blocked/child.bin'),
        ], $layoutRoot),
        'existing target ancestor file was accepted as a directory'
    );

    legacy_file_smoke_throws(
        static fn () => assertRootsStrictlySeparated($targetRoot, $targetRoot),
        'equal cache and target roots were accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertRootsStrictlySeparated(
            $targetRoot . DIRECTORY_SEPARATOR . 'cache-child',
            $targetRoot
        ),
        'cache root below target root was accepted'
    );
    legacy_file_smoke_throws(
        static fn () => assertRootsStrictlySeparated(dirname($targetRoot), $targetRoot),
        'cache root above target root was accepted'
    );
    $cacheRoot = preparePrivateSourceCacheRoot(
        $temporary . DIRECTORY_SEPARATOR . 'private-source-cache',
        $targetRoot
    );
    legacy_file_smoke_assert(
        is_dir($cacheRoot) && !pathIsSameOrDescendant($cacheRoot, $targetRoot),
        'strictly separated private source cache root was not prepared'
    );
    if (DIRECTORY_SEPARATOR === '/') {
        legacy_file_smoke_assert(
            (((int)fileperms($cacheRoot)) & 0777) === 0700,
            'source cache root permissions are not exactly 0700'
        );
    }

    $reparseMode = PHP_OS_FAMILY === 'Windows' ? 0 : 0120000;
    legacy_file_smoke_assert(
        pathComponentIsLinkOrReparse($temporary . DIRECTORY_SEPARATOR . 'synthetic-link', ['mode' => $reparseMode]),
        'lstat symlink/reparse-point mode was not rejected'
    );
    $nestedLinkRoot = $temporary . DIRECTORY_SEPARATOR . 'nested-link-root';
    $nestedLinkTarget = $temporary . DIRECTORY_SEPARATOR . 'nested-link-target';
    mkdir($nestedLinkRoot, 0700);
    mkdir($nestedLinkTarget, 0700);
    $nestedLink = $nestedLinkRoot . DIRECTORY_SEPARATOR . 'unsafe-link';
    if (@symlink($nestedLinkTarget, $nestedLink)) {
        legacy_file_smoke_throws(
            static fn () => assertSafeTargetPath($nestedLinkRoot, $nestedLink . DIRECTORY_SEPARATOR . 'file.bin'),
            'nested target symlink was accepted'
        );
        @unlink($nestedLink);
        @rmdir($nestedLink);
    }

    $identityRoot = $temporary . DIRECTORY_SEPARATOR . 'identity-root';
    mkdir($identityRoot, 0700);
    $identity = directoryIdentitySha256($identityRoot);
    $originalIdentityRoot = $identityRoot . '-original';
    rename($identityRoot, $originalIdentityRoot);
    mkdir($identityRoot, 0700);
    legacy_file_smoke_throws(
        static fn () => assertTargetRootIdentity($identityRoot, $identity),
        'target-root replacement after the gate was accepted'
    );
    rmdir($identityRoot);
    rename($originalIdentityRoot, $identityRoot);

    $evidenceDirectory = $temporary . DIRECTORY_SEPARATOR . 'database-migration-evidence';
    mkdir($evidenceDirectory, 0700, true);
    @chmod($evidenceDirectory, 0700);
    $evidencePath = $evidenceDirectory . DIRECTORY_SEPARATOR . 'completed.json';
    $evidence = [
        'status' => 'completed',
        'mode' => 'apply',
        'readyForApply' => true,
        'writesPerformed' => true,
        'targetCreatedFromTemplateSchemaOnly' => true,
        'sourceCreateOrDropImported' => false,
        'installerIdempotencyVerified' => true,
        'source' => ['redacted' => true],
        'template' => ['redacted' => true],
        'targetDatabase' => $placeholderDatabase,
        'schemaComparison' => ['valid' => true],
        'finalSchemaSha256' => str_repeat('b', 64),
        'manifestDirectory' => $evidenceDirectory,
    ];
    file_put_contents($evidencePath, json_encode($evidence, JSON_THROW_ON_ERROR));
    @chmod($evidencePath, 0600);
    $databaseEvidence = validateDatabaseMigrationCompleted($evidencePath, $placeholderDatabase);
    legacy_file_smoke_assert(
        preg_match('/\A[0-9a-f]{64}\z/', $databaseEvidence['sha256']) === 1,
        'database migration evidence digest is invalid'
    );
    legacy_file_smoke_throws(
        static fn () => validateDatabaseMigrationCompleted($evidencePath, 'other_database_migrated'),
        'database migration evidence target mismatch was accepted'
    );
    $invalidEvidence = $evidence;
    $invalidEvidence['writesPerformed'] = false;
    $invalidEvidenceDirectory = $temporary . DIRECTORY_SEPARATOR . 'invalid-database-migration-evidence';
    mkdir($invalidEvidenceDirectory, 0700, true);
    @chmod($invalidEvidenceDirectory, 0700);
    $invalidEvidence['manifestDirectory'] = $invalidEvidenceDirectory;
    $invalidEvidencePath = $invalidEvidenceDirectory . DIRECTORY_SEPARATOR . 'completed.json';
    file_put_contents($invalidEvidencePath, json_encode($invalidEvidence, JSON_THROW_ON_ERROR));
    @chmod($invalidEvidencePath, 0600);
    legacy_file_smoke_throws(
        static fn () => validateDatabaseMigrationCompleted($invalidEvidencePath, $placeholderDatabase),
        'non-completed database migration evidence was accepted'
    );
    $detachedEvidenceDirectory = $temporary . DIRECTORY_SEPARATOR . 'detached-database-migration-evidence';
    mkdir($detachedEvidenceDirectory, 0700, true);
    @chmod($detachedEvidenceDirectory, 0700);
    $detachedEvidence = $evidence;
    $detachedEvidence['manifestDirectory'] = $temporary . DIRECTORY_SEPARATOR . 'missing-manifest-directory';
    $detachedEvidencePath = $detachedEvidenceDirectory . DIRECTORY_SEPARATOR . 'completed.json';
    file_put_contents($detachedEvidencePath, json_encode($detachedEvidence, JSON_THROW_ON_ERROR));
    @chmod($detachedEvidencePath, 0600);
    legacy_file_smoke_throws(
        static fn () => validateDatabaseMigrationCompleted($detachedEvidencePath, $placeholderDatabase),
        'completed evidence with an unresolvable manifestDirectory was accepted'
    );

    $schemaTables = [[
        'TABLE_NAME' => 'dev_file',
        'ENGINE' => 'InnoDB',
        'TABLE_COLLATION' => 'utf8mb4_general_ci',
    ]];
    $schemaColumns = [[
        'TABLE_NAME' => 'dev_file',
        'COLUMN_NAME' => 'ID',
        'ORDINAL_POSITION' => 1,
        'COLUMN_TYPE' => 'varchar(64)',
        'IS_NULLABLE' => 'NO',
        'COLUMN_DEFAULT' => null,
        'EXTRA' => '',
        'CHARACTER_SET_NAME' => 'utf8mb4',
        'COLLATION_NAME' => 'utf8mb4_general_ci',
        'GENERATION_EXPRESSION' => '',
    ]];
    $schemaIndexes = [[
        'TABLE_NAME' => 'dev_file',
        'INDEX_NAME' => 'PRIMARY',
        'NON_UNIQUE' => 0,
        'SEQ_IN_INDEX' => 1,
        'COLUMN_NAME' => 'ID',
        'SUB_PART' => null,
        'COLLATION' => 'A',
        'INDEX_TYPE' => 'BTREE',
    ]];
    $schemaDigest = databaseSchemaSha256FromMetadata(
        $schemaTables,
        $schemaColumns,
        $schemaIndexes,
        []
    );
    $expectedSchemaDigest = canonicalSha256([
        'dev_file' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
            'columns' => [
                'ID' => [
                    'ordinal' => 1,
                    'type' => 'varchar(64)',
                    'nullable' => 'NO',
                    'default' => null,
                    'extra' => '',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_general_ci',
                    'generationExpression' => '',
                ],
            ],
            'indexes' => [
                'PRIMARY' => [
                    'unique' => true,
                    'type' => 'BTREE',
                    'columns' => [[
                        'name' => 'ID',
                        'prefix' => null,
                        'collation' => 'A',
                    ]],
                ],
            ],
            'foreignKeys' => [],
        ],
    ]);
    legacy_file_smoke_assert(
        hash_equals($expectedSchemaDigest, $schemaDigest),
        'information_schema metadata digest does not match the database migration canonical schema format'
    );
    $driftedSchemaColumns = $schemaColumns;
    $driftedSchemaColumns[0]['COLUMN_TYPE'] = 'varchar(65)';
    legacy_file_smoke_assert(
        !hash_equals(
            $schemaDigest,
            databaseSchemaSha256FromMetadata($schemaTables, $driftedSchemaColumns, $schemaIndexes, [])
        ),
        'schema metadata drift did not change the canonical schema digest'
    );

    $cachePath = $cacheRoot . DIRECTORY_SEPARATOR . 'verified-cache.bin';
    $preparedCache = $cacheRoot . DIRECTORY_SEPARATOR . 'prepared-cache.tmp';
    file_put_contents($preparedCache, 'cache-content');
    @chmod($preparedCache, 0600);
    LegacyFileSource::finalizePreparedCacheFile($preparedCache, $cachePath, $cacheRoot);
    $cacheDigest = hash_file('sha256', $cachePath);
    legacy_file_smoke_assert(
        is_file($cachePath) && is_string($cacheDigest),
        'prepared cache file was not finalized with no-clobber semantics'
    );
    $matchingRace = $cacheRoot . DIRECTORY_SEPARATOR . 'matching-race.tmp';
    file_put_contents($matchingRace, 'cache-content');
    @chmod($matchingRace, 0600);
    LegacyFileSource::finalizePreparedCacheFile($matchingRace, $cachePath, $cacheRoot);
    legacy_file_smoke_assert(
        !file_exists($matchingRace) && hash_file('sha256', $cachePath) === $cacheDigest,
        'same-digest cache race was not accepted safely'
    );
    $differentRace = $cacheRoot . DIRECTORY_SEPARATOR . 'different-race.tmp';
    file_put_contents($differentRace, 'different-cache-content');
    @chmod($differentRace, 0600);
    legacy_file_smoke_throws(
        static fn () => LegacyFileSource::finalizePreparedCacheFile(
            $differentRace,
            $cachePath,
            $cacheRoot
        ),
        'different-digest cache race was accepted'
    );
    legacy_file_smoke_assert(
        !file_exists($differentRace) && hash_file('sha256', $cachePath) === $cacheDigest,
        'different-digest cache race overwrote the approved destination or left a temporary file'
    );

    $cacheLinkTarget = $temporary . DIRECTORY_SEPARATOR . 'cache-link-target';
    mkdir($cacheLinkTarget, 0700);
    $cacheLink = $cacheRoot . DIRECTORY_SEPARATOR . 'unsafe-link';
    if (@symlink($cacheLinkTarget, $cacheLink)) {
        $unsafePrepared = $cacheRoot . DIRECTORY_SEPARATOR . 'unsafe-link-prepared.tmp';
        file_put_contents($unsafePrepared, 'unsafe');
        @chmod($unsafePrepared, 0600);
        legacy_file_smoke_throws(
            static fn () => LegacyFileSource::finalizePreparedCacheFile(
                $unsafePrepared,
                $cacheLink . DIRECTORY_SEPARATOR . 'cache.bin',
                $cacheRoot
            ),
            'nested source-cache symlink was accepted'
        );
        @unlink($unsafePrepared);
        @unlink($cacheLink);
        @rmdir($cacheLink);
    }

    $drySummary = initialMigrationSummary(false);
    $dryProbe = new LegacyFileOfflineUpdateProbe();
    legacy_file_smoke_throws(
        static function () use ($dryProbe, &$drySummary): void {
            applyDatabaseUpdate($dryProbe, ['field' => 'value'], false, $drySummary);
        },
        'dry-run database update was not blocked'
    );
    legacy_file_smoke_assert($dryProbe->calls === 0, 'dry-run invoked the database update target');
    legacy_file_smoke_assert(
        $drySummary['databaseWriteStatements'] === 0 && $drySummary['databaseRowsAffected'] === 0,
        'dry-run database write counters are non-zero'
    );

    $applySummary = initialMigrationSummary(true);
    $applyProbe = new LegacyFileOfflineUpdateProbe();
    legacy_file_smoke_assert(
        applyDatabaseUpdate($applyProbe, ['field' => 'value'], true, $applySummary) === 2,
        'confirmed apply update did not return affected rows'
    );
    legacy_file_smoke_assert(
        $applyProbe->calls === 1
            && $applySummary['databaseWriteStatements'] === 1
            && $applySummary['databaseRowsAffected'] === 2,
        'apply database write aggregation is incorrect'
    );

    $privateProject = $temporary . DIRECTORY_SEPARATOR . 'private-project';
    $privateRoot = ensurePrivateManifestRoot($privateProject);
    $issuesPath = $privateRoot . DIRECTORY_SEPARATOR . 'issues.jsonl';
    $issuesManifest = openExclusiveManifest($issuesPath, $privateRoot);
    manifest($issuesManifest, ['type' => 'start', 'mode' => 'dry-run']);
    $issuesSummary = initialMigrationSummary(false);
    $issuesSummary['scoped'] = false;
    $issuesSummary['targetRoot'] = cliPath($targetRoot);
    $issuesSummary['planVersion'] = 3;
    $issuesSummary['planSha256'] = str_repeat('c', 64);
    $issuesSummary['databaseMigrationEvidenceSha256'] = $databaseEvidence['sha256'];
    $issuesSummary['databaseSchemaSha256'] = str_repeat('b', 64);
    recordSourceStatus($issuesSummary, 'unresolved');
    $issuesSummary['sourceInventory']['selected'] = 1;
    recordFileStatus($issuesSummary, 'missing');
    $issuesSummary['completion'] = [
        'completed' => false,
        'status' => 'completed-with-issues',
        'time' => date(DATE_ATOM),
    ];
    writeManifestCompletion($issuesManifest, $issuesSummary);
    fclose($issuesManifest);
    $issuesRecords = array_values(array_filter(array_map(
        static fn (string $line): mixed => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        preg_split('/\R/', trim((string)file_get_contents($issuesPath))) ?: []
    )));
    $issuesCompletion = $issuesRecords[count($issuesRecords) - 1] ?? [];
    legacy_file_smoke_assert(
        ($issuesCompletion['status'] ?? '') === 'completed-with-issues'
            && ($issuesCompletion['completed'] ?? true) === false,
        'completion with unresolved outcomes was incorrectly marked completed'
    );
    legacy_file_smoke_throws(
        static fn () => openExclusiveManifest($issuesPath, $privateRoot),
        'existing manifest was reopened instead of rejected'
    );
    legacy_file_smoke_throws(
        static fn () => openExclusiveManifest($temporary . DIRECTORY_SEPARATOR . 'outside.jsonl', $privateRoot),
        'manifest outside private runtime/backup was accepted'
    );

    $approvedPath = $privateRoot . DIRECTORY_SEPARATOR . 'approved.jsonl';
    $approvedHandle = openExclusiveManifest($approvedPath, $privateRoot);
    manifest($approvedHandle, [
        'type' => 'start',
        'mode' => 'dry-run',
        'scoped' => false,
        'targetRoot' => cliPath($targetRoot),
        'databaseMigrationEvidenceSha256' => $databaseEvidence['sha256'],
    ]);
    $approvedSummary = initialMigrationSummary(false);
    $approvedSummary['scoped'] = false;
    $approvedSummary['targetRoot'] = cliPath($targetRoot);
    $approvedSummary['databaseMigrationEvidenceSha256'] = $databaseEvidence['sha256'];
    $approvedSummary['sourceInventory'] = ['selected' => 1, 'byKind' => ['source-root' => 1]];
    $approvedSummary['outcomes'] = ['success' => 1, 'missing' => 0, 'conflict' => 0, 'error' => 0];
    $approvedSummary['planVersion'] = 3;
    $approvedSummary['planSha256'] = str_repeat('d', 64);
    $approvedSummary['migrationCodeSha256'] = legacyFileMigrationCodeSha256();
    $approvedSummary['databaseSchemaSha256'] = str_repeat('b', 64);
    $approvedSummary['completion'] = [
        'completed' => true,
        'status' => 'completed',
        'time' => date(DATE_ATOM),
    ];
    manifest($approvedHandle, [
        'type' => 'preflight-plan',
        'status' => 'computed',
        'planVersion' => 3,
        'planSha256' => str_repeat('d', 64),
        'migrationCodeSha256' => $approvedSummary['migrationCodeSha256'],
        'databaseSchemaSha256' => $approvedSummary['databaseSchemaSha256'],
        'sourceInventory' => $approvedSummary['sourceInventory'],
        'outcomes' => $approvedSummary['outcomes'],
        'errors' => 0,
    ]);
    writeManifestCompletion($approvedHandle, $approvedSummary);
    fclose($approvedHandle);
    $approvedSha256 = hash_file('sha256', $approvedPath);
    $approved = readApprovedDryRunManifest($approvedPath, (string)$approvedSha256, $privateRoot);
    legacy_file_smoke_assert(
        ($approved['planSha256'] ?? '') === str_repeat('d', 64),
        'approved dry-run plan digest was not loaded'
    );
    legacy_file_smoke_throws(
        static fn () => readApprovedDryRunManifest($approvedPath, str_repeat('e', 64), $privateRoot),
        'approved dry-run manifest with wrong SHA-256 was accepted'
    );
    assertApprovedDryRunMatches(
        $approved,
        ['version' => 3, 'sha256' => str_repeat('d', 64)],
        $approvedSummary,
        cliPath($targetRoot),
        $databaseEvidence['sha256']
    );
    legacy_file_smoke_assert(true, 'matching approved dry-run plan was rejected');
    legacy_file_smoke_throws(
        static fn () => assertApprovedDryRunMatches(
            $approved,
            ['version' => 3, 'sha256' => str_repeat('f', 64)],
            $approvedSummary,
            cliPath($targetRoot),
            $databaseEvidence['sha256']
        ),
        'drifted current preflight plan was accepted'
    );

    $sourceFile = $temporary . DIRECTORY_SEPARATOR . 'source.bin';
    file_put_contents($sourceFile, 'approved-content');
    $sourceSize = filesize($sourceFile);
    $sourceSha256 = hash_file('sha256', $sourceFile);
    assertFileDigest($sourceFile, (int)$sourceSize, (string)$sourceSha256);
    legacy_file_smoke_assert(true, 'stable source digest was rejected');
    $copyRoot = $temporary . DIRECTORY_SEPARATOR . 'copy-target';
    mkdir($copyRoot, 0700, true);
    $copyRoot = cliPath((string)realpath($copyRoot));
    $copyRootIdentity = directoryIdentitySha256($copyRoot);
    $copiedTarget = $copyRoot . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'copied.bin';
    copyVerified(
        $sourceFile,
        $copiedTarget,
        (int)$sourceSize,
        (string)$sourceSha256,
        $copyRoot,
        $copyRootIdentity
    );
    legacy_file_smoke_assert(
        is_file($copiedTarget) && hash_file('sha256', $copiedTarget) === $sourceSha256,
        'offline verified copy did not preserve the approved digest'
    );
    legacy_file_smoke_throws(
        static fn () => copyVerified(
            $sourceFile,
            $copiedTarget,
            (int)$sourceSize,
            (string)$sourceSha256,
            $copyRoot,
            $copyRootIdentity
        ),
        'verified copy overwrote an existing target'
    );
    file_put_contents($sourceFile, 'drifted-content');
    legacy_file_smoke_throws(
        static fn () => assertFileDigest($sourceFile, (int)$sourceSize, (string)$sourceSha256),
        'source drift was accepted by the final zero-write gate'
    );

    $safeError = safeMigrationError(
        new RuntimeException('password=value https://user:pass@example.invalid ' . $placeholderDatabase),
        $placeholderDatabase
    );
    legacy_file_smoke_assert(!str_contains($safeError, 'value'), 'password value was not redacted');
    legacy_file_smoke_assert(!str_contains($safeError, 'user:pass'), 'URL credentials were not redacted');
    legacy_file_smoke_assert(!str_contains($safeError, $placeholderDatabase), 'database name was not redacted');

    $script = file_get_contents(__DIR__ . '/migrate-legacy-files.php');
    legacy_file_smoke_assert(is_string($script), 'migration script is unreadable');
    legacy_file_smoke_assert(
        str_contains((string)$script, 'SELECT DATABASE() AS selected_database'),
        'actual connected database verification is missing'
    );
    legacy_file_smoke_assert(
        str_contains((string)$script, 'SET SESSION TRANSACTION READ ONLY'),
        'read-only preflight database gate is missing'
    );
    legacy_file_smoke_assert(
        str_contains((string)$script, 'fsync($handle)'),
        'manifest records are not fsynced'
    );
    legacy_file_smoke_assert(
        !str_contains((string)$script, 'databaseBindingDigest')
            && !str_contains((string)$script, "hash_hmac('sha256', \$database"),
        'dictionary-guessable database digest is still present'
    );
    legacy_file_smoke_assert(
        substr_count((string)$script, '->update(') === 1,
        'database updates bypass the centralized apply gate'
    );
    legacy_file_smoke_assert(
        str_contains((string)$script, 'buildSafeTargetLayout($selectedRows, $targetRoot)')
            && str_contains((string)$script, 'assertTargetLayoutHasNoConflicts($layout)'),
        'complete target-layout conflict gate is missing'
    );
    legacy_file_smoke_assert(
        str_contains((string)$script, 'assertCurrentDatabaseSchemaMatches(')
            && str_contains((string)$script, "information_schema.TABLES")
            && str_contains((string)$script, "databaseEvidence['finalSchemaSha256']"),
        'current target schema is not strongly bound to completed.json finalSchemaSha256'
    );
    $legacySourceScript = file_get_contents(dirname(__DIR__) . '/app/support/LegacyFileSource.php');
    legacy_file_smoke_assert(is_string($legacySourceScript), 'LegacyFileSource is unreadable');
    legacy_file_smoke_assert(
        !str_contains((string)$legacySourceScript, 'rename(')
            && str_contains((string)$legacySourceScript, 'link($temporary, $cachePath)')
            && str_contains((string)$legacySourceScript, 'fsync($handle)'),
        'source cache does not use durable atomic no-clobber finalization'
    );
    $stabilityGate = strpos((string)$script, 'assertRuntimePlanStable(');
    $schemaRecheck = strpos(
        (string)$script,
        "assertCurrentDatabaseSchemaMatches(\$database, \$currentEvidence['finalSchemaSha256'])"
    );
    $targetRootGate = strpos(
        (string)$script,
        'assertTargetRootIdentity($targetRoot, $targetRootIdentity);'
    );
    $readWriteGate = strpos((string)$script, "Db::execute('SET SESSION TRANSACTION READ WRITE')");
    $applyCall = strpos((string)$script, 'applyMigrationPlan(');
    legacy_file_smoke_assert(
        is_int($stabilityGate) && is_int($schemaRecheck) && is_int($targetRootGate)
            && is_int($readWriteGate) && is_int($applyCall)
            && $stabilityGate < $schemaRecheck
            && $schemaRecheck < $targetRootGate
            && $targetRootGate < $readWriteGate
            && $readWriteGate < $applyCall,
        'apply mutation call is not strictly after stability, schema, path-identity, and read-write gates'
    );
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($temporary);
}

fwrite(STDOUT, "legacy file migration offline smoke passed ({$checks} checks; no network or database access)" . PHP_EOL);
