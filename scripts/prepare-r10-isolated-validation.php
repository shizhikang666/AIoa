#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\App;
use Oa\DatabaseMigration\DatabaseManifest;
use function Oa\IsolatedValidationParameters\databaseIdentifier;
use function Oa\IsolatedValidationParameters\expectedCount;
use function Oa\IsolatedValidationParameters\loopbackHost;
use function Oa\IsolatedValidationParameters\parseNamedOptions;
use function Oa\IsolatedValidationParameters\runDate;
use function Oa\IsolatedValidationParameters\runLabel;

require_once __DIR__ . '/lib/isolated-validation-parameters.php';

/** @return array<string, string> */
function prepareParseOptions(array $argv): array
{
    $options = parseNamedOptions($argv, [
        'canonical-db',
        'confirm-canonical-db',
        'target-db',
        'database-host',
        'run-label',
        'run-date',
        'expected-table-count',
        'expected-foreign-key-count',
        'manifest-dir',
        'pointer-path',
        'target-final-marker',
    ], [
        'canonical-db',
        'confirm-canonical-db',
        'target-db',
        'database-host',
        'run-label',
        'run-date',
        'expected-table-count',
        'expected-foreign-key-count',
        'manifest-dir',
        'pointer-path',
        'target-final-marker',
    ]);
    $canonical = databaseIdentifier($options['canonical-db'], 'canonical');
    if (!hash_equals($canonical, $options['confirm-canonical-db'])) {
        throw new RuntimeException('isolated validation canonical database confirmation does not match');
    }
    $target = databaseIdentifier($options['target-db'], 'target');
    if (strcasecmp($canonical, $target) === 0) {
        throw new RuntimeException('isolated validation target must differ from canonical database');
    }
    loopbackHost($options['database-host']);
    runLabel($options['run-label']);
    runDate($options['run-date']);
    expectedCount($options['expected-table-count'], 'expected table count', false);
    expectedCount($options['expected-foreign-key-count'], 'expected foreign key count', true);

    return $options;
}

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

/**
 * The clone marker schema hash fingerprints SHOW CREATE plus database-level metadata.
 * target-final schemaSha256 fingerprints DatabaseManifest::capture(). They are
 * deliberately independent hashes and must never be compared to each other.
 *
 * @param array<string, mixed> $targetFinal
 * @param array<string, mixed> $marker
 */
function prepareAssertTargetFinalCloneBaseline(
    array $targetFinal,
    array $marker,
    string $canonical,
    int $expectedTableCount
): void {
    $targetFinalRowCounts = is_array($targetFinal['rowCounts'] ?? null)
        ? $targetFinal['rowCounts']
        : [];
    $cloneRowCounts = is_array($marker['rowCounts'] ?? null)
        ? $marker['rowCounts']
        : [];
    ksort($targetFinalRowCounts, SORT_STRING);
    ksort($cloneRowCounts, SORT_STRING);
    if (($targetFinal['database'] ?? null) !== $canonical
        || (int) ($targetFinal['tableCount'] ?? -1) !== $expectedTableCount
        || preg_match('/^[a-f0-9]{64}$/', (string) ($targetFinal['schemaSha256'] ?? '')) !== 1
        || !is_array($targetFinal['tables'] ?? null)
        || count($targetFinal['tables']) !== $expectedTableCount
        || count($targetFinalRowCounts) !== $expectedTableCount
        || $targetFinalRowCounts !== $cloneRowCounts
    ) {
        throw new RuntimeException('isolated validation target-final evidence differs from the clone baseline');
    }
}

/**
 * @param array<string, mixed> $manifest
 * @param array<string, mixed> $targetFinal
 */
function prepareAssertSchemaManifestMatchesTargetFinal(
    array $manifest,
    array $targetFinal,
    int $expectedTableCount,
    string $label
): void {
    if ((int) ($manifest['tableCount'] ?? -1) !== $expectedTableCount
        || !hash_equals(
            (string) ($targetFinal['schemaSha256'] ?? ''),
            (string) ($manifest['schemaSha256'] ?? '')
        )
        || ($manifest['tables'] ?? null) !== ($targetFinal['tables'] ?? null)
    ) {
        throw new RuntimeException("isolated validation {$label} manifest differs from target-final evidence");
    }
}

/** @param array<string, mixed> $targetFinal */
function prepareAssertTargetFinalManifestSelfConsistent(array $targetFinal): void
{
    $tables = is_array($targetFinal['tables'] ?? null) ? $targetFinal['tables'] : [];
    $expected = (string) ($targetFinal['schemaSha256'] ?? '');
    if ($tables === []
        || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1
        || !hash_equals($expected, DatabaseManifest::schemaHash($tables))
    ) {
        throw new RuntimeException('isolated validation target-final schema manifest is not self-consistent');
    }
}

/** @param array<string, mixed> $connection @return array<string, mixed> */
function prepareDatabaseConnectionConfiguration(array $connection, string $explicitHost): array
{
    $configuredHost = loopbackHost((string) ($connection['hostname'] ?? ''));
    $databaseHost = loopbackHost($explicitHost);
    if (!hash_equals($databaseHost, $configuredHost)) {
        throw new RuntimeException('isolated validation database host differs from the explicit invocation');
    }
    $port = (int) ($connection['hostport'] ?? 3306);
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('isolated validation database port is invalid');
    }

    $connection['hostname'] = $databaseHost;
    $connection['hostport'] = $port;

    return $connection;
}

/**
 * @param array<string, mixed> $connection
 * @param null|callable(string,string,string,array<int|string, mixed>):PDO $factory
 */
function preparePdo(
    array $connection,
    string $database,
    string $explicitHost,
    ?callable $factory = null
): PDO {
    $connection = prepareDatabaseConnectionConfiguration($connection, $explicitHost);
    $databaseHost = (string) $connection['hostname'];
    $port = (int) $connection['hostport'];
    $factory ??= static fn (string $dsn, string $username, string $password, array $attributes): PDO => new PDO(
        $dsn,
        $username,
        $password,
        $attributes
    );

    $pdo = $factory(
        "mysql:host={$databaseHost};port={$port};dbname={$database};charset=utf8mb4",
        (string) ($connection['username'] ?? ''),
        (string) ($connection['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('isolated validation PDO factory returned an invalid connection');
    }

    return $pdo;
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

function prepareContainedPath(string $child, string $parent): bool
{
    $childReal = realpath($child);
    $parentReal = realpath($parent);
    if ($childReal === false || $parentReal === false) {
        return false;
    }
    $childPrefix = rtrim($childReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $parentPrefix = rtrim($parentReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($childPrefix, $parentPrefix);
}

function prepareExistingPrivatePath(
    string $projectRoot,
    string $runtimeRoot,
    string $value,
    bool $directory,
    string $label
): string {
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($value));
    if ($normalized === '' || preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/', $normalized) === 1) {
        throw new RuntimeException("{$label} must be a relative private runtime path");
    }
    $candidate = $projectRoot . DIRECTORY_SEPARATOR . $normalized;
    $real = realpath($candidate);
    if ($real === false
        || is_link($candidate)
        || is_link($real)
        || !prepareContainedPath($real, $runtimeRoot)
        || ($directory && !is_dir($real))
        || (!$directory && !is_file($real))
    ) {
        throw new RuntimeException("{$label} is missing or outside private runtime");
    }

    return $real;
}

function prepareNewPrivateFile(string $projectRoot, string $runtimeRoot, string $value, string $label): string
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($value));
    if ($normalized === '' || preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/', $normalized) === 1) {
        throw new RuntimeException("{$label} must be a relative private runtime path");
    }
    $candidate = $projectRoot . DIRECTORY_SEPARATOR . $normalized;
    $parent = realpath(dirname($candidate));
    if ($parent === false
        || is_link($parent)
        || !prepareContainedPath($parent, $runtimeRoot)
        || file_exists($candidate)
        || is_link($candidate)
    ) {
        throw new RuntimeException("{$label} is unsafe or already exists");
    }

    return $candidate;
}

function prepareRelativeProjectPath(string $projectRoot, string $absolute): string
{
    $prefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($absolute, $prefix)) {
        throw new RuntimeException('isolated validation private path escaped the project root');
    }

    return str_replace('\\', '/', substr($absolute, strlen($prefix)));
}

/** @return array{raw:string,sha256:string} */
function prepareReadPinnedEvidence(string $path, string $label): array
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("{$label} is missing or unsafe");
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException("{$label} is unreadable");
    }

    return [
        'raw' => $raw,
        'sha256' => hash('sha256', $raw),
    ];
}

/** @return array<string, bool|int|string> */
function prepareIsolatedValidation(array $argv): array
{
    $options = prepareParseOptions($argv);
    $projectRoot = realpath(dirname(__DIR__));
    if ($projectRoot === false || is_link($projectRoot)) {
        throw new RuntimeException('isolated validation project root is unavailable');
    }
    $runtimeRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'runtime');
    if ($runtimeRoot === false || is_link($runtimeRoot)) {
        throw new RuntimeException('isolated validation private runtime root is unavailable');
    }

    $canonical = databaseIdentifier($options['canonical-db'], 'canonical');
    $explicitTarget = databaseIdentifier($options['target-db'], 'target');
    $databaseHost = loopbackHost($options['database-host']);
    $validationRunLabel = runLabel($options['run-label']);
    $validationRunDate = runDate($options['run-date']);
    $expectedTableCount = expectedCount($options['expected-table-count'], 'expected table count', false);
    $expectedForeignKeyCount = expectedCount(
        $options['expected-foreign-key-count'],
        'expected foreign key count',
        true
    );
    $manifestDirectory = prepareExistingPrivatePath(
        $projectRoot,
        $runtimeRoot,
        $options['manifest-dir'],
        true,
        'clone manifest'
    );
    $targetFinalMarker = prepareExistingPrivatePath(
        $projectRoot,
        $runtimeRoot,
        $options['target-final-marker'],
        false,
        'target-final marker'
    );
    $pointerPath = prepareNewPrivateFile(
        $projectRoot,
        $runtimeRoot,
        $options['pointer-path'],
        'isolated validation pointer'
    );

    $markerPath = $manifestDirectory . DIRECTORY_SEPARATOR . 'clone-completed.json';
    if (!is_file($markerPath) || is_link($markerPath) || is_file($manifestDirectory . DIRECTORY_SEPARATOR . 'failed.json')) {
        throw new RuntimeException('isolated validation clone evidence is unavailable');
    }
    $markerEvidence = prepareReadPinnedEvidence($markerPath, 'clone-completed marker');
    $marker = json_decode($markerEvidence['raw'], true, 512, JSON_THROW_ON_ERROR);
    $database = databaseIdentifier((string) ($marker['targetDatabase'] ?? ''), 'target');
    if (!hash_equals($explicitTarget, $database)
        || strcasecmp($canonical, $database) === 0
        || ($marker['status'] ?? '') !== 'completed'
        || (string) ($marker['sourceDatabase'] ?? '') !== $canonical
        || (string) ($marker['runLabel'] ?? '') !== $validationRunLabel
        || (string) ($marker['runDate'] ?? '') !== $validationRunDate
        || (string) ($marker['databaseHost'] ?? '') !== $databaseHost
        || (int) ($marker['expectedTableCount'] ?? -1) !== $expectedTableCount
        || (int) ($marker['expectedForeignKeyConstraintCount'] ?? -1) !== $expectedForeignKeyCount
        || (int) ($marker['tableCount'] ?? -1) !== $expectedTableCount
        || (int) ($marker['foreignKeyConstraintCount'] ?? -1) !== $expectedForeignKeyCount
        || ($marker['foreignKeyDefinitionsMatch'] ?? false) !== true
        || ($marker['contentChecksumsMatch'] ?? false) !== true
        || ($marker['sourceConsistencyWindowPassed'] ?? false) !== true
        || ($marker['nonTableObjectsAbsent'] ?? false) !== true
        || ($marker['structureHashAlgorithm'] ?? '') !== 'show-create-structure-v1'
        || preg_match('/^[a-f0-9]{64}$/', (string) ($marker['schemaSha256'] ?? '')) !== 1
        || ($marker['sourceWritesPerformed'] ?? true) !== false
    ) {
        throw new RuntimeException('isolated validation clone evidence is incomplete');
    }
    $rowCounts = (array) ($marker['rowCounts'] ?? []);
    $tableChecksums = (array) ($marker['tableChecksums'] ?? []);
    if (count($rowCounts) !== $expectedTableCount || count($tableChecksums) !== $expectedTableCount) {
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
    $targetFinalEvidence = prepareReadPinnedEvidence($targetFinalMarker, 'target-final marker');
    $targetFinal = json_decode($targetFinalEvidence['raw'], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($targetFinal)) {
        throw new RuntimeException('isolated validation target-final evidence is invalid');
    }
    prepareAssertTargetFinalCloneBaseline($targetFinal, $marker, $canonical, $expectedTableCount);

    $loader = require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!$loader instanceof \Composer\Autoload\ClassLoader) {
        throw new RuntimeException('composer autoloader is unavailable');
    }
    $loader->setPsr4('app\\', [$projectRoot . DIRECTORY_SEPARATOR . 'app']);
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oa-database-migration.php';
    prepareAssertTargetFinalManifestSelfConsistent($targetFinal);
    $app = new App($projectRoot);
    $app->initialize();
    $connections = (array) $app->config->get('database.connections', []);
    $connection = prepareDatabaseConnectionConfiguration(
        (array) ($connections['mysql'] ?? []),
        $databaseHost
    );
    $connections['mysql'] = $connection;
    $app->config->set(['connections' => $connections], 'database');
    $app->make(\think\DbManager::class)->setConfig($app->config);

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'create-isolated-validation-clone.php';
    $sourceStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($canonical);
    $targetStructure = Oa\IsolatedValidationClone\databaseStructureFingerprint($database);
    if ($sourceStructure !== $targetStructure
        || ($sourceStructure['schemaSha256'] ?? null) !== (string) $marker['schemaSha256']
        || ($sourceStructure['tableCount'] ?? -1) !== $expectedTableCount
        || ($sourceStructure['foreignKeyConstraintCount'] ?? -1) !== $expectedForeignKeyCount
        || ($sourceStructure['nonTableObjectCount'] ?? -1) !== 0
    ) {
        throw new RuntimeException('isolated validation current structure differs from clone evidence');
    }
    $sourcePdo = preparePdo($connection, $canonical, $databaseHost);
    $targetPdo = preparePdo($connection, $database, $databaseHost);
    prepareAssertSchemaManifestMatchesTargetFinal(
        DatabaseManifest::capture($sourcePdo, $canonical, false),
        $targetFinal,
        $expectedTableCount,
        'canonical'
    );
    prepareAssertSchemaManifestMatchesTargetFinal(
        DatabaseManifest::capture($targetPdo, $database, false),
        $targetFinal,
        $expectedTableCount,
        'clone'
    );
    if (prepareTables($sourcePdo, $canonical) !== $expectedTables
        || prepareTables($targetPdo, $database) !== $expectedTables
    ) {
        throw new RuntimeException('isolated validation current table set differs from clone evidence');
    }
    foreach ($expectedTables as $table) {
        $sourceQualified = prepareQuoteIdentifier($canonical) . '.' . prepareQuoteIdentifier((string) $table);
        $targetQualified = prepareQuoteIdentifier($database) . '.' . prepareQuoteIdentifier((string) $table);
        $expectedRows = (int) $rowCounts[$table];
        if ((int) $sourcePdo->query("SELECT COUNT(*) FROM {$sourceQualified}")->fetchColumn() !== $expectedRows
            || (int) $targetPdo->query("SELECT COUNT(*) FROM {$targetQualified}")->fetchColumn() !== $expectedRows
            || !hash_equals((string) $tableChecksums[$table], prepareChecksum($sourcePdo, $canonical, (string) $table))
            || !hash_equals((string) $tableChecksums[$table], prepareChecksum($targetPdo, $database, (string) $table))
        ) {
            throw new RuntimeException('isolated validation current content differs from clone evidence');
        }
    }

    $serverRuntime = $manifestDirectory . DIRECTORY_SEPARATOR . 'server-runtime';
    if (!mkdir($serverRuntime, 0700) && !is_dir($serverRuntime)) {
        throw new RuntimeException('isolated validation runtime could not be created');
    }
    $serverRuntimeReal = realpath($serverRuntime);
    if ($serverRuntimeReal === false
        || is_link($serverRuntime)
        || !prepareContainedPath($serverRuntimeReal, $manifestDirectory)
    ) {
        throw new RuntimeException('isolated validation runtime escaped the private manifest');
    }

    $json = json_encode([
        'version' => 2,
        'canonicalDatabase' => $canonical,
        'database' => $database,
        'databaseHost' => $databaseHost,
        'runLabel' => $validationRunLabel,
        'runDate' => $validationRunDate,
        'expectedTableCount' => $expectedTableCount,
        'expectedForeignKeyConstraintCount' => $expectedForeignKeyCount,
        'manifest' => prepareRelativeProjectPath($projectRoot, $manifestDirectory),
        'cloneCompletedMarker' => prepareRelativeProjectPath($projectRoot, $markerPath),
        'cloneCompletedMarkerSha256' => $markerEvidence['sha256'],
        'serverRuntime' => prepareRelativeProjectPath($projectRoot, $serverRuntimeReal),
        'targetFinalMarker' => prepareRelativeProjectPath($projectRoot, $targetFinalMarker),
        'targetFinalMarkerSha256' => $targetFinalEvidence['sha256'],
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

    return [
        'status' => 'prepared',
        'tableCount' => $expectedTableCount,
        'foreignKeyConstraintCount' => $expectedForeignKeyCount,
        'helpersReady' => true,
        'pointerCreated' => true,
    ];
}

function prepareIsolatedValidationMain(array $argv): int
{
    try {
        fwrite(STDOUT, json_encode(
            prepareIsolatedValidation($argv),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL);

        return 0;
    } catch (Throwable) {
        fwrite(STDERR, "isolated validation preparation failed\n");

        return 1;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(prepareIsolatedValidationMain($argv));
}
