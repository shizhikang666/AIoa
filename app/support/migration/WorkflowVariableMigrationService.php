<?php

declare(strict_types=1);

namespace app\support\migration;

use JsonException;
use Throwable;

class WorkflowVariableMigrationService
{
    private const MAX_JSON_BYTES = 4000;

    private WorkflowVariableMigrationStore $store;
    private JavaSerializationDecoder $decoder;

    public function __construct(
        WorkflowVariableMigrationStore $store,
        ?JavaSerializationDecoder $decoder = null
    ) {
        $this->store = $store;
        $this->decoder = $decoder ?? new JavaSerializationDecoder();
    }

    /**
     * @return array{
     *     mode: string,
     *     candidateCount: int,
     *     runtimeCount: int,
     *     historyCount: int,
     *     processIdCount: int,
     *     processIdDigest: string,
     *     variableIdDigest: string,
     *     bytearrayIdCount: int,
     *     bytearrayIdDigest: string,
     *     appliedCount: int
     * }
     */
    public function run(bool $apply = false): array
    {
        $this->store->assertTargetSafety();
        if (!$apply) {
            $plan = $this->buildPlan($this->store->fetchSerializedVariables(false));
            return $this->summary($plan, false, 0);
        }

        $this->store->beginTransaction();
        try {
            $plan = $this->buildPlan($this->store->fetchSerializedVariables(true));
            $applied = 0;
            foreach ($plan as $item) {
                $count = $this->store->updateVariable(
                    $item['sourceTable'],
                    $item['id'],
                    $item['bytearrayId'],
                    $item['json']
                );
                if ($count !== 1) {
                    throw new WorkflowVariableMigrationException('TARGET_VARIABLE_CHANGED_DURING_APPLY');
                }
                $applied++;
            }
            $summary = $this->summary($plan, true, $applied);
            $this->store->commit();
            return $summary;
        } catch (Throwable $throwable) {
            if ($this->store->inTransaction()) {
                $this->store->rollBack();
            }
            throw $throwable;
        }
    }

    /**
     * @param array<int, array{
     *     sourceTable: string,
     *     id: string,
     *     processInstanceId: string,
     *     name: string,
     *     bytearrayId: string,
     *     serializedBytes: string
     * }> $rows
     * @return array<int, array{
     *     sourceTable: string,
     *     id: string,
     *     processInstanceId: string,
     *     name: string,
     *     bytearrayId: string,
     *     json: string
     * }>
     */
    private function buildPlan(array $rows): array
    {
        $plan = [];
        $seenRows = [];
        $semanticValues = [];

        foreach ($rows as $row) {
            $table = $row['sourceTable'];
            $id = $row['id'];
            $processId = $row['processInstanceId'];
            $name = $row['name'];
            $bytearrayId = $row['bytearrayId'];
            if (!in_array($table, ['act_ru_variable', 'act_hi_varinst'], true)
                || $id === ''
                || $processId === ''
                || $name === ''
                || $bytearrayId === '') {
                throw new WorkflowVariableMigrationException('VARIABLE_ROW_IDENTITY_REJECTED');
            }

            $rowKey = $table . "\0" . $id;
            if (isset($seenRows[$rowKey])) {
                throw new WorkflowVariableMigrationException('VARIABLE_ROW_DUPLICATE_REJECTED');
            }
            $seenRows[$rowKey] = true;

            try {
                $decoded = $this->decoder->decode($row['serializedBytes']);
                $json = json_encode(
                    $decoded,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } catch (WorkflowVariableMigrationException $exception) {
                throw new WorkflowVariableMigrationException(
                    'SERIALIZED_VALUE_REJECTED_' . $this->idSummary($id) . '_' . $exception->getMessage()
                );
            } catch (JsonException) {
                throw new WorkflowVariableMigrationException(
                    'SERIALIZED_JSON_REJECTED_' . $this->idSummary($id)
                );
            }

            if (strlen($json) > self::MAX_JSON_BYTES) {
                throw new WorkflowVariableMigrationException(
                    'SERIALIZED_JSON_TOO_LONG_' . $this->idSummary($id)
                );
            }

            $semanticKey = strlen($processId) . ':' . $processId . strlen($name) . ':' . $name;
            if (isset($semanticValues[$semanticKey]) && $semanticValues[$semanticKey] !== $json) {
                throw new WorkflowVariableMigrationException(
                    'RUNTIME_HISTORY_VALUE_MISMATCH_' . $this->idSummary($processId)
                );
            }
            $semanticValues[$semanticKey] = $json;

            $plan[] = [
                'sourceTable' => $table,
                'id' => $id,
                'processInstanceId' => $processId,
                'name' => $name,
                'bytearrayId' => $bytearrayId,
                'json' => $json,
            ];
        }

        return $plan;
    }

    /**
     * @param array<int, array{
     *     sourceTable: string,
     *     id: string,
     *     processInstanceId: string,
     *     name: string,
     *     bytearrayId: string,
     *     json: string
     * }> $plan
     * @return array{
     *     mode: string,
     *     candidateCount: int,
     *     runtimeCount: int,
     *     historyCount: int,
     *     processIdCount: int,
     *     processIdDigest: string,
     *     variableIdDigest: string,
     *     bytearrayIdCount: int,
     *     bytearrayIdDigest: string,
     *     appliedCount: int
     * }
     */
    private function summary(array $plan, bool $apply, int $applied): array
    {
        $runtime = 0;
        $history = 0;
        $processIds = [];
        $variableIds = [];
        $bytearrayIds = [];
        foreach ($plan as $item) {
            if ($item['sourceTable'] === 'act_ru_variable') {
                $runtime++;
            } else {
                $history++;
            }
            $processIds[$item['processInstanceId']] = true;
            $variableIds[] = $item['sourceTable'] . ':' . $item['id'];
            $bytearrayIds[$item['bytearrayId']] = true;
        }

        return [
            'mode' => $apply ? 'apply' : 'dry-run',
            'candidateCount' => count($plan),
            'runtimeCount' => $runtime,
            'historyCount' => $history,
            'processIdCount' => count($processIds),
            'processIdDigest' => $this->digest(array_keys($processIds)),
            'variableIdDigest' => $this->digest($variableIds),
            'bytearrayIdCount' => count($bytearrayIds),
            'bytearrayIdDigest' => $this->digest(array_keys($bytearrayIds)),
            'appliedCount' => $applied,
        ];
    }

    /** @param array<int, string> $ids */
    private function digest(array $ids): string
    {
        sort($ids, SORT_STRING);
        return hash('sha256', implode("\n", $ids));
    }

    private function idSummary(string $id): string
    {
        return substr(hash('sha256', $id), 0, 12);
    }
}
