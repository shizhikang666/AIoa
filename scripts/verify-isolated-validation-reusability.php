#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\facade\Db;

require __DIR__ . '/isolated-approval-validation-client.php';

/** @param array<string, mixed> $value */
function emitReuseVerification(array $value, int $exitCode): never
{
    fwrite(STDOUT, json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL);
    exit($exitCode);
}

$stage = 'environment';
try {
    $projectRoot = rtrim((string) getenv('OA_ISOLATED_PROJECT_ROOT'), "/\\");
    $runtimePath = rtrim((string) getenv('OA_ISOLATED_RUNTIME_PATH'), "/\\");
    $database = trim((string) getenv('OA_ISOLATED_DB_NAME'));
    if ($projectRoot === ''
        || $runtimePath === ''
        || preg_match('/^oa2026_r10_validation_20260718_[a-f0-9]{8}$/', $database) !== 1
    ) {
        throw new RuntimeException('isolated reuse verification environment is incomplete');
    }

    $stage = 'paths';
    $runtimeReal = realpath($runtimePath);
    $manifestReal = $runtimeReal === false ? false : realpath(dirname($runtimeReal));
    $cloneMarkerPath = $manifestReal === false ? '' : $manifestReal . DIRECTORY_SEPARATOR . 'clone-completed.json';
    $cloneMarkerReal = $cloneMarkerPath === '' ? false : realpath($cloneMarkerPath);
    if ($runtimeReal === false
        || $manifestReal === false
        || $cloneMarkerReal === false
        || strcasecmp(dirname($cloneMarkerReal), $manifestReal) !== 0
        || basename($cloneMarkerReal) !== 'clone-completed.json'
    ) {
        throw new RuntimeException('isolated reuse verification paths are invalid');
    }

    $stage = 'clone-evidence';
    $marker = json_decode((string) file_get_contents($cloneMarkerReal), true, 512, JSON_THROW_ON_ERROR);
    $rowCounts = is_array($marker['rowCounts'] ?? null) ? $marker['rowCounts'] : [];
    $tableChecksums = is_array($marker['tableChecksums'] ?? null) ? $marker['tableChecksums'] : [];
    if (($marker['status'] ?? null) !== 'completed'
        || ($marker['sourceDatabase'] ?? null) !== 'oa2026_rehearsal_r6_20260718_r10_migrated'
        || ($marker['targetDatabase'] ?? null) !== $database
        || ($marker['sourceConsistencyWindowPassed'] ?? null) !== true
        || ($marker['nonTableObjectsAbsent'] ?? null) !== true
        || ($marker['sourceWritesPerformed'] ?? null) !== false
        || count($rowCounts) !== 124
        || count($tableChecksums) !== 124
        || preg_match('/^[a-f0-9]{64}$/', (string) ($marker['schemaSha256'] ?? '')) !== 1
    ) {
        throw new RuntimeException('isolated reuse verification evidence is incomplete');
    }

    $stage = 'boot';
    $app = Oa\IsolatedValidation\boot($projectRoot, $runtimePath);
    $connection = (array) $app->config->get('database.connections.mysql', []);
    $stage = 'binding';
    $actualRows = Db::query('SELECT DATABASE() AS current_database');
    $actualDatabase = count($actualRows) === 1
        ? trim((string) ($actualRows[0]['current_database'] ?? ''))
        : '';
    if ($actualDatabase === '' || !hash_equals($database, $actualDatabase)) {
        throw new RuntimeException('isolated reuse verification database binding failed');
    }

    $stage = 'expected-fingerprints';
    $expectedFingerprints = [];
    foreach ($tableChecksums as $table => $checksum) {
        if (!array_key_exists($table, $rowCounts)
            || preg_match('/^[a-z0-9_]+$/', (string) $table) !== 1
            || !is_numeric($rowCounts[$table])
            || (int) $rowCounts[$table] < 0
            || trim((string) $checksum) === ''
        ) {
            throw new RuntimeException('isolated reuse verification fingerprint evidence is invalid');
        }
        $expectedFingerprints[(string) $table] = [
            'rowCount' => (int) $rowCounts[$table],
            'checksum' => (string) $checksum,
        ];
    }
    ksort($expectedFingerprints, SORT_STRING);

    $stage = 'structure';
    $canonicalDatabase = 'oa2026_rehearsal_r6_20260718_r10_migrated';
    $canonicalStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($canonicalDatabase);
    $isolatedStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($database);
    $stage = 'content';
    $canonicalFingerprints = databaseFingerprints($connection, $canonicalDatabase);
    $isolatedFingerprints = databaseFingerprints($connection, $database);
    $structureMatches = $canonicalStructure === $isolatedStructure
        && ($canonicalStructure['schemaSha256'] ?? null) === (string) $marker['schemaSha256']
        && ($canonicalStructure['tableCount'] ?? -1) === 124
        && ($canonicalStructure['foreignKeyConstraintCount'] ?? -1) === 42
        && ($canonicalStructure['nonTableObjectCount'] ?? -1) === 0;
    $contentMatches = $canonicalFingerprints === $expectedFingerprints
        && $isolatedFingerprints === $expectedFingerprints;
    if (!$structureMatches || !$contentMatches) {
        emitReuseVerification([
            'status' => 'invalid',
            'reusable' => false,
            'structureMatches' => $structureMatches,
            'contentMatches' => $contentMatches,
        ], 2);
    }

    emitReuseVerification([
        'status' => 'reusable',
        'reusable' => true,
        'structureMatches' => true,
        'contentMatches' => true,
        'tableCount' => 124,
    ], 0);
} catch (Throwable) {
    emitReuseVerification([
        'status' => 'unknown',
        'reusable' => false,
        'stage' => $stage,
    ], 3);
}
