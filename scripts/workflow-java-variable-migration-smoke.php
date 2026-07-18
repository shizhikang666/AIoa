<?php

declare(strict_types=1);

use app\support\migration\JavaSerializationDecoder;
use app\support\migration\WorkflowVariableMigrationException;
use app\support\migration\WorkflowVariableMigrationService;
use app\support\migration\WorkflowVariableMigrationStore;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/support/migration/WorkflowVariableMigrationException.php';
require dirname(__DIR__) . '/app/support/migration/WorkflowVariableMigrationStore.php';
require dirname(__DIR__) . '/app/support/migration/JavaSerializationDecoder.php';
require dirname(__DIR__) . '/app/support/migration/WorkflowVariableMigrationService.php';
require __DIR__ . '/fixtures/workflow-java-variable-fixtures.php';

final class OfflineWorkflowVariableStore implements WorkflowVariableMigrationStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows;
    /** @var array<string, string> */
    public array $bytearrays;
    public int $updateCalls = 0;
    public int $rollbackCalls = 0;
    public int $commitCalls = 0;
    public ?int $failOnUpdate = null;
    private bool $transaction = false;
    /** @var array<int, array<string, mixed>> */
    private array $snapshot = [];

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->bytearrays = [];
        foreach ($rows as $row) {
            $this->bytearrays[(string)$row['bytearrayId']] = (string)$row['serializedBytes'];
        }
    }

    public function assertTargetSafety(): void
    {
    }

    public function beginTransaction(): void
    {
        if ($this->transaction) {
            throw new RuntimeException('nested offline transaction');
        }
        $this->transaction = true;
        $this->snapshot = $this->rows;
    }

    public function commit(): void
    {
        $this->transaction = false;
        $this->snapshot = [];
        $this->commitCalls++;
    }

    public function rollBack(): void
    {
        $this->rows = $this->snapshot;
        $this->snapshot = [];
        $this->transaction = false;
        $this->rollbackCalls++;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function fetchSerializedVariables(bool $forUpdate): array
    {
        if ($forUpdate !== $this->transaction) {
            throw new RuntimeException('offline lock contract failed');
        }
        $result = [];
        foreach ($this->rows as $row) {
            if (($row['bytearrayId'] ?? '') === '') {
                continue;
            }
            $result[] = [
                'sourceTable' => (string)$row['sourceTable'],
                'id' => (string)$row['id'],
                'processInstanceId' => (string)$row['processInstanceId'],
                'name' => (string)$row['name'],
                'bytearrayId' => (string)$row['bytearrayId'],
                'serializedBytes' => (string)$row['serializedBytes'],
            ];
        }
        return $result;
    }

    public function updateVariable(
        string $sourceTable,
        string $id,
        string $expectedBytearrayId,
        string $json
    ): int {
        $this->updateCalls++;
        if ($this->failOnUpdate === $this->updateCalls) {
            throw new WorkflowVariableMigrationException('OFFLINE_FORCED_UPDATE_FAILURE');
        }
        foreach ($this->rows as &$row) {
            if ($row['sourceTable'] !== $sourceTable || $row['id'] !== $id) {
                continue;
            }
            if ($row['bytearrayId'] !== $expectedBytearrayId) {
                return 0;
            }
            $row['type'] = 'string';
            $row['text'] = $json;
            $row['bytearrayId'] = '';
            return 1;
        }
        unset($row);
        return 0;
    }
}

/** @param mixed $actual */
function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('assertion failed: ' . $label);
    }
}

/** @param callable(): void $callback */
function assertMigrationFailure(string $contains, callable $callback, string $label): void
{
    try {
        $callback();
    } catch (WorkflowVariableMigrationException $exception) {
        if (!str_contains($exception->getMessage(), $contains)) {
            throw new RuntimeException('unexpected failure code: ' . $label);
        }
        return;
    }
    throw new RuntimeException('missing failure: ' . $label);
}

/** @return array<string, mixed> */
function fixtureRow(
    string $table,
    string $id,
    string $processId,
    string $name,
    string $bytearrayId,
    string $bytes
): array {
    return [
        'sourceTable' => $table,
        'id' => $id,
        'processInstanceId' => $processId,
        'name' => $name,
        'bytearrayId' => $bytearrayId,
        'serializedBytes' => $bytes,
        'type' => 'object',
        'text' => null,
    ];
}

$decoder = new JavaSerializationDecoder();
$allowed = $decoder->decode(WorkflowJavaVariableFixtureBuilder::allowedObjectList());
assertSameValue('12.50', $allowed[0]['number'] ?? null, 'BigDecimal/BigInteger conversion');
assertSameValue('fixture-product', $allowed[0]['productName'] ?? null, 'procure product field');
assertSameValue('fixture-supplier', $allowed[1]['name'] ?? null, 'supplier field');
$projectAllowed = $decoder->decode(WorkflowJavaVariableFixtureBuilder::allowedProjectObjectList());
assertSameValue('0.2', $projectAllowed[0]['amount'] ?? null, 'project delivery amount');
assertSameValue('0.10', $projectAllowed[1]['discountRate'] ?? null, 'project reissue discount');
assertSameValue(
    'fixture-return-project-item-id',
    $projectAllowed[2]['projectProductItemId'] ?? null,
    'project return item id'
);
assertSameValue(
    'fixture-relation-product',
    $projectAllowed[3]['children'][0]['productName'] ?? null,
    'sale project child relation'
);
assertSameValue('1', $projectAllowed[4]['number'] ?? null, 'sale project relation number');
assertSameValue(null, $projectAllowed[5]['amount'] ?? null, 'sale project invoicing nullable amount');
assertSameValue(
    'fixture-customer-company',
    $projectAllowed[5]['customerCompany'] ?? null,
    'sale project invoicing customer'
);
$emptyChildren = $decoder->decode(WorkflowJavaVariableFixtureBuilder::saleProjectItemEmptyChildren());
assertSameValue([], $emptyChildren['children'] ?? null, 'sale project empty child relations');
assertSameValue([], $decoder->decode(WorkflowJavaVariableFixtureBuilder::emptyList()), 'empty list');
assertSameValue(
    [[], []],
    $decoder->decode(WorkflowJavaVariableFixtureBuilder::reusedClassDescriptorList()),
    'class descriptor reference'
);

assertMigrationFailure(
    'JAVA_CLASS_NOT_ALLOWED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::unknownObject()),
    'unknown class rejection'
);
assertMigrationFailure(
    'JAVA_CLASS_IDENTITY_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryWrongUid()),
    'project class UID rejection'
);
assertMigrationFailure(
    'JAVA_CLASS_IDENTITY_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryWrongFlags()),
    'project class flags rejection'
);
assertMigrationFailure(
    'JAVA_CLASS_FIELDS_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryWrongFieldSignature()),
    'project class field signature rejection'
);
assertMigrationFailure(
    'JAVA_CLASS_FIELDS_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryMissingField()),
    'project class missing field rejection'
);
assertMigrationFailure(
    'JAVA_CLASS_FIELDS_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryExtraField()),
    'project class extra field rejection'
);
assertMigrationFailure(
    'JAVA_CLASS_SUPER_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryWrongSuper()),
    'project class superclass rejection'
);
assertMigrationFailure(
    'JAVA_CUSTOM_DECIMAL_VALUE_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::projectDeliveryNullAmount()),
    'project class decimal value rejection'
);
assertMigrationFailure(
    'JAVA_CUSTOM_RELATION_ITEM_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::saleProjectItemInvalidChild()),
    'sale project child relation rejection'
);
assertMigrationFailure(
    'JAVA_CUSTOM_RELATION_ITEM_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::saleProjectItemNullChild()),
    'sale project null child rejection'
);
assertMigrationFailure(
    'JAVA_CUSTOM_RELATION_ITEM_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::saleProjectItemOtherObjectChild()),
    'sale project other DTO child rejection'
);
assertMigrationFailure(
    'JAVA_CUSTOM_RELATION_ITEM_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::saleProjectItemNestedListChild()),
    'sale project nested-list child rejection'
);
assertMigrationFailure(
    'JAVA_PROXY_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::proxyObject()),
    'proxy rejection'
);
assertMigrationFailure(
    'JAVA_ROOT_VALUE_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::rootString()),
    'non-audited root value rejection'
);
$truncated = substr(WorkflowJavaVariableFixtureBuilder::allowedObjectList(), 0, -2);
assertMigrationFailure('JAVA_STREAM_TRUNCATED', static fn () => $decoder->decode($truncated), 'truncated stream');

$scalarBoundary = $decoder->decode(
    WorkflowJavaVariableFixtureBuilder::repeatedStringReferenceList(str_repeat('x', 4000), 16)
);
assertSameValue(16, count($scalarBoundary), 'expanded scalar 64000-byte boundary');
assertMigrationFailure(
    'JAVA_EXPANDED_SCALAR_LIMIT_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::repeatedStringReferenceList(str_repeat('x', 4000), 17)
    ),
    'expanded scalar 68000-byte rejection'
);
$wireStringBoundary = $decoder->decode(
    WorkflowJavaVariableFixtureBuilder::stringList([str_repeat('s', 4000)])
);
assertSameValue(4000, strlen($wireStringBoundary[0] ?? ''), 'wire string 4000-byte boundary');
assertMigrationFailure(
    'JAVA_STRING_SIZE_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::stringList([str_repeat('s', 4001)])
    ),
    'wire string 4001-byte boundary'
);
assertMigrationFailure(
    'JAVA_EXPANDED_SCALAR_LIMIT_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::repeatedNestedListDag(
            [str_repeat('d', 200)],
            321
        )
    ),
    'repeated nested-list DAG expansion'
);
assertMigrationFailure(
    'JAVA_PARSED_CONTAINER_ITEM_LIMIT_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::nestedWireNullLists(1, 10000)
    ),
    'nested wire container parse budget'
);

$depthBoundary = $decoder->decode(
    WorkflowJavaVariableFixtureBuilder::referencedNestedListChain(63)
);
assertSameValue(63, count($depthBoundary), 'expanded depth boundary');
assertMigrationFailure(
    'JAVA_EXPANDED_DEPTH_LIMIT_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::referencedNestedListChain(64)
    ),
    'referenced nested-list chain depth'
);

$nullBoundary = $decoder->decode(WorkflowJavaVariableFixtureBuilder::nullList(4095));
assertSameValue(4095, count($nullBoundary), 'expanded node boundary');
assertMigrationFailure(
    'JAVA_EXPANDED_NODE_LIMIT_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::nullList(4096)),
    'all-null node expansion'
);
assertMigrationFailure(
    'JAVA_EXPANDED_NODE_LIMIT_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::repeatedEmptyListReferenceList(4096)
    ),
    'repeated empty-list node expansion'
);
assertMigrationFailure(
    'JAVA_PARSED_CONTAINER_ITEM_LIMIT_REJECTED',
    static fn () => $decoder->decode(WorkflowJavaVariableFixtureBuilder::nullList(4097)),
    'wire container item expansion'
);
assertMigrationFailure(
    'JAVA_EXPANDED_CONTAINER_ITEM_LIMIT_REJECTED',
    static fn () => $decoder->decode(
        WorkflowJavaVariableFixtureBuilder::repeatedNestedListDag(array_fill(0, 2, ''), 2048)
    ),
    'referenced container item expansion'
);

$sharedBytes = WorkflowJavaVariableFixtureBuilder::stringList(['fixture-a', 'fixture-b']);
$dryStore = new OfflineWorkflowVariableStore([
    fixtureRow('act_ru_variable', 'var-runtime', 'process-one', 'approveUserIdList', 'bytes-one', $sharedBytes),
    fixtureRow('act_hi_varinst', 'var-history', 'process-one', 'approveUserIdList', 'bytes-two', $sharedBytes),
]);
$drySummary = (new WorkflowVariableMigrationService($dryStore))->run();
assertSameValue('dry-run', $drySummary['mode'], 'dry-run default');
assertSameValue(0, $dryStore->updateCalls, 'dry-run writes');
assertSameValue('bytes-one', $dryStore->rows[0]['bytearrayId'], 'dry-run state');

$applyStore = new OfflineWorkflowVariableStore($dryStore->rows);
$originalBytearrays = $applyStore->bytearrays;
$applySummary = (new WorkflowVariableMigrationService($applyStore))->run(true);
assertSameValue(2, $applySummary['appliedCount'], 'apply count');
assertSameValue(1, $applyStore->commitCalls, 'apply commit');
assertSameValue('', $applyStore->rows[0]['bytearrayId'], 'runtime bytearray cleared');
assertSameValue('', $applyStore->rows[1]['bytearrayId'], 'history bytearray cleared');
assertSameValue($applyStore->rows[0]['text'], $applyStore->rows[1]['text'], 'runtime/history semantic equality');
assertSameValue($originalBytearrays, $applyStore->bytearrays, 'original bytearrays retained');

$jsonBoundaryStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_ru_variable',
        'var-json-boundary',
        'process-json-boundary',
        'fileIdList',
        'bytes-json-boundary',
        WorkflowJavaVariableFixtureBuilder::stringList([str_repeat('x', 3996)])
    ),
]);
$jsonBoundarySummary = (new WorkflowVariableMigrationService($jsonBoundaryStore))->run();
assertSameValue(1, $jsonBoundarySummary['candidateCount'], 'JSON 4000-byte boundary');
assertSameValue(0, $jsonBoundaryStore->updateCalls, 'JSON boundary dry-run no writes');

$overlongStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_ru_variable',
        'var-overlong',
        'process-overlong',
        'fileIdList',
        'bytes-overlong',
        WorkflowJavaVariableFixtureBuilder::stringList([str_repeat('x', 3997)])
    ),
]);
assertMigrationFailure(
    'SERIALIZED_JSON_TOO_LONG',
    static fn () => (new WorkflowVariableMigrationService($overlongStore))->run(),
    'JSON overlong rejection'
);
assertSameValue(0, $overlongStore->updateCalls, 'overlong no writes');

$longHistoryBytes = WorkflowJavaVariableFixtureBuilder::stringList([
    str_repeat('h', 3000),
    str_repeat('i', 3000),
]);
$longHistoryStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_hi_varinst',
        'var-long-history',
        'process-long-history',
        'productList',
        'bytes-long-history',
        $longHistoryBytes
    ),
]);
$longHistorySummary = (new WorkflowVariableMigrationService($longHistoryStore))->run();
assertSameValue(1, $longHistorySummary['candidateCount'], 'history JSON above runtime limit accepted');
assertSameValue(0, $longHistoryStore->updateCalls, 'long history dry-run no writes');

$historyLimitStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_hi_varinst',
        'var-history-limit',
        'process-history-limit',
        'productList',
        'bytes-history-limit',
        WorkflowJavaVariableFixtureBuilder::repeatedStringReferenceList(str_repeat('q', 4000), 15)
    ),
]);
$historyLimitSummary = (new WorkflowVariableMigrationService($historyLimitStore))->run();
assertSameValue(1, $historyLimitSummary['candidateCount'], 'history JSON below 64000-byte limit');

$historyOverlongStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_hi_varinst',
        'var-history-overlong',
        'process-history-overlong',
        'productList',
        'bytes-history-overlong',
        WorkflowJavaVariableFixtureBuilder::repeatedStringReferenceList(str_repeat('q', 4000), 16)
    ),
]);
assertMigrationFailure(
    'SERIALIZED_JSON_TOO_LONG',
    static fn () => (new WorkflowVariableMigrationService($historyOverlongStore))->run(),
    'history JSON overlong rejection'
);
assertSameValue(0, $historyOverlongStore->updateCalls, 'history overlong no writes');

$budgetRollbackStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_ru_variable',
        'budget-rollback-valid',
        'process-budget-valid',
        'copyUserIdList',
        'budget-bytes-valid',
        $sharedBytes
    ),
    fixtureRow(
        'act_hi_varinst',
        'budget-rollback-amplified',
        'process-budget-amplified',
        'fileIdList',
        'budget-bytes-amplified',
        WorkflowJavaVariableFixtureBuilder::repeatedStringReferenceList(str_repeat('z', 4000), 17)
    ),
]);
$budgetRollbackRows = $budgetRollbackStore->rows;
$budgetRollbackBytearrays = $budgetRollbackStore->bytearrays;
assertMigrationFailure(
    'JAVA_EXPANDED_SCALAR_LIMIT_REJECTED',
    static fn () => (new WorkflowVariableMigrationService($budgetRollbackStore))->run(true),
    'expansion budget apply rollback'
);
assertSameValue($budgetRollbackRows, $budgetRollbackStore->rows, 'budget rollback restored rows');
assertSameValue(
    $budgetRollbackBytearrays,
    $budgetRollbackStore->bytearrays,
    'budget rollback retained bytearrays'
);
assertSameValue(1, $budgetRollbackStore->rollbackCalls, 'budget rollback count');
assertSameValue(0, $budgetRollbackStore->updateCalls, 'budget rollback before writes');

$parsedBudgetRollbackStore = new OfflineWorkflowVariableStore([
    fixtureRow(
        'act_ru_variable',
        'parsed-budget-valid',
        'process-parsed-valid',
        'copyUserIdList',
        'parsed-bytes-valid',
        $sharedBytes
    ),
    fixtureRow(
        'act_hi_varinst',
        'parsed-budget-amplified',
        'process-parsed-amplified',
        'fileIdList',
        'parsed-bytes-amplified',
        WorkflowJavaVariableFixtureBuilder::nestedWireNullLists(1, 10000)
    ),
]);
$parsedBudgetRows = $parsedBudgetRollbackStore->rows;
assertMigrationFailure(
    'JAVA_PARSED_CONTAINER_ITEM_LIMIT_REJECTED',
    static fn () => (new WorkflowVariableMigrationService($parsedBudgetRollbackStore))->run(true),
    'parsed container budget apply rollback'
);
assertSameValue($parsedBudgetRows, $parsedBudgetRollbackStore->rows, 'parsed budget rollback restored rows');
assertSameValue(1, $parsedBudgetRollbackStore->rollbackCalls, 'parsed budget rollback count');
assertSameValue(0, $parsedBudgetRollbackStore->updateCalls, 'parsed budget rollback before writes');

$rollbackStore = new OfflineWorkflowVariableStore([
    fixtureRow('act_ru_variable', 'rollback-one', 'process-rollback', 'copyUserIdList', 'rollback-bytes-one', $sharedBytes),
    fixtureRow('act_hi_varinst', 'rollback-two', 'process-rollback', 'copyUserIdList', 'rollback-bytes-two', $sharedBytes),
]);
$rollbackOriginalRows = $rollbackStore->rows;
$rollbackOriginalBytearrays = $rollbackStore->bytearrays;
$rollbackStore->failOnUpdate = 2;
assertMigrationFailure(
    'OFFLINE_FORCED_UPDATE_FAILURE',
    static fn () => (new WorkflowVariableMigrationService($rollbackStore))->run(true),
    'transaction rollback'
);
assertSameValue($rollbackOriginalRows, $rollbackStore->rows, 'rollback restored rows');
assertSameValue($rollbackOriginalBytearrays, $rollbackStore->bytearrays, 'rollback retained bytearrays');
assertSameValue(1, $rollbackStore->rollbackCalls, 'rollback count');
assertSameValue(0, $rollbackStore->commitCalls, 'rollback no commit');

$mismatchStore = new OfflineWorkflowVariableStore([
    fixtureRow('act_ru_variable', 'mismatch-one', 'process-mismatch', 'copyUserIdList', 'mismatch-bytes-one', $sharedBytes),
    fixtureRow(
        'act_hi_varinst',
        'mismatch-two',
        'process-mismatch',
        'copyUserIdList',
        'mismatch-bytes-two',
        WorkflowJavaVariableFixtureBuilder::stringList(['different'])
    ),
]);
assertMigrationFailure(
    'RUNTIME_HISTORY_VALUE_MISMATCH',
    static fn () => (new WorkflowVariableMigrationService($mismatchStore))->run(true),
    'runtime/history mismatch rollback'
);
assertSameValue(1, $mismatchStore->rollbackCalls, 'mismatch rollback count');
assertSameValue(0, $mismatchStore->updateCalls, 'mismatch no writes');

fwrite(STDOUT, "workflow Java variable migration offline smoke passed\n");
