#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/prepare-r10-isolated-validation.php';
require_once __DIR__ . '/audit-r10-isolated-validation-provenance.php';

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
    isolated_evidence_smoke_expect_failure(
        static fn (): string => prepareRelativeProjectPath($caseRoot, $caseEscapedChild)
    );
} finally {
    if (is_link($symlinkAlias)) {
        @unlink($symlinkAlias);
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
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
