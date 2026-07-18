#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\App;

function prepareQuoteIdentifier(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        throw new RuntimeException('isolated validation encountered an unsafe identifier');
    }

    return '`' . $value . '`';
}

function prepareChecksum(PDO $pdo, string $database, string $table): string
{
    $qualified = prepareQuoteIdentifier($database) . '.' . prepareQuoteIdentifier($table);
    $row = $pdo->query("CHECKSUM TABLE {$qualified}")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('isolated validation checksum is unavailable');
    }
    foreach ($row as $column => $value) {
        if (strtolower((string) preg_replace('/[^a-z]/i', '', (string) $column)) === 'checksum'
            && $value !== null
            && $value !== ''
        ) {
            return (string) $value;
        }
    }

    throw new RuntimeException('isolated validation checksum is unavailable');
}

/** @param array<string, mixed> $connection */
function preparePdo(array $connection, string $database): PDO
{
    $host = strtolower(trim((string) ($connection['hostname'] ?? '')));
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('isolated validation refuses a non-loopback database host');
    }
    $port = (int) ($connection['hostport'] ?? 3306);

    return new PDO(
        "mysql:host=127.0.0.1;port={$port};dbname={$database};charset=utf8mb4",
        (string) ($connection['username'] ?? ''),
        (string) ($connection['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

/** @return list<string> */
function prepareTables(PDO $pdo, string $database): array
{
    $statement = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES "
        . "WHERE BINARY TABLE_SCHEMA=BINARY ? AND TABLE_TYPE='BASE TABLE' ORDER BY BINARY TABLE_NAME"
    );
    $statement->execute([$database]);

    return array_map(
        static fn (array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
}

$projectRoot = dirname(__DIR__);
$runtimeRoot = $projectRoot . DIRECTORY_SEPARATOR . 'runtime';
$pointerPath = $runtimeRoot . DIRECTORY_SEPARATOR . 'isolated-r10-validation-active-private.json';
if (file_exists($pointerPath)) {
    throw new RuntimeException('isolated validation pointer already exists');
}

$candidates = glob($runtimeRoot . DIRECTORY_SEPARATOR . 'isolated-r10-validation-final-20260718_*', GLOB_ONLYDIR) ?: [];
$completed = [];
foreach ($candidates as $candidate) {
    $marker = $candidate . DIRECTORY_SEPARATOR . 'clone-completed.json';
    if (is_file($marker) && !is_file($candidate . DIRECTORY_SEPARATOR . 'failed.json')) {
        $completed[] = [$candidate, $marker];
    }
}
if (count($completed) !== 1) {
    throw new RuntimeException('isolated validation requires exactly one completed final clone');
}

[$manifestDirectory, $markerPath] = $completed[0];
$marker = json_decode((string) file_get_contents($markerPath), true, 512, JSON_THROW_ON_ERROR);
$database = (string) ($marker['targetDatabase'] ?? '');
if (($marker['status'] ?? '') !== 'completed'
    || (int) ($marker['tableCount'] ?? 0) !== 124
    || (int) ($marker['foreignKeyConstraintCount'] ?? 0) !== 42
    || ($marker['foreignKeyDefinitionsMatch'] ?? false) !== true
    || ($marker['contentChecksumsMatch'] ?? false) !== true
    || ($marker['sourceConsistencyWindowPassed'] ?? false) !== true
    || ($marker['nonTableObjectsAbsent'] ?? false) !== true
    || preg_match('/^[a-f0-9]{64}$/', (string) ($marker['schemaSha256'] ?? '')) !== 1
    || ($marker['sourceWritesPerformed'] ?? true) !== false
    || (string) ($marker['sourceDatabase'] ?? '') !== 'oa2026_rehearsal_r6_20260718_r10_migrated'
    || preg_match('/^oa2026_r10_validation_20260718_[a-f0-9]{8}$/', $database) !== 1
) {
    throw new RuntimeException('isolated validation clone evidence is incomplete');
}
$rowCounts = (array) ($marker['rowCounts'] ?? []);
$tableChecksums = (array) ($marker['tableChecksums'] ?? []);
if (count($rowCounts) !== 124 || count($tableChecksums) !== 124) {
    throw new RuntimeException('isolated validation clone fingerprints are incomplete');
}
$expectedTables = array_keys($tableChecksums);
$rowCountTables = array_keys($rowCounts);
sort($expectedTables, SORT_STRING);
sort($rowCountTables, SORT_STRING);
if ($expectedTables !== $rowCountTables) {
    throw new RuntimeException('isolated validation clone fingerprint table sets differ');
}
foreach ($expectedTables as $table) {
    prepareQuoteIdentifier((string) $table);
    if (!is_numeric($rowCounts[$table] ?? null)
        || (int) $rowCounts[$table] < 0
        || trim((string) ($tableChecksums[$table] ?? '')) === ''
    ) {
        throw new RuntimeException('isolated validation clone fingerprint is invalid');
    }
}

$loader = require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!$loader instanceof \Composer\Autoload\ClassLoader) {
    throw new RuntimeException('composer autoloader is unavailable');
}
$loader->setPsr4('app\\', [$projectRoot . DIRECTORY_SEPARATOR . 'app']);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'create-isolated-validation-clone.php';
$app = new App($projectRoot);
$app->initialize();
$sourceDatabase = (string) $marker['sourceDatabase'];
$sourceStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($sourceDatabase);
$targetStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($database);
if ($sourceStructure !== $targetStructure
    || ($sourceStructure['schemaSha256'] ?? null) !== (string) $marker['schemaSha256']
    || ($sourceStructure['tableCount'] ?? -1) !== 124
    || ($sourceStructure['foreignKeyConstraintCount'] ?? -1) !== 42
    || ($sourceStructure['nonTableObjectCount'] ?? -1) !== 0
) {
    throw new RuntimeException('isolated validation current structure differs from clone evidence');
}
$connection = (array) $app->config->get('database.connections.mysql', []);
$sourcePdo = preparePdo($connection, $sourceDatabase);
$targetPdo = preparePdo($connection, $database);
if (prepareTables($sourcePdo, $sourceDatabase) !== $expectedTables
    || prepareTables($targetPdo, $database) !== $expectedTables
) {
    throw new RuntimeException('isolated validation current table set differs from clone evidence');
}
foreach ($expectedTables as $table) {
    $sourceQualified = prepareQuoteIdentifier($sourceDatabase) . '.' . prepareQuoteIdentifier((string) $table);
    $targetQualified = prepareQuoteIdentifier($database) . '.' . prepareQuoteIdentifier((string) $table);
    $expectedRows = (int) $rowCounts[$table];
    if ((int) $sourcePdo->query("SELECT COUNT(*) FROM {$sourceQualified}")->fetchColumn() !== $expectedRows
        || (int) $targetPdo->query("SELECT COUNT(*) FROM {$targetQualified}")->fetchColumn() !== $expectedRows
        || !hash_equals((string) $tableChecksums[$table], prepareChecksum($sourcePdo, $sourceDatabase, (string) $table))
        || !hash_equals((string) $tableChecksums[$table], prepareChecksum($targetPdo, $database, (string) $table))
    ) {
        throw new RuntimeException('isolated validation current content differs from clone evidence');
    }
}

$serverRuntime = $manifestDirectory . DIRECTORY_SEPARATOR . 'server-runtime';
$runtimeReal = realpath($runtimeRoot);
$manifestReal = realpath($manifestDirectory);
if ($runtimeReal === false
    || $manifestReal === false
    || is_link($manifestDirectory)
    || !str_starts_with(
        strtolower($manifestReal . DIRECTORY_SEPARATOR),
        strtolower(rtrim($runtimeReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
    )
) {
    throw new RuntimeException('isolated validation paths escaped the private runtime root');
}
if (!mkdir($serverRuntime, 0700) && !is_dir($serverRuntime)) {
    throw new RuntimeException('isolated validation runtime could not be created');
}
$serverRuntimeReal = realpath($serverRuntime);
if ($serverRuntimeReal === false
    || is_link($serverRuntime)
    || !str_starts_with(
        strtolower($serverRuntimeReal . DIRECTORY_SEPARATOR),
        strtolower(rtrim($manifestReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
    )
) {
    throw new RuntimeException('isolated validation runtime escaped the private manifest');
}
$relativeManifest = str_replace('\\', '/', substr($manifestDirectory, strlen($projectRoot) + 1));
$json = json_encode([
    'database' => $database,
    'manifest' => $relativeManifest,
    'serverRuntime' => str_replace('\\', '/', $relativeManifest . '/server-runtime'),
    'createdAt' => gmdate(DATE_ATOM),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$pointerTemporary = $pointerPath . '.tmp-' . bin2hex(random_bytes(4));
if (file_put_contents($pointerTemporary, $json . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('isolated validation pointer could not be staged');
}
if (file_exists($pointerPath) || !rename($pointerTemporary, $pointerPath)) {
    @unlink($pointerTemporary);
    throw new RuntimeException('isolated validation pointer could not be finalized');
}

echo json_encode([
    'status' => 'prepared',
    'tableCount' => 124,
    'foreignKeyConstraintCount' => 42,
    'helpersReady' => true,
    'pointerCreated' => true,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
