<?php

declare(strict_types=1);

namespace app\service\workflow;

use app\model\ActHiVarinst;
use app\model\ActRuVariable;

/**
 * Normalizes Camunda runtime/history variable rows for workflow query APIs.
 */
class WorkflowVariableService
{
    /**
     * @return array<string, mixed>
     */
    public function runtimeByProcessInstance(string $processInstanceId): array
    {
        $rows = ActRuVariable::where('PROC_INST_ID_', $processInstanceId)
            ->select()
            ->toArray();

        return $this->normalizeMap($rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function historyByProcessInstance(string $processInstanceId): array
    {
        $rows = ActHiVarinst::where('PROC_INST_ID_', $processInstanceId)
            ->select()
            ->toArray();

        return $this->normalizeMap($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function normalizeMap(array $rows): array
    {
        $variables = [];

        foreach ($rows as $row) {
            $name = (string)($row['NAME_'] ?? '');
            if ($name === '') {
                continue;
            }

            $variables[$name] = $this->normalizeValue($row);
        }

        return $variables;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeValue(array $row): mixed
    {
        $type = (string)($row['TYPE_'] ?? $row['VAR_TYPE_'] ?? '');

        if (array_key_exists('TEXT_', $row) && $row['TEXT_'] !== null) {
            return $this->decodeText((string)$row['TEXT_'], $type);
        }

        if (array_key_exists('LONG_', $row) && $row['LONG_'] !== null) {
            return (int)$row['LONG_'];
        }

        if (array_key_exists('DOUBLE_', $row) && $row['DOUBLE_'] !== null) {
            return (float)$row['DOUBLE_'];
        }

        if (array_key_exists('BYTEARRAY_ID_', $row) && $row['BYTEARRAY_ID_'] !== null) {
            return ['bytearrayId' => $row['BYTEARRAY_ID_'], 'type' => $type];
        }

        return null;
    }

    private function decodeText(string $value, string $type): mixed
    {
        if ($type === 'boolean') {
            return in_array(strtolower($value), ['true', '1'], true);
        }

        if ($type === 'integer' || $type === 'long' || $type === 'short') {
            return (int)$value;
        }

        if ($type === 'double') {
            return (float)$value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
