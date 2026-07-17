<?php

declare(strict_types=1);

namespace Oa\DatabaseMigration;

use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class MigrationOptions
{
    private const ALLOWED = [
        'source-defaults',
        'source-db',
        'target-defaults',
        'template-db',
        'target-db',
        'quarantine-db',
        'allow-target',
        'known-orphans',
        'workflow-converter',
        'manifest-dir',
        'mysql-bin',
        'mysqldump-bin',
        'php-bin',
        'apply',
        'allow-remote-workflow-converter',
        'confirm-token',
        'source-freeze-token',
    ];

    /** @var array<string, string|bool|list<string>> */
    private array $values;

    /** @param array<string, string|bool|list<string>> $values */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function fromArgv(array $argv): self
    {
        $values = [];
        foreach (array_slice($argv, 1) as $argument) {
            if (!is_string($argument) || !str_starts_with($argument, '--')) {
                throw new RuntimeException('all migration arguments must use --name=value or --flag');
            }
            $body = substr($argument, 2);
            if ($body === '') {
                throw new RuntimeException('empty migration option');
            }
            $separator = strpos($body, '=');
            if ($separator === false) {
                $name = $body;
                $value = true;
            } else {
                $name = substr($body, 0, $separator);
                $value = substr($body, $separator + 1);
            }
            if (!in_array($name, self::ALLOWED, true)) {
                throw new RuntimeException("unknown migration option --{$name}");
            }
            $flags = ['apply', 'allow-remote-workflow-converter'];
            if (in_array($name, $flags, true) && $value !== true) {
                throw new RuntimeException("--{$name} is a flag and may not have a value");
            }
            if (!in_array($name, $flags, true) && $value === true) {
                throw new RuntimeException("--{$name} requires a value");
            }
            if ($name === 'allow-target') {
                $existing = $values[$name] ?? [];
                if (!is_array($existing)) {
                    $existing = [];
                }
                $existing[] = (string)$value;
                $values[$name] = $existing;
                continue;
            }
            if (array_key_exists($name, $values)) {
                throw new RuntimeException("duplicate migration option --{$name}");
            }
            $values[$name] = $value;
        }

        return new self($values);
    }

    public function flag(string $name): bool
    {
        return ($this->values[$name] ?? false) === true;
    }

    public function string(string $name, string $default = ''): string
    {
        $value = $this->values[$name] ?? $default;
        if (is_array($value) || is_bool($value)) {
            return $default;
        }

        return trim((string)$value);
    }

    /** @return list<string> */
    public function list(string $name): array
    {
        $value = $this->values[$name] ?? [];
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $value), static fn (string $item): bool => $item !== ''));
    }

}

final class MigrationSafety
{
    public const FREEZE_TOKEN = 'JAVA_STOPPED_AND_SOURCE_FROZEN';

    public static function identifier(string $value, string $label): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new RuntimeException("{$label} must contain only letters, numbers, and underscores");
        }

        return $value;
    }

    public static function confirmToken(string $sourceDatabase, string $targetDatabase, string $quarantineDatabase): string
    {
        $raw = "MIGRATE_{$sourceDatabase}_TO_{$targetDatabase}_WITH_{$quarantineDatabase}";

        return strtoupper(preg_replace('/[^A-Za-z0-9_]+/', '_', $raw) ?? $raw);
    }

    public static function planBoundConfirmToken(
        string $sourceDatabase,
        string $targetDatabase,
        string $quarantineDatabase,
        string $planSha256
    ): string {
        if (!preg_match('/^[a-f0-9]{64}$/', $planSha256)) {
            throw new RuntimeException('migration plan digest is invalid');
        }

        return self::confirmToken($sourceDatabase, $targetDatabase, $quarantineDatabase)
            . '_PLAN_' . strtoupper(substr($planSha256, 0, 32));
    }

    /** @param list<string> $allowedTargets */
    public static function assertSafeTopology(
        MysqlProfile $source,
        string $sourceDatabase,
        MysqlProfile $target,
        string $templateDatabase,
        string $targetDatabase,
        string $quarantineDatabase,
        array $allowedTargets
    ): void {
        foreach ([
            'source database' => $sourceDatabase,
            'template database' => $templateDatabase,
            'target database' => $targetDatabase,
            'quarantine database' => $quarantineDatabase,
        ] as $label => $database) {
            self::identifier($database, $label);
        }
        if (!str_ends_with(strtolower($targetDatabase), '_migrated')) {
            throw new RuntimeException('target database must end with _migrated');
        }
        if (!in_array($targetDatabase, $allowedTargets, true)) {
            throw new RuntimeException('target database is not present in the explicit --allow-target whitelist');
        }
        if (!preg_match('/_quarantine_\d{8}$/i', $quarantineDatabase)) {
            throw new RuntimeException('quarantine database must end with _quarantine_YYYYMMDD');
        }
        if ($targetDatabase === $templateDatabase || $targetDatabase === $quarantineDatabase) {
            throw new RuntimeException('target, template, and quarantine databases must be distinct');
        }
        if ($source->endpointKey() === $target->endpointKey() && $sourceDatabase === $targetDatabase) {
            throw new RuntimeException('source and target resolve to the same MySQL schema');
        }
    }
}

final class MysqlProfile
{
    /** @param array<string, string> $client */
    private function __construct(private readonly string $path, private readonly array $client)
    {
    }

    public static function fromDefaultsFile(string $path): self
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('MySQL defaults file is not readable');
        }
        $parsed = parse_ini_file($path, true, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            throw new RuntimeException('unable to parse MySQL defaults file');
        }
        $client = $parsed['client'] ?? $parsed;
        if (!is_array($client)) {
            throw new RuntimeException('MySQL defaults file has no [client] section');
        }
        $normalized = [];
        foreach ($client as $key => $value) {
            if (is_scalar($value)) {
                $normalized[strtolower((string)$key)] = (string)$value;
            }
        }
        if (trim($normalized['user'] ?? '') === '') {
            throw new RuntimeException('MySQL defaults file has no client user');
        }

        return new self((string)realpath($path), $normalized);
    }

    public function defaultsPath(): string
    {
        return $this->path;
    }

    public function endpointKey(): string
    {
        $socket = trim($this->client['socket'] ?? '');
        if ($socket !== '') {
            return 'socket:' . strtolower(str_replace('\\', '/', $socket));
        }
        $host = strtolower(trim($this->client['host'] ?? '127.0.0.1'));
        $port = trim($this->client['port'] ?? '3306');

        return "tcp:{$host}:{$port}";
    }

    public function redactedSummary(): array
    {
        return [
            'endpointHash' => hash('sha256', $this->endpointKey()),
            'socket' => trim($this->client['socket'] ?? '') !== '',
        ];
    }

    /** @return array{host:string,port:string,user:string,password:string,remote:bool} */
    public function childConnection(): array
    {
        $host = trim($this->client['host'] ?? '127.0.0.1');
        if (trim($this->client['socket'] ?? '') !== '') {
            $host = 'localhost';
        }
        $port = trim($this->client['port'] ?? '3306');
        $normalizedHost = strtolower($host);

        return [
            'host' => $host,
            'port' => $port,
            'user' => (string)$this->client['user'],
            'password' => (string)($this->client['password'] ?? ''),
            'remote' => !in_array($normalizedHost, ['127.0.0.1', 'localhost', '::1'], true),
        ];
    }

    public function connect(string $database = ''): PDO
    {
        $charset = trim($this->client['default-character-set'] ?? 'utf8mb4');
        $databasePart = $database !== '' ? ';dbname=' . MigrationSafety::identifier($database, 'connection database') : '';
        $socket = trim($this->client['socket'] ?? '');
        if ($socket !== '') {
            $dsn = "mysql:unix_socket={$socket}{$databasePart};charset={$charset}";
        } else {
            $host = trim($this->client['host'] ?? '127.0.0.1');
            $port = trim($this->client['port'] ?? '3306');
            $dsn = "mysql:host={$host};port={$port}{$databasePart};charset={$charset}";
        }
        $pdo = new PDO(
            $dsn,
            (string)$this->client['user'],
            (string)($this->client['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => true,
            ]
        );
        return $pdo;
    }
}

final class ManifestStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('unable to create migration manifest directory');
        }
    }

    public function path(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?? $name;

        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $safe;
    }

    public function writeJson(string $name, mixed $value): string
    {
        try {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('unable to encode migration manifest: ' . $exception->getMessage(), 0, $exception);
        }
        $path = $this->path($name);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('unable to write migration manifest');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('unable to finalize migration manifest');
        }

        return $path;
    }
}

final class DatabaseManifest
{
    /** @return array<string, mixed> */
    public static function capture(PDO $pdo, string $database, bool $includeRows): array
    {
        MigrationSafety::identifier($database, 'manifest database');
        $tableStatement = $pdo->prepare(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        );
        $tableStatement->execute([$database]);
        $tables = [];
        foreach ($tableStatement->fetchAll() as $row) {
            $name = (string)$row['TABLE_NAME'];
            $tables[$name] = [
                'engine' => (string)($row['ENGINE'] ?? ''),
                'collation' => (string)($row['TABLE_COLLATION'] ?? ''),
                'columns' => [],
                'indexes' => [],
                'foreignKeys' => [],
            ];
        }

        $columnStatement = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
            . 'CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION'
        );
        $columnStatement->execute([$database]);
        foreach ($columnStatement->fetchAll() as $row) {
            $table = (string)$row['TABLE_NAME'];
            if (!isset($tables[$table])) {
                continue;
            }
            $tables[$table]['columns'][(string)$row['COLUMN_NAME']] = [
                'ordinal' => (int)$row['ORDINAL_POSITION'],
                'type' => strtolower((string)$row['COLUMN_TYPE']),
                'nullable' => (string)$row['IS_NULLABLE'],
                'default' => $row['COLUMN_DEFAULT'],
                'extra' => strtolower((string)($row['EXTRA'] ?? '')),
                'charset' => $row['CHARACTER_SET_NAME'],
                'collation' => $row['COLLATION_NAME'],
            ];
        }

        $indexStatement = $pdo->prepare(
            'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, COLLATION, INDEX_TYPE '
            . 'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? '
            . 'ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
        );
        $indexStatement->execute([$database]);
        foreach ($indexStatement->fetchAll() as $row) {
            $table = (string)$row['TABLE_NAME'];
            $index = (string)$row['INDEX_NAME'];
            if (!isset($tables[$table])) {
                continue;
            }
            if (!isset($tables[$table]['indexes'][$index])) {
                $tables[$table]['indexes'][$index] = [
                    'unique' => (string)$row['NON_UNIQUE'] === '0',
                    'type' => (string)$row['INDEX_TYPE'],
                    'columns' => [],
                ];
            }
            $tables[$table]['indexes'][$index]['columns'][] = [
                'name' => $row['COLUMN_NAME'],
                'prefix' => $row['SUB_PART'] === null ? null : (int)$row['SUB_PART'],
                'collation' => $row['COLLATION'],
            ];
        }

        $foreignStatement = $pdo->prepare(
            'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION, k.COLUMN_NAME, '
            . 'k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE '
            . 'FROM information_schema.KEY_COLUMN_USAGE k '
            . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
            . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME '
            . 'AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
            . 'WHERE k.CONSTRAINT_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL '
            . 'ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION'
        );
        $foreignStatement->execute([$database]);
        foreach ($foreignStatement->fetchAll() as $row) {
            $table = (string)$row['TABLE_NAME'];
            $constraint = (string)$row['CONSTRAINT_NAME'];
            if (!isset($tables[$table])) {
                continue;
            }
            if (!isset($tables[$table]['foreignKeys'][$constraint])) {
                $tables[$table]['foreignKeys'][$constraint] = [
                    'referencedTable' => (string)$row['REFERENCED_TABLE_NAME'],
                    'updateRule' => (string)$row['UPDATE_RULE'],
                    'deleteRule' => (string)$row['DELETE_RULE'],
                    'columns' => [],
                ];
            }
            $tables[$table]['foreignKeys'][$constraint]['columns'][] = [
                'name' => (string)$row['COLUMN_NAME'],
                'referenced' => (string)$row['REFERENCED_COLUMN_NAME'],
            ];
        }

        $rowCounts = [];
        if ($includeRows) {
            foreach (array_keys($tables) as $table) {
                $quoted = self::quoteIdentifier($table);
                $rowCounts[$table] = (int)$pdo->query(
                    'SELECT COUNT(*) FROM ' . self::quoteIdentifier($database) . '.' . $quoted
                )->fetchColumn();
            }
        }

        return [
            'database' => $database,
            'capturedAt' => gmdate('c'),
            'tableCount' => count($tables),
            'columnCount' => array_sum(array_map(
                static fn (array $table): int => count($table['columns'] ?? []),
                $tables
            )),
            'tables' => $tables,
            'rowCounts' => $rowCounts,
            'schemaSha256' => self::schemaHash($tables),
        ];
    }

    /** @param array<string, mixed> $tables */
    public static function schemaHash(array $tables): string
    {
        return hash('sha256', json_encode($tables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function quoteIdentifier(string $identifier): string
    {
        MigrationSafety::identifier($identifier, 'SQL identifier');

        return '`' . $identifier . '`';
    }

    /** @param list<string> $tables @return array<string, string> */
    public static function tableChecksums(PDO $pdo, string $database, array $tables): array
    {
        $checksums = [];
        foreach ($tables as $table) {
            $rows = $pdo->query(
                'CHECKSUM TABLE ' . self::quoteIdentifier($database) . '.' . self::quoteIdentifier($table)
            )->fetchAll();
            $checksum = $rows[0]['Checksum'] ?? $rows[0]['CHECKSUM'] ?? null;
            if ($checksum === null || $checksum === '') {
                throw new RuntimeException("source checksum is unavailable for {$table}");
            }
            $checksums[$table] = (string)$checksum;
        }
        ksort($checksums);

        return $checksums;
    }
}

final class SchemaPolicy
{
    public const NEW_TABLES = [
        'biz_after_sales_category',
        'biz_after_sales_record',
        'biz_sale_project_delivery_plan',
    ];

    /** @return array<string, mixed> */
    public static function compareSourceToTemplate(array $source, array $template): array
    {
        $sourceTables = $source['tables'] ?? [];
        $templateTables = $template['tables'] ?? [];
        if (!is_array($sourceTables) || !is_array($templateTables)) {
            throw new RuntimeException('schema manifest is malformed');
        }
        $missingTables = array_values(array_diff(array_keys($sourceTables), array_keys($templateTables)));
        $newTables = array_values(array_diff(array_keys($templateTables), array_keys($sourceTables)));
        sort($missingTables);
        sort($newTables);

        $columnMismatches = [];
        $extraColumns = [];
        $indexMismatches = [];
        $foreignKeyMismatches = [];
        foreach ($sourceTables as $table => $sourceDefinition) {
            if (!isset($templateTables[$table]) || !is_array($sourceDefinition)) {
                continue;
            }
            $targetDefinition = $templateTables[$table];
            $sourceColumns = $sourceDefinition['columns'] ?? [];
            $targetColumns = $targetDefinition['columns'] ?? [];
            foreach ($sourceColumns as $column => $definition) {
                if (!array_key_exists($column, $targetColumns)) {
                    $columnMismatches[] = "{$table}.{$column}:missing-in-template";
                    continue;
                }
                if (self::normalizedColumn($definition) !== self::normalizedColumn($targetColumns[$column])) {
                    $columnMismatches[] = "{$table}.{$column}:definition-differs";
                }
            }
            foreach (array_diff(array_keys($targetColumns), array_keys($sourceColumns)) as $column) {
                $extraColumns[] = "{$table}.{$column}";
            }
            if (($sourceDefinition['indexes'] ?? []) !== ($targetDefinition['indexes'] ?? [])) {
                $indexMismatches[] = (string)$table;
            }
            if (($sourceDefinition['foreignKeys'] ?? []) !== ($targetDefinition['foreignKeys'] ?? [])) {
                $foreignKeyMismatches[] = (string)$table;
            }
        }
        sort($columnMismatches);
        sort($extraColumns);
        sort($indexMismatches);
        sort($foreignKeyMismatches);
        $requiredNewTables = self::NEW_TABLES;
        sort($requiredNewTables);
        $expectedExtraColumns = ['biz_sale_project.TRAVEL_DAYS'];

        return [
            'sourceTableCount' => count($sourceTables),
            'templateTableCount' => count($templateTables),
            'sourceColumnCount' => array_sum(array_map(
                static fn (array $table): int => count($table['columns'] ?? []),
                $sourceTables
            )),
            'templateColumnCount' => array_sum(array_map(
                static fn (array $table): int => count($table['columns'] ?? []),
                $templateTables
            )),
            'missingTables' => $missingTables,
            'newTables' => $newTables,
            'columnMismatches' => $columnMismatches,
            'extraColumns' => $extraColumns,
            'indexMismatches' => $indexMismatches,
            'foreignKeyMismatches' => $foreignKeyMismatches,
            'valid' => $missingTables === []
                && $newTables === $requiredNewTables
                && $columnMismatches === []
                && $extraColumns === $expectedExtraColumns
                && $indexMismatches === []
                && $foreignKeyMismatches === [],
        ];
    }

    public static function assertExpected(
        array $comparison,
        int $expectedSourceTables,
        int $expectedTemplateTables,
        ?int $expectedSourceColumns = null,
        ?int $expectedTemplateColumns = null
    ): void {
        if (($comparison['sourceTableCount'] ?? -1) !== $expectedSourceTables) {
            throw new RuntimeException('source table count differs from the audited baseline');
        }
        if (($comparison['templateTableCount'] ?? -1) !== $expectedTemplateTables) {
            throw new RuntimeException('template table count differs from the audited baseline');
        }
        if ($expectedSourceColumns !== null && ($comparison['sourceColumnCount'] ?? -1) !== $expectedSourceColumns) {
            throw new RuntimeException('source column count differs from the audited baseline');
        }
        if ($expectedTemplateColumns !== null && ($comparison['templateColumnCount'] ?? -1) !== $expectedTemplateColumns) {
            throw new RuntimeException('template column count differs from the audited baseline');
        }
        if (($comparison['valid'] ?? false) !== true) {
            throw new RuntimeException('source/template schema compatibility gate failed');
        }
    }

    /** @return array<string, mixed> */
    private static function normalizedColumn(mixed $definition): array
    {
        if (!is_array($definition)) {
            return [];
        }
        $type = strtolower((string)($definition['type'] ?? ''));
        $type = preg_replace('/\b(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $type) ?? $type;

        return [
            'type' => $type,
            'nullable' => strtoupper((string)($definition['nullable'] ?? '')),
            'default' => $definition['default'] ?? null,
            'extra' => strtolower((string)($definition['extra'] ?? '')),
            'charset' => $definition['charset'] ?? null,
            'collation' => $definition['collation'] ?? null,
        ];
    }
}

final class OrphanPolicy
{
    /** @return list<array<string, string>> */
    public static function detect(PDO $pdo, string $database): array
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $sql = "SELECT t.ID_ AS taskId, t.PROC_INST_ID_ AS processId, t.EXECUTION_ID_ AS executionId, "
            . "t.PROC_DEF_ID_ AS processDefinitionId, COALESCE(t.TENANT_ID_, '') AS tenantId, "
            . "COALESCE(t.ASSIGNEE_, '') AS assigneeId "
            . "FROM {$db}.act_ru_task t "
            . "LEFT JOIN {$db}.act_hi_procinst h ON h.PROC_INST_ID_ = t.PROC_INST_ID_ "
            . "WHERE h.PROC_INST_ID_ IS NULL ORDER BY t.ID_";
        $rows = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $rows[] = array_map(static fn (mixed $value): string => (string)($value ?? ''), $row);
        }

        return $rows;
    }

    /** @param list<array<string, string>> $orphans @return array<string, mixed> */
    public static function assertIsolationEligible(PDO $pdo, string $database, array $orphans): array
    {
        if ($orphans === []) {
            throw new RuntimeException('orphan isolation eligibility requires detected tasks');
        }
        $db = DatabaseManifest::quoteIdentifier($database);
        $taskIds = self::values($orphans, 'taskId');
        $processIds = self::values($orphans, 'processId');
        $checks = [];
        $specs = [
            'act_ru_variable' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_procinst' => ['PROC_INST_ID_' => $processIds],
            'act_hi_taskinst' => ['ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_actinst' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_detail' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_varinst' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_identitylink' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_comment' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
            'act_hi_attachment' => ['TASK_ID_' => $taskIds, 'PROC_INST_ID_' => $processIds],
        ];
        foreach ($specs as $table => $columns) {
            $available = self::columns($pdo, $database, $table);
            if ($available === []) {
                continue;
            }
            [$where, $parameters] = self::whereFromColumns($columns, $available);
            if ($where === '') {
                continue;
            }
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}." . DatabaseManifest::quoteIdentifier($table) . " WHERE {$where}"
            );
            $statement->execute($parameters);
            $checks[$table] = (int)$statement->fetchColumn();
            if ($checks[$table] !== 0) {
                throw new RuntimeException("known orphan candidate unexpectedly has linked rows in {$table}");
            }
        }

        $businessColumns = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'PROCESS_ID' "
            . "AND TABLE_NAME NOT LIKE 'act\\_%' ORDER BY TABLE_NAME"
        );
        $businessColumns->execute([$database]);
        foreach ($businessColumns->fetchAll() as $row) {
            $table = (string)$row['TABLE_NAME'];
            [$where, $parameters] = self::inPredicate('PROCESS_ID', $processIds);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}." . DatabaseManifest::quoteIdentifier($table) . " WHERE {$where}"
            );
            $statement->execute($parameters);
            $count = (int)$statement->fetchColumn();
            $checks["business:{$table}.PROCESS_ID"] = $count;
            if ($count !== 0) {
                throw new RuntimeException('known orphan candidate is now linked to a business record');
            }
        }

        if (self::columns($pdo, $database, 'sys_user') !== []) {
            $userStatement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.sys_user WHERE ID = ? AND TENANT_ID = ? "
                . "AND (DELETE_FLAG IS NULL OR DELETE_FLAG = 'NOT_DELETE')"
            );
            foreach ($orphans as $orphan) {
                $assignee = trim($orphan['assigneeId'] ?? '');
                if ($assignee === '') {
                    continue;
                }
                $userStatement->execute([$assignee, $orphan['tenantId']]);
                if ((int)$userStatement->fetchColumn() !== 0) {
                    throw new RuntimeException('known orphan assignee now exists in the same tenant; manual review is required');
                }
            }
        }

        $bytearrayCount = 0;
        $bytearrayColumns = self::columns($pdo, $database, 'act_ge_bytearray');
        if (isset($bytearrayColumns['ROOT_PROC_INST_ID_'])) {
            [$where, $parameters] = self::inPredicate('ROOT_PROC_INST_ID_', $processIds);
            $statement = $pdo->prepare("SELECT COUNT(*) FROM {$db}.act_ge_bytearray WHERE {$where}");
            $statement->execute($parameters);
            $bytearrayCount = (int)$statement->fetchColumn();
        }

        return [
            'taskCount' => count($taskIds),
            'processCount' => count($processIds),
            'linkedRowChecks' => $checks,
            'rootBytearrayRowsPreservedForQuarantine' => $bytearrayCount,
        ];
    }

    /** @return list<string> */
    public static function loadAllowlist(string $path, int $expectedCount): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('known orphan allowlist is required and must be readable');
        }
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('known orphan allowlist is not valid JSON', 0, $exception);
        }
        $ids = $decoded['taskIds'] ?? null;
        if (!is_array($ids)) {
            throw new RuntimeException('known orphan allowlist must contain taskIds');
        }
        $ids = array_values(array_unique(array_map(static fn (mixed $id): string => trim((string)$id), $ids)));
        sort($ids);
        if (count($ids) !== $expectedCount || in_array('', $ids, true)) {
            throw new RuntimeException('known orphan allowlist count differs from the audited baseline');
        }

        return $ids;
    }

    /** @param list<array<string, string>> $detected @param list<string> $allowed */
    public static function assertExact(array $detected, array $allowed): void
    {
        $detectedIds = array_values(array_unique(array_map(static fn (array $row): string => $row['taskId'], $detected)));
        sort($detectedIds);
        sort($allowed);
        if ($detectedIds !== $allowed) {
            $unknown = array_values(array_diff($detectedIds, $allowed));
            $missing = array_values(array_diff($allowed, $detectedIds));
            throw new RuntimeException(
                'orphan freeze gate failed: unknown=' . count($unknown) . ', missing=' . count($missing)
            );
        }
    }

    /** @param list<array<string, string>> $rows @return list<string> */
    private static function values(array $rows, string $key): array
    {
        $values = array_values(array_unique(array_filter(
            array_map(static fn (array $row): string => trim((string)($row[$key] ?? '')), $rows),
            static fn (string $value): bool => $value !== ''
        )));
        sort($values);

        return $values;
    }

    /** @return array<string, true> */
    private static function columns(PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $statement->execute([$database, $table]);
        $columns = [];
        foreach ($statement->fetchAll() as $row) {
            $columns[(string)$row['COLUMN_NAME']] = true;
        }

        return $columns;
    }

    /** @param array<string, list<string>> $columns @param array<string, true> $available @return array{string,list<string>} */
    private static function whereFromColumns(array $columns, array $available): array
    {
        $predicates = [];
        $parameters = [];
        foreach ($columns as $column => $values) {
            if (!isset($available[$column]) || $values === []) {
                continue;
            }
            [$predicate, $items] = self::inPredicate($column, $values);
            $predicates[] = $predicate;
            array_push($parameters, ...$items);
        }

        return [$predicates === [] ? '' : '(' . implode(' OR ', $predicates) . ')', $parameters];
    }

    /** @param list<string> $values @return array{string,list<string>} */
    private static function inPredicate(string $column, array $values): array
    {
        if ($values === []) {
            throw new RuntimeException('cannot build an empty orphan predicate');
        }

        return [
            DatabaseManifest::quoteIdentifier($column) . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')',
            array_values($values),
        ];
    }
}

final class DumpPolicy
{
    /** @param list<string> $tables @return list<string> */
    public static function dataDumpCommand(
        string $binary,
        MysqlProfile $source,
        string $sourceDatabase,
        string $outputPath,
        array $tables
    ): array {
        $command = [
            $binary,
            '--defaults-extra-file=' . $source->defaultsPath(),
            '--no-create-info',
            '--complete-insert',
            '--single-transaction',
            '--quick',
            '--hex-blob',
            '--skip-triggers',
            '--column-statistics=0',
            '--set-gtid-purged=OFF',
            '--no-tablespaces',
            '--skip-lock-tables',
            '--skip-add-locks',
            '--skip-disable-keys',
            '--default-character-set=utf8mb4',
            '--result-file=' . $outputPath,
            $sourceDatabase,
        ];
        foreach ($tables as $table) {
            $command[] = MigrationSafety::identifier($table, 'dump table');
        }

        return $command;
    }

    /** @return list<string> */
    public static function schemaDumpCommand(
        string $binary,
        MysqlProfile $target,
        string $templateDatabase,
        string $outputPath
    ): array {
        return [
            $binary,
            '--defaults-extra-file=' . $target->defaultsPath(),
            '--no-data',
            '--skip-triggers',
            '--skip-add-drop-table',
            '--column-statistics=0',
            '--set-gtid-purged=OFF',
            '--no-tablespaces',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--result-file=' . $outputPath,
            $templateDatabase,
        ];
    }

    /** @param list<string> $allowedTables @return array<string, mixed> */
    public static function validateDataDump(string $path, array $allowedTables): array
    {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('source data dump is missing or empty');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('unable to read source data dump');
        }
        $dangerousPattern = '/(?:^|\n)\s*(?:\/\*![0-9]{5}\s+)?(?:CREATE|DROP|ALTER|TRUNCATE|RENAME|USE|GRANT|REVOKE|LOCK|UNLOCK|DELETE|UPDATE|REPLACE|LOAD|CALL)\s+/i';
        $insertPattern = '/INSERT\s+INTO\s+`([^`]+)`\s*\(([^)]*)\)\s+VALUES\s*/i';
        $tail = '';
        $insertCount = 0;
        $streamOffset = 0;
        $lastInsertOffset = -1;
        $seenTables = [];
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('unable to scan source data dump');
                }
                $buffer = $tail . $chunk;
                $bufferOffset = $streamOffset - strlen($tail);
                if (preg_match($dangerousPattern, $buffer) === 1) {
                    throw new RuntimeException('source data dump contains forbidden non-INSERT SQL');
                }
                if (preg_match_all($insertPattern, $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
                    foreach ($matches as $match) {
                        $absoluteOffset = $bufferOffset + (int)$match[0][1];
                        if ($absoluteOffset <= $lastInsertOffset) {
                            continue;
                        }
                        $lastInsertOffset = $absoluteOffset;
                        $table = (string)$match[1][0];
                        if (!in_array($table, $allowedTables, true)) {
                            throw new RuntimeException('source data dump contains an unexpected table');
                        }
                        if (trim((string)$match[2][0]) === '') {
                            throw new RuntimeException('source data dump INSERT has no explicit column list');
                        }
                        $insertCount++;
                        $seenTables[$table] = true;
                    }
                }
                $tail = strlen($buffer) > 65536 ? substr($buffer, -65536) : $buffer;
                $streamOffset += strlen($chunk);
            }
        } finally {
            fclose($handle);
        }
        if ($insertCount === 0) {
            throw new RuntimeException('source data dump contains no explicit INSERT statements');
        }

        $tablesWithRows = array_values(array_keys($seenTables));
        sort($tablesWithRows);

        return [
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'insertStatements' => $insertCount,
            'tablesWithRows' => $tablesWithRows,
        ];
    }

    /** @return array<string, mixed> */
    public static function validateSchemaDump(string $path): array
    {
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('template schema dump is missing or empty');
        }
        $contents = (string)file_get_contents($path);
        if (preg_match('/(?:^|\n)\s*(?:\/\*![0-9]{5}\s+)?(?:CREATE|DROP)\s+DATABASE\b|(?:^|\n)\s*(?:\/\*![0-9]{5}\s+)?USE\s+/i', $contents) === 1) {
            throw new RuntimeException('template schema dump may not create, drop, or select a database');
        }
        if (preg_match('/(?:^|\n)\s*(?:\/\*![0-9]{5}\s+)?DROP\s+TABLE\b/i', $contents) === 1) {
            throw new RuntimeException('template schema dump may not drop tables');
        }
        $createCount = preg_match_all('/(?:^|\n)\s*CREATE\s+TABLE\b/i', $contents);
        if ($createCount === false || $createCount === 0) {
            throw new RuntimeException('template schema dump contains no CREATE TABLE statements');
        }

        return [
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'createTables' => $createCount,
        ];
    }
}

final class CommandRunner
{
    /** @return array{exitCode:int,stdout:string,stderr:string} */
    public function run(
        array $command,
        ?string $inputPath = null,
        array $environmentOverrides = [],
        array $sensitiveValues = []
    ): array
    {
        $descriptor = [
            0 => $inputPath === null ? ['pipe', 'r'] : ['file', $inputPath, 'rb'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment = null;
        if ($environmentOverrides !== []) {
            $inherited = getenv();
            $environment = is_array($inherited) ? $inherited : [];
            foreach ($environmentOverrides as $name => $value) {
                $environment[(string)$name] = (string)$value;
            }
        }
        $process = proc_open($command, $descriptor, $pipes, null, $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('unable to start migration command');
        }
        if ($inputPath === null) {
            fclose($pipes[0]);
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $result = [
            'exitCode' => $exitCode,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
        if ($exitCode !== 0) {
            throw new RuntimeException('migration command failed: ' . self::safeError($result['stderr'], $sensitiveValues));
        }

        return $result;
    }

    private static function safeError(string $error, array $sensitiveValues): string
    {
        $error = preg_replace('/password\s*=\s*[^\s]+/i', 'password=[redacted]', $error) ?? $error;
        foreach ($sensitiveValues as $sensitiveValue) {
            $sensitiveValue = (string)$sensitiveValue;
            if ($sensitiveValue !== '') {
                $error = str_replace($sensitiveValue, '[redacted]', $error);
            }
        }

        return trim(substr($error, 0, 2000));
    }
}

final class WorkflowVariableGate
{
    /** @return list<array<string, string>> */
    public static function pending(PDO $pdo, string $database): array
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $pending = [];
        $specs = [
            ['table' => 'act_ru_variable', 'type' => 'TYPE_'],
            ['table' => 'act_hi_varinst', 'type' => 'VAR_TYPE_'],
        ];
        foreach ($specs as $spec) {
            if (!self::tableExists($pdo, $database, $spec['table'])) {
                continue;
            }
            $table = DatabaseManifest::quoteIdentifier($spec['table']);
            $type = DatabaseManifest::quoteIdentifier($spec['type']);
            $sql = "SELECT ID_, PROC_INST_ID_, NAME_, {$type} AS TYPE_NAME, BYTEARRAY_ID_, TEXT_ "
                . "FROM {$db}.{$table} WHERE BYTEARRAY_ID_ IS NOT NULL "
                . "OR LOWER(COALESCE({$type}, '')) IN ('serializable','object') ORDER BY ID_";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                $pending[] = [
                    'table' => $spec['table'],
                    'id' => (string)$row['ID_'],
                    'processId' => (string)($row['PROC_INST_ID_'] ?? ''),
                    'name' => (string)($row['NAME_'] ?? ''),
                    'type' => (string)($row['TYPE_NAME'] ?? ''),
                    'bytearrayId' => (string)($row['BYTEARRAY_ID_'] ?? ''),
                    'bytearraySha256' => '',
                ];
            }
        }

        $byteStatement = $pdo->prepare(
            'SELECT BYTES_ FROM ' . DatabaseManifest::quoteIdentifier($database)
            . '.act_ge_bytearray WHERE ID_ = ?'
        );
        foreach ($pending as &$item) {
            if ($item['bytearrayId'] === '') {
                continue;
            }
            $byteStatement->execute([$item['bytearrayId']]);
            $bytes = $byteStatement->fetchColumn();
            if ($bytes === false) {
                throw new RuntimeException('workflow variable references a missing byte-array row');
            }
            $item['bytearraySha256'] = hash('sha256', (string)$bytes);
        }
        unset($item);

        return $pending;
    }

    /** @param list<array<string, string>> $before */
    public static function assertConverted(PDO $pdo, string $database, array $before): void
    {
        if (self::pending($pdo, $database) !== []) {
            throw new RuntimeException('workflow variable converter left serialized or byte-array variables behind');
        }
        $db = DatabaseManifest::quoteIdentifier($database);
        $runtimeValues = [];
        $historyValues = [];
        $byteStatement = $pdo->prepare(
            'SELECT BYTES_ FROM ' . $db . '.act_ge_bytearray WHERE ID_ = ?'
        );
        foreach ($before as $item) {
            $table = $item['table'];
            $typeColumn = $table === 'act_ru_variable' ? 'TYPE_' : 'VAR_TYPE_';
            $statement = $pdo->prepare(
                'SELECT ' . DatabaseManifest::quoteIdentifier($typeColumn) . ' AS TYPE_NAME, TEXT_, BYTEARRAY_ID_ FROM '
                . $db . '.' . DatabaseManifest::quoteIdentifier($table) . ' WHERE ID_ = ?'
            );
            $statement->execute([$item['id']]);
            $row = $statement->fetch();
            if (!is_array($row)) {
                throw new RuntimeException('workflow variable converter removed an audited variable row');
            }
            $text = (string)($row['TEXT_'] ?? '');
            if (strtolower((string)$row['TYPE_NAME']) !== 'string'
                || $row['BYTEARRAY_ID_'] !== null
                || strlen($text) > 4000
            ) {
                throw new RuntimeException('workflow variable converter output does not meet the string/size gate');
            }
            try {
                json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('workflow variable converter output is not valid JSON', 0, $exception);
            }
            $key = $item['processId'] . "\0" . $item['name'];
            if ($table === 'act_ru_variable') {
                $runtimeValues[$key][$text] = true;
            } else {
                $historyValues[$key][$text] = true;
            }
            if (($item['bytearrayId'] ?? '') !== '') {
                $byteStatement->execute([$item['bytearrayId']]);
                $bytes = $byteStatement->fetchColumn();
                if ($bytes === false || hash('sha256', (string)$bytes) !== ($item['bytearraySha256'] ?? '')) {
                    throw new RuntimeException('workflow converter did not preserve the audited original byte-array content');
                }
            }
        }
        foreach (array_intersect(array_keys($runtimeValues), array_keys($historyValues)) as $key) {
            $runtimeJson = array_keys($runtimeValues[$key]);
            $historyJson = array_keys($historyValues[$key]);
            sort($runtimeJson);
            sort($historyJson);
            if ($runtimeJson !== $historyJson) {
                throw new RuntimeException('runtime/history workflow variable JSON values differ');
            }
        }
    }

    private static function tableExists(PDO $pdo, string $database, string $table): bool
    {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
        );
        $statement->execute([$database, $table]);

        return $statement->fetchColumn() !== false;
    }
}

final class QuarantineManager
{
    /** @var array<string, array<string, string>> */
    private const ROW_SPECS = [
        'act_ru_identitylink' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes'],
        'act_ru_variable' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_incident' => ['PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_event_subscr' => ['PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_job' => ['PROCESS_INSTANCE_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_deadletter_job' => ['PROCESS_INSTANCE_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_suspended_job' => ['PROCESS_INSTANCE_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_timer_job' => ['PROCESS_INSTANCE_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_ext_task' => ['PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_task' => ['ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_ru_execution' => ['ID_' => 'executions', 'PROC_INST_ID_' => 'processes', 'ROOT_PROC_INST_ID_' => 'processes'],
        'act_hi_attachment' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes'],
        'act_hi_comment' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes'],
        'act_hi_detail' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_identitylink' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes'],
        'act_hi_taskinst' => ['ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_actinst' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_varinst' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_procinst' => ['PROC_INST_ID_' => 'processes'],
        'act_hi_op_log' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
    ];

    /** @var list<string> */
    private const DELETE_ORDER = [
        'act_ru_identitylink',
        'act_ru_variable',
        'act_ru_incident',
        'act_ru_event_subscr',
        'act_ru_job',
        'act_ru_deadletter_job',
        'act_ru_suspended_job',
        'act_ru_timer_job',
        'act_ru_ext_task',
        'act_hi_attachment',
        'act_hi_comment',
        'act_hi_detail',
        'act_hi_identitylink',
        'act_hi_taskinst',
        'act_hi_actinst',
        'act_hi_varinst',
        'act_hi_procinst',
        'act_hi_op_log',
        'act_ru_task',
        'act_ru_execution',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $targetDatabase,
        private readonly string $quarantineDatabase,
        private readonly string $runId
    ) {
    }

    /** @param list<array<string, string>> $orphans @return array<string, mixed> */
    public function quarantine(array $orphans): array
    {
        if ($orphans === []) {
            throw new RuntimeException('quarantine cannot run with an empty orphan list');
        }
        $sets = [
            'tasks' => self::uniqueValues($orphans, 'taskId'),
            'processes' => self::uniqueValues($orphans, 'processId'),
            'executions' => self::uniqueValues($orphans, 'executionId'),
            'processDefinitions' => self::uniqueValues($orphans, 'processDefinitionId'),
            'deployments' => [],
        ];
        $sets['executions'] = array_values(array_unique(array_merge(
            $sets['executions'],
            $this->executionIds($sets['processes'])
        )));
        sort($sets['executions']);
        $sets['deployments'] = $this->deploymentIds($sets['processDefinitions']);

        $this->createAuditTables();
        $target = DatabaseManifest::quoteIdentifier($this->targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $audit = [];

        foreach (self::ROW_SPECS as $table => $spec) {
            if (!$this->tableExists($this->targetDatabase, $table)) {
                continue;
            }
            [$where, $parameters] = $this->whereForSpec($table, $spec, $sets);
            if ($where === '') {
                continue;
            }
            $audit[$table] = $this->copyAndVerify($table, $where, $parameters, 'orphan-runtime-or-history');
        }

        $bytearrayPredicates = [];
        $bytearrayParameters = [];
        $bytearrayColumns = $this->columns('act_ge_bytearray');
        if (isset($bytearrayColumns['ROOT_PROC_INST_ID_']) && $sets['processes'] !== []) {
            [$predicate, $values] = self::inPredicate('ROOT_PROC_INST_ID_', $sets['processes']);
            $bytearrayPredicates[] = $predicate;
            array_push($bytearrayParameters, ...$values);
        }
        if (isset($bytearrayColumns['DEPLOYMENT_ID_']) && $sets['deployments'] !== []) {
            [$predicate, $values] = self::inPredicate('DEPLOYMENT_ID_', $sets['deployments']);
            $bytearrayPredicates[] = $predicate;
            array_push($bytearrayParameters, ...$values);
        }
        if ($bytearrayPredicates !== []) {
            $audit['act_ge_bytearray'] = $this->copyAndVerify(
                'act_ge_bytearray',
                '(' . implode(' OR ', $bytearrayPredicates) . ')',
                $bytearrayParameters,
                'orphan-bytearray-and-shared-deployment-resource'
            );
        }

        foreach ([
            'act_re_procdef' => ['ID_' => $sets['processDefinitions']],
            'act_re_deployment' => ['ID_' => $sets['deployments']],
        ] as $table => $selection) {
            if (!$this->tableExists($this->targetDatabase, $table)) {
                continue;
            }
            $column = (string)array_key_first($selection);
            $values = $selection[$column];
            if ($values === []) {
                continue;
            }
            [$where, $parameters] = self::inPredicate($column, $values);
            $audit[$table] = $this->copyAndVerify($table, $where, $parameters, 'shared-process-definition-reference');
        }

        $itemStatement = $this->pdo->prepare(
            "INSERT INTO {$quarantine}.migration_quarantine_item "
            . '(RUN_ID, TASK_ID, PROCESS_ID, EXECUTION_ID, PROCESS_DEFINITION_ID, TENANT_ID, REASON, COPIED_AT) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        foreach ($orphans as $orphan) {
            $itemStatement->execute([
                $this->runId,
                $orphan['taskId'],
                $orphan['processId'],
                $orphan['executionId'],
                $orphan['processDefinitionId'],
                $orphan['tenantId'],
                'missing act_hi_procinst at frozen-source audit; explicitly allowlisted',
            ]);
        }
        $auditStatement = $this->pdo->prepare(
            "INSERT INTO {$quarantine}.migration_quarantine_audit "
            . '(RUN_ID, TABLE_NAME, ROW_COUNT, SHA256, CATEGORY, COPIED_AT) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        foreach ($audit as $table => $item) {
            $auditStatement->execute([
                $this->runId,
                $table,
                $item['rowCount'],
                $item['sha256'],
                $item['category'],
            ]);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::DELETE_ORDER as $table) {
                if (!isset(self::ROW_SPECS[$table]) || !$this->tableExists($this->targetDatabase, $table)) {
                    continue;
                }
                [$where, $parameters] = $this->whereForSpec($table, self::ROW_SPECS[$table], $sets);
                if ($where === '') {
                    continue;
                }
                $statement = $this->pdo->prepare(
                    "DELETE FROM {$target}." . DatabaseManifest::quoteIdentifier($table) . " WHERE {$where}"
                );
                $statement->execute($parameters);
            }
            if (isset($bytearrayColumns['ROOT_PROC_INST_ID_']) && $sets['processes'] !== []) {
                [$where, $parameters] = self::inPredicate('ROOT_PROC_INST_ID_', $sets['processes']);
                $statement = $this->pdo->prepare("DELETE FROM {$target}.act_ge_bytearray WHERE {$where}");
                $statement->execute($parameters);
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            try {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Throwable) {
            }
            throw $exception;
        }

        foreach (self::ROW_SPECS as $table => $spec) {
            if (!$this->tableExists($this->targetDatabase, $table)) {
                continue;
            }
            [$where, $parameters] = $this->whereForSpec($table, $spec, $sets);
            if ($where !== '' && $this->countWhere($this->targetDatabase, $table, $where, $parameters) !== 0) {
                throw new RuntimeException("quarantined rows remain in normal runtime table {$table}");
            }
        }
        if (isset($bytearrayColumns['ROOT_PROC_INST_ID_']) && $sets['processes'] !== []) {
            [$where, $parameters] = self::inPredicate('ROOT_PROC_INST_ID_', $sets['processes']);
            if ($this->countWhere($this->targetDatabase, 'act_ge_bytearray', $where, $parameters) !== 0) {
                throw new RuntimeException('quarantined root-process byte arrays remain in the normal target');
            }
        }
        if (OrphanPolicy::detect($this->pdo, $this->targetDatabase) !== []) {
            throw new RuntimeException('unknown or residual orphan tasks remain after quarantine');
        }

        return [
            'runId' => $this->runId,
            'taskCount' => count($sets['tasks']),
            'processCount' => count($sets['processes']),
            'executionCount' => count($sets['executions']),
            'processDefinitionCount' => count($sets['processDefinitions']),
            'deploymentCount' => count($sets['deployments']),
            'tables' => $audit,
        ];
    }

    private function createAuditTables(): void
    {
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $this->pdo->exec(
            "CREATE TABLE {$quarantine}.migration_quarantine_item ("
            . 'ID bigint unsigned NOT NULL AUTO_INCREMENT, RUN_ID varchar(80) NOT NULL, TASK_ID varchar(128) NOT NULL, '
            . 'PROCESS_ID varchar(128) NOT NULL, EXECUTION_ID varchar(128) DEFAULT NULL, '
            . 'PROCESS_DEFINITION_ID varchar(128) DEFAULT NULL, TENANT_ID varchar(64) DEFAULT NULL, '
            . 'REASON varchar(500) NOT NULL, COPIED_AT datetime NOT NULL, PRIMARY KEY (ID), '
            . 'UNIQUE KEY uk_quarantine_task (RUN_ID, TASK_ID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->pdo->exec(
            "CREATE TABLE {$quarantine}.migration_quarantine_audit ("
            . 'ID bigint unsigned NOT NULL AUTO_INCREMENT, RUN_ID varchar(80) NOT NULL, TABLE_NAME varchar(128) NOT NULL, '
            . 'ROW_COUNT bigint NOT NULL, SHA256 char(64) NOT NULL, CATEGORY varchar(100) NOT NULL, '
            . 'COPIED_AT datetime NOT NULL, PRIMARY KEY (ID), UNIQUE KEY uk_quarantine_audit (RUN_ID, TABLE_NAME)) '
            . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->pdo->exec(
            "CREATE TABLE {$quarantine}.migration_assignee_repair_audit ("
            . 'ID bigint unsigned NOT NULL AUTO_INCREMENT, RUN_ID varchar(80) NOT NULL, TASK_ID varchar(128) NOT NULL, '
            . 'PROCESS_ID varchar(128) NOT NULL, TENANT_ID varchar(64) NOT NULL, VARIABLE_ID varchar(128) NOT NULL, '
            . 'ORIGINAL_ASSIGNEE varchar(128) DEFAULT NULL, REPAIRED_ASSIGNEE varchar(128) NOT NULL, '
            . 'REPAIRED_AT datetime NOT NULL, PRIMARY KEY (ID), UNIQUE KEY uk_assignee_repair (RUN_ID, TASK_ID)) '
            . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @param array<string, string> $spec @param array<string, list<string>> $sets @return array{string,list<string>} */
    private function whereForSpec(string $table, array $spec, array $sets): array
    {
        $columns = $this->columns($table);
        $predicates = [];
        $parameters = [];
        foreach ($spec as $column => $setName) {
            if (!isset($columns[$column]) || ($sets[$setName] ?? []) === []) {
                continue;
            }
            [$predicate, $values] = self::inPredicate($column, $sets[$setName]);
            $predicates[] = $predicate;
            array_push($parameters, ...$values);
        }

        return [$predicates === [] ? '' : '(' . implode(' OR ', $predicates) . ')', $parameters];
    }

    /** @param list<string> $parameters @return array<string, mixed> */
    private function copyAndVerify(string $table, string $where, array $parameters, string $category): array
    {
        $target = DatabaseManifest::quoteIdentifier($this->targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $quotedTable = DatabaseManifest::quoteIdentifier($table);
        $this->pdo->exec("CREATE TABLE {$quarantine}.{$quotedTable} LIKE {$target}.{$quotedTable}");
        $before = $this->rowDigest($this->targetDatabase, $table, $where, $parameters);
        $statement = $this->pdo->prepare(
            "INSERT INTO {$quarantine}.{$quotedTable} SELECT * FROM {$target}.{$quotedTable} WHERE {$where}"
        );
        $statement->execute($parameters);
        $after = $this->rowDigest($this->quarantineDatabase, $table, $where, $parameters);
        if ($before !== $after) {
            throw new RuntimeException("quarantine copy verification failed for {$table}");
        }

        return $before + ['category' => $category];
    }

    /** @param list<string> $parameters @return array{rowCount:int,sha256:string} */
    private function rowDigest(string $database, string $table, string $where, array $parameters): array
    {
        $columns = array_keys($this->columns($table));
        $order = $this->primaryKeyColumns($table);
        if ($order === []) {
            $order = $columns;
        }
        $orderSql = implode(', ', array_map([DatabaseManifest::class, 'quoteIdentifier'], $order));
        $statement = $this->pdo->prepare(
            'SELECT * FROM ' . DatabaseManifest::quoteIdentifier($database) . '.'
            . DatabaseManifest::quoteIdentifier($table) . " WHERE {$where} ORDER BY {$orderSql}"
        );
        $statement->execute($parameters);
        $hash = hash_init('sha256');
        $count = 0;
        while (($row = $statement->fetch()) !== false) {
            $count++;
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                hash_update($hash, $column . "\0");
                if ($value === null) {
                    hash_update($hash, "N\0");
                    continue;
                }
                $text = (string)$value;
                hash_update($hash, 'S' . strlen($text) . "\0" . $text . "\0");
            }
        }

        return ['rowCount' => $count, 'sha256' => hash_final($hash)];
    }

    /** @return array<string, true> */
    private function columns(string $table): array
    {
        if (!$this->tableExists($this->targetDatabase, $table)) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $statement->execute([$this->targetDatabase, $table]);
        $columns = [];
        foreach ($statement->fetchAll() as $row) {
            $columns[(string)$row['COLUMN_NAME']] = true;
        }

        return $columns;
    }

    /** @return list<string> */
    private function primaryKeyColumns(string $table): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? "
            . "AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX"
        );
        $statement->execute([$this->targetDatabase, $table]);

        return array_values(array_map(static fn (array $row): string => (string)$row['COLUMN_NAME'], $statement->fetchAll()));
    }

    private function tableExists(string $database, string $table): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
        );
        $statement->execute([$database, $table]);

        return $statement->fetchColumn() !== false;
    }

    /** @param list<string> $processes @return list<string> */
    private function executionIds(array $processes): array
    {
        if ($processes === [] || !$this->tableExists($this->targetDatabase, 'act_ru_execution')) {
            return [];
        }
        [$where, $parameters] = self::inPredicate('PROC_INST_ID_', $processes);
        [$rootWhere, $rootParameters] = self::inPredicate('ROOT_PROC_INST_ID_', $processes);
        $statement = $this->pdo->prepare(
            'SELECT ID_ FROM ' . DatabaseManifest::quoteIdentifier($this->targetDatabase)
            . ".act_ru_execution WHERE {$where} OR {$rootWhere} ORDER BY ID_"
        );
        $statement->execute(array_merge($parameters, $rootParameters));

        return array_values(array_unique(array_map(static fn (array $row): string => (string)$row['ID_'], $statement->fetchAll())));
    }

    /** @param list<string> $processDefinitions @return list<string> */
    private function deploymentIds(array $processDefinitions): array
    {
        if ($processDefinitions === [] || !$this->tableExists($this->targetDatabase, 'act_re_procdef')) {
            return [];
        }
        [$where, $parameters] = self::inPredicate('ID_', $processDefinitions);
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT DEPLOYMENT_ID_ FROM ' . DatabaseManifest::quoteIdentifier($this->targetDatabase)
            . ".act_re_procdef WHERE {$where} AND DEPLOYMENT_ID_ IS NOT NULL ORDER BY DEPLOYMENT_ID_"
        );
        $statement->execute($parameters);

        return array_values(array_map(static fn (array $row): string => (string)$row['DEPLOYMENT_ID_'], $statement->fetchAll()));
    }

    /** @param list<array<string, string>> $rows @return list<string> */
    private static function uniqueValues(array $rows, string $key): array
    {
        $values = array_values(array_unique(array_filter(
            array_map(static fn (array $row): string => trim((string)($row[$key] ?? '')), $rows),
            static fn (string $value): bool => $value !== ''
        )));
        sort($values);

        return $values;
    }

    /** @param list<string> $values @return array{string,list<string>} */
    private static function inPredicate(string $column, array $values): array
    {
        if ($values === []) {
            throw new RuntimeException('cannot build an empty quarantine predicate');
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));

        return [DatabaseManifest::quoteIdentifier($column) . " IN ({$placeholders})", array_values($values)];
    }

    /** @param list<string> $parameters */
    private function countWhere(string $database, string $table, string $where, array $parameters): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ' . DatabaseManifest::quoteIdentifier($database) . '.'
            . DatabaseManifest::quoteIdentifier($table) . " WHERE {$where}"
        );
        $statement->execute($parameters);

        return (int)$statement->fetchColumn();
    }
}

final class AssigneeRepair
{
    /** @return array<string, mixed> */
    public static function apply(
        PDO $pdo,
        string $database,
        string $quarantineDatabase,
        string $runId,
        int $expectedRepairs
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $quarantine = DatabaseManifest::quoteIdentifier($quarantineDatabase);
        $blankTasks = $pdo->query(
            "SELECT t.ID_ AS taskId, t.PROC_INST_ID_ AS processId, t.TENANT_ID_ AS tenantId, "
            . "t.TASK_DEF_KEY_ AS taskKey, d.KEY_ AS processKey "
            . "FROM {$db}.act_ru_task t LEFT JOIN {$db}.act_re_procdef d ON d.ID_ = t.PROC_DEF_ID_ "
            . "WHERE t.ASSIGNEE_ IS NULL OR TRIM(t.ASSIGNEE_) = '' ORDER BY t.ID_"
        )->fetchAll();
        if (count($blankTasks) !== $expectedRepairs) {
            throw new RuntimeException('blank workflow assignee count differs from the audited repair baseline');
        }

        $repairs = [];
        foreach ($blankTasks as $task) {
            if ((string)$task['processKey'] !== 'Process_procure'
                || (string)$task['taskKey'] !== 'Activity_approval_procure'
            ) {
                throw new RuntimeException('an unapproved blank-assignee task shape was detected');
            }
            $tenantId = trim((string)($task['tenantId'] ?? ''));
            if ($tenantId === '') {
                throw new RuntimeException('blank-assignee repair task has no tenant');
            }
            $variableStatement = $pdo->prepare(
                "SELECT ID_, TEXT_, TYPE_, TENANT_ID_ FROM {$db}.act_ru_variable "
                . "WHERE PROC_INST_ID_ = ? AND NAME_ = 'user' AND TEXT_ IS NOT NULL ORDER BY ID_"
            );
            $variableStatement->execute([(string)$task['processId']]);
            $candidates = [];
            foreach ($variableStatement->fetchAll() as $variable) {
                $userId = trim((string)($variable['TEXT_'] ?? ''));
                $variableTenant = trim((string)($variable['TENANT_ID_'] ?? ''));
                if ($userId === '' || $variableTenant !== $tenantId || strtolower((string)$variable['TYPE_']) !== 'string') {
                    continue;
                }
                $candidates[] = [
                    'userId' => $userId,
                    'variableId' => (string)$variable['ID_'],
                ];
            }
            if (count($candidates) !== 1) {
                throw new RuntimeException('blank-assignee repair requires exactly one same-tenant string user variable row');
            }
            $userId = $candidates[0]['userId'];
            if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $userId)) {
                throw new RuntimeException('blank-assignee repair user id has an unsupported format');
            }
            $userStatement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.sys_user WHERE ID = ? AND TENANT_ID = ? "
                . "AND (DELETE_FLAG IS NULL OR DELETE_FLAG = 'NOT_DELETE')"
            );
            $userStatement->execute([$userId, $tenantId]);
            if ((int)$userStatement->fetchColumn() !== 1) {
                throw new RuntimeException('blank-assignee repair user does not exist uniquely in the same tenant');
            }
            $historyStatement = $pdo->prepare("SELECT COUNT(*) FROM {$db}.act_hi_taskinst WHERE ID_ = ?");
            $historyStatement->execute([(string)$task['taskId']]);
            if ((int)$historyStatement->fetchColumn() !== 1) {
                throw new RuntimeException('blank-assignee repair requires one matching history task row');
            }
            $repairs[] = [
                'taskId' => (string)$task['taskId'],
                'processId' => (string)$task['processId'],
                'tenantId' => $tenantId,
                'userId' => $userId,
                'variableId' => $candidates[0]['variableId'],
            ];
        }

        $pdo->beginTransaction();
        try {
            $runtimeUpdate = $pdo->prepare("UPDATE {$db}.act_ru_task SET ASSIGNEE_ = ? WHERE ID_ = ? AND (ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = '')");
            $historyUpdate = $pdo->prepare("UPDATE {$db}.act_hi_taskinst SET ASSIGNEE_ = ? WHERE ID_ = ? AND (ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = '')");
            $auditInsert = $pdo->prepare(
                "INSERT INTO {$quarantine}.migration_assignee_repair_audit "
                . '(RUN_ID, TASK_ID, PROCESS_ID, TENANT_ID, VARIABLE_ID, ORIGINAL_ASSIGNEE, REPAIRED_ASSIGNEE, REPAIRED_AT) '
                . 'VALUES (?, ?, ?, ?, ?, NULL, ?, UTC_TIMESTAMP())'
            );
            foreach ($repairs as $repair) {
                $runtimeUpdate->execute([$repair['userId'], $repair['taskId']]);
                $historyUpdate->execute([$repair['userId'], $repair['taskId']]);
                if ($runtimeUpdate->rowCount() !== 1 || $historyUpdate->rowCount() !== 1) {
                    throw new RuntimeException('blank-assignee repair update count was not exactly one');
                }
                $auditInsert->execute([
                    $runId,
                    $repair['taskId'],
                    $repair['processId'],
                    $repair['tenantId'],
                    $repair['variableId'],
                    $repair['userId'],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        $remaining = (int)$pdo->query(
            "SELECT COUNT(*) FROM {$db}.act_ru_task WHERE ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = ''"
        )->fetchColumn();
        if ($remaining !== 0) {
            throw new RuntimeException('blank workflow assignees remain after the strict repair');
        }

        return ['repairCount' => count($repairs), 'tasks' => $repairs];
    }
}

final class MigrationRunner
{
    private const AUDITED_SOURCE_TABLES = 121;
    private const AUDITED_TEMPLATE_TABLES = 124;
    private const AUDITED_SOURCE_COLUMNS = 1836;
    private const AUDITED_TEMPLATE_COLUMNS = 1882;
    private const AUDITED_ORPHANS = 20;
    private const AUDITED_ASSIGNEE_REPAIRS = 2;

    private readonly CommandRunner $commands;

    public function __construct(private readonly string $projectRoot, ?CommandRunner $commands = null)
    {
        $this->commands = $commands ?? new CommandRunner();
    }

    /** @return array<string, mixed> */
    public function run(MigrationOptions $options): array
    {
        $sourceDefaults = $options->string('source-defaults');
        $targetDefaults = $options->string('target-defaults');
        $sourceDatabase = MigrationSafety::identifier($options->string('source-db'), 'source database');
        $templateDatabase = MigrationSafety::identifier($options->string('template-db'), 'template database');
        $targetDatabase = MigrationSafety::identifier($options->string('target-db'), 'target database');
        $quarantineDatabase = MigrationSafety::identifier($options->string('quarantine-db'), 'quarantine database');
        $source = MysqlProfile::fromDefaultsFile($sourceDefaults);
        $target = MysqlProfile::fromDefaultsFile($targetDefaults);
        $allowedTargets = $options->list('allow-target');
        MigrationSafety::assertSafeTopology(
            $source,
            $sourceDatabase,
            $target,
            $templateDatabase,
            $targetDatabase,
            $quarantineDatabase,
            $allowedTargets
        );

        $apply = $options->flag('apply');
        if ($apply) {
            if (!hash_equals(MigrationSafety::FREEZE_TOKEN, $options->string('source-freeze-token'))) {
                throw new RuntimeException('apply requires explicit confirmation that Java is stopped and the source is frozen');
            }
        }

        $runId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $manifestBase = $options->string(
            'manifest-dir',
            $this->projectRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backup'
            . DIRECTORY_SEPARATOR . 'database-migration-' . $runId
        );
        $manifestBase = $this->safeManifestPath($manifestBase);
        $store = new ManifestStore($manifestBase);
        $sourcePdo = $source->connect($sourceDatabase);
        $targetAdminPdo = $target->connect($templateDatabase);

        $sourceManifest = DatabaseManifest::capture($sourcePdo, $sourceDatabase, true);
        $templateManifest = DatabaseManifest::capture($targetAdminPdo, $templateDatabase, false);
        $comparison = SchemaPolicy::compareSourceToTemplate($sourceManifest, $templateManifest);
        SchemaPolicy::assertExpected(
            $comparison,
            self::AUDITED_SOURCE_TABLES,
            self::AUDITED_TEMPLATE_TABLES,
            self::AUDITED_SOURCE_COLUMNS,
            self::AUDITED_TEMPLATE_COLUMNS
        );
        $this->assertRequiredTemplateFeatures($templateManifest);
        $orphans = OrphanPolicy::detect($sourcePdo, $sourceDatabase);
        $orphanEligibility = $orphans === []
            ? ['taskCount' => 0, 'processCount' => 0, 'linkedRowChecks' => [], 'rootBytearrayRowsPreservedForQuarantine' => 0]
            : OrphanPolicy::assertIsolationEligible($sourcePdo, $sourceDatabase, $orphans);
        $pendingVariables = WorkflowVariableGate::pending($sourcePdo, $sourceDatabase);
        $planSha256 = hash('sha256', json_encode([
            'sourceSchema' => $sourceManifest['schemaSha256'],
            'templateSchema' => $templateManifest['schemaSha256'],
            'sourceRows' => $sourceManifest['rowCounts'],
            'orphans' => $orphans,
            'workflowVariables' => $pendingVariables,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $confirmToken = MigrationSafety::planBoundConfirmToken(
            $sourceDatabase,
            $targetDatabase,
            $quarantineDatabase,
            $planSha256
        );
        $converterConnection = $target->childConnection();
        $converterRemoteBlocked = $pendingVariables !== []
            && $converterConnection['remote']
            && !$options->flag('allow-remote-workflow-converter');
        $candidatePath = $store->writeJson('known-orphan-candidates.json', [
            'generatedAt' => gmdate('c'),
            'expectedCount' => self::AUDITED_ORPHANS,
            'taskIds' => array_values(array_map(static fn (array $item): string => $item['taskId'], $orphans)),
            'items' => $orphans,
        ]);
        $knownOrphanPath = $options->string('known-orphans');
        $allowlistValidated = false;
        if ($knownOrphanPath !== '') {
            $allowlist = OrphanPolicy::loadAllowlist($knownOrphanPath, self::AUDITED_ORPHANS);
            OrphanPolicy::assertExact($orphans, $allowlist);
            $allowlistValidated = true;
        }

        $preflight = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'runId' => $runId,
            'source' => $source->redactedSummary() + ['database' => $sourceDatabase],
            'template' => $target->redactedSummary() + ['database' => $templateDatabase],
            'targetDatabase' => $targetDatabase,
            'quarantineDatabase' => $quarantineDatabase,
            'confirmToken' => $confirmToken,
            'planSha256' => $planSha256,
            'requiredFreezeToken' => MigrationSafety::FREEZE_TOKEN,
            'schemaComparison' => $comparison,
            'orphanCount' => count($orphans),
            'orphanIsolationEligibility' => $orphanEligibility,
            'orphanAllowlistValidated' => $allowlistValidated,
            'orphanCandidateManifest' => $candidatePath,
            'workflowVariablesAwaitingExternalConversion' => count($pendingVariables),
            'workflowConverterConfigured' => $options->string('workflow-converter') !== '',
            'workflowConverterRemoteTarget' => $converterConnection['remote'],
            'workflowConverterRemoteGateSatisfied' => !$converterRemoteBlocked,
        ];
        $store->writeJson('source-schema-and-rows.json', $sourceManifest);
        $store->writeJson('template-schema.json', $templateManifest);
        $store->writeJson('source-workflow-variable-candidates.json', [
            'planSha256' => $planSha256,
            'candidateCount' => count($pendingVariables),
            'candidates' => $pendingVariables,
        ]);
        $store->writeJson('preflight.json', $preflight);

        if ($apply && !hash_equals($confirmToken, $options->string('confirm-token'))) {
            throw new RuntimeException('apply confirmation token does not match the current plan-bound dry-run');
        }

        if (!$apply) {
            return $preflight + [
                'readyForApply' => count($orphans) === self::AUDITED_ORPHANS
                    && $allowlistValidated
                    && ($pendingVariables === [] || $options->string('workflow-converter') !== '')
                    && !$converterRemoteBlocked,
                'writesPerformed' => false,
            ];
        }
        if (count($orphans) !== self::AUDITED_ORPHANS || !$allowlistValidated) {
            throw new RuntimeException('apply requires the exact reviewed 20-task orphan allowlist');
        }
        $workflowConverter = $options->string('workflow-converter');
        if ($pendingVariables !== [] && ($workflowConverter === '' || !is_file($workflowConverter))) {
            throw new RuntimeException('serialized workflow variables require the external converter before apply');
        }
        if ($converterRemoteBlocked) {
            throw new RuntimeException('remote workflow converter target requires --allow-remote-workflow-converter');
        }
        $this->assertDatabaseAbsent($targetAdminPdo, $targetDatabase);
        $this->assertDatabaseAbsent($targetAdminPdo, $quarantineDatabase);

        $dumpDirectory = $store->path('sql');
        if (!is_dir($dumpDirectory) && !mkdir($dumpDirectory, 0700, true) && !is_dir($dumpDirectory)) {
            throw new RuntimeException('unable to create migration SQL staging directory');
        }
        $schemaDump = $dumpDirectory . DIRECTORY_SEPARATOR . 'template-schema.sql';
        $dataDump = $dumpDirectory . DIRECTORY_SEPARATOR . 'source-data.sql';
        $mysqldump = $options->string('mysqldump-bin', 'mysqldump');
        $mysql = $options->string('mysql-bin', 'mysql');
        $tableNames = array_keys($sourceManifest['tables']);
        sort($tableNames);
        $sourceChecksumsBefore = DatabaseManifest::tableChecksums($sourcePdo, $sourceDatabase, $tableNames);
        $store->writeJson('source-checksums-before-dump.json', $sourceChecksumsBefore);

        $this->commands->run(DumpPolicy::schemaDumpCommand(
            $mysqldump,
            $target,
            $templateDatabase,
            $schemaDump
        ));
        @chmod($schemaDump, 0600);
        $schemaDumpAudit = DumpPolicy::validateSchemaDump($schemaDump);
        $this->commands->run(DumpPolicy::dataDumpCommand(
            $mysqldump,
            $source,
            $sourceDatabase,
            $dataDump,
            $tableNames
        ));
        @chmod($dataDump, 0600);
        $dataDumpAudit = DumpPolicy::validateDataDump($dataDump, $tableNames);
        $store->writeJson('dump-audit.json', [
            'schema' => $schemaDumpAudit,
            'data' => $dataDumpAudit,
        ]);

        $sourceAfterDump = DatabaseManifest::capture($sourcePdo, $sourceDatabase, true);
        if (($sourceAfterDump['rowCounts'] ?? []) !== ($sourceManifest['rowCounts'] ?? [])) {
            throw new RuntimeException('source row counts changed during dump; the source is not frozen');
        }
        if (OrphanPolicy::detect($sourcePdo, $sourceDatabase) !== $orphans) {
            throw new RuntimeException('source orphan set changed during dump; the source is not frozen');
        }
        $sourceChecksumsAfter = DatabaseManifest::tableChecksums($sourcePdo, $sourceDatabase, $tableNames);
        $store->writeJson('source-checksums-after-dump.json', $sourceChecksumsAfter);
        if ($sourceChecksumsAfter !== $sourceChecksumsBefore) {
            throw new RuntimeException('source table checksums changed during dump; the source is not frozen');
        }
        OrphanPolicy::assertIsolationEligible($sourcePdo, $sourceDatabase, $orphans);

        $collation = $this->databaseCollation($targetAdminPdo, $templateDatabase);
        $this->createDatabase($targetAdminPdo, $targetDatabase, $collation);
        $this->createDatabase($targetAdminPdo, $quarantineDatabase, $collation);
        $this->importSqlFile($mysql, $target, $targetDatabase, $schemaDump);
        $targetPdo = $target->connect($targetDatabase);
        $targetSchema = DatabaseManifest::capture($targetPdo, $targetDatabase, false);
        if (($targetSchema['schemaSha256'] ?? '') !== ($templateManifest['schemaSha256'] ?? '')) {
            throw new RuntimeException('new target schema differs from the PHP template schema');
        }

        $this->importSqlFile($mysql, $target, $targetDatabase, $dataDump);
        $afterImport = DatabaseManifest::capture($targetPdo, $targetDatabase, true);
        $this->assertImportedRows($sourceManifest, $afterImport);
        $this->assertNewFeatureRowsUntouched($targetPdo, $targetDatabase);
        $store->writeJson('target-after-import.json', $afterImport);

        $importedOrphans = OrphanPolicy::detect($targetPdo, $targetDatabase);
        $allowlist = OrphanPolicy::loadAllowlist($knownOrphanPath, self::AUDITED_ORPHANS);
        OrphanPolicy::assertExact($importedOrphans, $allowlist);
        OrphanPolicy::assertIsolationEligible($targetPdo, $targetDatabase, $importedOrphans);
        $quarantineAudit = (new QuarantineManager(
            $targetPdo,
            $targetDatabase,
            $quarantineDatabase,
            $runId
        ))->quarantine($importedOrphans);
        $store->writeJson('quarantine-audit.json', $quarantineAudit);

        $targetPendingVariables = WorkflowVariableGate::pending($targetPdo, $targetDatabase);
        $store->writeJson('target-workflow-variable-candidates-before-conversion.json', [
            'candidateCount' => count($targetPendingVariables),
            'candidates' => $targetPendingVariables,
        ]);
        if ($targetPendingVariables !== $pendingVariables) {
            throw new RuntimeException('imported workflow variable/byte-array candidate digest differs from the frozen source');
        }
        if ($targetPendingVariables !== []) {
            $converterResult = $this->runWorkflowConverter(
                $workflowConverter,
                $options->string('php-bin', PHP_BINARY),
                $target,
                $targetDatabase,
                $options->flag('allow-remote-workflow-converter')
            );
            $converterResult['inputVariableRows'] = count($targetPendingVariables);
            $store->writeJson('workflow-converter-command.json', $converterResult);
            WorkflowVariableGate::assertConverted($targetPdo, $targetDatabase, $targetPendingVariables);
            $converterResult['remainingSerializedVariableRows'] = 0;
            $store->writeJson('workflow-converter-validation.json', $converterResult);
        }

        $assigneeAudit = AssigneeRepair::apply(
            $targetPdo,
            $targetDatabase,
            $quarantineDatabase,
            $runId,
            self::AUDITED_ASSIGNEE_REPAIRS
        );
        $store->writeJson('assignee-repair-audit.json', $assigneeAudit);

        $installerAudit = $this->runInstallers(
            $options->string('php-bin', PHP_BINARY),
            $target,
            $targetDatabase
        );
        $store->writeJson('installer-audit.json', $installerAudit);
        $finalManifest = DatabaseManifest::capture($targetPdo, $targetDatabase, true);
        $this->assertRequiredTemplateFeatures($finalManifest);
        if (($finalManifest['schemaSha256'] ?? '') !== ($templateManifest['schemaSha256'] ?? '')) {
            throw new RuntimeException('final migrated schema no longer matches the PHP template schema');
        }
        if (OrphanPolicy::detect($targetPdo, $targetDatabase) !== []) {
            throw new RuntimeException('final migrated database still contains orphan runtime tasks');
        }
        if (WorkflowVariableGate::pending($targetPdo, $targetDatabase) !== []) {
            throw new RuntimeException('final migrated database still contains unconverted workflow variables');
        }
        $store->writeJson('target-final.json', $finalManifest);

        $summary = $preflight + [
            'readyForApply' => true,
            'writesPerformed' => true,
            'targetCreatedFromTemplateSchemaOnly' => true,
            'sourceCreateOrDropImported' => false,
            'quarantinedTasks' => $quarantineAudit['taskCount'],
            'assigneesRepaired' => $assigneeAudit['repairCount'],
            'installerIdempotencyVerified' => true,
            'finalSchemaSha256' => $finalManifest['schemaSha256'],
            'manifestDirectory' => $manifestBase,
        ];
        $store->writeJson('completed.json', $summary);

        return $summary;
    }

    /** @param array<string, mixed> $manifest */
    private function assertRequiredTemplateFeatures(array $manifest): void
    {
        $tables = $manifest['tables'] ?? [];
        foreach (SchemaPolicy::NEW_TABLES as $table) {
            if (!isset($tables[$table])) {
                throw new RuntimeException("required PHP feature table {$table} is missing");
            }
        }
        $travel = $tables['biz_sale_project']['columns']['TRAVEL_DAYS'] ?? null;
        if (!is_array($travel)
            || strtolower((string)($travel['type'] ?? '')) !== 'decimal(10,1)'
            || strtoupper((string)($travel['nullable'] ?? '')) !== 'NO'
            || !in_array((string)($travel['default'] ?? ''), ['0.0', '0.0', '0'], true)
        ) {
            throw new RuntimeException('required biz_sale_project.TRAVEL_DAYS definition is missing or unsafe');
        }
    }

    private function assertDatabaseAbsent(PDO $pdo, string $database): void
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        $statement->execute([$database]);
        if ($statement->fetchColumn() !== false) {
            throw new RuntimeException("refusing to reuse existing migration database {$database}");
        }
    }

    private function safeManifestPath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('migration manifest directory is empty');
        }
        $absolute = preg_match('/^(?:[A-Za-z]:[\\\/]|[\\\/])/', $path) === 1
            ? $path
            : $this->projectRoot . DIRECTORY_SEPARATOR . $path;
        $resolved = realpath($absolute);
        $normalizedPath = is_string($resolved) ? $resolved : $absolute;
        $normalized = strtolower(rtrim(str_replace('\\', '/', $normalizedPath), '/'));
        $public = strtolower(rtrim(str_replace('\\', '/', $this->projectRoot), '/') . '/public');
        if ($normalized === $public || str_starts_with($normalized, $public . '/')) {
            throw new RuntimeException('migration manifests and SQL dumps may not be stored under the public web root');
        }

        return $absolute;
    }

    private function databaseCollation(PDO $pdo, string $database): string
    {
        $statement = $pdo->prepare(
            'SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
        );
        $statement->execute([$database]);
        $collation = (string)$statement->fetchColumn();
        if (!preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            throw new RuntimeException('template database collation is unavailable');
        }

        return $collation;
    }

    private function createDatabase(PDO $pdo, string $database, string $collation): void
    {
        $charset = str_starts_with(strtolower($collation), 'utf8mb4_') ? 'utf8mb4' : 'utf8';
        $pdo->exec(
            'CREATE DATABASE ' . DatabaseManifest::quoteIdentifier($database)
            . " CHARACTER SET {$charset} COLLATE {$collation}"
        );
    }

    private function importSqlFile(string $mysql, MysqlProfile $target, string $database, string $path): void
    {
        $this->commands->run([
            $mysql,
            '--defaults-extra-file=' . $target->defaultsPath(),
            '--binary-mode=1',
            '--default-character-set=utf8mb4',
            '--database=' . $database,
        ], $path);
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $target */
    private function assertImportedRows(array $source, array $target): void
    {
        $sourceRows = $source['rowCounts'] ?? [];
        $targetRows = $target['rowCounts'] ?? [];
        foreach ($sourceRows as $table => $count) {
            if (!array_key_exists($table, $targetRows) || (int)$targetRows[$table] !== (int)$count) {
                throw new RuntimeException("row-count import gate failed for {$table}");
            }
        }
        foreach (SchemaPolicy::NEW_TABLES as $table) {
            if (($targetRows[$table] ?? -1) !== 0) {
                throw new RuntimeException("new PHP feature table {$table} was unexpectedly populated by old data");
            }
        }
    }

    private function assertNewFeatureRowsUntouched(PDO $pdo, string $database): void
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $nonZero = (int)$pdo->query(
            "SELECT COUNT(*) FROM {$db}.biz_sale_project WHERE COALESCE(TRAVEL_DAYS, 0.0) <> 0.0"
        )->fetchColumn();
        if ($nonZero !== 0) {
            throw new RuntimeException('old data import unexpectedly populated TRAVEL_DAYS');
        }
    }

    /** @return array<string, mixed> */
    private function runWorkflowConverter(
        string $converter,
        string $phpBinary,
        MysqlProfile $target,
        string $database,
        bool $allowRemote
    ): array {
        if ($converter === '' || !is_file($converter)) {
            throw new RuntimeException('external workflow variable converter is unavailable');
        }
        $connection = $target->childConnection();
        if ($connection['remote'] && !$allowRemote) {
            throw new RuntimeException('remote workflow converter target requires --allow-remote-workflow-converter');
        }
        $passwordEnvironment = 'OA_MIGRATION_DB_PASSWORD_' . strtoupper(bin2hex(random_bytes(6)));
        $command = str_ends_with(strtolower($converter), '.php') ? [$phpBinary, $converter] : [$converter];
        $command[] = '--apply';
        $command[] = '--database=' . $database;
        $command[] = '--confirm-target=' . $database;
        $command[] = '--host=' . $connection['host'];
        $command[] = '--port=' . $connection['port'];
        $command[] = '--user=' . $connection['user'];
        $command[] = '--password-env=' . $passwordEnvironment;
        if ($connection['remote']) {
            $command[] = '--allow-remote-target';
        }
        $result = $this->commands->run(
            $command,
            null,
            [$passwordEnvironment => $connection['password']],
            [$connection['password']]
        );

        return [
            'exitCode' => $result['exitCode'],
            'stdoutSha256' => hash('sha256', $result['stdout']),
            'stderrSha256' => hash('sha256', $result['stderr']),
            'targetConfirmationMatched' => true,
            'passwordTransport' => 'temporary-child-environment',
            'remoteTarget' => $connection['remote'],
        ];
    }

    /** @return array<string, mixed> */
    private function runInstallers(string $phpBinary, MysqlProfile $target, string $database): array
    {
        $installers = [
            'travelDays' => 'install-sale-project-travel-days.php',
            'deliveryPlan' => 'install-sale-project-delivery-plan.php',
            'afterSales' => 'install-after-sales-module.php',
        ];
        $audit = [];
        foreach ($installers as $name => $file) {
            $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                throw new RuntimeException("required installer {$file} is missing");
            }
            $first = $this->runInstaller($phpBinary, $path, $target, $database);
            $second = $this->runInstaller($phpBinary, $path, $target, $database);
            $this->assertInstallerSecondRunIsNoOp($name, $second);
            $audit[$name] = ['first' => $first, 'idempotencyRun' => $second];
        }

        return $audit;
    }

    /** @return array<string, mixed> */
    private function runInstaller(string $phpBinary, string $path, MysqlProfile $target, string $database): array
    {
        $result = $this->commands->run([
            $phpBinary,
            $path,
            '--apply',
            '--target-defaults=' . $target->defaultsPath(),
            '--database=' . $database,
        ]);
        try {
            $decoded = json_decode(trim($result['stdout']), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('installer did not return a JSON audit summary', 0, $exception);
        }
        if (!is_array($decoded) || ($decoded['mode'] ?? '') !== 'apply') {
            throw new RuntimeException('installer audit summary is invalid');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $summary */
    private function assertInstallerSecondRunIsNoOp(string $name, array $summary): void
    {
        if ($name === 'travelDays') {
            foreach (['travelDaysColumn', 'leaveObjectIdColumn', 'travelStatisticsIndex'] as $key) {
                if (($summary[$key] ?? '') !== 'exists') {
                    throw new RuntimeException('travel-days installer is not idempotent');
                }
            }
            return;
        }
        if ($name === 'deliveryPlan') {
            foreach (['addedColumns', 'alteredColumns', 'addedIndexes'] as $key) {
                if (($summary[$key] ?? null) !== []) {
                    throw new RuntimeException('delivery-plan installer is not idempotent');
                }
            }
            if (($summary['collationChanged'] ?? true) !== false || ($summary['tableStatus'] ?? '') !== 'exists') {
                throw new RuntimeException('delivery-plan installer is not idempotent');
            }
            return;
        }
        foreach (['menuCreated', 'menuIconCleared'] as $key) {
            if (($summary[$key] ?? true) !== false) {
                throw new RuntimeException('after-sales installer is not idempotent');
            }
        }
        foreach (['roleMenuGrants', 'roleApiGrants', 'legacyDefaultCategoriesRemoved'] as $key) {
            if ((int)($summary[$key] ?? -1) !== 0) {
                throw new RuntimeException('after-sales installer is not idempotent');
            }
        }
    }
}
