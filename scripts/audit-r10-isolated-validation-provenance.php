#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Oa\IsolatedValidationAudit;

use Oa\DatabaseMigration\DatabaseManifest;
use PDO;
use RuntimeException;
use Throwable;
use think\App;
use function Oa\IsolatedValidationParameters\approvalComment;
use function Oa\IsolatedValidationParameters\environmentConfiguration;
use function Oa\IsolatedValidationParameters\legacyPosthocMode;
use function Oa\IsolatedValidationParameters\loopbackHost;
use function Oa\IsolatedValidationParameters\requiredEnvironment;

require_once __DIR__ . '/lib/isolated-validation-parameters.php';


/** @return array<string, string> */
function parseOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new RuntimeException('audit options must use --name=value');
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, ['inspect-only', 'diff-only', 'verify-only', 'write-evidence'], true) || isset($options[$name])) {
            throw new RuntimeException('audit received an unsupported or duplicate option');
        }
        $options[$name] = trim($value);
    }
    $enabled = array_filter(
        ['inspect-only', 'diff-only', 'verify-only', 'write-evidence'],
        static fn (string $name): bool => ($options[$name] ?? '') === '1'
    );
    if (count($enabled) !== 1 || count($options) !== 1) {
        throw new RuntimeException('audit requires exactly one inspection mode');
    }

    return $options;
}

/** @return array<string, mixed> */
function readJson(string $path, string $label): array
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("{$label} is missing or unsafe");
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException("{$label} is unreadable");
    }
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("{$label} is invalid");
    }

    return $decoded;
}

/** @return array<string, mixed> */
function readPinnedJson(string $path, string $label, string $expectedSha256): array
{
    if (preg_match('/^[a-f0-9]{64}$/', $expectedSha256) !== 1) {
        throw new RuntimeException("{$label} SHA256 is missing or invalid");
    }
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("{$label} is missing or unsafe");
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '' || !hash_equals($expectedSha256, hash('sha256', $raw))) {
        throw new RuntimeException("{$label} SHA256 differs from the private pointer");
    }
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("{$label} is invalid");
    }

    return $decoded;
}

/** @param array<string, mixed> $parameters */
function canonicalDatabaseFromParameters(array $parameters): string
{
    $canonicalDatabase = $parameters['canonicalDatabase'] ?? null;
    if (!is_string($canonicalDatabase) || $canonicalDatabase === '') {
        throw new RuntimeException('audit canonical database parameter is missing');
    }

    return $canonicalDatabase;
}

function containedPath(string $child, string $parent): bool
{
    $childPrefix = rtrim($child, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $parentPrefix = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($childPrefix, $parentPrefix);
}

function sameEvidencePath(string $expected, string $actual): bool
{
    return hash_equals($expected, $actual);
}

function resolveEvidenceCandidate(
    string $candidate,
    string $privateRoot,
    string $label,
    bool $directory = false
): string {
    if ($candidate === '' || is_link($candidate)) {
        throw new RuntimeException("{$label} candidate is missing or a symbolic link");
    }
    $resolved = realpath($candidate);
    if ($resolved === false
        || is_link($resolved)
        || ($directory && !is_dir($resolved))
        || (!$directory && !is_file($resolved))
        || !containedPath($resolved, $privateRoot)
    ) {
        throw new RuntimeException("{$label} candidate is invalid or outside private runtime");
    }

    return $resolved;
}

function quoteIdentifier(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        throw new RuntimeException('audit encountered an unsafe SQL identifier');
    }

    return '`' . $value . '`';
}

/** @param array<string, mixed> $connection */
function pdoForDatabase(array $connection, string $database): PDO
{
    $validation = environmentConfiguration();
    $configuredHost = loopbackHost((string) ($connection['hostname'] ?? ''));
    if (!hash_equals($validation['databaseHost'], $configuredHost)) {
        throw new RuntimeException('audit database host differs from the explicit invocation');
    }
    if (!in_array($database, [
        $validation['canonicalDatabase'],
        $validation['targetDatabase'],
    ], true)) {
        throw new RuntimeException('audit database is outside the explicit validation pair');
    }
    $port = (int) ($connection['hostport'] ?? 3306);
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('audit database port is invalid');
    }

    return new PDO(
        "mysql:host={$validation['databaseHost']};port={$port};dbname={$database};charset=utf8mb4",
        (string) ($connection['username'] ?? ''),
        (string) ($connection['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]
    );
}

/** @return array{rowCount:int,checksum:string} */
function fingerprint(PDO $pdo, string $database, string $table): array
{
    $qualified = quoteIdentifier($database) . '.' . quoteIdentifier($table);
    $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM {$qualified}")->fetchColumn();
    $row = $pdo->query("CHECKSUM TABLE {$qualified}")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('audit table checksum is unavailable');
    }
    $checksum = null;
    foreach ($row as $column => $value) {
        if (strtolower((string) preg_replace('/[^a-z]/i', '', (string) $column)) === 'checksum') {
            $checksum = $value;
            break;
        }
    }
    if ($checksum === null || $checksum === '') {
        throw new RuntimeException('audit table checksum is unavailable');
    }

    return ['rowCount' => $rowCount, 'checksum' => (string) $checksum];
}

/** @param array<string, mixed>|false $row @return array<string, null|string>|null */
function normalizedRow(array|false $row): ?array
{
    if ($row === false) {
        return null;
    }

    return array_map(
        static fn (mixed $value): ?string => $value === null ? null : (string) $value,
        $row
    );
}

/** @return array<string, mixed> */
function sanitizedRowDiff(PDO $canonical, PDO $target, string $targetDatabase, string $table): array
{
    $beforeTable = quoteIdentifier(environmentConfiguration()['canonicalDatabase']) . '.' . quoteIdentifier($table);
    $afterTable = quoteIdentifier($targetDatabase) . '.' . quoteIdentifier($table);
    $beforeStatement = $canonical->query("SELECT * FROM {$beforeTable} ORDER BY BINARY `ID_`");
    $afterStatement = $target->query("SELECT * FROM {$afterTable} ORDER BY BINARY `ID_`");
    $beforeRow = normalizedRow($beforeStatement->fetch(PDO::FETCH_ASSOC));
    $afterRow = normalizedRow($afterStatement->fetch(PDO::FETCH_ASSOC));
    $addedCount = 0;
    $removedCount = 0;
    $modifiedCount = 0;
    $changedColumns = [];
    $processHashes = [];
    $safeTransitions = [];
    $safeAdded = [];
    $safeRemoved = [];
    $safeModified = [];

    $safeValueForRow = static fn (?array $row): string => $row === null ? '' : match ($table) {
        'act_ru_task', 'act_hi_taskinst' => (string) ($row['TASK_DEF_KEY_'] ?? ''),
        'act_hi_actinst' => (string) ($row['ACT_ID_'] ?? ''),
        'act_ru_variable', 'act_hi_varinst' => (string) ($row['NAME_'] ?? ''),
        default => '',
    };

    $observe = static function (?array $row) use (&$processHashes, &$safeTransitions, $safeValueForRow): void {
        if ($row === null) {
            return;
        }
        $processId = (string) ($row['PROC_INST_ID_'] ?? '');
        if ($processId !== '') {
            $processHashes[hash('sha256', $processId)] = true;
        }
        $safeValue = $safeValueForRow($row);
        if ($safeValue !== '') {
            $safeTransitions[$safeValue] = true;
        }
    };

    while ($beforeRow !== null || $afterRow !== null) {
        $beforeId = (string) ($beforeRow['ID_'] ?? '');
        $afterId = (string) ($afterRow['ID_'] ?? '');
        if (($beforeRow !== null && $beforeId === '') || ($afterRow !== null && $afterId === '')) {
            throw new RuntimeException('audit requires a non-empty primary key');
        }
        $comparison = $beforeRow === null ? 1 : ($afterRow === null ? -1 : strcmp($beforeId, $afterId));
        if ($comparison < 0) {
            $removedCount++;
            $observe($beforeRow);
            $safeValue = $safeValueForRow($beforeRow);
            if ($safeValue !== '') {
                $safeRemoved[$safeValue] = true;
            }
            $beforeRow = normalizedRow($beforeStatement->fetch(PDO::FETCH_ASSOC));
            continue;
        }
        if ($comparison > 0) {
            $addedCount++;
            $observe($afterRow);
            $safeValue = $safeValueForRow($afterRow);
            if ($safeValue !== '') {
                $safeAdded[$safeValue] = true;
            }
            $afterRow = normalizedRow($afterStatement->fetch(PDO::FETCH_ASSOC));
            continue;
        }
        if ($beforeRow !== $afterRow) {
            $modifiedCount++;
            $observe($beforeRow);
            $observe($afterRow);
            foreach ([$safeValueForRow($beforeRow), $safeValueForRow($afterRow)] as $safeValue) {
                if ($safeValue !== '') {
                    $safeModified[$safeValue] = true;
                }
            }
            foreach ($beforeRow as $column => $value) {
                if ($value !== ($afterRow[$column] ?? null)) {
                    $changedColumns[$column] = ($changedColumns[$column] ?? 0) + 1;
                }
            }
        }
        $beforeRow = normalizedRow($beforeStatement->fetch(PDO::FETCH_ASSOC));
        $afterRow = normalizedRow($afterStatement->fetch(PDO::FETCH_ASSOC));
    }
    ksort($changedColumns);
    $safeValues = array_keys($safeTransitions);
    sort($safeValues, SORT_STRING);
    $safeAddedValues = array_keys($safeAdded);
    $safeRemovedValues = array_keys($safeRemoved);
    $safeModifiedValues = array_keys($safeModified);
    sort($safeAddedValues, SORT_STRING);
    sort($safeRemovedValues, SORT_STRING);
    sort($safeModifiedValues, SORT_STRING);

    return [
        'added' => $addedCount,
        'removed' => $removedCount,
        'modified' => $modifiedCount,
        'changedColumns' => $changedColumns,
        'distinctChangedProcessCount' => count($processHashes),
        'safeWorkflowValues' => $safeValues,
        'safeAddedValues' => $safeAddedValues,
        'safeRemovedValues' => $safeRemovedValues,
        'safeModifiedValues' => $safeModifiedValues,
    ];
}

/**
 * @return array{
 *   added:list<array<string, null|string>>,
 *   removed:list<array<string, null|string>>,
 *   modified:list<array{before:array<string, null|string>,after:array<string, null|string>}>
 * }
 */
function rawRowDiff(PDO $canonical, PDO $target, string $targetDatabase, string $table): array
{
    $beforeTable = quoteIdentifier(environmentConfiguration()['canonicalDatabase']) . '.' . quoteIdentifier($table);
    $afterTable = quoteIdentifier($targetDatabase) . '.' . quoteIdentifier($table);
    $beforeStatement = $canonical->query("SELECT * FROM {$beforeTable} ORDER BY BINARY `ID_`");
    $afterStatement = $target->query("SELECT * FROM {$afterTable} ORDER BY BINARY `ID_`");
    $beforeRow = normalizedRow($beforeStatement->fetch(PDO::FETCH_ASSOC));
    $afterRow = normalizedRow($afterStatement->fetch(PDO::FETCH_ASSOC));
    $result = ['added' => [], 'removed' => [], 'modified' => []];

    while ($beforeRow !== null || $afterRow !== null) {
        $beforeId = (string) ($beforeRow['ID_'] ?? '');
        $afterId = (string) ($afterRow['ID_'] ?? '');
        if (($beforeRow !== null && $beforeId === '') || ($afterRow !== null && $afterId === '')) {
            throw new RuntimeException('audit requires a non-empty primary key');
        }
        $comparison = $beforeRow === null ? 1 : ($afterRow === null ? -1 : strcmp($beforeId, $afterId));
        if ($comparison < 0) {
            $result['removed'][] = $beforeRow;
            $beforeRow = normalizedRow($beforeStatement->fetch(PDO::FETCH_ASSOC));
        } elseif ($comparison > 0) {
            $result['added'][] = $afterRow;
            $afterRow = normalizedRow($afterStatement->fetch(PDO::FETCH_ASSOC));
        } else {
            if ($beforeRow !== $afterRow) {
                $result['modified'][] = ['before' => $beforeRow, 'after' => $afterRow];
            }
            $beforeRow = normalizedRow($beforeStatement->fetch(PDO::FETCH_ASSOC));
            $afterRow = normalizedRow($afterStatement->fetch(PDO::FETCH_ASSOC));
        }
        if (count($result['added']) + count($result['removed']) + count($result['modified']) > 100) {
            throw new RuntimeException('audit found too many changed workflow rows');
        }
    }

    return $result;
}

/** @param array<string, null|string> $before @param array<string, null|string> $after @return list<string> */
function changedColumns(array $before, array $after): array
{
    if (array_keys($before) !== array_keys($after)) {
        throw new RuntimeException('audit row columns differ');
    }
    $changed = [];
    foreach ($before as $column => $value) {
        if ($value !== $after[$column]) {
            $changed[] = $column;
        }
    }
    sort($changed, SORT_STRING);

    return $changed;
}

/** @param list<string> $actual @param list<string> $expected */
function assertStringList(array $actual, array $expected, string $label): void
{
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        throw new RuntimeException("{$label} differs from the expected transition");
    }
}

/** @param array<string, null|string> $row */
function workflowValueName(string $table, array $row): string
{
    return match ($table) {
        'act_ru_task', 'act_hi_taskinst' => (string) ($row['TASK_DEF_KEY_'] ?? ''),
        'act_hi_actinst' => (string) ($row['ACT_ID_'] ?? ''),
        'act_ru_variable', 'act_hi_varinst' => (string) ($row['NAME_'] ?? ''),
        default => '',
    };
}

/** @param array<string, mixed> $diff @param array<string, mixed> $expected */
function assertDiffShape(string $table, array $diff, array $expected, string $processId): void
{
    foreach (['added', 'removed', 'modified'] as $category) {
        if (count((array) ($diff[$category] ?? [])) !== (int) ($expected[$category] ?? -1)) {
            throw new RuntimeException("{$table} row change count is unexpected");
        }
    }
    $columnCounts = [];
    foreach ((array) $diff['modified'] as $change) {
        foreach (changedColumns((array) $change['before'], (array) $change['after']) as $column) {
            $columnCounts[$column] = ($columnCounts[$column] ?? 0) + 1;
        }
    }
    ksort($columnCounts);
    $expectedColumns = (array) ($expected['changedColumns'] ?? []);
    ksort($expectedColumns);
    if ($columnCounts !== $expectedColumns) {
        throw new RuntimeException("{$table} changed columns are unexpected");
    }

    $names = ['added' => [], 'removed' => [], 'modified' => []];
    foreach ((array) $diff['added'] as $row) {
        $names['added'][] = workflowValueName($table, (array) $row);
        if ((string) ($row['PROC_INST_ID_'] ?? '') !== $processId) {
            throw new RuntimeException("{$table} changed outside the validated process");
        }
    }
    foreach ((array) $diff['removed'] as $row) {
        $names['removed'][] = workflowValueName($table, (array) $row);
        if ((string) ($row['PROC_INST_ID_'] ?? '') !== $processId) {
            throw new RuntimeException("{$table} changed outside the validated process");
        }
    }
    foreach ((array) $diff['modified'] as $change) {
        $before = (array) $change['before'];
        $after = (array) $change['after'];
        $names['modified'][] = workflowValueName($table, $after);
        if ((string) ($before['PROC_INST_ID_'] ?? '') !== $processId
            || (string) ($after['PROC_INST_ID_'] ?? '') !== $processId
        ) {
            throw new RuntimeException("{$table} changed outside the validated process");
        }
    }
    foreach (['added', 'removed', 'modified'] as $category) {
        $expectedNames = array_values((array) ($expected[$category . 'Names'] ?? []));
        if ($expectedNames !== [] || array_filter($names[$category], static fn (string $name): bool => $name !== '') !== []) {
            assertStringList(
                array_values(array_filter($names[$category], static fn (string $name): bool => $name !== '')),
                $expectedNames,
                "{$table} {$category} workflow values"
            );
        }
    }
}

/** @return array<string, null|string> */
function oneRow(PDO $pdo, string $sql, array $parameters, string $label): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        throw new RuntimeException("{$label} must resolve to exactly one row");
    }

    return array_map(
        static fn (mixed $value): ?string => $value === null ? null : (string) $value,
        $rows[0]
    );
}

/** @param array<string, null|string> $row */
function assertVariableValue(array $row, bool $history, string $type, null|int|string $value): void
{
    $typeColumn = $history ? 'VAR_TYPE_' : 'TYPE_';
    if ((string) ($row[$typeColumn] ?? '') !== $type) {
        throw new RuntimeException('workflow variable type is unexpected');
    }
    $expectedLong = is_int($value) ? (string) $value : null;
    $expectedText = is_string($value) ? $value : ($type === 'integer' ? (string) $value : null);
    if (($row['BYTEARRAY_ID_'] ?? null) !== null
        || ($row['DOUBLE_'] ?? null) !== null
        || ($row['LONG_'] ?? null) !== $expectedLong
        || ($row['TEXT_'] ?? null) !== $expectedText
        || ($row['TEXT2_'] ?? null) !== null
    ) {
        throw new RuntimeException('workflow variable value is unexpected');
    }
}

/** @param list<array<string, null|string>> $rows @return array<string, array<string, null|string>> */
function rowsByWorkflowName(array $rows): array
{
    $result = [];
    foreach ($rows as $row) {
        $name = (string) ($row['NAME_'] ?? '');
        if ($name === '' || isset($result[$name])) {
            throw new RuntimeException('workflow variable names are not unique within the change set');
        }
        $result[$name] = $row;
    }
    ksort($result);

    return $result;
}

/** @param array<string, mixed> $diff @return array<string, array<string, null|string>> */
function changedVariableRows(array $diff): array
{
    $rows = array_values((array) ($diff['added'] ?? []));
    foreach ((array) ($diff['modified'] ?? []) as $change) {
        $rows[] = (array) $change['after'];
    }

    return rowsByWorkflowName($rows);
}

/**
 * @param array<string, array<string, mixed>> $diffs
 * @return array<string, bool|int>
 */
function assertExactSingleTransition(PDO $canonical, PDO $target, array $diffs): array
{
    $candidate = oneRow($canonical, <<<'SQL'
SELECT t.*,p.PROC_DEF_KEY_ AS PROCESS_DEF_KEY,p.STATE_ AS PROCESS_STATE,p.END_TIME_ AS PROCESS_END_TIME,
       v.TEXT_ AS PROCURE_USER
FROM act_ru_task t
INNER JOIN act_hi_procinst p ON BINARY p.PROC_INST_ID_=BINARY t.PROC_INST_ID_
INNER JOIN sys_user u ON BINARY u.ID=BINARY t.ASSIGNEE_
INNER JOIN act_hi_varinst v ON BINARY v.PROC_INST_ID_=BINARY t.PROC_INST_ID_ AND v.NAME_='procure'
INNER JOIN sys_user n ON BINARY n.ID=BINARY v.TEXT_
WHERE p.PROC_DEF_KEY_='Process_procure'
AND t.TASK_DEF_KEY_='Activity_approval'
AND p.STATE_='ACTIVE'
AND p.END_TIME_ IS NULL
AND u.USER_STATUS='ENABLE'
AND (u.DELETE_FLAG IS NULL OR u.DELETE_FLAG='NOT_DELETE')
AND n.USER_STATUS='ENABLE'
AND (n.DELETE_FLAG IS NULL OR n.DELETE_FLAG='NOT_DELETE')
AND BINARY u.TENANT_ID=BINARY t.TENANT_ID_
AND BINARY n.TENANT_ID=BINARY t.TENANT_ID_
ORDER BY t.CREATE_TIME_,t.ID_
SQL, [], 'eligible canonical approval task');
    $processId = (string) ($candidate['PROC_INST_ID_'] ?? '');
    $oldTaskId = (string) ($candidate['ID_'] ?? '');
    $executionId = (string) ($candidate['EXECUTION_ID_'] ?? '');
    $definitionId = (string) ($candidate['PROC_DEF_ID_'] ?? '');
    $tenantId = (string) ($candidate['TENANT_ID_'] ?? '');
    $procureUser = (string) ($candidate['PROCURE_USER'] ?? '');
    if ($processId === '' || $oldTaskId === '' || $executionId === '' || $definitionId === '' || $procureUser === '') {
        throw new RuntimeException('eligible canonical approval task is incomplete');
    }

    $expectations = [
        'act_hi_actinst' => [
            'added' => 1, 'removed' => 0, 'modified' => 1,
            'changedColumns' => ['ACT_INST_STATE_' => 1, 'DURATION_' => 1, 'END_TIME_' => 1],
            'addedNames' => ['Activity_procure_approval'], 'modifiedNames' => ['Activity_approval'],
        ],
        'act_hi_taskinst' => [
            'added' => 1, 'removed' => 0, 'modified' => 1,
            'changedColumns' => ['DELETE_REASON_' => 1, 'DURATION_' => 1, 'END_TIME_' => 1],
            'addedNames' => ['Activity_procure_approval'], 'modifiedNames' => ['Activity_approval'],
        ],
        'act_hi_varinst' => [
            'added' => 4, 'removed' => 0, 'modified' => 4,
            'changedColumns' => ['LONG_' => 1, 'REV_' => 4, 'TEXT_' => 2],
            'addedNames' => ['approval', 'comment', 'state', 'status'],
            'modifiedNames' => ['nrOfActiveInstances', 'nrOfCompletedInstances', 'procure', 'user'],
        ],
        'act_ru_execution' => [
            'added' => 0, 'removed' => 0, 'modified' => 1,
            'changedColumns' => ['ACT_ID_' => 1, 'ACT_INST_ID_' => 1, 'REV_' => 1, 'SEQUENCE_COUNTER_' => 1],
        ],
        'act_ru_task' => [
            'added' => 1, 'removed' => 1, 'modified' => 0, 'changedColumns' => [],
            'addedNames' => ['Activity_procure_approval'], 'removedNames' => ['Activity_approval'],
        ],
        'act_ru_variable' => [
            'added' => 2, 'removed' => 0, 'modified' => 6,
            'changedColumns' => ['LONG_' => 1, 'REV_' => 6, 'SEQUENCE_COUNTER_' => 6, 'TEXT_' => 2],
            'addedNames' => ['comment', 'state'],
            'modifiedNames' => ['approval', 'nrOfActiveInstances', 'nrOfCompletedInstances', 'procure', 'status', 'user'],
        ],
    ];
    foreach ($expectations as $table => $expected) {
        assertDiffShape($table, $diffs[$table], $expected, $processId);
    }

    $taskDiff = $diffs['act_ru_task'];
    $oldTask = $taskDiff['removed'][0];
    $nextTask = $taskDiff['added'][0];
    $nextTaskId = (string) ($nextTask['ID_'] ?? '');
    if ((string) ($oldTask['ID_'] ?? '') !== $oldTaskId
        || $nextTaskId === ''
        || (string) ($nextTask['EXECUTION_ID_'] ?? '') !== $executionId
        || (string) ($nextTask['PROC_INST_ID_'] ?? '') !== $processId
        || (string) ($nextTask['PROC_DEF_ID_'] ?? '') !== $definitionId
        || (string) ($nextTask['TASK_DEF_KEY_'] ?? '') !== 'Activity_procure_approval'
        || (string) ($nextTask['ASSIGNEE_'] ?? '') !== $procureUser
        || (string) ($nextTask['PRIORITY_'] ?? '') !== '50'
        || (string) ($nextTask['SUSPENSION_STATE_'] ?? '') !== '1'
        || (string) ($nextTask['TENANT_ID_'] ?? '') !== $tenantId
        || trim((string) ($nextTask['CREATE_TIME_'] ?? '')) === ''
    ) {
        throw new RuntimeException('next runtime task is inconsistent');
    }

    $executionChange = $diffs['act_ru_execution']['modified'][0];
    $beforeExecution = $executionChange['before'];
    $afterExecution = $executionChange['after'];
    if ((string) ($beforeExecution['ID_'] ?? '') !== $executionId
        || (string) ($afterExecution['ID_'] ?? '') !== $executionId
        || (int) ($afterExecution['REV_'] ?? -1) !== (int) ($beforeExecution['REV_'] ?? -2) + 1
        || (int) ($afterExecution['SEQUENCE_COUNTER_'] ?? -1) !== (int) ($beforeExecution['SEQUENCE_COUNTER_'] ?? -2) + 1
        || (string) ($afterExecution['ACT_ID_'] ?? '') !== 'Activity_procure_approval'
    ) {
        throw new RuntimeException('runtime execution transition is inconsistent');
    }
    $nextActivityId = (string) ($afterExecution['ACT_INST_ID_'] ?? '');
    if ($nextActivityId === '') {
        throw new RuntimeException('next activity instance is missing');
    }

    $historyTaskDiff = $diffs['act_hi_taskinst'];
    $historicOldTask = $historyTaskDiff['modified'][0];
    $historicNextTask = $historyTaskDiff['added'][0];
    if ((string) ($historicOldTask['before']['ID_'] ?? '') !== $oldTaskId
        || (string) ($historicOldTask['after']['END_TIME_'] ?? '') !== (string) ($nextTask['CREATE_TIME_'] ?? '')
        || (int) ($historicOldTask['after']['DURATION_'] ?? -1) < 0
        || (string) ($historicOldTask['after']['DELETE_REASON_'] ?? '') !== 'completed'
        || (string) ($historicNextTask['ID_'] ?? '') !== $nextTaskId
        || (string) ($historicNextTask['ACT_INST_ID_'] ?? '') !== $nextActivityId
        || (string) ($historicNextTask['START_TIME_'] ?? '') !== (string) ($nextTask['CREATE_TIME_'] ?? '')
        || ($historicNextTask['END_TIME_'] ?? null) !== null
        || ($historicNextTask['DURATION_'] ?? null) !== null
        || ($historicNextTask['DELETE_REASON_'] ?? null) !== null
        || (string) ($historicNextTask['ASSIGNEE_'] ?? '') !== $procureUser
        || (string) ($historicNextTask['TENANT_ID_'] ?? '') !== $tenantId
    ) {
        throw new RuntimeException('historic task transition is inconsistent');
    }

    $historyActivityDiff = $diffs['act_hi_actinst'];
    $historicOldActivity = $historyActivityDiff['modified'][0];
    $historicNextActivity = $historyActivityDiff['added'][0];
    if ((string) ($historicOldActivity['before']['TASK_ID_'] ?? '') !== $oldTaskId
        || (string) ($historicOldActivity['after']['END_TIME_'] ?? '') !== (string) ($nextTask['CREATE_TIME_'] ?? '')
        || (int) ($historicOldActivity['after']['DURATION_'] ?? -1) < 0
        || (string) ($historicOldActivity['after']['ACT_INST_STATE_'] ?? '') !== '4'
        || (string) ($historicNextActivity['ID_'] ?? '') !== $nextActivityId
        || (string) ($historicNextActivity['TASK_ID_'] ?? '') !== $nextTaskId
        || (string) ($historicNextActivity['PROC_INST_ID_'] ?? '') !== $processId
        || (string) ($historicNextActivity['EXECUTION_ID_'] ?? '') !== $executionId
        || (string) ($historicNextActivity['ACT_ID_'] ?? '') !== 'Activity_procure_approval'
        || (string) ($historicNextActivity['ACT_TYPE_'] ?? '') !== 'userTask'
        || (string) ($historicNextActivity['ASSIGNEE_'] ?? '') !== $procureUser
        || (string) ($historicNextActivity['START_TIME_'] ?? '') !== (string) ($nextTask['CREATE_TIME_'] ?? '')
        || ($historicNextActivity['END_TIME_'] ?? null) !== null
        || ($historicNextActivity['DURATION_'] ?? null) !== null
        || (string) ($historicNextActivity['ACT_INST_STATE_'] ?? '') !== '0'
        || (string) ($historicNextActivity['TENANT_ID_'] ?? '') !== $tenantId
    ) {
        throw new RuntimeException('historic activity transition is inconsistent');
    }

    $expectedValues = [
        'approval' => ['boolean', 1],
        'comment' => ['string', approvalComment(requiredEnvironment('OA_ISOLATED_APPROVAL_COMMENT'))],
        'nrOfActiveInstances' => ['integer', 1],
        'nrOfCompletedInstances' => ['integer', 1],
        'procure' => ['string', $procureUser],
        'state' => ['string', 'AGREE'],
        'status' => ['string', 'progress'],
        'user' => ['string', $procureUser],
    ];
    foreach ($diffs['act_ru_variable']['modified'] as $change) {
        $before = $change['before'];
        $after = $change['after'];
        if ((int) ($after['REV_'] ?? -1) !== (int) ($before['REV_'] ?? -2) + 1
            || (int) ($after['SEQUENCE_COUNTER_'] ?? -1) !== (int) ($before['SEQUENCE_COUNTER_'] ?? -2) + 1
        ) {
            throw new RuntimeException('runtime variable revision transition is inconsistent');
        }
    }
    foreach ($diffs['act_ru_variable']['added'] as $row) {
        if ((string) ($row['REV_'] ?? '') !== '1'
            || (string) ($row['EXECUTION_ID_'] ?? '') !== $processId
            || (string) ($row['VAR_SCOPE_'] ?? '') !== $processId
            || (string) ($row['SEQUENCE_COUNTER_'] ?? '') !== '1'
            || (string) ($row['IS_CONCURRENT_LOCAL_'] ?? '') !== '0'
            || ($row['TASK_ID_'] ?? null) !== null
        ) {
            throw new RuntimeException('new runtime variable scope is inconsistent');
        }
    }
    $oldActivityInstanceId = (string) ($historicOldTask['before']['ACT_INST_ID_'] ?? '');
    if ($oldActivityInstanceId === '') {
        throw new RuntimeException('historic activity instance is missing');
    }
    foreach ($diffs['act_hi_varinst']['modified'] as $change) {
        if ((int) ($change['after']['REV_'] ?? -1) !== (int) ($change['before']['REV_'] ?? -2) + 1) {
            throw new RuntimeException('historic variable revision transition is inconsistent');
        }
    }
    foreach ($diffs['act_hi_varinst']['added'] as $row) {
        if ((string) ($row['REV_'] ?? '') !== '0'
            || (string) ($row['ROOT_PROC_INST_ID_'] ?? '') !== $processId
            || (string) ($row['EXECUTION_ID_'] ?? '') !== $executionId
            || (string) ($row['ACT_INST_ID_'] ?? '') !== $oldActivityInstanceId
            || ($row['TASK_ID_'] ?? null) !== null
            || (string) ($row['STATE_'] ?? '') !== 'CREATED'
        ) {
            throw new RuntimeException('new historic variable scope is inconsistent');
        }
    }
    foreach ([false => 'act_ru_variable', true => 'act_hi_varinst'] as $history => $table) {
        $rows = changedVariableRows($diffs[$table]);
        assertStringList(array_keys($rows), array_keys($expectedValues), "{$table} changed variable names");
        foreach ($expectedValues as $name => [$type, $value]) {
            $row = $rows[$name];
            if ((string) ($row['PROC_INST_ID_'] ?? '') !== $processId
                || (string) ($row['PROC_DEF_ID_'] ?? '') !== $definitionId
                || (string) ($row['TENANT_ID_'] ?? '') !== $tenantId
                || ((bool) $history && (string) ($row['ROOT_PROC_INST_ID_'] ?? '') !== $processId)
            ) {
                throw new RuntimeException('workflow variable scope is inconsistent');
            }
            assertVariableValue($row, (bool) $history, (string) $type, $value);
        }
    }

    $canonicalProcess = oneRow(
        $canonical,
        'SELECT * FROM act_hi_procinst WHERE BINARY PROC_INST_ID_=BINARY ?',
        [$processId],
        'canonical historic process'
    );
    $targetProcess = oneRow(
        $target,
        'SELECT * FROM act_hi_procinst WHERE BINARY PROC_INST_ID_=BINARY ?',
        [$processId],
        'isolated historic process'
    );
    if ($canonicalProcess !== $targetProcess
        || (string) ($targetProcess['STATE_'] ?? '') !== 'ACTIVE'
        || ($targetProcess['END_TIME_'] ?? null) !== null
    ) {
        throw new RuntimeException('historic process changed unexpectedly');
    }
    $nextUser = oneRow(
        $target,
        'SELECT USER_STATUS,DELETE_FLAG,TENANT_ID FROM sys_user WHERE BINARY ID=BINARY ?',
        [$procureUser],
        'next task user'
    );
    if ((string) ($nextUser['USER_STATUS'] ?? '') !== 'ENABLE'
        || !in_array($nextUser['DELETE_FLAG'] ?? null, [null, 'NOT_DELETE'], true)
        || (string) ($nextUser['TENANT_ID'] ?? '') !== $tenantId
    ) {
        throw new RuntimeException('next task user is not active in the process tenant');
    }

    return [
        'rowsOutsideValidatedProcessMatched' => true,
        'exactSingleTransitionMatched' => true,
        'validatedWorkflowTableCount' => count($diffs),
    ];
}

/** @param array<string, mixed> $value */
function writeAtomicJson(string $path, array $value): void
{
    if (file_exists($path)) {
        throw new RuntimeException('provenance evidence already exists');
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false) {
        throw new RuntimeException('unable to write provenance evidence');
    }
    if (file_exists($path) || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('unable to finalize provenance evidence');
    }
}

/** @return array<string, mixed> */
function inspect(array $argv): array
{
    $options = parseOptions($argv);
    $parameters = environmentConfiguration();
    $legacyPosthoc = legacyPosthocMode();
    $canonicalDatabase = canonicalDatabaseFromParameters($parameters);
    $projectRoot = dirname(__DIR__);
    $runtimeInput = $projectRoot . DIRECTORY_SEPARATOR . 'runtime';
    if (is_link($runtimeInput)) {
        throw new RuntimeException('audit runtime root is unavailable');
    }
    $runtimeRoot = realpath($runtimeInput);
    if ($runtimeRoot === false || !is_dir($runtimeRoot) || is_link($runtimeRoot)) {
        throw new RuntimeException('audit runtime root is unavailable');
    }

    $pointerInputPath = requiredEnvironment('OA_ISOLATED_POINTER_PATH');
    $pointerPath = resolveEvidenceCandidate(
        $pointerInputPath,
        $runtimeRoot,
        'private validation pointer'
    );
    $pointer = readJson($pointerPath, 'private validation pointer');
    $targetDatabase = trim((string) ($pointer['database'] ?? ''));
    if (!hash_equals($parameters['targetDatabase'], $targetDatabase)) {
        throw new RuntimeException('private validation database differs from the explicit invocation');
    }
    $pointerExpectedMetadata = [
        'version' => 2,
        'canonicalDatabase' => $canonicalDatabase,
        'databaseHost' => $parameters['databaseHost'],
        'runLabel' => $parameters['runLabel'],
        'runDate' => $parameters['runDate'],
        'expectedTableCount' => $parameters['expectedTableCount'],
        'expectedForeignKeyConstraintCount' => $parameters['expectedForeignKeyCount'],
    ];
    foreach ($pointerExpectedMetadata as $field => $expected) {
        if (array_key_exists($field, $pointer) && (string) $pointer[$field] !== (string) $expected) {
            throw new RuntimeException('private validation pointer metadata differs from the explicit invocation');
        }
        if (!$legacyPosthoc && !array_key_exists($field, $pointer)) {
            throw new RuntimeException('private validation pointer metadata is incomplete');
        }
    }
    $manifestRelative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) ($pointer['manifest'] ?? ''));
    $manifestInput = $projectRoot . DIRECTORY_SEPARATOR . $manifestRelative;
    $manifest = resolveEvidenceCandidate(
        $manifestInput,
        $runtimeRoot,
        'private validation manifest',
        true
    );

    $defaultClonePath = $manifest . DIRECTORY_SEPARATOR . 'clone-completed.json';
    $cloneRelative = str_replace(
        ['/', '\\'],
        DIRECTORY_SEPARATOR,
        (string) ($pointer['cloneCompletedMarker'] ?? '')
    );
    $cloneSha256 = (string) ($pointer['cloneCompletedMarkerSha256'] ?? '');
    $cloneInput = $defaultClonePath;
    if ($cloneRelative !== '') {
        $cloneInput = $projectRoot . DIRECTORY_SEPARATOR . $cloneRelative;
    } elseif (!$legacyPosthoc) {
        throw new RuntimeException('clone marker path is missing from the private pointer');
    }
    $clonePath = resolveEvidenceCandidate($cloneInput, $manifest, 'clone marker');
    if (!sameEvidencePath($defaultClonePath, $clonePath)) {
        throw new RuntimeException('clone marker path differs from the private pointer');
    }
    if ($cloneSha256 !== '') {
        $clone = readPinnedJson($clonePath, 'clone marker', $cloneSha256);
    } elseif ($legacyPosthoc) {
        $clone = readJson($clonePath, 'clone marker');
    } else {
        throw new RuntimeException('clone marker SHA256 is missing from the private pointer');
    }
    $cloneExpectedMetadata = [
        'runLabel' => $parameters['runLabel'],
        'runDate' => $parameters['runDate'],
        'databaseHost' => $parameters['databaseHost'],
        'expectedTableCount' => $parameters['expectedTableCount'],
        'expectedForeignKeyConstraintCount' => $parameters['expectedForeignKeyCount'],
    ];
    foreach ($cloneExpectedMetadata as $field => $expected) {
        if (array_key_exists($field, $clone) && (string) $clone[$field] !== (string) $expected) {
            throw new RuntimeException('clone marker metadata differs from the explicit invocation');
        }
        if (!$legacyPosthoc && !array_key_exists($field, $clone)) {
            throw new RuntimeException('clone marker metadata is incomplete');
        }
    }
    if (($clone['status'] ?? null) !== 'completed'
        || ($clone['sourceDatabase'] ?? null) !== $canonicalDatabase
        || ($clone['targetDatabase'] ?? null) !== $targetDatabase
        || (int) ($clone['tableCount'] ?? -1) !== $parameters['expectedTableCount']
        || (int) ($clone['foreignKeyConstraintCount'] ?? -1) !== $parameters['expectedForeignKeyCount']
        || ($clone['foreignKeyDefinitionsMatch'] ?? null) !== true
        || ($clone['sourceWritesPerformed'] ?? null) !== false
    ) {
        throw new RuntimeException('clone marker is incomplete');
    }

    $validation = readJson($manifest . DIRECTORY_SEPARATOR . 'validation-completed.json', 'validation marker');
    if ($legacyPosthoc
        && !array_key_exists('canonicalFingerprintsUnchanged', $validation)
        && ($validation['canonicalBaselineUnchanged'] ?? null) === true
    ) {
        $validation['canonicalFingerprintsUnchanged'] = true;
    }
    foreach ([
        'approvalContinuationPassed',
        'currentTaskReadApisPassed',
        'nextTaskReadApisPassed',
        'nextTaskCountMatchedDatabase',
        'businessFingerprintsUnchanged',
        'canonicalFingerprintsUnchanged',
        'validationWritesIsolated',
    ] as $field) {
        if (($validation[$field] ?? null) !== true) {
            throw new RuntimeException('validation marker is incomplete');
        }
    }
    if (($validation['status'] ?? null) !== 'completed') {
        throw new RuntimeException('validation marker is incomplete');
    }
    try {
        $cloneCompletedAt = new \DateTimeImmutable((string) ($clone['completedAt'] ?? ''));
        $validationCompletedAt = new \DateTimeImmutable((string) ($validation['completedAt'] ?? ''));
    } catch (Throwable $exception) {
        throw new RuntimeException('validation evidence timestamps are invalid', 0, $exception);
    }
    if ($cloneCompletedAt >= $validationCompletedAt) {
        throw new RuntimeException('validation evidence timestamps are out of order');
    }
    $evidencePath = $manifest . DIRECTORY_SEPARATOR . 'validation-provenance-audit.json';
    if (($options['write-evidence'] ?? '') === '1' && file_exists($evidencePath)) {
        throw new RuntimeException('provenance evidence already exists');
    }

    $targetFinalInputPath = requiredEnvironment('OA_ISOLATED_TARGET_FINAL_MARKER_PATH');
    $targetFinalPath = resolveEvidenceCandidate(
        $targetFinalInputPath,
        $runtimeRoot,
        'target-final marker'
    );
    $pointerTargetFinal = str_replace(
        ['/', '\\'],
        DIRECTORY_SEPARATOR,
        (string) ($pointer['targetFinalMarker'] ?? '')
    );
    if ($pointerTargetFinal === '' && !$legacyPosthoc) {
        throw new RuntimeException('target-final marker is missing from the private pointer');
    }
    if ($pointerTargetFinal !== '') {
        $pointerTargetFinalInput = $projectRoot . DIRECTORY_SEPARATOR . $pointerTargetFinal;
        $pointerTargetFinalPath = resolveEvidenceCandidate(
            $pointerTargetFinalInput,
            $runtimeRoot,
            'pointer target-final marker'
        );
        if (!sameEvidencePath($targetFinalPath, $pointerTargetFinalPath)) {
            throw new RuntimeException('target-final marker differs from the private pointer');
        }
    }
    $targetFinalSha256 = (string) ($pointer['targetFinalMarkerSha256'] ?? '');
    if ($targetFinalSha256 !== '') {
        $targetFinal = readPinnedJson($targetFinalPath, 'target-final marker', $targetFinalSha256);
    } elseif ($legacyPosthoc) {
        $targetFinal = readJson($targetFinalPath, 'target-final marker');
    } else {
        throw new RuntimeException('target-final marker SHA256 is missing from the private pointer');
    }
    if (($targetFinal['database'] ?? null) !== $canonicalDatabase
        || (int) ($targetFinal['tableCount'] ?? -1) !== $parameters['expectedTableCount']
        || preg_match('/^[a-f0-9]{64}$/', (string) ($targetFinal['schemaSha256'] ?? '')) !== 1
        || !is_array($targetFinal['tables'] ?? null)
        || !is_array($targetFinal['rowCounts'] ?? null)
    ) {
        throw new RuntimeException('target-final marker is incomplete');
    }

    $loader = require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!$loader instanceof \Composer\Autoload\ClassLoader) {
        throw new RuntimeException('composer autoloader is unavailable');
    }
    $loader->setPsr4('app\\', [$projectRoot . DIRECTORY_SEPARATOR . 'app']);
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oa-database-migration.php';
    $app = new App($projectRoot);
    $app->initialize();
    $connection = (array) $app->config->get('database.connections.mysql', []);
    $canonicalPdo = pdoForDatabase($connection, $canonicalDatabase);
    $targetPdo = pdoForDatabase($connection, $targetDatabase);

    $canonicalManifest = DatabaseManifest::capture($canonicalPdo, $canonicalDatabase, true);
    if (($canonicalManifest['schemaSha256'] ?? null) !== ($targetFinal['schemaSha256'] ?? null)
        || ($canonicalManifest['tables'] ?? null) !== ($targetFinal['tables'] ?? null)
        || ($canonicalManifest['rowCounts'] ?? null) !== ($targetFinal['rowCounts'] ?? null)
    ) {
        throw new RuntimeException('canonical database differs from target-final evidence');
    }
    $targetManifest = DatabaseManifest::capture($targetPdo, $targetDatabase, true);
    if (($targetManifest['schemaSha256'] ?? null) !== ($targetFinal['schemaSha256'] ?? null)
        || ($targetManifest['tables'] ?? null) !== ($targetFinal['tables'] ?? null)
        || (int) ($targetManifest['tableCount'] ?? -1) !== $parameters['expectedTableCount']
    ) {
        throw new RuntimeException('isolated validation schema differs from canonical schema');
    }

    if (($options['diff-only'] ?? '') === '1') {
        $rowDiffs = [];
        foreach ([
            'act_hi_actinst',
            'act_hi_taskinst',
            'act_hi_varinst',
            'act_ru_execution',
            'act_ru_task',
            'act_ru_variable',
        ] as $table) {
            $rowDiffs[$table] = sanitizedRowDiff($canonicalPdo, $targetPdo, $targetDatabase, $table);
        }

        return [
            'status' => 'diff-inspected',
            'canonicalTargetFinalMatched' => true,
            'isolatedSchemaMatched' => true,
            'rowDiffs' => $rowDiffs,
            'databaseWritesPerformed' => false,
        ];
    }

    $tables = array_keys((array) $targetFinal['tables']);
    sort($tables, SORT_STRING);
    $changedTables = [];
    foreach ($tables as $table) {
        $canonical = fingerprint($canonicalPdo, $canonicalDatabase, (string) $table);
        $target = fingerprint($targetPdo, $targetDatabase, (string) $table);
        if ($canonical !== $target) {
            $changedTables[] = (string) $table;
        }
    }

    $expectedChangedTables = [
        'act_hi_actinst',
        'act_hi_taskinst',
        'act_hi_varinst',
        'act_ru_execution',
        'act_ru_task',
        'act_ru_variable',
    ];
    if ($changedTables !== $expectedChangedTables) {
        throw new RuntimeException('isolated validation changed an unexpected table set');
    }

    if (($options['write-evidence'] ?? '') === '1' || ($options['verify-only'] ?? '') === '1') {
        $rawDiffs = [];
        foreach ($expectedChangedTables as $table) {
            $rawDiffs[$table] = rawRowDiff($canonicalPdo, $targetPdo, $targetDatabase, $table);
        }
        $transition = assertExactSingleTransition($canonicalPdo, $targetPdo, $rawDiffs);
        $toolFiles = [
            __FILE__,
            __DIR__ . DIRECTORY_SEPARATOR . 'create-isolated-validation-clone.php',
            __DIR__ . DIRECTORY_SEPARATOR . 'isolated-approval-validation-client.php',
            __DIR__ . DIRECTORY_SEPARATOR . 'run-r10-isolated-approval-validation.ps1',
            __DIR__ . DIRECTORY_SEPARATOR . 'prepare-r10-isolated-validation.php',
            __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'isolated-validation-parameters.php',
        ];
        $toolHashes = [];
        foreach ($toolFiles as $toolFile) {
            $hash = hash_file('sha256', $toolFile);
            if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new RuntimeException('validation tooling hash is unavailable');
            }
            $toolHashes[] = $hash;
        }
        $evidence = [
            'status' => 'completed',
            'posthocDatabaseProvenancePassed' => true,
            'canonicalSnapshotMatched' => true,
            'schemaAndForeignKeysMatched' => true,
            'tableCount' => count($tables),
            'unchangedTableCount' => count($tables) - count($changedTables),
            'changedTableCount' => count($changedTables),
            'changedTables' => $changedTables,
            'changedTableSetMatched' => true,
            'rowsOutsideValidatedProcessMatched' => (bool) $transition['rowsOutsideValidatedProcessMatched'],
            'exactSingleTransitionMatched' => (bool) $transition['exactSingleTransitionMatched'],
            'validatedWorkflowTableCount' => (int) $transition['validatedWorkflowTableCount'],
            'businessTablesUnchanged' => true,
            'canonicalDatabaseWritesDetected' => false,
            'databaseWritesPerformedByAudit' => false,
            'historicServerIdentityRetroactivelyProvable' => false,
            'historicValidationStartedMarkerPresent' => false,
            'validationToolingSha256' => hash('sha256', implode('', $toolHashes)),
            'completedAt' => gmdate(DATE_ATOM),
        ];
        if (($options['write-evidence'] ?? '') === '1') {
            writeAtomicJson($evidencePath, $evidence);
        } else {
            $evidence['status'] = 'verified';
        }

        return $evidence;
    }

    return [
        'status' => 'inspected',
        'canonicalTargetFinalMatched' => true,
        'isolatedSchemaMatched' => true,
        'tableCount' => count($tables),
        'changedTableCount' => count($changedTables),
        'changedTables' => $changedTables,
        'databaseWritesPerformed' => false,
    ];
}

function auditMain(array $argv): int
{
    try {
        fwrite(STDOUT, json_encode(inspect($argv), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

        return 0;
    } catch (Throwable) {
        fwrite(STDERR, "isolated validation provenance inspection failed\n");

        return 1;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(auditMain($argv));
}
