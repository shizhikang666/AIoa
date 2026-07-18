<?php

declare(strict_types=1);

namespace app\support\migration;

use PDO;
use PDOException;

class PdoWorkflowVariableMigrationStore implements WorkflowVariableMigrationStore
{
    private const MIN_RUNTIME_TEXT_BYTES = 4000;
    private const MIN_HISTORY_TEXT_BYTES = 64000;

    private const VARIABLE_TABLES = [
        'act_ru_variable' => 'TYPE_',
        'act_hi_varinst' => 'VAR_TYPE_',
    ];

    private PDO $pdo;
    private string $expectedDatabase;

    public function __construct(PDO $pdo, string $expectedDatabase)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $expectedDatabase)) {
            throw new WorkflowVariableMigrationException('TARGET_DATABASE_NAME_REJECTED');
        }
        $this->pdo = $pdo;
        $this->expectedDatabase = $expectedDatabase;
    }

    public function assertTargetSafety(): void
    {
        if (!preg_match('/_(?:migrated|migration|rehearsal)(?:_[A-Za-z0-9]+)*$/i', $this->expectedDatabase)) {
            throw new WorkflowVariableMigrationException('TARGET_DATABASE_SUFFIX_REJECTED');
        }

        $actual = (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($actual === '' || $actual !== $this->expectedDatabase) {
            throw new WorkflowVariableMigrationException('TARGET_DATABASE_MISMATCH');
        }

        $requiredColumns = [
            'act_ru_variable' => [
                'ID_', 'PROC_INST_ID_', 'NAME_', 'TYPE_', 'BYTEARRAY_ID_',
                'DOUBLE_', 'LONG_', 'TEXT_', 'TEXT2_',
            ],
            'act_hi_varinst' => [
                'ID_', 'PROC_INST_ID_', 'NAME_', 'VAR_TYPE_', 'BYTEARRAY_ID_',
                'DOUBLE_', 'LONG_', 'TEXT_', 'TEXT2_',
            ],
            'act_ge_bytearray' => ['ID_', 'BYTES_'],
            // Marker proving that the target was built from the new PHP schema.
            'biz_sale_project' => ['TRAVEL_DAYS'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    throw new WorkflowVariableMigrationException('TARGET_SCHEMA_REJECTED');
                }
            }
        }
        if ($this->textColumnCapacity('act_ru_variable', 'TEXT_') < self::MIN_RUNTIME_TEXT_BYTES
            || $this->textColumnCapacity('act_hi_varinst', 'TEXT_') < self::MIN_HISTORY_TEXT_BYTES
        ) {
            throw new WorkflowVariableMigrationException('TARGET_VARIABLE_TEXT_CAPACITY_REJECTED');
        }

        foreach ([
            'biz_after_sales_category',
            'biz_after_sales_record',
            'biz_sale_project_delivery_plan',
        ] as $table) {
            if (!$this->tableExists($table)) {
                throw new WorkflowVariableMigrationException('TARGET_SCHEMA_MARKER_REJECTED');
            }
        }

        foreach (array_keys(self::VARIABLE_TABLES) as $table) {
            $engine = $this->tableEngine($table);
            if (strtoupper($engine) !== 'INNODB') {
                throw new WorkflowVariableMigrationException('TARGET_TRANSACTION_ENGINE_REJECTED');
            }
        }
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->beginTransaction()) {
            throw new WorkflowVariableMigrationException('TARGET_TRANSACTION_BEGIN_FAILED');
        }
    }

    public function commit(): void
    {
        if (!$this->pdo->commit()) {
            throw new WorkflowVariableMigrationException('TARGET_TRANSACTION_COMMIT_FAILED');
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction() && !$this->pdo->rollBack()) {
            throw new WorkflowVariableMigrationException('TARGET_TRANSACTION_ROLLBACK_FAILED');
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function fetchSerializedVariables(bool $forUpdate): array
    {
        $rows = [];
        foreach (array_keys(self::VARIABLE_TABLES) as $table) {
            $sql = sprintf(
                'SELECT v.`ID_`, v.`PROC_INST_ID_`, v.`NAME_`, v.`BYTEARRAY_ID_`, '
                . 'b.`ID_` AS `BYTEARRAY_ROW_ID_`, b.`BYTES_` '
                . 'FROM `%s`.`%s` v '
                . 'LEFT JOIN `%s`.`act_ge_bytearray` b ON b.`ID_` = v.`BYTEARRAY_ID_` '
                . 'WHERE v.`BYTEARRAY_ID_` IS NOT NULL '
                . 'ORDER BY v.`ID_`%s',
                $this->expectedDatabase,
                $table,
                $this->expectedDatabase,
                $forUpdate ? ' FOR UPDATE' : ''
            );
            try {
                $statement = $this->pdo->query($sql);
            } catch (PDOException) {
                throw new WorkflowVariableMigrationException('TARGET_VARIABLE_READ_FAILED');
            }
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ((string)($row['BYTEARRAY_ROW_ID_'] ?? '') !== (string)($row['BYTEARRAY_ID_'] ?? '')) {
                    throw new WorkflowVariableMigrationException('TARGET_BYTEARRAY_REFERENCE_REJECTED');
                }
                $bytes = $row['BYTES_'] ?? null;
                if (is_resource($bytes)) {
                    $bytes = stream_get_contents($bytes);
                }
                if (!is_string($bytes)) {
                    throw new WorkflowVariableMigrationException('TARGET_BYTEARRAY_VALUE_REJECTED');
                }
                $rows[] = [
                    'sourceTable' => $table,
                    'id' => (string)($row['ID_'] ?? ''),
                    'processInstanceId' => (string)($row['PROC_INST_ID_'] ?? ''),
                    'name' => (string)($row['NAME_'] ?? ''),
                    'bytearrayId' => (string)($row['BYTEARRAY_ID_'] ?? ''),
                    'serializedBytes' => $bytes,
                ];
            }
        }
        return $rows;
    }

    public function updateVariable(
        string $sourceTable,
        string $id,
        string $expectedBytearrayId,
        string $json
    ): int {
        $typeColumn = self::VARIABLE_TABLES[$sourceTable] ?? null;
        if ($typeColumn === null) {
            throw new WorkflowVariableMigrationException('TARGET_VARIABLE_TABLE_REJECTED');
        }

        $sql = sprintf(
            'UPDATE `%s`.`%s` SET `%s` = :type, `BYTEARRAY_ID_` = NULL, '
            . '`DOUBLE_` = NULL, `LONG_` = NULL, `TEXT_` = :json, `TEXT2_` = NULL '
            . 'WHERE `ID_` = :id AND `BYTEARRAY_ID_` = :bytearrayId',
            $this->expectedDatabase,
            $sourceTable,
            $typeColumn
        );
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'type' => 'string',
                'json' => $json,
                'id' => $id,
                'bytearrayId' => $expectedBytearrayId,
            ]);
            return $statement->rowCount();
        } catch (PDOException) {
            throw new WorkflowVariableMigrationException('TARGET_VARIABLE_UPDATE_FAILED');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute([
            'database' => $this->expectedDatabase,
            'table' => $table,
            'column' => $column,
        ]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function textColumnCapacity(string $table, string $column): int
    {
        $statement = $this->pdo->prepare(
            'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute([
            'database' => $this->expectedDatabase,
            'table' => $table,
            'column' => $column,
        ]);
        $capacity = $statement->fetchColumn();
        return $capacity === false || $capacity === null ? 0 : (int)$capacity;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table'
        );
        $statement->execute([
            'database' => $this->expectedDatabase,
            'table' => $table,
        ]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function tableEngine(string $table): string
    {
        $statement = $this->pdo->prepare(
            'SELECT ENGINE FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table'
        );
        $statement->execute([
            'database' => $this->expectedDatabase,
            'table' => $table,
        ]);
        return (string)$statement->fetchColumn();
    }
}
