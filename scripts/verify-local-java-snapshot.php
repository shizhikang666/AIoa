#!/usr/bin/env php
<?php

declare(strict_types=1);

use Oa\DatabaseMigration\DatabaseManifest;
use Oa\DatabaseMigration\MigrationSafety;
use Oa\DatabaseMigration\MysqlProfile;
use Oa\DatabaseMigration\SchemaPolicy;

require __DIR__ . '/lib/oa-database-migration.php';

/** @return array<string, string> */
function snapshot_verifier_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new RuntimeException('snapshot verifier options must use --name=value');
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, [
            'source-defaults',
            'source-db',
            'snapshot-defaults',
            'snapshot-db',
            'row-reference-defaults',
            'row-reference-db',
            'source-frozen',
        ], true)) {
            throw new RuntimeException('snapshot verifier received an unknown option');
        }
        if (array_key_exists($name, $options)) {
            throw new RuntimeException('snapshot verifier received a duplicate option');
        }
        $options[$name] = $value;
    }

    return $options;
}

try {
    $options = snapshot_verifier_options($argv);
    foreach (['source-defaults', 'source-db', 'snapshot-defaults', 'snapshot-db'] as $required) {
        if (!isset($options[$required]) || trim($options[$required]) === '') {
            throw new RuntimeException('snapshot verifier is missing a required option');
        }
    }
    $sourceDatabase = MigrationSafety::identifier($options['source-db'], 'source database');
    $snapshotDatabase = MigrationSafety::identifier($options['snapshot-db'], 'snapshot database');
    if (preg_match('/^oa2026_java_snapshot_\d{8}(?:_[a-z0-9]+)?$/', $snapshotDatabase) !== 1) {
        throw new RuntimeException('snapshot database name is outside the guarded namespace');
    }

    $source = MysqlProfile::fromDefaultsFile($options['source-defaults']);
    $snapshot = MysqlProfile::fromDefaultsFile($options['snapshot-defaults']);
    if (($snapshot->childConnection()['remote'] ?? true) !== false) {
        throw new RuntimeException('snapshot verifier refuses every non-loopback snapshot host');
    }
    $rowReferenceDatabase = trim((string)($options['row-reference-db'] ?? ''));
    $rowReferenceDefaults = trim((string)($options['row-reference-defaults'] ?? ''));
    if (($rowReferenceDatabase === '') !== ($rowReferenceDefaults === '')) {
        throw new RuntimeException('row-reference database and defaults must be supplied together');
    }
    $sourceFrozen = ($options['source-frozen'] ?? '') === '1';
    $compareSourceRows = $sourceFrozen && $rowReferenceDatabase === '';
    $verifyRows = $compareSourceRows || $rowReferenceDatabase !== '';

    $sourceManifest = DatabaseManifest::capture(
        $source->connect($sourceDatabase),
        $sourceDatabase,
        $compareSourceRows
    );
    $snapshotManifest = DatabaseManifest::capture(
        $snapshot->connect($snapshotDatabase),
        $snapshotDatabase,
        $verifyRows
    );
    $comparison = SchemaPolicy::compareSourceToTemplate($sourceManifest, $snapshotManifest);
    $differenceKeys = [
        'missingTables',
        'newTables',
        'columnMismatches',
        'extraColumns',
        'columnOrderMismatches',
        'tableDefinitionMismatches',
        'indexMismatches',
        'extraIndexes',
        'foreignKeyMismatches',
        'extraForeignKeys',
    ];
    foreach ($differenceKeys as $key) {
        if (($comparison[$key] ?? null) !== []) {
            throw new RuntimeException("snapshot schema fidelity failed at {$key}");
        }
    }
    if (($comparison['sourceTableCount'] ?? -1) !== 121
        || ($comparison['templateTableCount'] ?? -1) !== 121
        || ($comparison['sourceColumnCount'] ?? -1) !== 1836
        || ($comparison['templateColumnCount'] ?? -1) !== 1836
    ) {
        throw new RuntimeException('snapshot structure counts differ from the audited baseline');
    }
    $expectedRowCounts = $sourceManifest['rowCounts'] ?? [];
    if ($rowReferenceDatabase !== '') {
        $rowReferenceDatabase = MigrationSafety::identifier($rowReferenceDatabase, 'row-reference database');
        $rowReference = MysqlProfile::fromDefaultsFile($rowReferenceDefaults);
        if (($rowReference->childConnection()['remote'] ?? true) !== false) {
            throw new RuntimeException('row-reference verifier refuses every non-loopback host');
        }
        $rowReferenceManifest = DatabaseManifest::capture(
            $rowReference->connect($rowReferenceDatabase),
            $rowReferenceDatabase,
            true
        );
        if (($rowReferenceManifest['tableCount'] ?? -1) !== 121
            || ($rowReferenceManifest['columnCount'] ?? -1) !== 1836
        ) {
            throw new RuntimeException('row-reference structure differs from the audited baseline');
        }
        $expectedRowCounts = $rowReferenceManifest['rowCounts'] ?? [];
    }
    if ($verifyRows && $expectedRowCounts !== ($snapshotManifest['rowCounts'] ?? null)) {
        throw new RuntimeException('snapshot per-table row counts differ from the frozen/reference source');
    }

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'schemaEquivalent' => true,
        'perTableRowCountsVerified' => $verifyRows,
        'perTableRowCountsEquivalent' => $verifyRows ? true : null,
        'tableCount' => 121,
        'columnCount' => 1836,
        'sourceWritesPerformed' => false,
        'snapshotWritesPerformed' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'snapshot verification failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
