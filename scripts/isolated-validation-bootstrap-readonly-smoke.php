#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\facade\Db;

require __DIR__ . '/lib/isolated-validation-bootstrap.php';
require __DIR__ . '/create-isolated-validation-clone.php';

$stage = 'environment';
try {
    $projectRoot = rtrim((string) getenv('OA_ISOLATED_PROJECT_ROOT'), "/\\");
    $runtimePath = rtrim((string) getenv('OA_ISOLATED_RUNTIME_PATH'), "/\\");
    $expectedDatabase = trim((string) getenv('OA_ISOLATED_DB_NAME'));
    $stage = 'boot';
    Oa\IsolatedValidation\boot($projectRoot, $runtimePath);
    $stage = 'query';
    $rows = Db::query('SELECT DATABASE() AS current_database');
    $actualDatabase = count($rows) === 1 ? trim((string) ($rows[0]['current_database'] ?? '')) : '';
    $stage = 'compare';
    if ($expectedDatabase === '' || $actualDatabase === '' || !hash_equals($expectedDatabase, $actualDatabase)) {
        throw new RuntimeException('isolated bootstrap database binding failed');
    }
    $stage = 'structure';
    $canonicalStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint(
        'oa2026_rehearsal_r6_20260718_r10_migrated'
    );
    $isolatedStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($expectedDatabase);
    if ($canonicalStructure !== $isolatedStructure
        || ($canonicalStructure['tableCount'] ?? -1) !== 124
        || ($canonicalStructure['foreignKeyConstraintCount'] ?? -1) !== 42
        || ($canonicalStructure['nonTableObjectCount'] ?? -1) !== 0
    ) {
        throw new RuntimeException('isolated structure binding failed');
    }
    fwrite(STDOUT, "isolated bootstrap database binding passed\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "isolated bootstrap database binding failed at {$stage} (" . get_class($exception) . ")\n");
    exit(1);
}
