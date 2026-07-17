<?php

declare(strict_types=1);

namespace app\support\migration;

interface WorkflowVariableMigrationStore
{
    public function assertTargetSafety(): void;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    /**
     * @return array<int, array{
     *     sourceTable: string,
     *     id: string,
     *     processInstanceId: string,
     *     name: string,
     *     bytearrayId: string,
     *     serializedBytes: string
     * }>
     */
    public function fetchSerializedVariables(bool $forUpdate): array;

    public function updateVariable(
        string $sourceTable,
        string $id,
        string $expectedBytearrayId,
        string $json
    ): int;
}
