#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/prepare-r10-isolated-validation.php';
require_once __DIR__ . '/audit-r10-isolated-validation-provenance.php';
require_once __DIR__ . '/create-isolated-validation-clone.php';
require_once __DIR__ . '/lib/oa-database-migration.php';

function isolated_evidence_smoke_expect_failure(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException('expected isolated evidence policy failure was not raised');
}

$connectionAttempts = 0;
$connectionFactory = static function () use (&$connectionAttempts): PDO {
    $connectionAttempts++;

    return new class () extends PDO {
        public function __construct()
        {
        }
    };
};

isolated_evidence_smoke_expect_failure(static function () use (&$connectionAttempts, $connectionFactory): void {
    preparePdo([
        'hostname' => '192.0.2.10',
        'hostport' => 3306,
        'username' => 'fixture',
        'password' => 'fixture',
    ], 'fixture_target', '127.0.0.1', $connectionFactory);
});
if ($connectionAttempts !== 0) {
    throw new RuntimeException('remote environment host reached the PDO connection factory');
}

$manifestTables = [
    'fixture_table' => [
        'engine' => 'InnoDB',
        'collation' => 'utf8mb4_0900_ai_ci',
        'columns' => [
            'id' => [
                'ordinal' => 1,
                'type' => 'bigint',
                'nullable' => 'NO',
                'default' => null,
                'extra' => '',
                'charset' => null,
                'collation' => null,
                'generationExpression' => '',
            ],
        ],
        'indexes' => [
            'PRIMARY' => [
                'unique' => true,
                'type' => 'BTREE',
                'columns' => [['name' => 'id', 'prefix' => null, 'collation' => 'A']],
            ],
        ],
        'foreignKeys' => [],
    ],
];
$manifestHash = Oa\DatabaseMigration\DatabaseManifest::schemaHash($manifestTables);
$structureHash = Oa\IsolatedValidationClone\databaseStructurePayloadSha256([
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_0900_ai_ci',
    'tables' => [
        'fixture_table' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'ddl' => 'CREATE TABLE `fixture_table` (`id` bigint NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB',
        ],
    ],
    'foreignKeys' => [],
    'nonTableObjects' => ['views' => 0, 'triggers' => 0, 'routines' => 0, 'events' => 0],
]);
if (hash_equals($manifestHash, $structureHash)) {
    throw new RuntimeException('independent schema hash algorithms unexpectedly converged');
}
$targetFinalFixture = [
    'database' => 'fixture_canonical',
    'tableCount' => 1,
    'tables' => $manifestTables,
    'rowCounts' => ['fixture_table' => 7],
    'schemaSha256' => $manifestHash,
];
$cloneMarkerFixture = [
    'rowCounts' => ['fixture_table' => 7],
    'structureHashAlgorithm' => 'show-create-structure-v1',
    'schemaSha256' => $structureHash,
];
prepareAssertTargetFinalCloneBaseline($targetFinalFixture, $cloneMarkerFixture, 'fixture_canonical', 1);
prepareAssertTargetFinalManifestSelfConsistent($targetFinalFixture);
prepareAssertSchemaManifestMatchesTargetFinal([
    'tableCount' => 1,
    'tables' => $manifestTables,
    'schemaSha256' => $manifestHash,
], $targetFinalFixture, 1, 'offline fixture');
isolated_evidence_smoke_expect_failure(static function () use ($manifestTables, $targetFinalFixture): void {
    prepareAssertSchemaManifestMatchesTargetFinal([
        'tableCount' => 1,
        'tables' => $manifestTables,
        'schemaSha256' => str_repeat('0', 64),
    ], $targetFinalFixture, 1, 'tampered offline fixture');
});
isolated_evidence_smoke_expect_failure(static function () use ($targetFinalFixture): void {
    $tampered = $targetFinalFixture;
    $tampered['schemaSha256'] = str_repeat('0', 64);
    prepareAssertTargetFinalManifestSelfConsistent($tampered);
});
$selfConsistentForgery = $targetFinalFixture;
$selfConsistentForgery['tables']['fixture_table']['columns']['id']['type'] = 'varchar(64)';
$selfConsistentForgery['schemaSha256'] = Oa\DatabaseMigration\DatabaseManifest::schemaHash(
    $selfConsistentForgery['tables']
);
prepareAssertTargetFinalManifestSelfConsistent($selfConsistentForgery);
isolated_evidence_smoke_expect_failure(
    static function () use ($manifestTables, $selfConsistentForgery, $manifestHash): void {
        prepareAssertSchemaManifestMatchesTargetFinal([
            'tableCount' => 1,
            'tables' => $manifestTables,
            'schemaSha256' => $manifestHash,
        ], $selfConsistentForgery, 1, 'self-consistent forged fixture');
    }
);

$pdo = preparePdo([
    'hostname' => 'localhost',
    'hostport' => 3306,
    'username' => 'fixture',
    'password' => 'fixture',
], 'fixture_target', '127.0.0.1', $connectionFactory);
if (!$pdo instanceof PDO || $connectionAttempts !== 1) {
    throw new RuntimeException('loopback host preflight did not reach the injected offline PDO factory exactly once');
}

$prepareSource = file_get_contents(__DIR__ . '/prepare-r10-isolated-validation.php');
if (!is_string($prepareSource)) {
    throw new RuntimeException('prepare source is unavailable');
}
$initializePosition = strpos($prepareSource, '$app->initialize();');
$rebindPosition = strpos($prepareSource, '$connection = prepareDatabaseConnectionConfiguration(', (int) $initializePosition);
$fingerprintPosition = strpos(
    $prepareSource,
    'databaseStructureFingerprint($canonical)',
    (int) $initializePosition
);
if ($initializePosition === false
    || $rebindPosition === false
    || $fingerprintPosition === false
    || $rebindPosition >= $fingerprintPosition
) {
    throw new RuntimeException('database host rejection/rebinding no longer precedes structure fingerprinting');
}

$canonical = Oa\IsolatedValidationAudit\canonicalDatabaseFromParameters([
    'canonicalDatabase' => 'fixture_canonical',
]);
if ($canonical !== 'fixture_canonical') {
    throw new RuntimeException('audit canonical database was not read from explicit parameters');
}
isolated_evidence_smoke_expect_failure(
    static fn (): string => Oa\IsolatedValidationAudit\canonicalDatabaseFromParameters([])
);

$temporary = tempnam(sys_get_temp_dir(), 'oa-isolated-evidence-');
if ($temporary === false) {
    throw new RuntimeException('unable to create isolated evidence smoke fixture');
}
$symlinkAlias = $temporary . '-alias';
$prepareCaseRoot = $temporary . '-case-root';
try {
    $original = "{\"status\":\"completed\"}\n";
    if (file_put_contents($temporary, $original, LOCK_EX) === false) {
        throw new RuntimeException('unable to write isolated evidence smoke fixture');
    }
    $expectedSha256 = hash('sha256', $original);
    $decoded = Oa\IsolatedValidationAudit\readPinnedJson(
        $temporary,
        'offline fixture',
        $expectedSha256
    );
    if (($decoded['status'] ?? null) !== 'completed') {
        throw new RuntimeException('audit rejected intact pinned evidence');
    }

    if (file_put_contents($temporary, "{\"status\":\"replaced\"}\n", LOCK_EX) === false) {
        throw new RuntimeException('unable to replace isolated evidence smoke fixture');
    }
    isolated_evidence_smoke_expect_failure(
        static fn (): array => Oa\IsolatedValidationAudit\readPinnedJson(
            $temporary,
            'offline fixture',
            $expectedSha256
        )
    );

    if (!@symlink($temporary, $symlinkAlias)) {
        throw new RuntimeException('unable to create the symlink alias smoke fixture');
    }
    $privateRoot = realpath(dirname($temporary));
    if ($privateRoot === false) {
        throw new RuntimeException('unable to resolve the symlink alias smoke root');
    }
    isolated_evidence_smoke_expect_failure(
        static fn (): string => Oa\IsolatedValidationAudit\resolveEvidenceCandidate(
            $symlinkAlias,
            $privateRoot,
            'symlink alias fixture'
        )
    );

    $caseExpected = DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'Evidence.json';
    $caseVariant = DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'evidence.json';
    if (Oa\IsolatedValidationAudit\sameEvidencePath($caseExpected, $caseVariant)) {
        throw new RuntimeException('audit accepted a case-variant evidence path');
    }
    $caseRoot = DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'runtime';
    $caseEscapedChild = DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR
        . 'Runtime' . DIRECTORY_SEPARATOR . 'marker.json';
    if (Oa\IsolatedValidationAudit\containedPath($caseEscapedChild, $caseRoot)) {
        throw new RuntimeException('audit containment accepted a case-variant sibling path');
    }
    if (prepareContainedPath($caseEscapedChild, $caseRoot)) {
        throw new RuntimeException('prepare containment accepted a case-variant sibling path');
    }
    if (!mkdir($prepareCaseRoot, 0700)) {
        throw new RuntimeException('unable to create the prepare containment smoke root');
    }
    $prepareRuntime = $prepareCaseRoot . DIRECTORY_SEPARATOR . 'runtime';
    if (!mkdir($prepareRuntime, 0700)) {
        throw new RuntimeException('unable to create the prepare containment runtime');
    }
    $prepareMarker = $prepareRuntime . DIRECTORY_SEPARATOR . 'marker.json';
    if (file_put_contents($prepareMarker, "{}\n", LOCK_EX) === false
        || !prepareContainedPath($prepareMarker, $prepareRuntime)
    ) {
        throw new RuntimeException('prepare containment rejected an existing canonical child');
    }
    $prepareCaseVariantRuntime = $prepareCaseRoot . DIRECTORY_SEPARATOR . 'Runtime';
    if (@mkdir($prepareCaseVariantRuntime, 0700)) {
        $prepareCaseVariantMarker = $prepareCaseVariantRuntime . DIRECTORY_SEPARATOR . 'marker.json';
        if (file_put_contents($prepareCaseVariantMarker, "{}\n", LOCK_EX) === false
            || prepareContainedPath($prepareCaseVariantMarker, $prepareRuntime)
        ) {
            throw new RuntimeException('prepare containment accepted an existing Linux case-variant sibling');
        }
    } elseif (realpath($prepareCaseVariantRuntime) !== realpath($prepareRuntime)
        || !prepareContainedPath($prepareCaseVariantRuntime . DIRECTORY_SEPARATOR . 'marker.json', $prepareRuntime)
    ) {
        throw new RuntimeException('prepare containment rejected a Windows case-normalized child');
    }
    isolated_evidence_smoke_expect_failure(
        static fn (): string => prepareRelativeProjectPath($caseRoot, $caseEscapedChild)
    );
} finally {
    if (is_link($symlinkAlias)) {
        @unlink($symlinkAlias);
    }
    foreach ([
        $prepareCaseRoot . DIRECTORY_SEPARATOR . 'Runtime' . DIRECTORY_SEPARATOR . 'marker.json',
        $prepareCaseRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'marker.json',
    ] as $prepareCaseMarker) {
        if (is_file($prepareCaseMarker)) {
            @unlink($prepareCaseMarker);
        }
    }
    foreach ([
        $prepareCaseRoot . DIRECTORY_SEPARATOR . 'Runtime',
        $prepareCaseRoot . DIRECTORY_SEPARATOR . 'runtime',
        $prepareCaseRoot,
    ] as $prepareCaseDirectory) {
        if (is_dir($prepareCaseDirectory)) {
            @rmdir($prepareCaseDirectory);
        }
    }
    @unlink($temporary);
}

echo json_encode([
    'status' => 'passed',
    'databaseConnectionsOpened' => 0,
    'databaseWritesPerformed' => false,
    'networkConnectionsOpened' => 0,
    'remoteHostRejectedBeforeConnectionFactory' => true,
    'auditParameterSourceVerified' => true,
    'samePathReplacementRejected' => true,
    'symlinkAliasRejectedBeforeRealpath' => true,
    'caseVariantPathRejected' => true,
    'prepareCaseVariantPathRejected' => true,
    'prepareRealpathContainmentVerified' => true,
    'independentSchemaHashAlgorithmsVerified' => true,
    'manifestSchemaContractVerified' => true,
    'selfConsistentManifestForgeryRejected' => true,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
