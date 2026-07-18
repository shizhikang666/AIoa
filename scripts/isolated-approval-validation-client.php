#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/isolated-validation-bootstrap.php';
require __DIR__ . '/isolated-approval-context.php';
require __DIR__ . '/isolated-approval-after.php';
require __DIR__ . '/audit-r10-isolated-validation-provenance.php';
require __DIR__ . '/create-isolated-validation-clone.php';

/** @return array<string, mixed> */
function isolatedHttp(string $method, string $url, string $token = '', ?array $body = null): array
{
    $headers = ['Accept: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $content = '';
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $content = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $content,
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException('isolated HTTP request failed');
    }
    $statusLine = is_array($http_response_header ?? null) ? (string) ($http_response_header[0] ?? '') : '';
    if (preg_match('/\s([0-9]{3})\s/', $statusLine, $matches) !== 1
        || (int) $matches[1] < 200
        || (int) $matches[1] >= 300
    ) {
        throw new RuntimeException('isolated HTTP status is not successful');
    }
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('isolated HTTP response is invalid');
    }

    return $decoded;
}

/** @param array<string, mixed> $response */
function requireApiSuccess(array $response): void
{
    if ((int) ($response['code'] ?? 0) !== 200) {
        throw new RuntimeException('isolated API returned a non-success response');
    }
}

function writeMutationStartedMarker(string $runtimePath): void
{
    $markerPath = trim((string) getenv('OA_ISOLATED_MUTATION_MARKER_PATH'));
    $runtimeReal = realpath($runtimePath);
    $markerParentReal = $markerPath === '' ? false : realpath(dirname($markerPath));
    $expectedParentReal = $runtimeReal === false ? false : realpath(dirname($runtimeReal));
    if ($runtimeReal === false
        || $markerParentReal === false
        || $expectedParentReal === false
        || strcasecmp($markerParentReal, $expectedParentReal) !== 0
        || basename($markerPath) !== 'validation-mutation-started.json'
    ) {
        throw new RuntimeException('isolated mutation marker path is invalid');
    }
    $stream = @fopen($markerPath, 'x+b');
    if (!is_resource($stream)) {
        throw new RuntimeException('isolated mutation marker already exists or cannot be created');
    }
    try {
        $json = json_encode([
            'status' => 'mutation-started',
            'targetMustNotBeReusedIfInterrupted' => true,
            'readonlyPreflightPassed' => true,
            'serverIdentityVerified' => true,
            'startedAt' => gmdate(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (fwrite($stream, $json) !== strlen($json) || !fflush($stream)) {
            throw new RuntimeException('isolated mutation marker could not be persisted');
        }
        if (!function_exists('fsync') || !fsync($stream)) {
            throw new RuntimeException('isolated mutation marker could not be synchronized');
        }
    } finally {
        fclose($stream);
    }
}

function jsonContainsIdentifier(mixed $value, string $needle): bool
{
    if (!is_array($value)) {
        return false;
    }
    foreach ($value as $key => $item) {
        $normalizedKey = strtolower((string) preg_replace('/[^a-z_]/i', '', (string) $key));
        if (in_array($normalizedKey, ['id', 'id_', 'taskid', 'task_id'], true)
            && is_scalar($item)
            && hash_equals($needle, (string) $item)
        ) {
            return true;
        }
        if (is_array($item) && jsonContainsIdentifier($item, $needle)) {
            return true;
        }
    }

    return false;
}

/** @param array<string, mixed> $connection @return array<string, array{rowCount:int,checksum:string}> */
function databaseFingerprints(array $connection, string $database): array
{
    $host = strtolower(trim((string) ($connection['hostname'] ?? '')));
    if ($host !== '127.0.0.1') {
        throw new RuntimeException('database fingerprint refuses a non-loopback host');
    }
    if ($database !== 'oa2026_rehearsal_r6_20260718_r10_migrated'
        && preg_match('/^oa2026_r10_validation_20260718_[a-f0-9]{8}$/', $database) !== 1
    ) {
        throw new RuntimeException('database fingerprint namespace is invalid');
    }
    $port = (int) ($connection['hostport'] ?? 3306);
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port={$port};dbname={$database};charset=utf8mb4",
        (string) ($connection['username'] ?? ''),
        (string) ($connection['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $statement = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES "
        . "WHERE BINARY TABLE_SCHEMA=BINARY ? AND TABLE_TYPE='BASE TABLE' ORDER BY BINARY TABLE_NAME"
    );
    $statement->execute([$database]);
    $tables = array_map(
        static fn (array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
        $statement->fetchAll(PDO::FETCH_ASSOC)
    );
    if (count($tables) !== 124) {
        throw new RuntimeException('database fingerprint table count is unexpected');
    }
    $result = [];
    foreach ($tables as $table) {
        if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
            throw new RuntimeException('database fingerprint table name is unsafe');
        }
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        $checksumRow = $pdo->query('CHECKSUM TABLE `' . $table . '`')->fetch(PDO::FETCH_ASSOC);
        $checksum = null;
        if (is_array($checksumRow)) {
            foreach ($checksumRow as $column => $value) {
                if (strtolower((string) preg_replace('/[^a-z]/i', '', (string) $column)) === 'checksum') {
                    $checksum = $value;
                    break;
                }
            }
        }
        if ($checksum === null || $checksum === '') {
            throw new RuntimeException('database fingerprint checksum is unavailable');
        }
        $result[$table] = ['rowCount' => $count, 'checksum' => (string) $checksum];
    }

    return $result;
}

/** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
function changedFingerprintTables(array $before, array $after): array
{
    if (array_keys($before) !== array_keys($after)) {
        throw new RuntimeException('database fingerprint table sets differ');
    }
    $changed = [];
    foreach ($before as $table => $fingerprint) {
        if ($fingerprint !== $after[$table]) {
            $changed[] = (string) $table;
        }
    }
    sort($changed, SORT_STRING);

    return $changed;
}

function runIsolatedApprovalValidation(): array
{
    $projectRoot = rtrim((string) getenv('OA_ISOLATED_PROJECT_ROOT'), "/\\");
    $runtimePath = rtrim((string) getenv('OA_ISOLATED_RUNTIME_PATH'), "/\\");
    $database = trim((string) getenv('OA_ISOLATED_DB_NAME'));
    $nonce = trim((string) getenv('OA_ISOLATED_HEALTH_NONCE'));
    $expectedPid = (int) getenv('OA_ISOLATED_EXPECTED_SERVER_PID');
    $port = (int) getenv('OA_ISOLATED_PORT');
    if (preg_match('/^[a-f0-9]{64}$/', $nonce) !== 1 || $expectedPid < 1 || $port < 1024 || $port > 65535) {
        throw new RuntimeException('isolated validation client environment is incomplete');
    }

    $app = Oa\IsolatedValidation\boot($projectRoot, $runtimePath);
    $connection = (array) $app->config->get('database.connections.mysql', []);
    $baseUrl = "http://127.0.0.1:{$port}";
    $health = isolatedHttp('GET', $baseUrl . '/__oa_isolated_validation_health');
    if ((int) ($health['pid'] ?? 0) !== $expectedPid
        || ($health['databaseVerified'] ?? null) !== true
        || !hash_equals(hash_hmac('sha256', $database, $nonce), (string) ($health['proof'] ?? ''))
    ) {
        throw new RuntimeException('isolated server identity proof failed');
    }

    $canonicalStructureBefore = Oa\IsolatedValidationClone\databaseStructureFingerprint(
        'oa2026_rehearsal_r6_20260718_r10_migrated'
    );
    $isolatedStructureBefore = Oa\IsolatedValidationClone\databaseStructureFingerprint($database);
    if ($canonicalStructureBefore !== $isolatedStructureBefore
        || ($canonicalStructureBefore['tableCount'] ?? -1) !== 124
        || ($canonicalStructureBefore['foreignKeyConstraintCount'] ?? -1) !== 42
        || ($canonicalStructureBefore['nonTableObjectCount'] ?? -1) !== 0
    ) {
        throw new RuntimeException('isolated database structure differs from the canonical baseline');
    }

    $canonicalBefore = databaseFingerprints($connection, 'oa2026_rehearsal_r6_20260718_r10_migrated');
    $isolatedBefore = databaseFingerprints($connection, $database);
    if ($canonicalBefore !== $isolatedBefore) {
        throw new RuntimeException('isolated database does not match the canonical baseline before validation');
    }
    $context = Oa\IsolatedValidation\approvalContext();
    if (trim((string) ($context['token'] ?? '')) === ''
        || trim((string) ($context['taskId'] ?? '')) === ''
        || trim((string) ($context['processId'] ?? '')) === ''
        || (!(bool) ($context['authorization']['hasApprovePermission'] ?? false)
            && !(bool) ($context['authorization']['hasBuiltInRole'] ?? false))
    ) {
        throw new RuntimeException('approval context is incomplete');
    }

    $token = (string) $context['token'];
    $taskId = (string) $context['taskId'];
    $processId = (string) $context['processId'];
    $currentCount = isolatedHttp('GET', $baseUrl . '/biz/task/count', $token);
    $currentList = isolatedHttp('GET', $baseUrl . '/biz/task/list', $token);
    $currentPage = isolatedHttp('GET', $baseUrl . '/biz/task/page', $token);
    requireApiSuccess($currentCount);
    requireApiSuccess($currentList);
    requireApiSuccess($currentPage);
    if ((int) ($currentCount['data'] ?? 0) < 1
        || !jsonContainsIdentifier($currentList['data'] ?? null, $taskId)
        || !jsonContainsIdentifier($currentPage['data'] ?? null, $taskId)
    ) {
        throw new RuntimeException('current task is not visible through authenticated read APIs');
    }

    $canonicalStructurePreMutation = Oa\IsolatedValidationClone\databaseStructureFingerprint(
        'oa2026_rehearsal_r6_20260718_r10_migrated'
    );
    $isolatedStructurePreMutation = Oa\IsolatedValidationClone\databaseStructureFingerprint($database);
    $canonicalPreMutation = databaseFingerprints(
        $connection,
        'oa2026_rehearsal_r6_20260718_r10_migrated'
    );
    $isolatedPreMutation = databaseFingerprints($connection, $database);
    if ($canonicalStructurePreMutation !== $canonicalStructureBefore
        || $isolatedStructurePreMutation !== $isolatedStructureBefore
        || $canonicalPreMutation !== $canonicalBefore
        || $isolatedPreMutation !== $isolatedBefore
    ) {
        throw new RuntimeException('isolated read-only preflight changed database state');
    }

    writeMutationStartedMarker($runtimePath);
    $approval = isolatedHttp('POST', $baseUrl . '/biz/task/approve', $token, [
        'id' => $taskId,
        'form' => ['approval' => true, 'comment' => 'R10 isolated continuation validation'],
    ]);
    requireApiSuccess($approval);
    $approvalData = is_array($approval['data'] ?? null) ? $approval['data'] : [];
    $nextTaskId = trim((string) ($approvalData['nextTaskId'] ?? ''));
    if ($nextTaskId === ''
        || (string) ($approvalData['processInstanceId'] ?? '') !== $processId
        || (string) ($approvalData['taskDefinitionKey'] ?? '') !== 'Activity_procure_approval'
    ) {
        throw new RuntimeException('approval response did not contain the expected next task');
    }

    $after = Oa\IsolatedValidation\approvalAfter($processId, $taskId, $nextTaskId);
    if ((int) ($after['runtimeTaskCount'] ?? 0) !== 1
        || (int) ($after['oldRuntimeTaskCount'] ?? -1) !== 0
        || !(bool) ($after['nextTaskExists'] ?? false)
        || !(bool) ($after['nextTaskMatchesResponse'] ?? false)
        || !(bool) ($after['nextTaskProcessMatches'] ?? false)
        || !(bool) ($after['nextTaskIsOnlyProcessTask'] ?? false)
        || (string) ($after['nextTaskDefinitionKey'] ?? '') !== 'Activity_procure_approval'
        || !(bool) ($after['nextAssigneeActive'] ?? false)
        || !(bool) ($after['nextAssigneeTenantMatches'] ?? false)
        || !(bool) ($after['nextTaskTenantMatchesProcess'] ?? false)
        || !(bool) ($after['historicTaskEnded'] ?? false)
        || !(bool) ($after['processStillActive'] ?? false)
        || trim((string) ($after['nextToken'] ?? '')) === ''
        || json_encode($context['businessFingerprints'], JSON_THROW_ON_ERROR)
            !== json_encode($after['businessFingerprints'], JSON_THROW_ON_ERROR)
    ) {
        throw new RuntimeException('post-approval database assertions failed');
    }

    $nextToken = (string) $after['nextToken'];
    $nextCount = isolatedHttp('GET', $baseUrl . '/biz/task/count', $nextToken);
    $nextList = isolatedHttp('GET', $baseUrl . '/biz/task/list', $nextToken);
    $nextPage = isolatedHttp('GET', $baseUrl . '/biz/task/page', $nextToken);
    requireApiSuccess($nextCount);
    requireApiSuccess($nextList);
    requireApiSuccess($nextPage);
    if ((int) ($nextCount['data'] ?? -1) !== (int) ($after['directPendingCount'] ?? -2)
        || !jsonContainsIdentifier($nextList['data'] ?? null, $nextTaskId)
        || !jsonContainsIdentifier($nextPage['data'] ?? null, $nextTaskId)
    ) {
        throw new RuntimeException('next task is not visible through authenticated read APIs');
    }

    $canonicalAfter = databaseFingerprints($connection, 'oa2026_rehearsal_r6_20260718_r10_migrated');
    if ($canonicalBefore !== $canonicalAfter) {
        throw new RuntimeException('R10 canonical fingerprint changed during isolated validation');
    }
    $isolatedAfter = databaseFingerprints($connection, $database);
    if (Oa\IsolatedValidationClone\databaseStructureFingerprint(
        'oa2026_rehearsal_r6_20260718_r10_migrated'
    ) !== $canonicalStructureBefore
        || Oa\IsolatedValidationClone\databaseStructureFingerprint($database) !== $isolatedStructureBefore
    ) {
        throw new RuntimeException('database structure changed during isolated validation');
    }
    $changedIsolatedTables = changedFingerprintTables($isolatedBefore, $isolatedAfter);
    if ($changedIsolatedTables !== [
        'act_hi_actinst',
        'act_hi_taskinst',
        'act_hi_varinst',
        'act_ru_execution',
        'act_ru_task',
        'act_ru_variable',
    ]) {
        throw new RuntimeException('isolated validation changed an unexpected table set');
    }
    $canonicalPdo = Oa\IsolatedValidationAudit\pdoForDatabase(
        $connection,
        'oa2026_rehearsal_r6_20260718_r10_migrated'
    );
    $isolatedPdo = Oa\IsolatedValidationAudit\pdoForDatabase($connection, $database);
    $workflowDiffs = [];
    foreach ($changedIsolatedTables as $table) {
        $workflowDiffs[$table] = Oa\IsolatedValidationAudit\rawRowDiff(
            $canonicalPdo,
            $isolatedPdo,
            $database,
            $table
        );
    }
    $transitionEvidence = Oa\IsolatedValidationAudit\assertExactSingleTransition(
        $canonicalPdo,
        $isolatedPdo,
        $workflowDiffs
    );
    if (($transitionEvidence['rowsOutsideValidatedProcessMatched'] ?? null) !== true
        || ($transitionEvidence['exactSingleTransitionMatched'] ?? null) !== true
    ) {
        throw new RuntimeException('isolated validation exact transition evidence is incomplete');
    }

    return [
        'status' => 'completed',
        'serverIdentityVerified' => true,
        'approvalContinuationPassed' => true,
        'currentTaskReadApisPassed' => true,
        'nextTaskReadApisPassed' => true,
        'nextTaskCountMatchedDatabase' => true,
        'businessFingerprintsUnchanged' => true,
        'canonicalFingerprintsUnchanged' => true,
        'databaseStructuresUnchanged' => true,
        'isolatedExpectedTableChangesOnly' => true,
        'exactSingleTransitionMatched' => true,
        'validationWritesIsolated' => true,
    ];
}

function isolatedApprovalValidationMain(): int
{
    try {
        fwrite(STDOUT, json_encode(
            runIsolatedApprovalValidation(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL);

        return 0;
    } catch (Throwable) {
        fwrite(STDERR, "isolated approval validation client failed\n");

        return 1;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(isolatedApprovalValidationMain());
}
