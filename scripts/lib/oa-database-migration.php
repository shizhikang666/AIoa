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
        $host = self::normalizedHost($this->client['host'] ?? '127.0.0.1');
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
        $normalizedHost = self::normalizedHost($host);

        return [
            'host' => $host,
            'port' => $port,
            'user' => (string)$this->client['user'],
            'password' => (string)($this->client['password'] ?? ''),
            'remote' => !in_array($normalizedHost, ['127.0.0.1', 'localhost', '::1'], true),
        ];
    }

    private static function normalizedHost(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return $host;
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
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(private readonly string $directory)
    {
        if (is_dir($directory)) {
            $entries = scandir($directory);
            if ($entries === false) {
                throw new RuntimeException('unable to inspect migration manifest directory');
            }
            if (array_values(array_diff($entries, ['.', '..'])) !== []) {
                throw new RuntimeException('migration manifest directory must be empty for every new run');
            }
        } elseif (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('unable to create migration manifest directory');
        }
        self::secureDirectory($directory);
        $lockPath = $this->path('.run.lock');
        $lock = @fopen($lockPath, 'x+b');
        if ($lock === false) {
            throw new RuntimeException('migration manifest directory is already owned by another or previous run');
        }
        $this->lockHandle = $lock;
        self::secureFile($lockPath);
    }

    public function __destruct()
    {
        if (is_resource($this->lockHandle)) {
            fclose($this->lockHandle);
        }
    }

    public function path(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?? $name;
        if ($safe === '' || $safe === '.' || $safe === '..') {
            throw new RuntimeException('migration manifest child name is unsafe');
        }

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
        self::secureFile($temporary);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('unable to finalize migration manifest');
        }
        self::secureFile($path);

        return $path;
    }

    public static function secureDirectory(string $path): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            return;
        }
        if (!chmod($path, 0700)) {
            throw new RuntimeException('migration private directory permissions could not be enforced');
        }
        clearstatcache(true, $path);
        if (((int)fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('migration private directory permissions could not be enforced');
        }
    }

    public static function secureFile(string $path): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            return;
        }
        if (!chmod($path, 0600)) {
            throw new RuntimeException('migration private file permissions could not be enforced');
        }
        clearstatcache(true, $path);
        if (((int)fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('migration private file permissions could not be enforced');
        }
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
            . 'CHARACTER_SET_NAME, COLLATION_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS '
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
                'generationExpression' => $row['GENERATION_EXPRESSION'],
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
        return hash('sha256', json_encode(
            self::canonicalValue($tables),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalValue($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalValue($item);
        }

        return $value;
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
    public const EXPECTED_EXTRA_INDEXES = [
        'biz_leave_application.idx_leave_after_sales_travel' => [
            'unique' => false,
            'type' => 'BTREE',
            'columns' => [
                ['name' => 'TENANT_ID', 'prefix' => null, 'collation' => 'A'],
                ['name' => 'OBJECT_ID', 'prefix' => null, 'collation' => 'A'],
                ['name' => 'category', 'prefix' => null, 'collation' => 'A'],
                ['name' => 'DELETE_FLAG', 'prefix' => null, 'collation' => 'A'],
            ],
        ],
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
        $columnOrderMismatches = [];
        $tableDefinitionMismatches = [];
        $indexMismatches = [];
        $extraIndexes = [];
        $extraIndexDefinitions = [];
        $foreignKeyMismatches = [];
        $extraForeignKeys = [];
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
            $targetCommonColumnOrder = array_values(array_filter(
                array_keys($targetColumns),
                static fn (string $column): bool => array_key_exists($column, $sourceColumns)
            ));
            if (array_keys($sourceColumns) !== $targetCommonColumnOrder) {
                $columnOrderMismatches[] = (string)$table;
            }
            $sourceEngine = strtolower((string)($sourceDefinition['engine'] ?? ''));
            $targetEngine = strtolower((string)($targetDefinition['engine'] ?? ''));
            if ($sourceEngine !== $targetEngine) {
                $tableDefinitionMismatches[] = "{$table}.engine";
            }
            $sourceCollation = self::normalizedCollation($sourceDefinition['collation'] ?? null);
            $targetCollation = self::normalizedCollation($targetDefinition['collation'] ?? null);
            if ($sourceCollation !== $targetCollation) {
                $tableDefinitionMismatches[] = "{$table}.collation";
            }
            $sourceIndexes = $sourceDefinition['indexes'] ?? [];
            $targetIndexes = $targetDefinition['indexes'] ?? [];
            foreach ($sourceIndexes as $index => $definition) {
                if (!array_key_exists($index, $targetIndexes)) {
                    $indexMismatches[] = "{$table}.{$index}:missing-in-template";
                    continue;
                }
                if (self::canonicalValue($definition) !== self::canonicalValue($targetIndexes[$index])) {
                    $indexMismatches[] = "{$table}.{$index}:definition-differs";
                }
            }
            foreach (array_diff(array_keys($targetIndexes), array_keys($sourceIndexes)) as $index) {
                $qualified = "{$table}.{$index}";
                $extraIndexes[] = $qualified;
                $extraIndexDefinitions[$qualified] = self::canonicalValue($targetIndexes[$index]);
            }
            $sourceForeignKeys = $sourceDefinition['foreignKeys'] ?? [];
            $targetForeignKeys = $targetDefinition['foreignKeys'] ?? [];
            foreach ($sourceForeignKeys as $foreignKey => $definition) {
                if (!array_key_exists($foreignKey, $targetForeignKeys)) {
                    $foreignKeyMismatches[] = "{$table}.{$foreignKey}:missing-in-template";
                    continue;
                }
                if (self::normalizedForeignKey($definition) !== self::normalizedForeignKey($targetForeignKeys[$foreignKey])) {
                    $foreignKeyMismatches[] = "{$table}.{$foreignKey}:definition-differs";
                }
            }
            foreach (array_diff(array_keys($targetForeignKeys), array_keys($sourceForeignKeys)) as $foreignKey) {
                $extraForeignKeys[] = "{$table}.{$foreignKey}";
            }
        }
        sort($columnMismatches);
        sort($extraColumns);
        sort($columnOrderMismatches);
        sort($tableDefinitionMismatches);
        sort($indexMismatches);
        sort($extraIndexes);
        ksort($extraIndexDefinitions);
        sort($foreignKeyMismatches);
        sort($extraForeignKeys);
        $requiredNewTables = self::NEW_TABLES;
        sort($requiredNewTables);
        $expectedExtraColumns = ['biz_sale_project.TRAVEL_DAYS'];
        $expectedExtraIndexDefinitions = self::canonicalValue(self::EXPECTED_EXTRA_INDEXES);
        $expectedExtraIndexes = array_keys($expectedExtraIndexDefinitions);

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
            'columnOrderMismatches' => $columnOrderMismatches,
            'tableDefinitionMismatches' => $tableDefinitionMismatches,
            'indexMismatches' => $indexMismatches,
            'extraIndexes' => $extraIndexes,
            'extraIndexDefinitions' => $extraIndexDefinitions,
            'foreignKeyMismatches' => $foreignKeyMismatches,
            'extraForeignKeys' => $extraForeignKeys,
            'valid' => $missingTables === []
                && $newTables === $requiredNewTables
                && $columnMismatches === []
                && $extraColumns === $expectedExtraColumns
                && $columnOrderMismatches === []
                && $tableDefinitionMismatches === []
                && $indexMismatches === []
                && $extraIndexes === $expectedExtraIndexes
                && $extraIndexDefinitions === $expectedExtraIndexDefinitions
                && $foreignKeyMismatches === []
                && $extraForeignKeys === [],
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
        $extra = strtolower((string)($definition['extra'] ?? ''));
        $extra = preg_replace('/\bdefault_generated\b/', '', $extra) ?? $extra;
        $extra = trim(preg_replace('/\s+/', ' ', $extra) ?? $extra);
        $charset = $definition['charset'] ?? null;
        if (is_string($charset)) {
            $charset = strtolower($charset);
            $charset = $charset === 'utf8' ? 'utf8mb3' : $charset;
        }
        $collation = self::normalizedCollation($definition['collation'] ?? null);

        return [
            'type' => $type,
            'nullable' => strtoupper((string)($definition['nullable'] ?? '')),
            'default' => $definition['default'] ?? null,
            'extra' => $extra,
            'charset' => $charset,
            'collation' => $collation,
            'generationExpression' => $definition['generationExpression'] ?? null,
        ];
    }

    private static function normalizedCollation(mixed $collation): mixed
    {
        if (!is_string($collation)) {
            return $collation;
        }
        $collation = strtolower($collation);

        return preg_replace('/^utf8_/', 'utf8mb3_', $collation) ?? $collation;
    }

    /** @return array<string, mixed> */
    private static function normalizedForeignKey(mixed $definition): array
    {
        if (!is_array($definition)) {
            return [];
        }
        foreach (['updateRule', 'deleteRule'] as $rule) {
            $value = strtoupper(trim((string)($definition[$rule] ?? '')));
            // MySQL enforces NO ACTION as RESTRICT and reports the same
            // default rule differently across 5.7 dumps and 8.0 imports.
            $definition[$rule] = $value === 'NO ACTION' ? 'RESTRICT' : $value;
        }

        return self::canonicalValue($definition);
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalValue($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalValue($item);
        }

        return $value;
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
            . "LEFT JOIN {$db}.act_hi_procinst h ON BINARY h.PROC_INST_ID_ = BINARY t.PROC_INST_ID_ "
            . "WHERE h.PROC_INST_ID_ IS NULL ORDER BY BINARY t.ID_";
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
            'act_hi_identitylink' => [
                'TASK_ID_' => $taskIds,
                'PROC_INST_ID_' => $processIds,
                'ROOT_PROC_INST_ID_' => $processIds,
            ],
            'act_hi_op_log' => [
                'TASK_ID_' => $taskIds,
                'PROC_INST_ID_' => $processIds,
                'ROOT_PROC_INST_ID_' => $processIds,
            ],
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
                "SELECT COUNT(*) FROM {$db}.sys_user WHERE BINARY ID = BINARY ? AND BINARY TENANT_ID = BINARY ? "
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
        $bytearrayReferenceChecks = DetachedBytearrayPolicy::assertNoConsumerReferencesForRootProcessIds(
            $pdo,
            $database,
            $processIds
        );

        return [
            'taskCount' => count($taskIds),
            'processCount' => count($processIds),
            'linkedRowChecks' => $checks,
            'rootBytearrayRowsPreservedForQuarantine' => $bytearrayCount,
            'rootBytearrayReferenceChecks' => $bytearrayReferenceChecks,
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
            'BINARY ' . DatabaseManifest::quoteIdentifier($column) . ' IN ('
                . implode(',', array_fill(0, count($values), '?')) . ')',
            array_values($values),
        ];
    }
}

final class DetachedBytearrayPolicy
{
    private const REVIEWED_IDENTIFIER_COMPARISON = [
        'dataType' => 'varchar',
        'columnType' => 'varchar(64)',
    ];

    private const BYTEARRAY_ROOT_INDEX = 'ACT_IDX_BYTEARRAY_ROOT_PI';

    private const EXPECTED_CONSUMER_COLUMNS = [
        ['table' => 'act_hi_attachment', 'column' => 'CONTENT_ID_'],
        ['table' => 'act_hi_dec_in', 'column' => 'BYTEARRAY_ID_'],
        ['table' => 'act_hi_dec_out', 'column' => 'BYTEARRAY_ID_'],
        ['table' => 'act_hi_detail', 'column' => 'BYTEARRAY_ID_'],
        ['table' => 'act_hi_ext_task_log', 'column' => 'ERROR_DETAILS_ID_'],
        ['table' => 'act_hi_job_log', 'column' => 'JOB_EXCEPTION_STACK_ID_'],
        ['table' => 'act_hi_varinst', 'column' => 'BYTEARRAY_ID_'],
        ['table' => 'act_ru_ext_task', 'column' => 'ERROR_DETAILS_ID_'],
        ['table' => 'act_ru_job', 'column' => 'EXCEPTION_STACK_ID_'],
        ['table' => 'act_ru_variable', 'column' => 'BYTEARRAY_ID_'],
    ];

    private const ALLOWED_BUSINESS_REFERENCE_TABLES = [
        'biz_sale_project_reissue_order',
    ];

    /** @param list<string> $excludedProcessIds @return array<string, mixed> */
    public static function audit(PDO $pdo, string $database, array $excludedProcessIds): array
    {
        $requiredColumns = [
            'ID_', 'REV_', 'NAME_', 'DEPLOYMENT_ID_', 'BYTES_', 'GENERATED_', 'TENANT_ID_',
            'TYPE_', 'CREATE_TIME_', 'ROOT_PROC_INST_ID_', 'REMOVAL_TIME_',
        ];
        $columns = self::columns($pdo, $database, 'act_ge_bytearray');
        if ($columns !== $requiredColumns) {
            throw new RuntimeException('detached byte-array audit requires the frozen act_ge_bytearray column layout');
        }

        $consumerColumns = self::consumerColumns($pdo, $database);
        if ($consumerColumns !== self::EXPECTED_CONSUMER_COLUMNS) {
            throw new RuntimeException('byte-array consumer column set differs from the reviewed migration policy');
        }
        self::assertForeignKeyConsumersCovered($pdo, $database, $consumerColumns);
        $processEvidenceColumns = self::processEvidenceColumns($pdo, $database);
        $indexSafePrerequisites = self::indexSafePrerequisiteSummary(
            $pdo,
            $database,
            $consumerColumns,
            $processEvidenceColumns
        );

        [$where, $parameters] = self::candidatePredicate($database, 'b', $excludedProcessIds);
        $db = DatabaseManifest::quoteIdentifier($database);
        $indexSafePrerequisites['candidateDistinctness'] = self::candidateDistinctnessSummary(
            $pdo,
            $database,
            $where,
            $parameters
        );
        $aggregateStatement = $pdo->prepare(
            "SELECT COUNT(*) AS candidateRows, "
            . "COUNT(DISTINCT BINARY NULLIF(TRIM(b.ROOT_PROC_INST_ID_), '')) AS distinctRoots, "
            . 'COALESCE(SUM(OCTET_LENGTH(b.BYTES_)), 0) AS totalByteLength, '
            . "COALESCE(SUM(CASE WHEN NULLIF(TRIM(b.DEPLOYMENT_ID_), '') IS NOT NULL THEN 1 ELSE 0 END), 0) "
            . 'AS deploymentBoundRows, '
            . 'COALESCE(SUM(CASE WHEN b.BYTES_ IS NULL THEN 1 ELSE 0 END), 0) AS nullPayloadRows, '
            . 'COALESCE(SUM(CASE WHEN b.REMOVAL_TIME_ IS NULL THEN 1 ELSE 0 END), 0) AS missingRemovalTimeRows '
            . "FROM {$db}.act_ge_bytearray b WHERE {$where}"
        );
        $aggregateStatement->execute($parameters);
        $aggregate = $aggregateStatement->fetch();
        if (!is_array($aggregate)) {
            throw new RuntimeException('detached byte-array aggregate audit did not return one row');
        }

        $candidateRows = (int)($aggregate['candidateRows'] ?? 0);
        $distinctRoots = (int)($aggregate['distinctRoots'] ?? 0);
        $totalByteLength = (int)($aggregate['totalByteLength'] ?? 0);
        $deploymentBoundRows = (int)($aggregate['deploymentBoundRows'] ?? 0);
        $nullPayloadRows = (int)($aggregate['nullPayloadRows'] ?? 0);
        $missingRemovalTimeRows = (int)($aggregate['missingRemovalTimeRows'] ?? 0);
        if (($indexSafePrerequisites['candidateDistinctness']['binaryDistinctRoots'] ?? -1) !== $distinctRoots) {
            throw new RuntimeException('detached byte-array candidate distinctness differs from aggregate counts');
        }
        if ($candidateRows === 0) {
            throw new RuntimeException('detached byte-array audit unexpectedly found no candidates');
        }
        if ($deploymentBoundRows !== 0 || $nullPayloadRows !== 0 || $missingRemovalTimeRows !== 0) {
            throw new RuntimeException('detached byte-array candidates include protected or incomplete payload rows');
        }

        $consumerReferenceChecks = self::consumerReferenceChecks(
            $pdo,
            $database,
            $where,
            $parameters,
            $consumerColumns
        );
        foreach ($consumerReferenceChecks as $count) {
            if ($count !== 0) {
                throw new RuntimeException('detached byte-array candidate is still referenced by a reviewed payload consumer');
            }
        }

        $processEvidenceChecks = self::processEvidenceChecks(
            $pdo,
            $database,
            $where,
            $parameters,
            $processEvidenceColumns
        );
        foreach ($processEvidenceChecks as $count) {
            if ($count !== 0) {
                throw new RuntimeException('detached byte-array candidate still has workflow-engine process evidence');
            }
        }

        $businessReferenceTables = self::businessReferenceTables($pdo, $database);
        $businessReferences = self::businessReferenceChecks(
            $pdo,
            $database,
            $where,
            $parameters,
            $businessReferenceTables
        );
        foreach ($businessReferences as $table => $item) {
            if (($item['matchingRows'] ?? 0) !== 0
                && !in_array($table, self::ALLOWED_BUSINESS_REFERENCE_TABLES, true)
            ) {
                throw new RuntimeException('detached byte-array candidate has an unreviewed business process reference');
            }
        }
        self::assertAllowedBusinessTenantEvidence($pdo, $database, $where, $parameters);

        [$businessLinkedRows, $businessLinkedRoots] = self::businessLinkedCandidateCounts(
            $pdo,
            $database,
            $where,
            $parameters
        );
        [$rowCount, $rootIds, $rowIdentitySha256, $rowContentSha256] = self::candidateDigests(
            $pdo,
            $database,
            $where,
            $parameters,
            $columns
        );
        if ($rowCount !== $candidateRows || count($rootIds) !== $distinctRoots) {
            throw new RuntimeException('detached byte-array identity audit differs from its aggregate counts');
        }

        $rootHash = hash_init('sha256');
        foreach ($rootIds as $rootId) {
            self::hashValue($rootHash, $rootId);
        }

        return [
            'eligible' => true,
            'candidateRows' => $candidateRows,
            'distinctRoots' => $distinctRoots,
            'totalByteLength' => $totalByteLength,
            'fullyDetachedRows' => $candidateRows - $businessLinkedRows,
            'businessLinkedRows' => $businessLinkedRows,
            'businessLinkedRoots' => $businessLinkedRoots,
            'deploymentBoundRows' => $deploymentBoundRows,
            'nullPayloadRows' => $nullPayloadRows,
            'missingRemovalTimeRows' => $missingRemovalTimeRows,
            'indexSafePrerequisites' => $indexSafePrerequisites,
            'consumerColumns' => $consumerColumns,
            'consumerReferenceChecks' => $consumerReferenceChecks,
            'processEvidenceColumns' => $processEvidenceColumns,
            'processEvidenceChecks' => $processEvidenceChecks,
            'businessReferenceTables' => $businessReferenceTables,
            'businessReferences' => $businessReferences,
            'rowIdentitySha256' => $rowIdentitySha256,
            'rowContentSha256' => $rowContentSha256,
            'rootIdentitySha256' => hash_final($rootHash),
            'rootIds' => $rootIds,
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(array $plan): array
    {
        unset($plan['rootIds']);

        return $plan;
    }

    public static function assertExpected(
        array $plan,
        int $expectedRows,
        int $expectedRoots,
        int $expectedBytes,
        int $expectedBusinessLinkedRows,
        int $expectedBusinessLinkedRoots
    ): void {
        if (($plan['eligible'] ?? false) !== true
            || (int)($plan['candidateRows'] ?? -1) !== $expectedRows
            || (int)($plan['distinctRoots'] ?? -1) !== $expectedRoots
            || (int)($plan['totalByteLength'] ?? -1) !== $expectedBytes
            || (int)($plan['businessLinkedRows'] ?? -1) !== $expectedBusinessLinkedRows
            || (int)($plan['businessLinkedRoots'] ?? -1) !== $expectedBusinessLinkedRoots
            || (int)($plan['fullyDetachedRows'] ?? -1) !== $expectedRows - $expectedBusinessLinkedRows
        ) {
            throw new RuntimeException('detached byte-array plan differs from the reviewed local snapshot baseline');
        }
    }

    public static function assertSamePlan(array $actual, array $expected): void
    {
        if (self::comparableSummary($actual) !== self::comparableSummary($expected)) {
            throw new RuntimeException('detached byte-array plan changed after the frozen source audit');
        }
    }

    /** @return array<string, mixed> */
    private static function comparableSummary(array $plan): array
    {
        $plan = self::summary($plan);
        unset(
            $plan['processEvidenceColumns'],
            $plan['processEvidenceChecks'],
            $plan['businessReferenceTables']
        );
        $businessReferences = [];
        foreach (($plan['businessReferences'] ?? []) as $table => $item) {
            if (is_array($item) && (int)($item['matchingRows'] ?? 0) !== 0) {
                $businessReferences[$table] = $item;
            }
        }
        ksort($businessReferences);
        $plan['businessReferences'] = $businessReferences;

        return $plan;
    }

    /** @param list<string> $rootProcessIds @return array<string, int> */
    public static function assertNoConsumerReferencesForRootProcessIds(
        PDO $pdo,
        string $database,
        array $rootProcessIds
    ): array {
        $rootProcessIds = self::normalizeIds($rootProcessIds);
        if ($rootProcessIds === []) {
            return [];
        }
        $consumerColumns = self::consumerColumns($pdo, $database);
        if ($consumerColumns !== self::EXPECTED_CONSUMER_COLUMNS) {
            throw new RuntimeException('byte-array consumer column set differs from the reviewed migration policy');
        }
        self::assertForeignKeyConsumersCovered($pdo, $database, $consumerColumns);
        $db = DatabaseManifest::quoteIdentifier($database);
        $placeholders = implode(',', array_fill(0, count($rootProcessIds), '?'));
        $checks = [];
        foreach ($consumerColumns as $spec) {
            $table = $spec['table'];
            $column = $spec['column'];
            $quotedTable = DatabaseManifest::quoteIdentifier($table);
            $quotedColumn = DatabaseManifest::quoteIdentifier($column);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.{$quotedTable} r "
                . "STRAIGHT_JOIN {$db}.act_ge_bytearray b FORCE INDEX (PRIMARY) "
                . "ON b.ID_ = r.{$quotedColumn} AND BINARY b.ID_ = BINARY r.{$quotedColumn} "
                . "WHERE BINARY NULLIF(TRIM(b.ROOT_PROC_INST_ID_), '') IN ({$placeholders})"
            );
            $statement->execute($rootProcessIds);
            $key = $table . '.' . $column;
            $checks[$key] = (int)$statement->fetchColumn();
            if ($checks[$key] !== 0) {
                throw new RuntimeException('orphan root byte-array is still referenced by a workflow consumer');
            }
        }

        return $checks;
    }

    /** @param list<string> $excludedProcessIds @return array{string,list<string>} */
    public static function candidatePredicate(string $database, string $alias, array $excludedProcessIds): array
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $root = "NULLIF(TRIM({$alias}.ROOT_PROC_INST_ID_), '')";
        $where = "{$root} IS NOT NULL AND NOT EXISTS ("
            . "SELECT 1 FROM {$db}.act_hi_procinst hp "
            . "WHERE hp.PROC_INST_ID_ = {$root} "
            . "AND BINARY TRIM(hp.PROC_INST_ID_) = BINARY {$root})";
        $parameters = [];
        $excludedProcessIds = self::normalizeIds($excludedProcessIds);
        if ($excludedProcessIds !== []) {
            $where .= " AND BINARY {$root} NOT IN ("
                . implode(',', array_fill(0, count($excludedProcessIds), '?')) . ')';
            $parameters = $excludedProcessIds;
        }

        return [$where, $parameters];
    }

    public static function assertNoRemainingCandidates(PDO $pdo, string $database): void
    {
        self::indexSafePrerequisiteSummary(
            $pdo,
            $database,
            self::consumerColumns($pdo, $database),
            self::processEvidenceColumns($pdo, $database)
        );
        [$where, $parameters] = self::candidatePredicate($database, 'b', []);
        $db = DatabaseManifest::quoteIdentifier($database);
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM {$db}.act_ge_bytearray b WHERE {$where}"
        );
        $statement->execute($parameters);
        if ((int)$statement->fetchColumn() !== 0) {
            throw new RuntimeException('detached byte-array candidates remain in the normal target');
        }
    }

    /** @param list<array{table:string,column:string}> $consumerColumns @param list<string> $parameters @return array<string, int> */
    private static function consumerReferenceChecks(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters,
        array $consumerColumns
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $checks = [];
        foreach ($consumerColumns as $spec) {
            $table = $spec['table'];
            $column = $spec['column'];
            $quotedTable = DatabaseManifest::quoteIdentifier($table);
            $quotedColumn = DatabaseManifest::quoteIdentifier($column);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.{$quotedTable} r "
                . "STRAIGHT_JOIN {$db}.act_ge_bytearray b FORCE INDEX (PRIMARY) "
                . "ON b.ID_ = r.{$quotedColumn} AND BINARY b.ID_ = BINARY r.{$quotedColumn} "
                . "WHERE {$where}"
            );
            $statement->execute($parameters);
            $checks[$table . '.' . $column] = (int)$statement->fetchColumn();
        }

        return $checks;
    }

    /** @param list<array{table:string,column:string}> $specs @param list<string> $parameters @return array<string, int> */
    private static function processEvidenceChecks(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters,
        array $specs
    ): array {
        if ($specs === []) {
            return [];
        }
        $db = DatabaseManifest::quoteIdentifier($database);
        $rootIndex = DatabaseManifest::quoteIdentifier(self::BYTEARRAY_ROOT_INDEX);
        $checks = [];
        foreach ($specs as $spec) {
            $table = DatabaseManifest::quoteIdentifier($spec['table']);
            $column = DatabaseManifest::quoteIdentifier($spec['column']);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.{$table} e "
                . "STRAIGHT_JOIN {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) "
                . "ON b.ROOT_PROC_INST_ID_ = TRIM(e.{$column}) "
                . "AND BINARY b.ROOT_PROC_INST_ID_ = BINARY TRIM(e.{$column}) "
                . "WHERE NULLIF(TRIM(e.{$column}), '') IS NOT NULL AND {$where}"
            );
            $statement->execute($parameters);
            $checks[$spec['table'] . '.' . $spec['column']] = (int)$statement->fetchColumn();
        }

        return $checks;
    }

    /** @param list<string> $tables @param list<string> $parameters @return array<string, array{matchingRows:int,distinctRoots:int}> */
    private static function businessReferenceChecks(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters,
        array $tables
    ): array {
        if ($tables === []) {
            return [];
        }
        $db = DatabaseManifest::quoteIdentifier($database);
        $rootIndex = DatabaseManifest::quoteIdentifier(self::BYTEARRAY_ROOT_INDEX);
        $candidateStatement = $pdo->prepare(
            "SELECT DISTINCT HEX(TRIM(b.ROOT_PROC_INST_ID_)) AS rootProcessIdHex "
            . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}"
        );
        $candidateStatement->execute($parameters);
        $candidateRoots = [];
        while (($row = $candidateStatement->fetch()) !== false) {
            $rootHex = (string)($row['rootProcessIdHex'] ?? '');
            if ($rootHex !== '') {
                $candidateRoots[self::hexLookupKey($rootHex, 'candidate root process id')] = true;
            }
        }
        $checks = [];
        foreach ($tables as $table) {
            $quotedTable = DatabaseManifest::quoteIdentifier($table);
            $statement = $pdo->query(
                "SELECT HEX(TRIM(PROCESS_ID)) AS processIdHex FROM {$db}.{$quotedTable} "
                . "WHERE NULLIF(TRIM(PROCESS_ID), '') IS NOT NULL"
            );
            $matchingRows = 0;
            $matchingRoots = [];
            while (($row = $statement->fetch()) !== false) {
                $processIdHex = (string)($row['processIdHex'] ?? '');
                $key = self::hexLookupKey($processIdHex, 'business process id');
                if (isset($candidateRoots[$key])) {
                    ++$matchingRows;
                    $matchingRoots[$key] = true;
                }
            }
            $checks[$table] = [
                'matchingRows' => $matchingRows,
                'distinctRoots' => count($matchingRoots),
            ];
        }

        return $checks;
    }

    /** @param list<string> $parameters */
    private static function assertAllowedBusinessTenantEvidence(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters
    ): void {
        $db = DatabaseManifest::quoteIdentifier($database);
        $rootIndex = DatabaseManifest::quoteIdentifier(self::BYTEARRAY_ROOT_INDEX);
        $candidateStatement = $pdo->prepare(
            "SELECT HEX(TRIM(b.ROOT_PROC_INST_ID_)) AS rootProcessIdHex, "
            . "HEX(NULLIF(TRIM(b.TENANT_ID_), '')) AS byteTenantHex "
            . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}"
        );
        $candidateStatement->execute($parameters);
        $candidateTenants = [];
        while (($row = $candidateStatement->fetch()) !== false) {
            $rootKey = self::hexLookupKey(
                (string)($row['rootProcessIdHex'] ?? ''),
                'candidate root process id'
            );
            $candidateTenants[$rootKey] ??= [];
            if (($row['byteTenantHex'] ?? null) !== null) {
                $tenantKey = self::hexLookupKey((string)$row['byteTenantHex'], 'candidate tenant id');
                $candidateTenants[$rootKey][$tenantKey] = true;
            }
        }
        $activeTenants = [];
        $activeTenantStatement = $pdo->query(
            "SELECT HEX(Tenant_ID) AS tenantHex FROM {$db}.tenants "
            . "WHERE DELETE_FLAG IS NULL OR DELETE_FLAG = 'NOT_DELETE'"
        );
        while (($row = $activeTenantStatement->fetch()) !== false) {
            $tenantHex = (string)($row['tenantHex'] ?? '');
            if ($tenantHex !== '') {
                $activeTenants[self::hexLookupKey($tenantHex, 'active tenant id')] = true;
            }
        }
        foreach (self::ALLOWED_BUSINESS_REFERENCE_TABLES as $table) {
            if (!self::tableHasColumns($pdo, $database, $table, ['PROCESS_ID', 'TENANT_ID'])) {
                throw new RuntimeException('reviewed business process evidence table is missing required columns');
            }
            $quotedTable = DatabaseManifest::quoteIdentifier($table);
            $statement = $pdo->query(
                "SELECT HEX(TRIM(PROCESS_ID)) AS processIdHex, "
                . "HEX(NULLIF(TRIM(TENANT_ID), '')) AS tenantHex FROM {$db}.{$quotedTable} "
                . "WHERE NULLIF(TRIM(PROCESS_ID), '') IS NOT NULL"
            );
            $linkedTenantSets = [];
            $conflict = false;
            while (($row = $statement->fetch()) !== false) {
                $rootKey = self::hexLookupKey(
                    (string)($row['processIdHex'] ?? ''),
                    'business process id'
                );
                if (!isset($candidateTenants[$rootKey])) {
                    continue;
                }
                if (($row['tenantHex'] ?? null) === null) {
                    $conflict = true;
                    continue;
                }
                $tenantKey = self::hexLookupKey((string)$row['tenantHex'], 'business tenant id');
                $linkedTenantSets[$rootKey][$tenantKey] = true;
                if (!isset($activeTenants[$tenantKey])) {
                    $conflict = true;
                }
                if ($candidateTenants[$rootKey] !== []
                    && (count($candidateTenants[$rootKey]) !== 1
                        || !isset($candidateTenants[$rootKey][$tenantKey]))
                ) {
                    $conflict = true;
                }
            }
            foreach ($linkedTenantSets as $tenantSet) {
                if (count($tenantSet) !== 1) {
                    $conflict = true;
                }
            }
            if ($conflict) {
                throw new RuntimeException('business-linked detached byte-array tenant evidence conflicts');
            }
        }
    }

    /** @param list<string> $parameters @return array{int,int} */
    private static function businessLinkedCandidateCounts(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $businessRoots = [];
        foreach (self::ALLOWED_BUSINESS_REFERENCE_TABLES as $table) {
            $quotedTable = DatabaseManifest::quoteIdentifier($table);
            $statement = $pdo->query(
                "SELECT HEX(TRIM(PROCESS_ID)) AS processIdHex FROM {$db}.{$quotedTable} "
                . "WHERE NULLIF(TRIM(PROCESS_ID), '') IS NOT NULL"
            );
            while (($row = $statement->fetch()) !== false) {
                $processIdHex = (string)($row['processIdHex'] ?? '');
                $businessRoots[self::hexLookupKey($processIdHex, 'business process id')] = true;
            }
        }
        if ($businessRoots === []) {
            return [0, 0];
        }
        $rootIndex = DatabaseManifest::quoteIdentifier(self::BYTEARRAY_ROOT_INDEX);
        $statement = $pdo->prepare(
            "SELECT HEX(TRIM(b.ROOT_PROC_INST_ID_)) AS rootProcessIdHex "
            . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}"
        );
        $statement->execute($parameters);
        $linkedRows = 0;
        $linkedRoots = [];
        while (($row = $statement->fetch()) !== false) {
            $key = self::hexLookupKey(
                (string)($row['rootProcessIdHex'] ?? ''),
                'candidate root process id'
            );
            if (isset($businessRoots[$key])) {
                ++$linkedRows;
                $linkedRoots[$key] = true;
            }
        }

        return [$linkedRows, count($linkedRoots)];
    }

    /** @param list<string> $parameters @param list<string> $columns @return array{int,list<string>,string,string} */
    private static function candidateDigests(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters,
        array $columns
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $statement = $pdo->prepare(
            "SELECT b.* FROM {$db}.act_ge_bytearray b WHERE {$where} ORDER BY BINARY b.ID_"
        );
        $statement->execute($parameters);
        $identityHash = hash_init('sha256');
        $contentHash = hash_init('sha256');
        $roots = [];
        $count = 0;
        while (($row = $statement->fetch()) !== false) {
            ++$count;
            $id = (string)($row['ID_'] ?? '');
            $root = trim((string)($row['ROOT_PROC_INST_ID_'] ?? ''), ' ');
            if ($id === '' || $root === '') {
                throw new RuntimeException('detached byte-array identity contains an empty key');
            }
            self::hashValue($identityHash, $id);
            self::hashValue($identityHash, $root);
            $roots[$root] = true;
            foreach ($columns as $column) {
                hash_update($contentHash, $column . "\0");
                self::hashValue($contentHash, $row[$column] ?? null);
            }
        }
        $rootIds = array_keys($roots);
        sort($rootIds, SORT_STRING);

        return [$count, $rootIds, hash_final($identityHash), hash_final($contentHash)];
    }

    /** @return list<array{table:string,column:string}> */
    private static function consumerColumns(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? "
            . "AND TABLE_NAME LIKE 'act\\_%' AND COLUMN_NAME IN ("
            . "'BYTEARRAY_ID_', 'CONTENT_ID_', 'ERROR_DETAILS_ID_', 'JOB_EXCEPTION_STACK_ID_', "
            . "'EXCEPTION_STACK_ID_') ORDER BY TABLE_NAME, COLUMN_NAME"
        );
        $statement->execute([$database]);
        $columns = [];
        foreach ($statement->fetchAll() as $row) {
            $columns[] = [
                'table' => MigrationSafety::identifier((string)$row['TABLE_NAME'], 'byte-array consumer table'),
                'column' => MigrationSafety::identifier((string)$row['COLUMN_NAME'], 'byte-array consumer column'),
            ];
        }

        return $columns;
    }

    /** @param list<array{table:string,column:string}> $consumerColumns */
    private static function assertForeignKeyConsumersCovered(
        PDO $pdo,
        string $database,
        array $consumerColumns
    ): void {
        $covered = [];
        foreach ($consumerColumns as $spec) {
            $covered[$spec['table'] . '.' . $spec['column']] = true;
        }
        $statement = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ? '
            . "AND REFERENCED_TABLE_NAME = 'act_ge_bytearray' ORDER BY TABLE_NAME, COLUMN_NAME"
        );
        $statement->execute([$database, $database]);
        foreach ($statement->fetchAll() as $row) {
            $key = (string)$row['TABLE_NAME'] . '.' . (string)$row['COLUMN_NAME'];
            if (!isset($covered[$key])) {
                throw new RuntimeException('foreign-key byte-array consumer is outside the reviewed migration policy');
            }
        }
    }

    /** @return list<array{table:string,column:string}> */
    private static function processEvidenceColumns(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? "
            . "AND TABLE_NAME LIKE 'act\\_%' "
            . "AND COLUMN_NAME IN ('PROC_INST_ID_', 'ROOT_PROC_INST_ID_', 'PROCESS_INSTANCE_ID_') "
            . "AND TABLE_NAME NOT IN ('act_ge_bytearray', 'act_ge_bytearray_bak') "
            . 'ORDER BY TABLE_NAME, COLUMN_NAME'
        );
        $statement->execute([$database]);
        $specs = [];
        foreach ($statement->fetchAll() as $row) {
            $table = MigrationSafety::identifier((string)$row['TABLE_NAME'], 'workflow evidence table');
            $column = MigrationSafety::identifier((string)$row['COLUMN_NAME'], 'workflow evidence column');
            $specs[] = ['table' => $table, 'column' => $column];
        }

        return $specs;
    }

    /** @return list<string> */
    private static function businessReferenceTables(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? "
            . "AND COLUMN_NAME = 'PROCESS_ID' AND TABLE_NAME NOT LIKE 'act\\_%' ORDER BY TABLE_NAME"
        );
        $statement->execute([$database]);
        $tables = [];
        foreach ($statement->fetchAll() as $row) {
            $tables[] = MigrationSafety::identifier((string)$row['TABLE_NAME'], 'business process evidence table');
        }

        return $tables;
    }

    /**
     * @param list<array{table:string,column:string}> $consumerColumns
     * @param list<array{table:string,column:string}> $processEvidenceColumns
     * @return array<string, mixed>
     */
    private static function indexSafePrerequisiteSummary(
        PDO $pdo,
        string $database,
        array $consumerColumns,
        array $processEvidenceColumns
    ): array {
        $bytearrayIndexes = self::indexDefinitions($pdo, $database, 'act_ge_bytearray');
        $primaryIndex = [
            'unique' => true,
            'type' => 'BTREE',
            'columns' => [['name' => 'ID_', 'prefix' => null]],
        ];
        $rootIndex = [
            'unique' => false,
            'type' => 'BTREE',
            'columns' => [['name' => 'ROOT_PROC_INST_ID_', 'prefix' => null]],
        ];
        if (($bytearrayIndexes['PRIMARY'] ?? null) !== $primaryIndex) {
            throw new RuntimeException('byte-array primary index differs from the reviewed index-safe policy');
        }
        if (($bytearrayIndexes[self::BYTEARRAY_ROOT_INDEX] ?? null) !== $rootIndex) {
            throw new RuntimeException('byte-array root-process index differs from the reviewed index-safe policy');
        }

        $historyIndexes = self::indexDefinitions($pdo, $database, 'act_hi_procinst');
        $historyProcessIndexes = [];
        foreach ($historyIndexes as $name => $definition) {
            $firstColumn = $definition['columns'][0] ?? null;
            if (($definition['type'] ?? null) === 'BTREE'
                && is_array($firstColumn)
                && ($firstColumn['name'] ?? null) === 'PROC_INST_ID_'
                && ($firstColumn['prefix'] ?? null) === null
            ) {
                $historyProcessIndexes[$name] = $definition;
            }
        }
        if ($historyProcessIndexes === []) {
            throw new RuntimeException('history process identity has no reviewed leading BTREE index');
        }
        ksort($historyProcessIndexes);

        $consumerSpecs = array_merge(
            [['table' => 'act_ge_bytearray', 'column' => 'ID_']],
            $consumerColumns
        );
        $processSpecs = array_merge(
            [
                ['table' => 'act_ge_bytearray', 'column' => 'ROOT_PROC_INST_ID_'],
                ['table' => 'act_hi_procinst', 'column' => 'PROC_INST_ID_'],
            ],
            $processEvidenceColumns
        );
        $comparisonMetadata = self::comparisonMetadata(
            $pdo,
            $database,
            array_merge($consumerSpecs, $processSpecs)
        );
        foreach ($comparisonMetadata as $metadata) {
            if (($metadata['dataType'] ?? '') !== self::REVIEWED_IDENTIFIER_COMPARISON['dataType']
                || ($metadata['columnType'] ?? '') !== self::REVIEWED_IDENTIFIER_COMPARISON['columnType']
            ) {
                throw new RuntimeException('workflow identifier comparison type differs from the reviewed policy');
            }
        }

        $bytearrayRootTrimDifferences = self::trimDifferenceCount(
            $pdo,
            $database,
            'act_ge_bytearray',
            'ROOT_PROC_INST_ID_'
        );
        $historyProcessTrimDifferences = self::trimDifferenceCount(
            $pdo,
            $database,
            'act_hi_procinst',
            'PROC_INST_ID_'
        );
        if ($bytearrayRootTrimDifferences !== 0 || $historyProcessTrimDifferences !== 0) {
            throw new RuntimeException('workflow identifier values are not trim-canonical for index-safe comparison');
        }

        return [
            'indexes' => [
                'bytearrayPrimary' => $primaryIndex,
                'bytearrayRootProcess' => $rootIndex,
                'historyProcessLeading' => $historyProcessIndexes,
            ],
            'trimCanonicality' => [
                'bytearrayRootProcessDifferences' => $bytearrayRootTrimDifferences,
                'historyProcessDifferences' => $historyProcessTrimDifferences,
            ],
            'comparisonMetadata' => [
                'reviewed' => self::REVIEWED_IDENTIFIER_COMPARISON,
                'consumer' => self::comparisonGroupSummary($comparisonMetadata, $consumerSpecs),
                'processEvidence' => self::comparisonGroupSummary($comparisonMetadata, $processSpecs),
            ],
        ];
    }

    /** @param list<string> $parameters @return array<string, int|bool> */
    private static function candidateDistinctnessSummary(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $rootIndex = DatabaseManifest::quoteIdentifier(self::BYTEARRAY_ROOT_INDEX);
        $queries = [
            'collationDistinctRoots' =>
                "SELECT COUNT(*) FROM (SELECT DISTINCT TRIM(b.ROOT_PROC_INST_ID_) AS rootProcessId "
                . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}) candidateRoots",
            'binaryDistinctRoots' =>
                "SELECT COUNT(*) FROM (SELECT DISTINCT BINARY TRIM(b.ROOT_PROC_INST_ID_) AS rootProcessId "
                . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}) candidateRoots",
            'collationDistinctRootTenants' =>
                "SELECT COUNT(*) FROM (SELECT DISTINCT TRIM(b.ROOT_PROC_INST_ID_) AS rootProcessId, "
                . "NULLIF(TRIM(b.TENANT_ID_), '') AS tenantId "
                . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}) candidateRootTenants",
            'binaryDistinctRootTenants' =>
                "SELECT COUNT(*) FROM (SELECT DISTINCT BINARY TRIM(b.ROOT_PROC_INST_ID_) AS rootProcessId, "
                . "BINARY NULLIF(TRIM(b.TENANT_ID_), '') AS tenantId "
                . "FROM {$db}.act_ge_bytearray b FORCE INDEX ({$rootIndex}) WHERE {$where}) candidateRootTenants",
        ];
        $counts = [];
        foreach ($queries as $name => $sql) {
            $statement = $pdo->prepare($sql);
            $statement->execute($parameters);
            $counts[$name] = (int)$statement->fetchColumn();
        }
        if ($counts['collationDistinctRoots'] !== $counts['binaryDistinctRoots']
            || $counts['collationDistinctRootTenants'] !== $counts['binaryDistinctRootTenants']
        ) {
            throw new RuntimeException('detached byte-array candidates contain collation-equivalent byte collisions');
        }

        return ['compatible' => true] + $counts;
    }

    /** @return array<string, array{unique:bool,type:string,columns:list<array{name:string,prefix:?int}>}> */
    private static function indexDefinitions(PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare(
            'SELECT INDEX_NAME, NON_UNIQUE, INDEX_TYPE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART '
            . 'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $statement->execute([$database, $table]);
        $indexes = [];
        foreach ($statement->fetchAll() as $row) {
            $name = (string)($row['INDEX_NAME'] ?? '');
            $column = (string)($row['COLUMN_NAME'] ?? '');
            if ($name === '' || $column === '') {
                throw new RuntimeException('workflow identifier index metadata is incomplete');
            }
            $definition = [
                'unique' => (int)($row['NON_UNIQUE'] ?? 1) === 0,
                'type' => strtoupper((string)($row['INDEX_TYPE'] ?? '')),
                'columns' => [],
            ];
            if (isset($indexes[$name])) {
                if ($indexes[$name]['unique'] !== $definition['unique']
                    || $indexes[$name]['type'] !== $definition['type']
                ) {
                    throw new RuntimeException('workflow identifier index metadata is inconsistent');
                }
            } else {
                $indexes[$name] = $definition;
            }
            $indexes[$name]['columns'][] = [
                'name' => $column,
                'prefix' => $row['SUB_PART'] === null ? null : (int)$row['SUB_PART'],
            ];
        }
        ksort($indexes);

        return $indexes;
    }

    /**
     * @param list<array{table:string,column:string}> $specs
     * @return array<string, array{dataType:string,columnType:string,characterSet:string,collation:string}>
     */
    private static function comparisonMetadata(PDO $pdo, string $database, array $specs): array
    {
        $required = [];
        $tables = [];
        $columns = [];
        foreach ($specs as $spec) {
            $table = MigrationSafety::identifier($spec['table'], 'comparison metadata table');
            $column = MigrationSafety::identifier($spec['column'], 'comparison metadata column');
            $required[$table . '.' . $column] = true;
            $tables[$table] = true;
            $columns[$column] = true;
        }
        if ($required === []) {
            throw new RuntimeException('workflow identifier comparison metadata set is empty');
        }
        ksort($required);
        $tableNames = array_keys($tables);
        $columnNames = array_keys($columns);
        sort($tableNames, SORT_STRING);
        sort($columnNames, SORT_STRING);
        $statement = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME '
            . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('
            . implode(',', array_fill(0, count($tableNames), '?')) . ') AND COLUMN_NAME IN ('
            . implode(',', array_fill(0, count($columnNames), '?')) . ') ORDER BY TABLE_NAME, COLUMN_NAME'
        );
        $statement->execute(array_merge([$database], $tableNames, $columnNames));
        $metadata = [];
        foreach ($statement->fetchAll() as $row) {
            $key = (string)($row['TABLE_NAME'] ?? '') . '.' . (string)($row['COLUMN_NAME'] ?? '');
            if (!isset($required[$key])) {
                continue;
            }
            $characterSet = strtolower((string)($row['CHARACTER_SET_NAME'] ?? ''));
            $collation = strtolower((string)($row['COLLATION_NAME'] ?? ''));
            // MySQL 8 reports the legacy utf8 alias as utf8mb3. Normalize only
            // that documented alias so the reviewed three-byte UTF-8 policy
            // stays identical across the source and loopback test engines.
            if ($characterSet === 'utf8mb3') {
                $characterSet = 'utf8';
            }
            if (str_starts_with($collation, 'utf8mb3_')) {
                $collation = 'utf8_' . substr($collation, strlen('utf8mb3_'));
            }
            $metadata[$key] = [
                'dataType' => strtolower((string)($row['DATA_TYPE'] ?? '')),
                'columnType' => strtolower((string)($row['COLUMN_TYPE'] ?? '')),
                'characterSet' => $characterSet,
                'collation' => $collation,
            ];
        }
        ksort($metadata);
        if (array_keys($metadata) !== array_keys($required)) {
            throw new RuntimeException('workflow identifier comparison metadata is incomplete');
        }

        return $metadata;
    }

    /**
     * @param array<string, array{dataType:string,columnType:string,characterSet:string,collation:string}> $metadata
     * @param list<array{table:string,column:string}> $specs
     * @return array{compatible:bool,columnCount:int,columns:list<string>,metadataSha256:string}
     */
    private static function comparisonGroupSummary(array $metadata, array $specs): array
    {
        $entries = [];
        $characterSet = null;
        $collation = null;
        foreach ($specs as $spec) {
            $key = $spec['table'] . '.' . $spec['column'];
            if (!isset($metadata[$key])) {
                throw new RuntimeException('workflow identifier comparison group metadata is incomplete');
            }
            $entries[$key] = $metadata[$key];
            $entryCharacterSet = (string)($metadata[$key]['characterSet'] ?? '');
            $entryCollation = (string)($metadata[$key]['collation'] ?? '');
            if ($entryCharacterSet === '' || $entryCollation === '') {
                throw new RuntimeException('workflow identifier comparison group is not textual');
            }
            if ($characterSet === null) {
                $characterSet = $entryCharacterSet;
                $collation = $entryCollation;
            } elseif ($entryCharacterSet !== $characterSet || $entryCollation !== $collation) {
                throw new RuntimeException('workflow identifier comparison group has incompatible collations');
            }
        }
        ksort($entries);

        return [
            'compatible' => true,
            'columnCount' => count($entries),
            'columns' => array_keys($entries),
            'characterSet' => $characterSet,
            'collation' => $collation,
            'metadataSha256' => hash('sha256', json_encode(
                $entries,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )),
        ];
    }

    private static function trimDifferenceCount(
        PDO $pdo,
        string $database,
        string $table,
        string $column
    ): int {
        $db = DatabaseManifest::quoteIdentifier($database);
        $quotedTable = DatabaseManifest::quoteIdentifier($table);
        $quotedColumn = DatabaseManifest::quoteIdentifier($column);
        $statement = $pdo->query(
            "SELECT COUNT(*) FROM {$db}.{$quotedTable} WHERE {$quotedColumn} IS NOT NULL "
            . "AND BINARY {$quotedColumn} <> BINARY TRIM({$quotedColumn})"
        );

        return (int)$statement->fetchColumn();
    }

    /** @return list<string> */
    private static function columns(PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY ORDINAL_POSITION'
        );
        $statement->execute([$database, $table]);

        return array_values(array_map(static fn (array $row): string => (string)$row['COLUMN_NAME'], $statement->fetchAll()));
    }

    /** @param list<string> $required */
    private static function tableHasColumns(PDO $pdo, string $database, string $table, array $required): bool
    {
        $columns = array_fill_keys(self::columns($pdo, $database, $table), true);
        foreach ($required as $column) {
            if (!isset($columns[$column])) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $ids @return list<string> */
    private static function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string)$value, ' '), $ids),
            static fn (string $value): bool => $value !== ''
        )));
        sort($ids, SORT_STRING);

        return $ids;
    }

    private static function hexLookupKey(string $value, string $label): string
    {
        if ($value === '' || strlen($value) % 2 !== 0 || preg_match('/\A[0-9A-F]+\z/D', $value) !== 1) {
            throw new RuntimeException($label . ' did not produce a non-empty canonical hexadecimal identity');
        }

        return 'hex:' . $value;
    }

    /** @param resource|\HashContext $context */
    private static function hashValue(mixed $context, mixed $value): void
    {
        if ($value === null) {
            hash_update($context, "N\0");
            return;
        }
        $text = (string)$value;
        hash_update($context, 'S' . strlen($text) . "\0" . $text . "\0");
    }
}

final class DetachedOperationLogPolicy
{
    private const REQUIRED_COLUMNS = [
        'ID_',
        'DEPLOYMENT_ID_',
        'PROC_DEF_ID_',
        'ROOT_PROC_INST_ID_',
        'PROC_INST_ID_',
        'EXECUTION_ID_',
        'TASK_ID_',
        'CASE_DEF_ID_',
        'JOB_ID_',
        'JOB_DEF_ID_',
        'BATCH_ID_',
        'EXTERNAL_TASK_ID_',
        'CASE_INST_ID_',
        'CASE_EXECUTION_ID_',
        'USER_ID_',
        'TIMESTAMP_',
        'OPERATION_ID_',
        'TENANT_ID_',
        'REMOVAL_TIME_',
    ];

    /** @param list<string> $excludedProcessIds @return array<string, mixed> */
    public static function audit(PDO $pdo, string $database, array $excludedProcessIds): array
    {
        $columns = self::columns($pdo, $database, 'act_hi_op_log');
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!in_array($column, $columns, true)) {
                throw new RuntimeException('detached operation-log audit is missing a required column');
            }
        }

        [$where, $parameters] = self::candidatePredicate($database, 'o', $excludedProcessIds);
        $db = DatabaseManifest::quoteIdentifier($database);
        $aggregateStatement = $pdo->prepare(
            'SELECT COUNT(*) AS candidateRows, '
            . "COUNT(DISTINCT BINARY TRIM(o.PROC_INST_ID_)) AS distinctProcesses, "
            . "COALESCE(SUM(CASE WHEN BINARY TRIM(o.PROC_INST_ID_) <> BINARY TRIM(o.ROOT_PROC_INST_ID_) "
            . 'THEN 1 ELSE 0 END), 0) AS mismatchedProcessRows, '
            . "COALESCE(SUM(CASE WHEN o.TENANT_ID_ IS NOT NULL AND TRIM(o.TENANT_ID_) <> '' "
            . 'THEN 1 ELSE 0 END), 0) AS nonBlankTenantRows, '
            . 'COALESCE(SUM(CASE WHEN o.REMOVAL_TIME_ IS NULL THEN 1 ELSE 0 END), 0) AS missingRemovalTimeRows, '
            . "COALESCE(SUM(CASE WHEN NULLIF(TRIM(o.USER_ID_), '') IS NULL THEN 1 ELSE 0 END), 0) "
            . 'AS missingUserRows, '
            . "COALESCE(SUM(CASE WHEN NULLIF(TRIM(o.DEPLOYMENT_ID_), '') IS NULL THEN 1 ELSE 0 END), 0) "
            . 'AS missingDeploymentRows, '
            . "COALESCE(SUM(CASE WHEN NULLIF(TRIM(o.PROC_DEF_ID_), '') IS NULL THEN 1 ELSE 0 END), 0) "
            . 'AS missingProcessDefinitionRows, '
            . "COALESCE(SUM(CASE WHEN NULLIF(TRIM(o.OPERATION_ID_), '') IS NULL THEN 1 ELSE 0 END), 0) "
            . 'AS missingOperationIdRows, '
            . 'COALESCE(SUM(CASE WHEN o.TIMESTAMP_ IS NULL THEN 1 ELSE 0 END), 0) AS missingTimestampRows, '
            . "COALESCE(SUM(CASE WHEN o.ID_ <> TRIM(o.ID_) OR o.PROC_INST_ID_ <> TRIM(o.PROC_INST_ID_) "
            . "OR o.ROOT_PROC_INST_ID_ <> TRIM(o.ROOT_PROC_INST_ID_) OR o.USER_ID_ <> TRIM(o.USER_ID_) "
            . "OR o.DEPLOYMENT_ID_ <> TRIM(o.DEPLOYMENT_ID_) OR o.PROC_DEF_ID_ <> TRIM(o.PROC_DEF_ID_) "
            . "OR o.OPERATION_ID_ <> TRIM(o.OPERATION_ID_) "
            . 'THEN 1 ELSE 0 END), 0) AS nonNormalizedIdentifierRows, '
            . "COALESCE(SUM(CASE WHEN NULLIF(TRIM(o.EXECUTION_ID_), '') IS NOT NULL "
            . "OR NULLIF(TRIM(o.TASK_ID_), '') IS NOT NULL OR NULLIF(TRIM(o.JOB_ID_), '') IS NOT NULL "
            . "OR NULLIF(TRIM(o.JOB_DEF_ID_), '') IS NOT NULL OR NULLIF(TRIM(o.BATCH_ID_), '') IS NOT NULL "
            . "OR NULLIF(TRIM(o.EXTERNAL_TASK_ID_), '') IS NOT NULL "
            . "OR NULLIF(TRIM(o.CASE_DEF_ID_), '') IS NOT NULL "
            . "OR NULLIF(TRIM(o.CASE_INST_ID_), '') IS NOT NULL "
            . "OR NULLIF(TRIM(o.CASE_EXECUTION_ID_), '') IS NOT NULL THEN 1 ELSE 0 END), 0) "
            . 'AS nonBlankDirectReferenceRows '
            . "FROM {$db}.act_hi_op_log o WHERE {$where}"
        );
        $aggregateStatement->execute($parameters);
        $aggregate = $aggregateStatement->fetch();
        if (!is_array($aggregate)) {
            throw new RuntimeException('detached operation-log aggregate audit did not return one row');
        }
        $candidateRows = (int)($aggregate['candidateRows'] ?? 0);
        $distinctProcesses = (int)($aggregate['distinctProcesses'] ?? 0);
        if ($candidateRows === 0) {
            throw new RuntimeException('detached operation-log audit unexpectedly found no candidates');
        }
        foreach ([
            'mismatchedProcessRows',
            'nonBlankTenantRows',
            'missingRemovalTimeRows',
            'missingUserRows',
            'missingDeploymentRows',
            'missingProcessDefinitionRows',
            'missingOperationIdRows',
            'missingTimestampRows',
            'nonNormalizedIdentifierRows',
            'nonBlankDirectReferenceRows',
        ] as $field) {
            if ((int)($aggregate[$field] ?? 0) !== 0) {
                throw new RuntimeException('detached operation-log candidate differs from the reviewed legacy shape');
            }
        }

        [$rowCount, $rowIds, $processIds, $rowIdentitySha256, $rowContentSha256] = self::candidateDigests(
            $pdo,
            $database,
            $where,
            $parameters,
            $columns
        );
        if ($rowCount !== $candidateRows || count($processIds) !== $distinctProcesses) {
            throw new RuntimeException('detached operation-log identity audit differs from its aggregate counts');
        }

        $ignoreScopeRows = self::ignoreScopeRows($pdo, $database, $processIds);
        if ($ignoreScopeRows !== $candidateRows) {
            throw new RuntimeException('detached operation-log table-specific ignore would hide additional rows');
        }

        $siblingStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM {$db}.act_hi_op_log o "
            . "INNER JOIN {$db}.act_hi_op_log sibling "
            . 'ON BINARY sibling.OPERATION_ID_ = BINARY o.OPERATION_ID_ '
            . 'AND BINARY sibling.ID_ <> BINARY o.ID_ '
            . "WHERE {$where}"
        );
        $siblingStatement->execute($parameters);
        $operationSiblingRows = (int)$siblingStatement->fetchColumn();
        if ($operationSiblingRows !== 0) {
            throw new RuntimeException('detached operation-log candidate has a preserved operation-group sibling');
        }

        $supportStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM {$db}.act_hi_op_log o WHERE {$where} AND ("
            . "(SELECT COUNT(*) FROM {$db}.sys_user u INNER JOIN {$db}.tenants tenant "
            . 'ON BINARY tenant.Tenant_ID = BINARY TRIM(u.TENANT_ID) '
            . "AND (tenant.DELETE_FLAG IS NULL OR tenant.DELETE_FLAG = 'NOT_DELETE') "
            . 'WHERE BINARY u.ID = BINARY TRIM(o.USER_ID_) '
            . "AND (u.DELETE_FLAG IS NULL OR u.DELETE_FLAG = 'NOT_DELETE') "
            . "AND NULLIF(TRIM(u.TENANT_ID), '') IS NOT NULL) <> 1 "
            . "OR (SELECT COUNT(*) FROM {$db}.act_re_procdef pd "
            . 'INNER JOIN ' . $db . '.act_re_deployment deployment '
            . 'ON BINARY deployment.ID_ = BINARY pd.DEPLOYMENT_ID_ '
            . 'WHERE BINARY pd.ID_ = BINARY TRIM(o.PROC_DEF_ID_) '
            . 'AND BINARY deployment.ID_ = BINARY TRIM(o.DEPLOYMENT_ID_)) <> 1)'
        );
        $supportStatement->execute($parameters);
        $invalidSupportRows = (int)$supportStatement->fetchColumn();
        if ($invalidSupportRows !== 0) {
            throw new RuntimeException('detached operation-log candidate has incomplete user or deployment evidence');
        }

        $engineReferenceColumns = self::engineReferenceColumns($pdo, $database);
        $engineReferenceChecks = self::referenceChecks(
            $pdo,
            $database,
            $processIds,
            $engineReferenceColumns
        );
        if (array_sum($engineReferenceChecks) !== 0) {
            throw new RuntimeException('detached operation-log candidate still has workflow-engine process evidence');
        }
        $businessReferenceTables = self::businessReferenceTables($pdo, $database);
        $businessReferenceChecks = self::businessReferenceChecks(
            $pdo,
            $database,
            $processIds,
            $businessReferenceTables
        );
        if (array_sum($businessReferenceChecks) !== 0) {
            throw new RuntimeException('detached operation-log candidate still has a business process reference');
        }
        $inboundForeignKeyCount = self::inboundForeignKeyCount($pdo, $database);
        if ($inboundForeignKeyCount !== 0) {
            throw new RuntimeException('detached operation-log table has an unreviewed inbound foreign key');
        }
        $tableStructure = self::tableStructure($pdo, $database, 'act_hi_op_log');
        if (($tableStructure['engine'] ?? '') !== 'InnoDB'
            || ($tableStructure['primaryKeyColumns'] ?? []) !== ['ID_']
        ) {
            throw new RuntimeException('detached operation-log table structure is not the reviewed InnoDB primary-key shape');
        }

        return [
            'eligible' => true,
            'candidateRows' => $candidateRows,
            'distinctProcesses' => $distinctProcesses,
            'mismatchedProcessRows' => (int)$aggregate['mismatchedProcessRows'],
            'nonBlankTenantRows' => (int)$aggregate['nonBlankTenantRows'],
            'missingRemovalTimeRows' => (int)$aggregate['missingRemovalTimeRows'],
            'missingUserRows' => (int)$aggregate['missingUserRows'],
            'missingDeploymentRows' => (int)$aggregate['missingDeploymentRows'],
            'missingProcessDefinitionRows' => (int)$aggregate['missingProcessDefinitionRows'],
            'missingOperationIdRows' => (int)$aggregate['missingOperationIdRows'],
            'missingTimestampRows' => (int)$aggregate['missingTimestampRows'],
            'nonNormalizedIdentifierRows' => (int)$aggregate['nonNormalizedIdentifierRows'],
            'nonBlankDirectReferenceRows' => (int)$aggregate['nonBlankDirectReferenceRows'],
            'operationSiblingRows' => $operationSiblingRows,
            'invalidSupportRows' => $invalidSupportRows,
            'ignoreScopeRows' => $ignoreScopeRows,
            'inboundForeignKeyCount' => $inboundForeignKeyCount,
            'columnCount' => count($columns),
            'columnLayoutSha256' => hash('sha256', json_encode($columns, JSON_THROW_ON_ERROR)),
            'tableStructureSha256' => hash('sha256', json_encode($tableStructure, JSON_THROW_ON_ERROR)),
            'engineReferenceColumns' => $engineReferenceColumns,
            'engineReferenceChecks' => $engineReferenceChecks,
            'businessReferenceTables' => $businessReferenceTables,
            'businessReferenceChecks' => $businessReferenceChecks,
            'rowIdentitySha256' => $rowIdentitySha256,
            'rowContentSha256' => $rowContentSha256,
            'rowIds' => $rowIds,
            'processIds' => $processIds,
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(array $plan): array
    {
        unset($plan['rowIds'], $plan['processIds']);

        return $plan;
    }

    public static function assertExpected(array $plan, int $expectedRows, int $expectedProcesses): void
    {
        if (($plan['eligible'] ?? false) !== true
            || (int)($plan['candidateRows'] ?? -1) !== $expectedRows
            || (int)($plan['distinctProcesses'] ?? -1) !== $expectedProcesses
        ) {
            throw new RuntimeException('detached operation-log plan differs from the reviewed local snapshot baseline');
        }
    }

    public static function assertSamePlan(array $actual, array $expected): void
    {
        if (self::comparableSummary($actual) !== self::comparableSummary($expected)) {
            throw new RuntimeException('detached operation-log plan changed after the frozen source audit');
        }
    }

    /** @param list<string> $excludedProcessIds @return array{string,list<string>} */
    public static function candidatePredicate(string $database, string $alias, array $excludedProcessIds): array
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $process = "NULLIF(TRIM({$alias}.PROC_INST_ID_), '')";
        $root = "NULLIF(TRIM({$alias}.ROOT_PROC_INST_ID_), '')";
        $where = "{$process} IS NOT NULL AND {$root} IS NOT NULL "
            . "AND BINARY {$process} = BINARY {$root} "
            . "AND NOT EXISTS (SELECT 1 FROM {$db}.act_hi_procinst hp "
            . "WHERE BINARY TRIM(hp.PROC_INST_ID_) = BINARY {$process}) "
            . "AND NOT EXISTS (SELECT 1 FROM {$db}.act_hi_procinst hr "
            . "WHERE BINARY TRIM(hr.PROC_INST_ID_) = BINARY {$root})";
        $parameters = [];
        $excludedProcessIds = self::normalizeIds($excludedProcessIds);
        if ($excludedProcessIds !== []) {
            $placeholders = implode(',', array_fill(0, count($excludedProcessIds), '?'));
            $where .= " AND BINARY {$process} NOT IN ({$placeholders}) "
                . "AND BINARY {$root} NOT IN ({$placeholders})";
            $parameters = array_merge($excludedProcessIds, $excludedProcessIds);
        }

        return [$where, $parameters];
    }

    public static function assertNoRemainingCandidates(PDO $pdo, string $database): void
    {
        [$where, $parameters] = self::candidatePredicate($database, 'o', []);
        $db = DatabaseManifest::quoteIdentifier($database);
        $statement = $pdo->prepare("SELECT COUNT(*) FROM {$db}.act_hi_op_log o WHERE {$where}");
        $statement->execute($parameters);
        if ((int)$statement->fetchColumn() !== 0) {
            throw new RuntimeException('detached operation-log candidates remain in the normal target');
        }
    }

    /** @param array<string, mixed> $expectedPlan */
    public static function assertQuarantinedEvidence(
        PDO $pdo,
        string $targetDatabase,
        string $quarantineDatabase,
        string $quarantineTable,
        array $expectedPlan
    ): void {
        $quarantineColumns = self::columns($pdo, $quarantineDatabase, $quarantineTable);
        if (count($quarantineColumns) !== (int)($expectedPlan['columnCount'] ?? -1)
            || !hash_equals(
                (string)($expectedPlan['columnLayoutSha256'] ?? ''),
                hash('sha256', json_encode($quarantineColumns, JSON_THROW_ON_ERROR))
            )
        ) {
            throw new RuntimeException('detached operation-log quarantine schema differs from the frozen plan');
        }
        $quarantineStructure = self::tableStructure($pdo, $quarantineDatabase, $quarantineTable);
        if (!hash_equals(
            (string)($expectedPlan['tableStructureSha256'] ?? ''),
            hash('sha256', json_encode($quarantineStructure, JSON_THROW_ON_ERROR))
        )) {
            throw new RuntimeException('detached operation-log quarantine structure differs from the frozen plan');
        }

        $target = DatabaseManifest::quoteIdentifier($targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($quarantineDatabase);
        $table = DatabaseManifest::quoteIdentifier($quarantineTable);
        $supportStatement = $pdo->query(
            "SELECT COUNT(*) FROM {$quarantine}.{$table} o WHERE "
            . "(SELECT COUNT(*) FROM {$target}.sys_user u INNER JOIN {$target}.tenants tenant "
            . 'ON BINARY tenant.Tenant_ID = BINARY TRIM(u.TENANT_ID) '
            . "AND (tenant.DELETE_FLAG IS NULL OR tenant.DELETE_FLAG = 'NOT_DELETE') "
            . 'WHERE BINARY u.ID = BINARY o.USER_ID_ '
            . "AND (u.DELETE_FLAG IS NULL OR u.DELETE_FLAG = 'NOT_DELETE') "
            . "AND NULLIF(TRIM(u.TENANT_ID), '') IS NOT NULL) <> 1 "
            . "OR (SELECT COUNT(*) FROM {$target}.act_re_procdef pd "
            . "INNER JOIN {$target}.act_re_deployment deployment "
            . 'ON BINARY deployment.ID_ = BINARY pd.DEPLOYMENT_ID_ '
            . 'WHERE BINARY pd.ID_ = BINARY o.PROC_DEF_ID_ '
            . 'AND BINARY deployment.ID_ = BINARY o.DEPLOYMENT_ID_) <> 1'
        );
        if ((int)$supportStatement->fetchColumn() !== 0) {
            throw new RuntimeException('detached operation-log quarantine lost its user or deployment evidence');
        }

        $processIds = self::normalizeIds($expectedPlan['processIds'] ?? []);
        if ($processIds === []) {
            throw new RuntimeException('detached operation-log quarantine has no frozen process identity');
        }
        if (self::ignoreScopeRows($pdo, $targetDatabase, $processIds) !== 0) {
            throw new RuntimeException('detached operation-log process identity reappeared in the normal target');
        }
        $operationSiblingStatement = $pdo->query(
            "SELECT COUNT(*) FROM {$quarantine}.{$table} copied "
            . "INNER JOIN {$target}.act_hi_op_log sibling "
            . 'ON BINARY sibling.OPERATION_ID_ = BINARY copied.OPERATION_ID_ '
            . 'AND BINARY sibling.ID_ <> BINARY copied.ID_'
        );
        if ((int)$operationSiblingStatement->fetchColumn() !== 0) {
            throw new RuntimeException('detached operation-log quarantine gained an operation-group sibling');
        }
        $engineChecks = self::referenceChecks(
            $pdo,
            $targetDatabase,
            $processIds,
            self::engineReferenceColumns($pdo, $targetDatabase)
        );
        if (array_sum($engineChecks) !== 0) {
            throw new RuntimeException('detached operation-log quarantine gained workflow-engine process evidence');
        }
        $businessChecks = self::businessReferenceChecks(
            $pdo,
            $targetDatabase,
            $processIds,
            self::businessReferenceTables($pdo, $targetDatabase)
        );
        if (array_sum($businessChecks) !== 0) {
            throw new RuntimeException('detached operation-log quarantine gained a business process reference');
        }
        if (self::inboundForeignKeyCount($pdo, $targetDatabase) !== 0) {
            throw new RuntimeException('detached operation-log target gained an unreviewed inbound foreign key');
        }
    }

    /** @return array<string, mixed> */
    private static function comparableSummary(array $plan): array
    {
        $plan = self::summary($plan);
        unset($plan['engineReferenceColumns'], $plan['businessReferenceTables']);
        $plan['engineReferenceChecks'] = array_filter(
            $plan['engineReferenceChecks'] ?? [],
            static fn (mixed $count): bool => (int)$count !== 0
        );
        $plan['businessReferenceChecks'] = array_filter(
            $plan['businessReferenceChecks'] ?? [],
            static fn (mixed $count): bool => (int)$count !== 0
        );

        return $plan;
    }

    /** @param list<string> $processIds */
    private static function ignoreScopeRows(PDO $pdo, string $database, array $processIds): int
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $placeholders = implode(',', array_fill(0, count($processIds), '?'));
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM {$db}.act_hi_op_log WHERE "
            . "BINARY NULLIF(TRIM(PROC_INST_ID_), '') IN ({$placeholders}) "
            . "OR BINARY NULLIF(TRIM(ROOT_PROC_INST_ID_), '') IN ({$placeholders})"
        );
        $statement->execute(array_merge($processIds, $processIds));

        return (int)$statement->fetchColumn();
    }

    private static function inboundForeignKeyCount(PDO $pdo, string $database): int
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?'
        );
        $statement->execute([$database, 'act_hi_op_log']);

        return (int)$statement->fetchColumn();
    }

    /** @param list<string> $parameters @param list<string> $columns @return array{int,list<string>,list<string>,string,string} */
    private static function candidateDigests(
        PDO $pdo,
        string $database,
        string $where,
        array $parameters,
        array $columns
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $statement = $pdo->prepare(
            "SELECT o.* FROM {$db}.act_hi_op_log o WHERE {$where} ORDER BY BINARY o.ID_"
        );
        $statement->execute($parameters);
        $identityHash = hash_init('sha256');
        $contentHash = hash_init('sha256');
        $rowIds = [];
        $processes = [];
        $count = 0;
        while (($row = $statement->fetch()) !== false) {
            ++$count;
            $rowId = trim((string)($row['ID_'] ?? ''));
            $processId = trim((string)($row['PROC_INST_ID_'] ?? ''));
            if ($rowId === '' || $processId === '') {
                throw new RuntimeException('detached operation-log candidate has an empty identity');
            }
            $rowIds[] = $rowId;
            $processes[$processId] = true;
            self::hashValue($identityHash, $rowId);
            self::hashValue($identityHash, $processId);
            foreach ($columns as $column) {
                hash_update($contentHash, $column . "\0");
                self::hashValue($contentHash, $row[$column] ?? null);
            }
        }
        $processIds = array_keys($processes);
        sort($processIds, SORT_STRING);

        return [$count, $rowIds, $processIds, hash_final($identityHash), hash_final($contentHash)];
    }

    /** @return list<array{table:string,column:string}> */
    private static function engineReferenceColumns(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? "
            . "AND TABLE_NAME LIKE 'act\\_%' AND TABLE_NAME <> 'act_hi_op_log' "
            . "AND COLUMN_NAME IN ('PROC_INST_ID_', 'ROOT_PROC_INST_ID_', 'PROCESS_INSTANCE_ID_', "
            . "'SUPER_PROCESS_INSTANCE_ID_', 'CALL_PROC_INST_ID_') "
            . 'ORDER BY TABLE_NAME, COLUMN_NAME'
        );
        $statement->execute([$database]);
        $columns = [];
        foreach ($statement->fetchAll() as $row) {
            $columns[] = [
                'table' => MigrationSafety::identifier((string)$row['TABLE_NAME'], 'operation-log engine table'),
                'column' => MigrationSafety::identifier((string)$row['COLUMN_NAME'], 'operation-log engine column'),
            ];
        }

        return $columns;
    }

    /** @param list<string> $processIds @param list<array{table:string,column:string}> $specs @return array<string,int> */
    private static function referenceChecks(PDO $pdo, string $database, array $processIds, array $specs): array
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $placeholders = implode(',', array_fill(0, count($processIds), '?'));
        $checks = [];
        foreach ($specs as $spec) {
            $table = DatabaseManifest::quoteIdentifier($spec['table']);
            $column = DatabaseManifest::quoteIdentifier($spec['column']);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.{$table} WHERE BINARY NULLIF(TRIM({$column}), '') "
                . "IN ({$placeholders})"
            );
            $statement->execute($processIds);
            $checks[$spec['table'] . '.' . $spec['column']] = (int)$statement->fetchColumn();
        }

        return $checks;
    }

    /** @return list<string> */
    private static function businessReferenceTables(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? "
            . "AND COLUMN_NAME = 'PROCESS_ID' AND TABLE_NAME NOT LIKE 'act\\_%' ORDER BY TABLE_NAME"
        );
        $statement->execute([$database]);

        return array_values(array_map(
            static fn (array $row): string => MigrationSafety::identifier(
                (string)$row['TABLE_NAME'],
                'operation-log business table'
            ),
            $statement->fetchAll()
        ));
    }

    /** @param list<string> $processIds @param list<string> $tables @return array<string,int> */
    private static function businessReferenceChecks(
        PDO $pdo,
        string $database,
        array $processIds,
        array $tables
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $placeholders = implode(',', array_fill(0, count($processIds), '?'));
        $checks = [];
        foreach ($tables as $table) {
            $quotedTable = DatabaseManifest::quoteIdentifier($table);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.{$quotedTable} WHERE BINARY NULLIF(TRIM(PROCESS_ID), '') "
                . "IN ({$placeholders})"
            );
            $statement->execute($processIds);
            $checks[$table] = (int)$statement->fetchColumn();
        }

        return $checks;
    }

    /** @return list<string> */
    private static function columns(PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY ORDINAL_POSITION'
        );
        $statement->execute([$database, $table]);

        return array_values(array_map(
            static fn (array $row): string => (string)$row['COLUMN_NAME'],
            $statement->fetchAll()
        ));
    }

    /** @return array<string, mixed> */
    private static function tableStructure(PDO $pdo, string $database, string $table): array
    {
        $columnStatement = $pdo->prepare(
            'SELECT COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, '
            . 'CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
        );
        $columnStatement->execute([$database, $table]);
        $columns = $columnStatement->fetchAll();
        $indexStatement = $pdo->prepare(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE '
            . 'FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $indexStatement->execute([$database, $table]);
        $indexes = $indexStatement->fetchAll();
        $engineStatement = $pdo->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $engineStatement->execute([$database, $table]);
        $engine = $engineStatement->fetchColumn();
        $primaryKeyColumns = [];
        foreach ($indexes as $index) {
            if ((string)($index['INDEX_NAME'] ?? '') === 'PRIMARY') {
                $primaryKeyColumns[] = (string)($index['COLUMN_NAME'] ?? '');
            }
        }

        return [
            'engine' => is_string($engine) ? $engine : '',
            'columns' => $columns,
            'indexes' => $indexes,
            'primaryKeyColumns' => $primaryKeyColumns,
        ];
    }

    /** @param list<string> $ids @return list<string> */
    private static function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string)$value, ' '), $ids),
            static fn (string $value): bool => $value !== ''
        )));
        sort($ids, SORT_STRING);

        return $ids;
    }

    /** @param resource|\HashContext $context */
    private static function hashValue(mixed $context, mixed $value): void
    {
        if ($value === null) {
            hash_update($context, "N\0");
            return;
        }
        $text = (string)$value;
        hash_update($context, 'S' . strlen($text) . "\0" . $text . "\0");
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
    private const MAX_RUNTIME_JSON_BYTES = 4000;
    private const MAX_HISTORY_JSON_BYTES = 64000;

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
            $sql = "SELECT v.ID_, v.PROC_INST_ID_, v.NAME_, v.{$type} AS TYPE_NAME, v.BYTEARRAY_ID_, v.TEXT_, "
                . 'b.ID_ AS BYTEARRAY_FOUND_ID, '
                . 'CASE WHEN b.ID_ IS NULL THEN NULL ELSE LOWER(SHA2(b.BYTES_, 256)) END AS BYTEARRAY_SHA256 '
                . "FROM {$db}.{$table} v LEFT JOIN {$db}.act_ge_bytearray b ON b.ID_ = v.BYTEARRAY_ID_ "
                . "WHERE v.BYTEARRAY_ID_ IS NOT NULL "
                . "OR LOWER(COALESCE(v.{$type}, '')) IN ('serializable','object') ORDER BY v.ID_";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                $bytearrayId = (string)($row['BYTEARRAY_ID_'] ?? '');
                if ($row['BYTEARRAY_ID_'] !== null && $bytearrayId === '') {
                    throw new RuntimeException('workflow variable has an empty non-null byte-array reference');
                }
                $bytearraySha256 = '';
                if ($bytearrayId !== '') {
                    if ($row['BYTEARRAY_FOUND_ID'] === null) {
                        throw new RuntimeException('workflow variable references a missing byte-array row');
                    }
                    $bytearraySha256 = strtolower((string)($row['BYTEARRAY_SHA256'] ?? ''));
                    if (preg_match('/^[0-9a-f]{64}$/', $bytearraySha256) !== 1) {
                        throw new RuntimeException('workflow byte-array server-side hash is unavailable');
                    }
                }
                $pending[] = [
                    'table' => $spec['table'],
                    'id' => (string)$row['ID_'],
                    'processId' => (string)($row['PROC_INST_ID_'] ?? ''),
                    'name' => (string)($row['NAME_'] ?? ''),
                    'type' => (string)($row['TYPE_NAME'] ?? ''),
                    'bytearrayId' => $bytearrayId,
                    'bytearraySha256' => $bytearraySha256,
                ];
            }
        }

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
        $itemsByTable = ['act_ru_variable' => [], 'act_hi_varinst' => []];
        $expectedBytearrayHashes = [];
        foreach ($before as $item) {
            $table = $item['table'];
            if (!array_key_exists($table, $itemsByTable)) {
                throw new RuntimeException('workflow variable audit contains an unexpected table');
            }
            $itemsByTable[$table][] = $item;
            $bytearrayId = (string)($item['bytearrayId'] ?? '');
            if ($bytearrayId !== '') {
                $expectedHash = strtolower((string)($item['bytearraySha256'] ?? ''));
                if (preg_match('/^[0-9a-f]{64}$/', $expectedHash) !== 1) {
                    throw new RuntimeException('workflow variable audit contains an invalid byte-array hash');
                }
                if (isset($expectedBytearrayHashes[$bytearrayId])
                    && !hash_equals($expectedBytearrayHashes[$bytearrayId], $expectedHash)
                ) {
                    throw new RuntimeException('workflow variable audit contains conflicting byte-array hashes');
                }
                $expectedBytearrayHashes[$bytearrayId] = $expectedHash;
            }
        }

        $convertedRows = [];
        foreach ($itemsByTable as $table => $items) {
            if ($items === []) {
                continue;
            }
            $typeColumn = $table === 'act_ru_variable' ? 'TYPE_' : 'VAR_TYPE_';
            $convertedRows[$table] = self::convertedRowsById(
                $pdo,
                $db,
                $table,
                $typeColumn,
                array_values(array_unique(array_column($items, 'id')))
            );
        }
        $actualBytearrayHashes = self::bytearrayHashesById(
            $pdo,
            $db,
            array_keys($expectedBytearrayHashes)
        );
        if (count($actualBytearrayHashes) !== count($expectedBytearrayHashes)) {
            throw new RuntimeException('workflow converter removed an audited original byte-array row');
        }
        foreach ($expectedBytearrayHashes as $bytearrayId => $expectedHash) {
            $actualHash = $actualBytearrayHashes[$bytearrayId] ?? '';
            if (!hash_equals($expectedHash, $actualHash)) {
                throw new RuntimeException('workflow converter did not preserve the audited original byte-array content');
            }
        }

        foreach ($before as $item) {
            $table = $item['table'];
            $row = $convertedRows[$table][$item['id']] ?? null;
            if (!is_array($row)) {
                throw new RuntimeException('workflow variable converter removed an audited variable row');
            }
            $text = (string)($row['TEXT_'] ?? '');
            $maxJsonBytes = $table === 'act_ru_variable'
                ? self::MAX_RUNTIME_JSON_BYTES
                : self::MAX_HISTORY_JSON_BYTES;
            if (strtolower((string)$row['TYPE_NAME']) !== 'string'
                || $row['BYTEARRAY_ID_'] !== null
                || strlen($text) > $maxJsonBytes
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

    /**
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    private static function convertedRowsById(
        PDO $pdo,
        string $quotedDatabase,
        string $table,
        string $typeColumn,
        array $ids
    ): array {
        $rows = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $statement = $pdo->prepare(
                'SELECT ID_, ' . DatabaseManifest::quoteIdentifier($typeColumn)
                . ' AS TYPE_NAME, TEXT_, BYTEARRAY_ID_ FROM ' . $quotedDatabase . '.'
                . DatabaseManifest::quoteIdentifier($table) . " WHERE ID_ IN ({$placeholders})"
            );
            $statement->execute($chunk);
            foreach ($statement->fetchAll() as $row) {
                $rows[(string)$row['ID_']] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private static function bytearrayHashesById(PDO $pdo, string $quotedDatabase, array $ids): array
    {
        $hashes = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $statement = $pdo->prepare(
                'SELECT ID_, LOWER(SHA2(BYTES_, 256)) AS BYTEARRAY_SHA256 FROM '
                . $quotedDatabase . ".act_ge_bytearray WHERE ID_ IN ({$placeholders})"
            );
            $statement->execute($chunk);
            foreach ($statement->fetchAll() as $row) {
                $hash = strtolower((string)($row['BYTEARRAY_SHA256'] ?? ''));
                if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
                    throw new RuntimeException('workflow byte-array server-side hash is unavailable');
                }
                $hashes[(string)$row['ID_']] = $hash;
            }
        }

        return $hashes;
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
        'act_hi_identitylink' => [
            'TASK_ID_' => 'tasks',
            'PROC_INST_ID_' => 'processes',
            'ROOT_PROC_INST_ID_' => 'processes',
        ],
        'act_hi_taskinst' => ['ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_actinst' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_varinst' => ['TASK_ID_' => 'tasks', 'PROC_INST_ID_' => 'processes', 'EXECUTION_ID_' => 'executions'],
        'act_hi_procinst' => ['PROC_INST_ID_' => 'processes'],
        'act_hi_op_log' => [
            'TASK_ID_' => 'tasks',
            'PROC_INST_ID_' => 'processes',
            'ROOT_PROC_INST_ID_' => 'processes',
            'EXECUTION_ID_' => 'executions',
        ],
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
        $selections = [];

        foreach (self::ROW_SPECS as $table => $spec) {
            if (!$this->tableExists($this->targetDatabase, $table)) {
                continue;
            }
            [$where, $parameters] = $this->whereForSpec($table, $spec, $sets);
            if ($where === '') {
                continue;
            }
            $audit[$table] = $this->copyAndVerify($table, $where, $parameters, 'orphan-runtime-or-history');
            $selections[$table] = [$where, $parameters];
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
            $bytearrayWhere = '(' . implode(' OR ', $bytearrayPredicates) . ')';
            $audit['act_ge_bytearray'] = $this->copyAndVerify(
                'act_ge_bytearray',
                $bytearrayWhere,
                $bytearrayParameters,
                'orphan-bytearray-and-shared-deployment-resource'
            );
            $selections['act_ge_bytearray'] = [$bytearrayWhere, $bytearrayParameters];
        }
        $rootBytearrayRowsDeleted = 0;
        if (isset($bytearrayColumns['ROOT_PROC_INST_ID_']) && $sets['processes'] !== []) {
            [$rootWhere, $rootParameters] = self::inPredicate('ROOT_PROC_INST_ID_', $sets['processes']);
            $rootBytearrayRowsDeleted = $this->countWhere(
                $this->targetDatabase,
                'act_ge_bytearray',
                $rootWhere,
                $rootParameters
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
            $selections[$table] = [$where, $parameters];
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

        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->beginTransaction();
        try {
            if (OrphanPolicy::detect($this->pdo, $this->targetDatabase) !== $orphans) {
                throw new RuntimeException('orphan set changed after its frozen quarantine copy');
            }
            OrphanPolicy::assertIsolationEligible($this->pdo, $this->targetDatabase, $orphans);
            foreach ($selections as $table => [$where, $parameters]) {
                $expected = $audit[$table] ?? null;
                if (!is_array($expected)) {
                    throw new RuntimeException("missing frozen quarantine audit for {$table}");
                }
                $current = $this->rowDigest($this->targetDatabase, $table, $where, $parameters);
                $copied = $this->rowDigest($this->quarantineDatabase, $table, $where, $parameters);
                if ($current['rowCount'] !== (int)($expected['rowCount'] ?? -1)
                    || $copied['rowCount'] !== (int)($expected['rowCount'] ?? -1)
                    || !hash_equals((string)($expected['sha256'] ?? ''), $current['sha256'])
                    || !hash_equals((string)($expected['sha256'] ?? ''), $copied['sha256'])
                ) {
                    throw new RuntimeException("quarantine selection changed before delete for {$table}");
                }
            }
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
                if ($statement->rowCount() !== $rootBytearrayRowsDeleted) {
                    throw new RuntimeException('orphan root byte-array delete count differs from the frozen copy');
                }
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
            'rootBytearrayRowsDeleted' => $rootBytearrayRowsDeleted,
            'tables' => $audit,
        ];
    }

    /** @return array<string, mixed> */
    public function quarantineDetachedBytearrays(array $expectedPlan): array
    {
        $currentPlan = DetachedBytearrayPolicy::audit($this->pdo, $this->targetDatabase, []);
        DetachedBytearrayPolicy::assertSamePlan($currentPlan, $expectedPlan);

        $target = DatabaseManifest::quoteIdentifier($this->targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $detachedTable = 'act_ge_bytearray_detached';
        $quotedDetachedTable = DatabaseManifest::quoteIdentifier($detachedTable);
        if ($this->tableExists($this->quarantineDatabase, $detachedTable)) {
            throw new RuntimeException('detached byte-array quarantine table already exists');
        }
        if (!$this->tableExists($this->quarantineDatabase, 'migration_quarantine_audit')) {
            throw new RuntimeException('detached byte-array quarantine requires the initialized audit schema');
        }
        $this->assertInnoDb($this->targetDatabase, 'act_ge_bytearray');
        $this->assertInnoDb($this->quarantineDatabase, 'migration_quarantine_audit');

        [$where, $parameters] = DetachedBytearrayPolicy::candidatePredicate(
            $this->targetDatabase,
            'b',
            []
        );
        [$digestWhere, $digestParameters] = DetachedBytearrayPolicy::candidatePredicate(
            $this->targetDatabase,
            'act_ge_bytearray',
            []
        );
        $this->pdo->exec(
            "CREATE TABLE {$quarantine}.{$quotedDetachedTable} LIKE {$target}.act_ge_bytearray"
        );
        $this->assertInnoDb($this->quarantineDatabase, $detachedTable);
        $copied = null;
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->beginTransaction();
        try {
            $lockedPlan = DetachedBytearrayPolicy::audit($this->pdo, $this->targetDatabase, []);
            DetachedBytearrayPolicy::assertSamePlan($lockedPlan, $expectedPlan);
            $before = $this->rowDigest(
                $this->targetDatabase,
                'act_ge_bytearray',
                $digestWhere,
                $digestParameters
            );
            if ($before['rowCount'] !== (int)($expectedPlan['candidateRows'] ?? -1)
                || !hash_equals((string)($expectedPlan['rowContentSha256'] ?? ''), $before['sha256'])
            ) {
                throw new RuntimeException('detached byte-array copy source differs from the frozen plan');
            }
            $copy = $this->pdo->prepare(
                "INSERT INTO {$quarantine}.{$quotedDetachedTable} "
                . "SELECT b.* FROM {$target}.act_ge_bytearray b WHERE {$where}"
            );
            $copy->execute($parameters);
            if ($copy->rowCount() !== $before['rowCount']) {
                throw new RuntimeException('detached byte-array quarantine copy count differs from the frozen plan');
            }
            $copied = $this->rowDigest($this->quarantineDatabase, $detachedTable, '1 = 1', []);
            if ($copied !== $before) {
                throw new RuntimeException('detached byte-array quarantine copy content verification failed');
            }
            $auditInsert = $this->pdo->prepare(
                "INSERT INTO {$quarantine}.migration_quarantine_audit "
                . '(RUN_ID, TABLE_NAME, ROW_COUNT, SHA256, CATEGORY, COPIED_AT) '
                . 'VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );
            $auditInsert->execute([
                $this->runId,
                $detachedTable,
                $copied['rowCount'],
                $copied['sha256'],
                'detached-and-business-linked-removed-engine-bytearray',
            ]);
            if ($auditInsert->rowCount() !== 1) {
                throw new RuntimeException('detached byte-array quarantine audit insert failed');
            }
            $delete = $this->pdo->prepare(
                "DELETE {$target}.act_ge_bytearray FROM {$target}.act_ge_bytearray FORCE INDEX (PRIMARY) "
                . "INNER JOIN {$quarantine}.{$quotedDetachedTable} copied FORCE INDEX (PRIMARY) "
                . "ON {$target}.act_ge_bytearray.ID_ = copied.ID_ "
                . "AND BINARY {$target}.act_ge_bytearray.ID_ = BINARY copied.ID_"
            );
            $delete->execute();
            if ($delete->rowCount() !== $before['rowCount']) {
                throw new RuntimeException('detached byte-array target delete count differs from the frozen copy');
            }
            $remaining = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM {$quarantine}.{$quotedDetachedTable} copied FORCE INDEX (PRIMARY) "
                . "INNER JOIN {$target}.act_ge_bytearray targetRow FORCE INDEX (PRIMARY) "
                . 'ON targetRow.ID_ = copied.ID_ '
                . 'AND BINARY targetRow.ID_ = BINARY copied.ID_'
            )->fetchColumn();
            if ($remaining !== 0) {
                throw new RuntimeException('detached byte-array rows remain in the normal target');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        if (!is_array($copied)) {
            throw new RuntimeException('detached byte-array quarantine did not produce a verified copy');
        }

        return [
            'runId' => $this->runId,
            'table' => $detachedTable,
            'rowCount' => $copied['rowCount'],
            'sha256' => $copied['sha256'],
            'fullyDetachedRows' => (int)$expectedPlan['fullyDetachedRows'],
            'businessLinkedRows' => (int)$expectedPlan['businessLinkedRows'],
            'businessLinkedRoots' => (int)$expectedPlan['businessLinkedRoots'],
        ];
    }

    /** @return array{rowCount:int,sha256:string} */
    public function assertDetachedBytearrayQuarantine(array $expectedPlan): array
    {
        $detachedTable = 'act_ge_bytearray_detached';
        if (!$this->tableExists($this->quarantineDatabase, $detachedTable)) {
            throw new RuntimeException('detached byte-array quarantine table is missing');
        }
        $copied = $this->rowDigest($this->quarantineDatabase, $detachedTable, '1 = 1', []);
        if ($copied['rowCount'] !== (int)($expectedPlan['candidateRows'] ?? -1)
            || !hash_equals((string)($expectedPlan['rowContentSha256'] ?? ''), $copied['sha256'])
        ) {
            throw new RuntimeException('detached byte-array quarantine no longer matches the frozen plan');
        }

        $target = DatabaseManifest::quoteIdentifier($this->targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $quotedDetachedTable = DatabaseManifest::quoteIdentifier($detachedTable);
        $remaining = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM {$quarantine}.{$quotedDetachedTable} copied FORCE INDEX (PRIMARY) "
            . "INNER JOIN {$target}.act_ge_bytearray targetRow FORCE INDEX (PRIMARY) "
            . 'ON targetRow.ID_ = copied.ID_ '
            . 'AND BINARY targetRow.ID_ = BINARY copied.ID_'
        )->fetchColumn();
        if ($remaining !== 0) {
            throw new RuntimeException('detached byte-array quarantine rows reappeared in the normal target');
        }

        $auditStatement = $this->pdo->prepare(
            "SELECT ROW_COUNT, SHA256 FROM {$quarantine}.migration_quarantine_audit "
            . 'WHERE BINARY RUN_ID = BINARY ? AND BINARY TABLE_NAME = BINARY ?'
        );
        $auditStatement->execute([$this->runId, $detachedTable]);
        $auditRows = $auditStatement->fetchAll();
        if (count($auditRows) !== 1
            || (int)($auditRows[0]['ROW_COUNT'] ?? -1) !== $copied['rowCount']
            || !hash_equals($copied['sha256'], (string)($auditRows[0]['SHA256'] ?? ''))
        ) {
            throw new RuntimeException('detached byte-array quarantine audit no longer matches its copy');
        }
        DetachedBytearrayPolicy::assertNoRemainingCandidates($this->pdo, $this->targetDatabase);

        return $copied;
    }

    /** @return array<string, mixed> */
    public function quarantineDetachedOperationLogs(array $expectedPlan): array
    {
        $currentPlan = DetachedOperationLogPolicy::audit($this->pdo, $this->targetDatabase, []);
        DetachedOperationLogPolicy::assertSamePlan($currentPlan, $expectedPlan);

        $target = DatabaseManifest::quoteIdentifier($this->targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $detachedTable = 'act_hi_op_log_detached';
        $quotedDetachedTable = DatabaseManifest::quoteIdentifier($detachedTable);
        if ($this->tableExists($this->quarantineDatabase, $detachedTable)) {
            throw new RuntimeException('detached operation-log quarantine table already exists');
        }
        if (!$this->tableExists($this->quarantineDatabase, 'migration_quarantine_audit')) {
            throw new RuntimeException('detached operation-log quarantine requires the initialized audit schema');
        }
        $this->assertInnoDb($this->targetDatabase, 'act_hi_op_log');
        $this->assertInnoDb($this->quarantineDatabase, 'migration_quarantine_audit');

        [$where, $parameters] = DetachedOperationLogPolicy::candidatePredicate(
            $this->targetDatabase,
            'o',
            []
        );
        [$digestWhere, $digestParameters] = DetachedOperationLogPolicy::candidatePredicate(
            $this->targetDatabase,
            'act_hi_op_log',
            []
        );
        $this->pdo->exec(
            "CREATE TABLE {$quarantine}.{$quotedDetachedTable} LIKE {$target}.act_hi_op_log"
        );
        $this->assertInnoDb($this->quarantineDatabase, $detachedTable);

        $copied = null;
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->beginTransaction();
        try {
            $lockedPlan = DetachedOperationLogPolicy::audit($this->pdo, $this->targetDatabase, []);
            DetachedOperationLogPolicy::assertSamePlan($lockedPlan, $expectedPlan);
            $before = $this->rowDigest(
                $this->targetDatabase,
                'act_hi_op_log',
                $digestWhere,
                $digestParameters
            );
            if ($before['rowCount'] !== (int)($expectedPlan['candidateRows'] ?? -1)
                || !hash_equals((string)($expectedPlan['rowContentSha256'] ?? ''), $before['sha256'])
            ) {
                throw new RuntimeException('detached operation-log copy source differs from the frozen plan');
            }

            $copy = $this->pdo->prepare(
                "INSERT INTO {$quarantine}.{$quotedDetachedTable} "
                . "SELECT o.* FROM {$target}.act_hi_op_log o WHERE {$where}"
            );
            $copy->execute($parameters);
            if ($copy->rowCount() !== $before['rowCount']) {
                throw new RuntimeException('detached operation-log quarantine copy count differs from the frozen plan');
            }
            $copied = $this->rowDigest($this->quarantineDatabase, $detachedTable, '1 = 1', []);
            if ($copied !== $before) {
                throw new RuntimeException('detached operation-log quarantine copy content verification failed');
            }

            $auditInsert = $this->pdo->prepare(
                "INSERT INTO {$quarantine}.migration_quarantine_audit "
                . '(RUN_ID, TABLE_NAME, ROW_COUNT, SHA256, CATEGORY, COPIED_AT) '
                . 'VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );
            $auditInsert->execute([
                $this->runId,
                $detachedTable,
                $copied['rowCount'],
                $copied['sha256'],
                'detached-legacy-operation-log',
            ]);
            if ($auditInsert->rowCount() !== 1) {
                throw new RuntimeException('detached operation-log quarantine audit insert failed');
            }

            $delete = $this->pdo->prepare(
                "DELETE {$target}.act_hi_op_log FROM {$target}.act_hi_op_log FORCE INDEX (PRIMARY) "
                . "INNER JOIN {$quarantine}.{$quotedDetachedTable} copied FORCE INDEX (PRIMARY) "
                . "ON {$target}.act_hi_op_log.ID_ = copied.ID_ "
                . "AND BINARY {$target}.act_hi_op_log.ID_ = BINARY copied.ID_"
            );
            $delete->execute();
            if ($delete->rowCount() !== $before['rowCount']) {
                throw new RuntimeException('detached operation-log target delete count differs from the frozen copy');
            }
            $remaining = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM {$quarantine}.{$quotedDetachedTable} copied FORCE INDEX (PRIMARY) "
                . "INNER JOIN {$target}.act_hi_op_log targetRow FORCE INDEX (PRIMARY) "
                . 'ON targetRow.ID_ = copied.ID_ '
                . 'AND BINARY targetRow.ID_ = BINARY copied.ID_'
            )->fetchColumn();
            if ($remaining !== 0) {
                throw new RuntimeException('detached operation-log rows remain in the normal target');
            }
            DetachedOperationLogPolicy::assertNoRemainingCandidates($this->pdo, $this->targetDatabase);
            DetachedOperationLogPolicy::assertQuarantinedEvidence(
                $this->pdo,
                $this->targetDatabase,
                $this->quarantineDatabase,
                $detachedTable,
                $expectedPlan
            );
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        if (!is_array($copied)) {
            throw new RuntimeException('detached operation-log quarantine did not produce a verified copy');
        }
        $this->assertDetachedOperationLogQuarantine($expectedPlan);

        return [
            'runId' => $this->runId,
            'table' => $detachedTable,
            'rowCount' => $copied['rowCount'],
            'processCount' => (int)($expectedPlan['distinctProcesses'] ?? 0),
            'sha256' => $copied['sha256'],
        ];
    }

    /** @return array{rowCount:int,sha256:string} */
    public function assertDetachedOperationLogQuarantine(array $expectedPlan): array
    {
        $detachedTable = 'act_hi_op_log_detached';
        if (!$this->tableExists($this->quarantineDatabase, $detachedTable)) {
            throw new RuntimeException('detached operation-log quarantine table is missing');
        }
        $this->assertInnoDb($this->targetDatabase, 'act_hi_op_log');
        $this->assertInnoDb($this->quarantineDatabase, 'migration_quarantine_audit');
        $this->assertInnoDb($this->quarantineDatabase, $detachedTable);

        $copied = $this->rowDigest($this->quarantineDatabase, $detachedTable, '1 = 1', []);
        if ($copied['rowCount'] !== (int)($expectedPlan['candidateRows'] ?? -1)
            || !hash_equals((string)($expectedPlan['rowContentSha256'] ?? ''), $copied['sha256'])
        ) {
            throw new RuntimeException('detached operation-log quarantine no longer matches the frozen plan');
        }

        $target = DatabaseManifest::quoteIdentifier($this->targetDatabase);
        $quarantine = DatabaseManifest::quoteIdentifier($this->quarantineDatabase);
        $quotedDetachedTable = DatabaseManifest::quoteIdentifier($detachedTable);
        $remaining = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM {$quarantine}.{$quotedDetachedTable} copied FORCE INDEX (PRIMARY) "
            . "INNER JOIN {$target}.act_hi_op_log targetRow FORCE INDEX (PRIMARY) "
            . 'ON targetRow.ID_ = copied.ID_ '
            . 'AND BINARY targetRow.ID_ = BINARY copied.ID_'
        )->fetchColumn();
        if ($remaining !== 0) {
            throw new RuntimeException('detached operation-log quarantine rows reappeared in the normal target');
        }

        $auditStatement = $this->pdo->prepare(
            "SELECT ROW_COUNT, SHA256, CATEGORY FROM {$quarantine}.migration_quarantine_audit "
            . 'WHERE BINARY RUN_ID = BINARY ? AND BINARY TABLE_NAME = BINARY ?'
        );
        $auditStatement->execute([$this->runId, $detachedTable]);
        $auditRows = $auditStatement->fetchAll();
        if (count($auditRows) !== 1
            || (int)($auditRows[0]['ROW_COUNT'] ?? -1) !== $copied['rowCount']
            || !hash_equals($copied['sha256'], (string)($auditRows[0]['SHA256'] ?? ''))
            || (string)($auditRows[0]['CATEGORY'] ?? '') !== 'detached-legacy-operation-log'
        ) {
            throw new RuntimeException('detached operation-log quarantine audit no longer matches its copy');
        }
        DetachedOperationLogPolicy::assertNoRemainingCandidates($this->pdo, $this->targetDatabase);
        DetachedOperationLogPolicy::assertQuarantinedEvidence(
            $this->pdo,
            $this->targetDatabase,
            $this->quarantineDatabase,
            $detachedTable,
            $expectedPlan
        );

        return $copied;
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
            "CREATE TABLE {$quarantine}.migration_workflow_tenant_repair_audit ("
            . 'ID bigint unsigned NOT NULL AUTO_INCREMENT, RUN_ID varchar(80) NOT NULL, '
            . 'PROCESS_ID varchar(128) NOT NULL, TENANT_ID varchar(64) NOT NULL, '
            . 'HISTORY_VARIABLE_ID varchar(128) NOT NULL, RUNTIME_VARIABLE_ID varchar(128) DEFAULT NULL, '
            . 'ACTIVE_PROCESS tinyint(1) NOT NULL, REPAIRED_AT datetime NOT NULL, PRIMARY KEY (ID), '
            . 'UNIQUE KEY uk_workflow_tenant_repair (RUN_ID, PROCESS_ID)) '
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
        $columns = array_keys($this->columns($table, $database));
        $order = $this->primaryKeyColumns($table, $database);
        if ($order === []) {
            $order = $columns;
        }
        $orderSql = implode(', ', array_map(
            static fn (string $column): string => 'BINARY ' . DatabaseManifest::quoteIdentifier($column),
            $order
        ));
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
    private function columns(string $table, ?string $database = null): array
    {
        $database ??= $this->targetDatabase;
        if (!$this->tableExists($database, $table)) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
            . 'ORDER BY ORDINAL_POSITION'
        );
        $statement->execute([$database, $table]);
        $columns = [];
        foreach ($statement->fetchAll() as $row) {
            $columns[(string)$row['COLUMN_NAME']] = true;
        }

        return $columns;
    }

    /** @return list<string> */
    private function primaryKeyColumns(string $table, ?string $database = null): array
    {
        $database ??= $this->targetDatabase;
        $statement = $this->pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? "
            . "AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX"
        );
        $statement->execute([$database, $table]);

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

    private function assertInnoDb(string $database, string $table): void
    {
        $statement = $this->pdo->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $statement->execute([$database, $table]);
        $engine = $statement->fetchColumn();
        if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
            throw new RuntimeException('quarantine transaction requires an InnoDB table');
        }
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

        return ['BINARY ' . DatabaseManifest::quoteIdentifier($column) . " IN ({$placeholders})", array_values($values)];
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

final class WorkflowTenantRepair
{
    /** @var array<string, list<string>> */
    private const TABLE_PROCESS_COLUMNS = [
        'act_ge_bytearray' => ['ROOT_PROC_INST_ID_'],
        'act_hi_actinst' => ['PROC_INST_ID_'],
        'act_hi_detail' => ['PROC_INST_ID_'],
        'act_hi_identitylink' => ['ROOT_PROC_INST_ID_'],
        'act_hi_op_log' => ['PROC_INST_ID_', 'ROOT_PROC_INST_ID_'],
        'act_hi_procinst' => ['PROC_INST_ID_'],
        'act_hi_taskinst' => ['PROC_INST_ID_'],
        'act_hi_varinst' => ['PROC_INST_ID_'],
        'act_ru_execution' => ['PROC_INST_ID_'],
        'act_ru_task' => ['PROC_INST_ID_'],
        'act_ru_variable' => ['PROC_INST_ID_'],
    ];

    /** @return array<string, mixed> */
    public static function audit(
        PDO $pdo,
        string $database,
        int $expectedHistoryProcesses,
        int $expectedActiveProcesses,
        int $expectedBlankAssignees,
        array $ignoredUnmappedProcessIds = [],
        array $tableIgnoredUnmappedProcessIds = []
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $tenantRows = $pdo->query(
            "SELECT Tenant_ID AS TENANT_ID_RAW, TRIM(Tenant_ID) AS Tenant_ID, "
            . "HEX(NULLIF(TRIM(Tenant_ID), '')) AS TENANT_ID_HEX FROM {$db}.tenants "
            . "WHERE DELETE_FLAG IS NULL OR DELETE_FLAG = 'NOT_DELETE' ORDER BY Tenant_ID"
        )->fetchAll();
        $activeTenants = [];
        foreach ($tenantRows as $row) {
            self::assertPhpAndMysqlTrimAgree(
                $row['TENANT_ID_RAW'] ?? null,
                $row['Tenant_ID'] ?? null,
                'active tenant id'
            );
            $tenantHex = $row['TENANT_ID_HEX'] ?? null;
            if ($tenantHex === null) {
                continue;
            }
            $activeTenants[self::mysqlHexIdentityKey((string)$tenantHex, 'active tenant id')] = true;
        }

        $userRows = $pdo->query(
            "SELECT ID AS ID_RAW, TRIM(ID) AS ID, HEX(NULLIF(TRIM(ID), '')) AS ID_HEX, "
            . "TENANT_ID AS TENANT_ID_RAW, TRIM(TENANT_ID) AS TENANT_ID, "
            . "HEX(NULLIF(TRIM(TENANT_ID), '')) AS TENANT_ID_HEX FROM {$db}.sys_user "
            . "WHERE DELETE_FLAG IS NULL OR DELETE_FLAG = 'NOT_DELETE' ORDER BY ID"
        )->fetchAll();
        $activeUsers = [];
        foreach ($userRows as $row) {
            self::assertPhpAndMysqlTrimAgree($row['ID_RAW'] ?? null, $row['ID'] ?? null, 'active user id');
            self::assertPhpAndMysqlTrimAgree(
                $row['TENANT_ID_RAW'] ?? null,
                $row['TENANT_ID'] ?? null,
                'active user tenant id'
            );
            $userHex = $row['ID_HEX'] ?? null;
            $tenantHex = $row['TENANT_ID_HEX'] ?? null;
            if ($userHex === null || $tenantHex === null) {
                throw new RuntimeException('workflow tenant repair requires non-empty active user identities');
            }
            $userKey = self::mysqlHexIdentityKey((string)$userHex, 'active user id');
            $tenantKey = self::mysqlHexIdentityKey((string)$tenantHex, 'active user tenant id');
            if (isset($activeUsers[$userKey])) {
                throw new RuntimeException('workflow tenant repair requires globally unique active user ids');
            }
            $activeUsers[$userKey] = $tenantKey;
        }

        $historyVariables = [];
        foreach ($pdo->query(
            "SELECT ID_, PROC_INST_ID_ AS PROC_INST_ID_RAW, TRIM(PROC_INST_ID_) AS PROC_INST_ID_, "
            . "HEX(NULLIF(TRIM(PROC_INST_ID_), '')) AS PROC_INST_ID_HEX, "
            . "VAR_TYPE_ AS VAR_TYPE_RAW, TRIM(VAR_TYPE_) AS VAR_TYPE_, "
            . "TEXT_ AS TEXT_RAW, TRIM(TEXT_) AS TEXT_, "
            . "HEX(NULLIF(TRIM(TEXT_), '')) AS TEXT_HEX FROM {$db}.act_hi_varinst "
            . "WHERE NAME_ = 'tenantId' ORDER BY PROC_INST_ID_, ID_"
        )->fetchAll() as $row) {
            self::assertPhpAndMysqlTrimAgree(
                $row['PROC_INST_ID_RAW'] ?? null,
                $row['PROC_INST_ID_'] ?? null,
                'history tenant variable process id'
            );
            self::assertPhpAndMysqlTrimAgree(
                $row['VAR_TYPE_RAW'] ?? null,
                $row['VAR_TYPE_'] ?? null,
                'history tenant variable type'
            );
            self::assertPhpAndMysqlTrimAgree(
                $row['TEXT_RAW'] ?? null,
                $row['TEXT_'] ?? null,
                'history tenant variable tenant id'
            );
            if (($row['PROC_INST_ID_HEX'] ?? null) === null) {
                throw new RuntimeException('history tenant variable has no process id');
            }
            $processKey = self::mysqlHexIdentityKey(
                (string)$row['PROC_INST_ID_HEX'],
                'history tenant variable process id'
            );
            $historyVariables[$processKey][] = $row;
        }

        $historyRows = $pdo->query(
            "SELECT PROC_INST_ID_ AS PROC_INST_ID_RAW, TRIM(PROC_INST_ID_) AS PROC_INST_ID_, "
            . "HEX(NULLIF(TRIM(PROC_INST_ID_), '')) AS PROC_INST_ID_HEX, "
            . "START_USER_ID_ AS START_USER_ID_RAW, TRIM(START_USER_ID_) AS START_USER_ID_, "
            . "HEX(NULLIF(TRIM(START_USER_ID_), '')) AS START_USER_ID_HEX "
            . "FROM {$db}.act_hi_procinst ORDER BY PROC_INST_ID_"
        )->fetchAll();
        if (count($historyRows) !== $expectedHistoryProcesses) {
            throw new RuntimeException('history workflow process count differs from the audited tenant repair baseline');
        }

        $processes = [];
        $processIndexes = [];
        $processIdentityKeys = [];
        $tenantIdentityKeys = [];
        foreach ($historyRows as $row) {
            self::assertPhpAndMysqlTrimAgree(
                $row['PROC_INST_ID_RAW'] ?? null,
                $row['PROC_INST_ID_'] ?? null,
                'history workflow process id'
            );
            self::assertPhpAndMysqlTrimAgree(
                $row['START_USER_ID_RAW'] ?? null,
                $row['START_USER_ID_'] ?? null,
                'workflow starter id'
            );
            $processId = (string)($row['PROC_INST_ID_'] ?? '');
            if (($row['PROC_INST_ID_HEX'] ?? null) === null) {
                throw new RuntimeException('history workflow process ids must be non-empty and unique');
            }
            $processKey = self::mysqlHexIdentityKey(
                (string)$row['PROC_INST_ID_HEX'],
                'history workflow process id'
            );
            if ($processId === '' || isset($processIndexes[$processKey])) {
                throw new RuntimeException('history workflow process ids must be non-empty and unique');
            }
            $variables = $historyVariables[$processKey] ?? [];
            if (count($variables) !== 1) {
                throw new RuntimeException('workflow tenant repair requires exactly one history tenantId variable');
            }
            $variable = $variables[0];
            $tenantId = (string)($variable['TEXT_'] ?? '');
            if (($variable['TEXT_HEX'] ?? null) === null) {
                throw new RuntimeException('history tenantId variable is not a supported non-empty string');
            }
            $tenantKey = self::mysqlHexIdentityKey(
                (string)$variable['TEXT_HEX'],
                'history tenant variable tenant id'
            );
            if (strtolower((string)($variable['VAR_TYPE_'] ?? '')) !== 'string'
                || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tenantId)
            ) {
                throw new RuntimeException('history tenantId variable is not a supported non-empty string');
            }
            if (!isset($activeTenants[$tenantKey])) {
                throw new RuntimeException('history tenantId variable does not identify one active tenant');
            }
            if (($row['START_USER_ID_HEX'] ?? null) === null) {
                throw new RuntimeException('workflow starter does not belong to the inferred tenant');
            }
            $starterKey = self::mysqlHexIdentityKey(
                (string)$row['START_USER_ID_HEX'],
                'workflow starter id'
            );
            if (($activeUsers[$starterKey] ?? null) !== $tenantKey) {
                throw new RuntimeException('workflow starter does not belong to the inferred tenant');
            }
            $processIndexes[$processKey] = count($processes);
            $processIdentityKeys[] = $processKey;
            $tenantIdentityKeys[] = $tenantKey;
            $processes[] = [
                'processId' => $processId,
                'tenantId' => $tenantId,
                'historyVariableId' => (string)$variable['ID_'],
                'runtimeVariableId' => null,
                'activeProcess' => false,
            ];
        }
        if (count($historyVariables) !== count($processes)) {
            throw new RuntimeException('history tenantId variables include an unknown process');
        }

        $activeProcessIds = [];
        $blankAssigneeCount = 0;
        foreach ($pdo->query(
            "SELECT ID_, PROC_INST_ID_ AS PROC_INST_ID_RAW, TRIM(PROC_INST_ID_) AS PROC_INST_ID_, "
            . "HEX(NULLIF(TRIM(PROC_INST_ID_), '')) AS PROC_INST_ID_HEX, "
            . "ASSIGNEE_ AS ASSIGNEE_RAW, TRIM(ASSIGNEE_) AS ASSIGNEE_, "
            . "HEX(NULLIF(TRIM(ASSIGNEE_), '')) AS ASSIGNEE_HEX "
            . "FROM {$db}.act_ru_task ORDER BY ID_"
        )->fetchAll() as $row) {
            self::assertPhpAndMysqlTrimAgree(
                $row['PROC_INST_ID_RAW'] ?? null,
                $row['PROC_INST_ID_'] ?? null,
                'active workflow process id'
            );
            self::assertPhpAndMysqlTrimAgree(
                $row['ASSIGNEE_RAW'] ?? null,
                $row['ASSIGNEE_'] ?? null,
                'active workflow assignee id'
            );
            if (($row['PROC_INST_ID_HEX'] ?? null) === null) {
                continue;
            }
            $processKey = self::mysqlHexIdentityKey(
                (string)$row['PROC_INST_ID_HEX'],
                'active workflow process id'
            );
            if (!isset($processIndexes[$processKey])) {
                continue;
            }
            $activeProcessIds[$processKey] = true;
            $tenantKey = $tenantIdentityKeys[$processIndexes[$processKey]];
            if (($row['ASSIGNEE_HEX'] ?? null) === null) {
                ++$blankAssigneeCount;
            } else {
                $assigneeKey = self::mysqlHexIdentityKey(
                    (string)$row['ASSIGNEE_HEX'],
                    'active workflow assignee id'
                );
                if (($activeUsers[$assigneeKey] ?? null) !== $tenantKey) {
                    throw new RuntimeException('active workflow assignee does not belong to the inferred tenant');
                }
            }
        }
        if (count($activeProcessIds) !== $expectedActiveProcesses) {
            throw new RuntimeException('active workflow process count differs from the audited tenant repair baseline');
        }
        if ($blankAssigneeCount !== $expectedBlankAssignees) {
            throw new RuntimeException('blank workflow assignee count differs from the audited repair baseline');
        }

        $runtimeVariables = [];
        foreach ($pdo->query(
            "SELECT ID_, PROC_INST_ID_ AS PROC_INST_ID_RAW, TRIM(PROC_INST_ID_) AS PROC_INST_ID_, "
            . "HEX(NULLIF(TRIM(PROC_INST_ID_), '')) AS PROC_INST_ID_HEX, "
            . "TYPE_ AS TYPE_RAW, TRIM(TYPE_) AS TYPE_, "
            . "TEXT_ AS TEXT_RAW, TRIM(TEXT_) AS TEXT_, "
            . "HEX(NULLIF(TRIM(TEXT_), '')) AS TEXT_HEX FROM {$db}.act_ru_variable "
            . "WHERE NAME_ = 'tenantId' ORDER BY PROC_INST_ID_, ID_"
        )->fetchAll() as $row) {
            self::assertPhpAndMysqlTrimAgree(
                $row['PROC_INST_ID_RAW'] ?? null,
                $row['PROC_INST_ID_'] ?? null,
                'runtime tenant variable process id'
            );
            self::assertPhpAndMysqlTrimAgree(
                $row['TYPE_RAW'] ?? null,
                $row['TYPE_'] ?? null,
                'runtime tenant variable type'
            );
            self::assertPhpAndMysqlTrimAgree(
                $row['TEXT_RAW'] ?? null,
                $row['TEXT_'] ?? null,
                'runtime tenant variable tenant id'
            );
            if (($row['PROC_INST_ID_HEX'] ?? null) === null) {
                throw new RuntimeException('runtime tenantId variable belongs to a process outside the active repair scope');
            }
            $processKey = self::mysqlHexIdentityKey(
                (string)$row['PROC_INST_ID_HEX'],
                'runtime tenant variable process id'
            );
            if (!isset($activeProcessIds[$processKey])) {
                throw new RuntimeException('runtime tenantId variable belongs to a process outside the active repair scope');
            }
            $runtimeVariables[$processKey][] = $row;
        }
        foreach (array_keys($activeProcessIds) as $processKey) {
            $variables = $runtimeVariables[$processKey] ?? [];
            if (count($variables) !== 1) {
                throw new RuntimeException('workflow tenant repair requires exactly one runtime tenantId variable');
            }
            $variable = $variables[0];
            if (($variable['TEXT_HEX'] ?? null) === null) {
                throw new RuntimeException('runtime and history tenantId evidence differs');
            }
            $tenantKey = self::mysqlHexIdentityKey(
                (string)$variable['TEXT_HEX'],
                'runtime tenant variable tenant id'
            );
            $index = $processIndexes[$processKey];
            if (strtolower((string)($variable['TYPE_'] ?? '')) !== 'string'
                || $tenantKey !== $tenantIdentityKeys[$index]
            ) {
                throw new RuntimeException('runtime and history tenantId evidence differs');
            }
            $processes[$index]['runtimeVariableId'] = (string)$variable['ID_'];
            $processes[$index]['activeProcess'] = true;
        }
        if (count($runtimeVariables) !== $expectedActiveProcesses) {
            throw new RuntimeException('runtime tenantId variable coverage differs from the active process baseline');
        }

        $ignoredUnmappedProcessIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string)$value, ' '), $ignoredUnmappedProcessIds),
            static fn (string $value): bool => $value !== ''
        )));
        sort($ignoredUnmappedProcessIds);
        foreach ($tableIgnoredUnmappedProcessIds as $table => $ids) {
            if (!array_key_exists($table, self::TABLE_PROCESS_COLUMNS) || !is_array($ids)) {
                throw new RuntimeException('workflow tenant repair table-specific ignore scope is invalid');
            }
            $ids = array_values(array_unique(array_filter(
                array_map(static fn (mixed $value): string => trim((string)$value, ' '), $ids),
                static fn (string $value): bool => $value !== ''
            )));
            sort($ids);
            $tableIgnoredUnmappedProcessIds[$table] = $ids;
        }

        $frozenTenantByProcess = [];
        foreach ($processes as $index => $_process) {
            $processKey = (string)($processIdentityKeys[$index] ?? '');
            $tenantKey = (string)($tenantIdentityKeys[$index] ?? '');
            if ($processKey === '' || $tenantKey === '' || isset($frozenTenantByProcess[$processKey])) {
                throw new RuntimeException('workflow tenant repair process mapping is not byte-unique');
            }
            $frozenTenantByProcess[$processKey] = $tenantKey;
        }

        $tables = [];
        foreach (self::TABLE_PROCESS_COLUMNS as $table => $columns) {
            $tableIgnored = array_values(array_unique(array_merge(
                $ignoredUnmappedProcessIds,
                $tableIgnoredUnmappedProcessIds[$table] ?? []
            )));
            sort($tableIgnored);
            $tables[$table] = self::tableAuditAgainstFrozenMapping(
                $pdo,
                $database,
                $table,
                $columns,
                $frozenTenantByProcess,
                $tableIgnored
            );
            if ($tables[$table]['unmappedRows'] !== 0) {
                throw new RuntimeException("workflow tenant mapping has unmapped process references in {$table}");
            }
            if ($tables[$table]['referenceConflictRows'] !== 0) {
                throw new RuntimeException("workflow tenant mapping has conflicting process references in {$table}");
            }
            if ($tables[$table]['conflictingRows'] !== 0) {
                throw new RuntimeException("workflow tenant evidence conflicts with {$table}");
            }
        }

        return [
            'historyProcessCount' => count($processes),
            'activeProcessCount' => count($activeProcessIds),
            'blankAssigneeCount' => $blankAssigneeCount,
            'processes' => $processes,
            'tables' => $tables,
        ];
    }

    /** @return array<string, string> */
    public static function tenantByProcess(array $audit): array
    {
        $tenants = [];
        foreach (($audit['processes'] ?? []) as $process) {
            if (!is_array($process)) {
                throw new RuntimeException('workflow tenant repair plan is malformed');
            }
            $processId = trim((string)($process['processId'] ?? ''));
            $tenantId = trim((string)($process['tenantId'] ?? ''));
            if ($processId === '' || $tenantId === '' || isset($tenants[$processId])) {
                throw new RuntimeException('workflow tenant repair plan has invalid process mapping');
            }
            $tenants[$processId] = $tenantId;
        }

        return $tenants;
    }

    /** @return array<string, mixed> */
    public static function apply(
        PDO $pdo,
        string $database,
        string $quarantineDatabase,
        string $runId,
        int $expectedHistoryProcesses,
        int $expectedActiveProcesses,
        int $expectedBlankAssignees,
        array $expectedPlan
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $quarantine = DatabaseManifest::quoteIdentifier($quarantineDatabase);
        $auditTable = "{$quarantine}.migration_workflow_tenant_repair_audit";
        $updates = [];
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();
        try {
            $currentPlan = self::audit(
                $pdo,
                $database,
                $expectedHistoryProcesses,
                $expectedActiveProcesses,
                $expectedBlankAssignees
            );
            if ($currentPlan !== $expectedPlan) {
                throw new RuntimeException('workflow tenant repair plan changed before the locked apply');
            }
            $insert = $pdo->prepare(
                "INSERT INTO {$auditTable} "
                . '(RUN_ID, PROCESS_ID, TENANT_ID, HISTORY_VARIABLE_ID, RUNTIME_VARIABLE_ID, ACTIVE_PROCESS, REPAIRED_AT) '
                . 'VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );
            foreach ($expectedPlan['processes'] as $process) {
                $insert->execute([
                    $runId,
                    $process['processId'],
                    $process['tenantId'],
                    $process['historyVariableId'],
                    $process['runtimeVariableId'],
                    !empty($process['activeProcess']) ? 1 : 0,
                ]);
            }

            foreach (self::TABLE_PROCESS_COLUMNS as $table => $columns) {
                $tableName = DatabaseManifest::quoteIdentifier($table);
                $processExpression = self::processExpression('t', $columns);
                $statement = $pdo->prepare(
                    "UPDATE {$db}.{$tableName} t INNER JOIN {$auditTable} a "
                    . "ON a.RUN_ID = ? AND BINARY a.PROCESS_ID = BINARY {$processExpression} "
                    . 'SET t.TENANT_ID_ = a.TENANT_ID '
                    . "WHERE t.TENANT_ID_ IS NULL OR TRIM(t.TENANT_ID_) = ''"
                );
                $statement->execute([$runId]);
                $updated = $statement->rowCount();
                $expected = (int)($expectedPlan['tables'][$table]['blankRows'] ?? -1);
                if ($updated !== $expected) {
                    throw new RuntimeException("workflow tenant repair update count differs for {$table}");
                }
                $updates[$table] = $updated;
            }

            $auditCountStatement = $pdo->prepare("SELECT COUNT(*) FROM {$auditTable} WHERE RUN_ID = ?");
            $auditCountStatement->execute([$runId]);
            if ((int)$auditCountStatement->fetchColumn() !== count($expectedPlan['processes'])) {
                throw new RuntimeException('workflow tenant repair audit row count differs from the frozen plan');
            }

            foreach (self::TABLE_PROCESS_COLUMNS as $table => $columns) {
                self::assertRunProcessReferences(
                    $pdo,
                    $database,
                    $quarantineDatabase,
                    $runId,
                    $table,
                    $columns
                );
                $validation = self::tableAuditForRun(
                    $pdo,
                    $database,
                    $quarantineDatabase,
                    $runId,
                    $table,
                    $columns
                );
                $expectedTable = $expectedPlan['tables'][$table] ?? null;
                if (!is_array($expectedTable)
                    || $validation['totalRows'] !== (int)($expectedTable['totalRows'] ?? -1)
                    || $validation['matchingRows'] !== (int)($expectedTable['totalRows'] ?? -1)
                    || $validation['blankRows'] !== 0
                    || $validation['conflictingRows'] !== 0
                ) {
                    throw new RuntimeException("workflow tenant repair validation failed for {$table}");
                }
            }
            $finalLockedAudit = self::audit(
                $pdo,
                $database,
                $expectedHistoryProcesses,
                $expectedActiveProcesses,
                $expectedBlankAssignees
            );
            self::assertApplied($finalLockedAudit, $expectedPlan);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'historyProcessCount' => $expectedPlan['historyProcessCount'],
            'activeProcessCount' => $expectedPlan['activeProcessCount'],
            'auditRowCount' => count($expectedPlan['processes']),
            'tableUpdates' => $updates,
            'rowsUpdated' => array_sum($updates),
        ];
    }

    public static function assertApplied(array $audit, array $expectedPlan): void
    {
        if ((int)($audit['historyProcessCount'] ?? -1) !== (int)($expectedPlan['historyProcessCount'] ?? -2)
            || (int)($audit['activeProcessCount'] ?? -1) !== (int)($expectedPlan['activeProcessCount'] ?? -2)
            || ($audit['processes'] ?? null) !== ($expectedPlan['processes'] ?? null)
        ) {
            throw new RuntimeException('workflow tenant repair final gate failed for the frozen process mapping');
        }
        foreach (self::TABLE_PROCESS_COLUMNS as $table => $_columns) {
            $tableAudit = $audit['tables'][$table] ?? null;
            $expectedTable = $expectedPlan['tables'][$table] ?? null;
            if (!is_array($tableAudit)
                || !is_array($expectedTable)
                || (int)($tableAudit['totalRows'] ?? -1) !== (int)($expectedTable['totalRows'] ?? -2)
                || (int)($tableAudit['matchingRows'] ?? -1) !== (int)($expectedTable['totalRows'] ?? -2)
                || (int)($tableAudit['blankRows'] ?? -1) !== 0
                || (int)($tableAudit['conflictingRows'] ?? -1) !== 0
                || (int)($tableAudit['unmappedRows'] ?? -1) !== 0
                || (int)($tableAudit['referenceConflictRows'] ?? -1) !== 0
            ) {
                throw new RuntimeException("workflow tenant repair final gate failed for {$table}");
            }
        }
    }

    /**
     * @param list<string> $columns
     * @param array<string, string> $tenantByProcess
     * @param list<string> $ignoredUnmappedProcessIds
     * @return array<string, int>
     */
    private static function tableAuditAgainstFrozenMapping(
        PDO $pdo,
        string $database,
        string $table,
        array $columns,
        array $tenantByProcess,
        array $ignoredUnmappedProcessIds = []
    ): array
    {
        $db = DatabaseManifest::quoteIdentifier($database);
        $tableName = DatabaseManifest::quoteIdentifier($table);
        $mapping = $tenantByProcess;
        $ignored = [];
        foreach ($ignoredUnmappedProcessIds as $processId) {
            $ignored[self::phpBinaryIdentityKey($processId)] = true;
        }

        $selects = [
            'HEX(t.TENANT_ID_) AS tenantRawHex',
            "HEX(NULLIF(TRIM(t.TENANT_ID_), '')) AS tenantTrimmedHex",
        ];
        foreach ($columns as $index => $column) {
            $quotedColumn = DatabaseManifest::quoteIdentifier($column);
            $selects[] = "HEX(NULLIF(TRIM(t.{$quotedColumn}), '')) AS processRef{$index}Hex";
        }
        $statement = $pdo->query(
            'SELECT ' . implode(', ', $selects) . " FROM {$db}.{$tableName} t"
        );
        $audit = [
            'totalRows' => 0,
            'blankRows' => 0,
            'matchingRows' => 0,
            'conflictingRows' => 0,
            'unmappedRows' => 0,
            'referenceConflictRows' => 0,
        ];
        while (($row = $statement->fetch()) !== false) {
            $referenceKeys = [];
            $mappedTenantKeys = [];
            $rowIgnored = false;
            $rowUnmapped = false;
            foreach ($columns as $index => $_column) {
                $hex = $row["processRef{$index}Hex"] ?? null;
                if ($hex === null) {
                    continue;
                }
                $referenceKey = self::mysqlHexIdentityKey((string)$hex, 'workflow process reference');
                $referenceKeys[] = $referenceKey;
                if (isset($mapping[$referenceKey])) {
                    $mappedTenantKeys[$mapping[$referenceKey]] = true;
                } elseif (!isset($ignored[$referenceKey])) {
                    $rowUnmapped = true;
                }
                if (isset($ignored[$referenceKey])) {
                    $rowIgnored = true;
                }
            }
            if ($rowUnmapped) {
                ++$audit['unmappedRows'];
            }
            if (count($mappedTenantKeys) > 1) {
                ++$audit['referenceConflictRows'];
            }
            if ($rowIgnored || $referenceKeys === []) {
                continue;
            }
            $processKey = $referenceKeys[0];
            if (!isset($mapping[$processKey])) {
                continue;
            }
            ++$audit['totalRows'];
            if (($row['tenantTrimmedHex'] ?? null) === null) {
                ++$audit['blankRows'];
                continue;
            }
            $tenantKey = self::mysqlHexIdentityKey(
                (string)($row['tenantRawHex'] ?? ''),
                'workflow row tenant id'
            );
            if ($tenantKey === $mapping[$processKey]) {
                ++$audit['matchingRows'];
            } else {
                ++$audit['conflictingRows'];
            }
        }

        return $audit;
    }

    private static function phpBinaryIdentityKey(string $value): string
    {
        $mysqlTrimmed = trim($value, ' ');
        if (trim($value) !== $mysqlTrimmed) {
            throw new RuntimeException('workflow binary identity contains unsupported control whitespace');
        }
        if ($mysqlTrimmed === '') {
            throw new RuntimeException('workflow binary identity must not be empty');
        }

        return 'hex:' . strtoupper(bin2hex($mysqlTrimmed));
    }

    private static function assertPhpAndMysqlTrimAgree(mixed $raw, mixed $mysqlTrimmed, string $label): void
    {
        if ($raw === null || $mysqlTrimmed === null) {
            if ($raw === null && $mysqlTrimmed === null) {
                return;
            }
            throw new RuntimeException($label . ' has inconsistent trim semantics');
        }
        if (trim((string)$raw) !== (string)$mysqlTrimmed) {
            throw new RuntimeException($label . ' contains unsupported control whitespace');
        }
    }

    private static function mysqlHexIdentityKey(string $value, string $label): string
    {
        if ($value === '' || strlen($value) % 2 !== 0 || preg_match('/\A[0-9A-F]+\z/D', $value) !== 1) {
            throw new RuntimeException($label . ' did not produce a non-empty canonical hexadecimal identity');
        }

        return 'hex:' . $value;
    }

    /** @param list<string> $columns @return array<string, int> */
    private static function tableAuditForRun(
        PDO $pdo,
        string $database,
        string $quarantineDatabase,
        string $runId,
        string $table,
        array $columns
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $quarantine = DatabaseManifest::quoteIdentifier($quarantineDatabase);
        $tableName = DatabaseManifest::quoteIdentifier($table);
        $processExpression = self::processExpression('t', $columns);
        $statement = $pdo->prepare(
            "SELECT COUNT(*) AS totalRows, "
            . "COALESCE(SUM(CASE WHEN t.TENANT_ID_ IS NULL OR TRIM(t.TENANT_ID_) = '' THEN 1 ELSE 0 END), 0) AS blankRows, "
            . "COALESCE(SUM(CASE WHEN BINARY t.TENANT_ID_ = BINARY a.TENANT_ID THEN 1 ELSE 0 END), 0) AS matchingRows, "
            . "COALESCE(SUM(CASE WHEN t.TENANT_ID_ IS NOT NULL AND TRIM(t.TENANT_ID_) <> '' "
            . "AND BINARY t.TENANT_ID_ <> BINARY a.TENANT_ID THEN 1 ELSE 0 END), 0) AS conflictingRows "
            . "FROM {$db}.{$tableName} t INNER JOIN {$quarantine}.migration_workflow_tenant_repair_audit a "
            . "ON a.RUN_ID = ? AND BINARY a.PROCESS_ID = BINARY {$processExpression}"
        );
        $statement->execute([$runId]);

        return self::integerAuditRow($statement->fetch()) + [
            'unmappedRows' => 0,
            'referenceConflictRows' => 0,
        ];
    }

    /** @return array<string, int> */
    private static function integerAuditRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new RuntimeException('workflow tenant table audit did not return one row');
        }

        return [
            'totalRows' => (int)($row['totalRows'] ?? 0),
            'blankRows' => (int)($row['blankRows'] ?? 0),
            'matchingRows' => (int)($row['matchingRows'] ?? 0),
            'conflictingRows' => (int)($row['conflictingRows'] ?? 0),
        ];
    }

    /** @param list<string> $columns */
    private static function processExpression(string $alias, array $columns): string
    {
        $quoted = array_map(
            static fn (string $column): string => "NULLIF(TRIM({$alias}." . DatabaseManifest::quoteIdentifier($column) . "), '')",
            $columns
        );

        return count($quoted) === 1 ? $quoted[0] : 'COALESCE(' . implode(', ', $quoted) . ')';
    }

    private static function processReference(string $alias, string $column): string
    {
        return "NULLIF(TRIM({$alias}." . DatabaseManifest::quoteIdentifier($column) . "), '')";
    }

    /** @param list<string> $columns */
    private static function assertRunProcessReferences(
        PDO $pdo,
        string $database,
        string $quarantineDatabase,
        string $runId,
        string $table,
        array $columns
    ): void {
        $db = DatabaseManifest::quoteIdentifier($database);
        $quarantine = DatabaseManifest::quoteIdentifier($quarantineDatabase);
        $tableName = DatabaseManifest::quoteIdentifier($table);
        $auditTable = "{$quarantine}.migration_workflow_tenant_repair_audit";
        foreach ($columns as $index => $column) {
            $reference = self::processReference('t', $column);
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM {$db}.{$tableName} t LEFT JOIN {$auditTable} a{$index} "
                . "ON a{$index}.RUN_ID = ? AND BINARY a{$index}.PROCESS_ID = BINARY {$reference} "
                . "WHERE {$reference} IS NOT NULL AND a{$index}.PROCESS_ID IS NULL"
            );
            $statement->execute([$runId]);
            if ((int)$statement->fetchColumn() !== 0) {
                throw new RuntimeException("workflow tenant apply has unmapped process references in {$table}");
            }
        }

        for ($left = 0; $left < count($columns); ++$left) {
            for ($right = $left + 1; $right < count($columns); ++$right) {
                $leftReference = self::processReference('t', $columns[$left]);
                $rightReference = self::processReference('t', $columns[$right]);
                $statement = $pdo->prepare(
                    "SELECT COUNT(*) FROM {$db}.{$tableName} t "
                    . "INNER JOIN {$auditTable} al ON al.RUN_ID = ? "
                    . "AND BINARY al.PROCESS_ID = BINARY {$leftReference} "
                    . "INNER JOIN {$auditTable} ar ON ar.RUN_ID = ? "
                    . "AND BINARY ar.PROCESS_ID = BINARY {$rightReference} "
                    . 'WHERE BINARY al.TENANT_ID <> BINARY ar.TENANT_ID'
                );
                $statement->execute([$runId, $runId]);
                if ((int)$statement->fetchColumn() !== 0) {
                    throw new RuntimeException("workflow tenant apply has conflicting process references in {$table}");
                }
            }
        }
    }

}

final class AssigneeRepair
{
    /** @return array<string, mixed> */
    public static function audit(
        PDO $pdo,
        string $database,
        int $expectedRepairs,
        array $tenantByProcess,
        bool $forUpdate = false,
        bool $requirePersistedTenant = false
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $blankTasks = $pdo->query(
            "SELECT t.ID_ AS taskId, t.PROC_INST_ID_ AS processId, t.TENANT_ID_ AS tenantId, "
            . "t.TASK_DEF_KEY_ AS taskKey, d.KEY_ AS processKey "
            . "FROM {$db}.act_ru_task t LEFT JOIN {$db}.act_re_procdef d "
            . "ON BINARY d.ID_ = BINARY t.PROC_DEF_ID_ "
            . "WHERE t.ASSIGNEE_ IS NULL OR TRIM(t.ASSIGNEE_) = '' ORDER BY t.ID_{$lock}"
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
            $processId = trim((string)($task['processId'] ?? ''));
            $taskId = trim((string)($task['taskId'] ?? ''));
            $taskTenantId = trim((string)($task['tenantId'] ?? ''));
            $plannedTenantId = trim((string)($tenantByProcess[$processId] ?? ''));
            if ($taskId === '' || $processId === '' || $plannedTenantId === '') {
                throw new RuntimeException('blank-assignee repair task is outside the frozen workflow tenant plan');
            }
            if (($requirePersistedTenant && $taskTenantId !== $plannedTenantId)
                || (!$requirePersistedTenant && $taskTenantId !== '' && $taskTenantId !== $plannedTenantId)
            ) {
                throw new RuntimeException('blank-assignee task tenant conflicts with the workflow tenant plan');
            }
            $tenantId = $plannedTenantId;
            $variableStatement = $pdo->prepare(
                "SELECT ID_, TEXT_, TYPE_, TENANT_ID_ FROM {$db}.act_ru_variable "
                . "WHERE BINARY PROC_INST_ID_ = BINARY ? AND BINARY NAME_ = BINARY 'user' "
                . "ORDER BY ID_{$lock}"
            );
            $variableStatement->execute([$processId]);
            $variables = $variableStatement->fetchAll();
            if (count($variables) !== 1) {
                throw new RuntimeException('blank-assignee repair requires exactly one user variable row');
            }
            $variable = $variables[0];
            $userId = trim((string)($variable['TEXT_'] ?? ''));
            $variableTenantId = trim((string)($variable['TENANT_ID_'] ?? ''));
            if ($userId === '' || strtolower(trim((string)$variable['TYPE_'])) !== 'string') {
                throw new RuntimeException('blank-assignee repair requires one non-empty string user variable row');
            }
            if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $userId)) {
                throw new RuntimeException('blank-assignee repair user id has an unsupported format');
            }
            if (($requirePersistedTenant && $variableTenantId !== $tenantId)
                || (!$requirePersistedTenant && $variableTenantId !== '' && $variableTenantId !== $tenantId)
            ) {
                throw new RuntimeException('blank-assignee user variable tenant conflicts with the workflow tenant plan');
            }
            $userStatement = $pdo->prepare(
                "SELECT ID, TENANT_ID FROM {$db}.sys_user WHERE BINARY ID = BINARY ? "
                . "AND (DELETE_FLAG IS NULL OR DELETE_FLAG = 'NOT_DELETE'){$lock}"
            );
            $userStatement->execute([$userId]);
            $userRows = $userStatement->fetchAll();
            if (count($userRows) !== 1 || trim((string)($userRows[0]['TENANT_ID'] ?? '')) !== $tenantId) {
                throw new RuntimeException('blank-assignee repair user does not exist uniquely in the audited tenant');
            }
            $historyStatement = $pdo->prepare(
                "SELECT ID_, PROC_INST_ID_, TENANT_ID_, ASSIGNEE_ FROM {$db}.act_hi_taskinst "
                . "WHERE BINARY ID_ = BINARY ?{$lock}"
            );
            $historyStatement->execute([$taskId]);
            $historyRows = $historyStatement->fetchAll();
            if (count($historyRows) !== 1) {
                throw new RuntimeException('blank-assignee repair requires one matching history task row');
            }
            $historyProcessId = trim((string)($historyRows[0]['PROC_INST_ID_'] ?? ''));
            $historyTenantId = trim((string)($historyRows[0]['TENANT_ID_'] ?? ''));
            $historyAssigneeId = trim((string)($historyRows[0]['ASSIGNEE_'] ?? ''));
            if ($historyProcessId !== $processId || $historyAssigneeId !== '') {
                throw new RuntimeException('blank-assignee history task does not match the frozen runtime task');
            }
            if (($requirePersistedTenant && $historyTenantId !== $tenantId)
                || (!$requirePersistedTenant && $historyTenantId !== '' && $historyTenantId !== $tenantId)
            ) {
                throw new RuntimeException('blank-assignee history task tenant conflicts with the workflow tenant plan');
            }
            $repairs[] = [
                'taskId' => $taskId,
                'processId' => $processId,
                'tenantId' => $tenantId,
                'userId' => $userId,
                'variableId' => (string)$variable['ID_'],
            ];
        }

        return [
            'repairCount' => count($repairs),
            'tasks' => $repairs,
        ];
    }

    /** @return array<string, mixed> */
    public static function apply(
        PDO $pdo,
        string $database,
        string $quarantineDatabase,
        string $runId,
        int $expectedRepairs,
        array $tenantByProcess,
        array $expectedPlan
    ): array {
        $db = DatabaseManifest::quoteIdentifier($database);
        $quarantine = DatabaseManifest::quoteIdentifier($quarantineDatabase);
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();
        try {
            $audit = self::audit(
                $pdo,
                $database,
                $expectedRepairs,
                $tenantByProcess,
                true,
                true
            );
            if ($audit !== $expectedPlan) {
                throw new RuntimeException('assignee repair plan changed before the locked apply');
            }
            $repairs = $audit['tasks'];
            $runtimeUpdate = $pdo->prepare(
                "UPDATE {$db}.act_ru_task SET ASSIGNEE_ = ? "
                . "WHERE BINARY ID_ = BINARY ? AND BINARY PROC_INST_ID_ = BINARY ? "
                . "AND BINARY TENANT_ID_ = BINARY ? "
                . "AND (ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = '')"
            );
            $historyUpdate = $pdo->prepare(
                "UPDATE {$db}.act_hi_taskinst SET ASSIGNEE_ = ? "
                . "WHERE BINARY ID_ = BINARY ? AND BINARY PROC_INST_ID_ = BINARY ? "
                . "AND BINARY TENANT_ID_ = BINARY ? "
                . "AND (ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = '')"
            );
            $auditInsert = $pdo->prepare(
                "INSERT INTO {$quarantine}.migration_assignee_repair_audit "
                . '(RUN_ID, TASK_ID, PROCESS_ID, TENANT_ID, VARIABLE_ID, ORIGINAL_ASSIGNEE, REPAIRED_ASSIGNEE, REPAIRED_AT) '
                . 'VALUES (?, ?, ?, ?, ?, NULL, ?, UTC_TIMESTAMP())'
            );
            foreach ($repairs as $repair) {
                $updateParameters = [
                    $repair['userId'],
                    $repair['taskId'],
                    $repair['processId'],
                    $repair['tenantId'],
                ];
                $runtimeUpdate->execute($updateParameters);
                $historyUpdate->execute($updateParameters);
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
                if ($auditInsert->rowCount() !== 1) {
                    throw new RuntimeException('blank-assignee repair audit insert count was not exactly one');
                }
            }

            $runtimeVerify = $pdo->prepare(
                "SELECT ID_ FROM {$db}.act_ru_task WHERE BINARY ID_ = BINARY ? "
                . 'AND BINARY PROC_INST_ID_ = BINARY ? '
                . 'AND BINARY TENANT_ID_ = BINARY ? AND BINARY ASSIGNEE_ = BINARY ? FOR UPDATE'
            );
            $historyVerify = $pdo->prepare(
                "SELECT ID_ FROM {$db}.act_hi_taskinst WHERE BINARY ID_ = BINARY ? "
                . 'AND BINARY PROC_INST_ID_ = BINARY ? AND BINARY TENANT_ID_ = BINARY ? '
                . 'AND BINARY ASSIGNEE_ = BINARY ? FOR UPDATE'
            );
            foreach ($repairs as $repair) {
                $verifyParameters = [
                    $repair['taskId'],
                    $repair['processId'],
                    $repair['tenantId'],
                    $repair['userId'],
                ];
                $runtimeVerify->execute($verifyParameters);
                $historyVerify->execute($verifyParameters);
                if (count($runtimeVerify->fetchAll()) !== 1 || count($historyVerify->fetchAll()) !== 1) {
                    throw new RuntimeException('blank-assignee repair result differs from the frozen plan');
                }
            }

            $remainingRows = $pdo->query(
                "SELECT ID_ FROM {$db}.act_ru_task "
                . "WHERE ASSIGNEE_ IS NULL OR TRIM(ASSIGNEE_) = '' FOR UPDATE"
            )->fetchAll();
            if ($remainingRows !== []) {
                throw new RuntimeException('blank workflow assignees remain after the strict repair');
            }
            $auditCount = $pdo->prepare(
                "SELECT TASK_ID FROM {$quarantine}.migration_assignee_repair_audit "
                . 'WHERE BINARY RUN_ID = BINARY ? FOR UPDATE'
            );
            $auditCount->execute([$runId]);
            if (count($auditCount->fetchAll()) !== $expectedRepairs) {
                throw new RuntimeException('blank-assignee repair audit rows differ from the frozen plan');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $audit;
    }
}

final class MigrationRunner
{
    private const AUDITED_SOURCE_TABLES = 121;
    private const AUDITED_TEMPLATE_TABLES = 124;
    private const AUDITED_SOURCE_COLUMNS = 1836;
    private const AUDITED_TEMPLATE_COLUMNS = 1882;
    private const AUDITED_ORPHANS = 20;
    private const AUDITED_HISTORY_PROCESSES = 3901;
    private const AUDITED_ACTIVE_PROCESSES = 43;
    private const AUDITED_ASSIGNEE_REPAIRS = 2;
    private const AUDITED_DETACHED_BYTEARRAY_ROWS = 96340;
    private const AUDITED_DETACHED_BYTEARRAY_ROOTS = 12557;
    private const AUDITED_DETACHED_BYTEARRAY_BYTES = 24678690;
    private const AUDITED_BUSINESS_LINKED_BYTEARRAY_ROWS = 152;
    private const AUDITED_BUSINESS_LINKED_BYTEARRAY_ROOTS = 19;
    private const AUDITED_DETACHED_OPERATION_LOG_ROWS = 1;
    private const AUDITED_DETACHED_OPERATION_LOG_PROCESSES = 1;

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
        $store->writeJson('source-schema-and-rows.json', $sourceManifest);
        $store->writeJson('template-schema.json', $templateManifest);
        $store->writeJson('schema-compatibility.json', $comparison);
        SchemaPolicy::assertExpected(
            $comparison,
            self::AUDITED_SOURCE_TABLES,
            self::AUDITED_TEMPLATE_TABLES,
            self::AUDITED_SOURCE_COLUMNS,
            self::AUDITED_TEMPLATE_COLUMNS
        );
        $this->assertRequiredTemplateFeatures($templateManifest);
        $orphans = OrphanPolicy::detect($sourcePdo, $sourceDatabase);
        $ignoredOrphanProcessIds = array_values(array_unique(array_filter(
            array_map(static fn (array $item): string => trim((string)($item['processId'] ?? ''), ' '), $orphans),
            static fn (string $value): bool => $value !== ''
        )));
        sort($ignoredOrphanProcessIds);
        $orphanEligibility = $orphans === []
            ? [
                'taskCount' => 0,
                'processCount' => 0,
                'linkedRowChecks' => [],
                'rootBytearrayRowsPreservedForQuarantine' => 0,
                'rootBytearrayReferenceChecks' => [],
            ]
            : OrphanPolicy::assertIsolationEligible($sourcePdo, $sourceDatabase, $orphans);
        $detachedBytearrayPlan = DetachedBytearrayPolicy::audit(
            $sourcePdo,
            $sourceDatabase,
            $ignoredOrphanProcessIds
        );
        DetachedBytearrayPolicy::assertExpected(
            $detachedBytearrayPlan,
            self::AUDITED_DETACHED_BYTEARRAY_ROWS,
            self::AUDITED_DETACHED_BYTEARRAY_ROOTS,
            self::AUDITED_DETACHED_BYTEARRAY_BYTES,
            self::AUDITED_BUSINESS_LINKED_BYTEARRAY_ROWS,
            self::AUDITED_BUSINESS_LINKED_BYTEARRAY_ROOTS
        );
        $store->writeJson(
            'source-detached-bytearray-plan.json',
            DetachedBytearrayPolicy::summary($detachedBytearrayPlan)
        );
        $detachedOperationLogPlan = DetachedOperationLogPolicy::audit(
            $sourcePdo,
            $sourceDatabase,
            $ignoredOrphanProcessIds
        );
        DetachedOperationLogPolicy::assertExpected(
            $detachedOperationLogPlan,
            self::AUDITED_DETACHED_OPERATION_LOG_ROWS,
            self::AUDITED_DETACHED_OPERATION_LOG_PROCESSES
        );
        $store->writeJson(
            'source-detached-operation-log-plan.json',
            DetachedOperationLogPolicy::summary($detachedOperationLogPlan)
        );
        $pendingVariables = WorkflowVariableGate::pending($sourcePdo, $sourceDatabase);
        $tenantRepairPlan = WorkflowTenantRepair::audit(
            $sourcePdo,
            $sourceDatabase,
            self::AUDITED_HISTORY_PROCESSES,
            self::AUDITED_ACTIVE_PROCESSES,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $ignoredOrphanProcessIds,
            [
                'act_ge_bytearray' => $detachedBytearrayPlan['rootIds'],
                'act_hi_op_log' => $detachedOperationLogPlan['processIds'],
            ]
        );
        $tenantByProcess = WorkflowTenantRepair::tenantByProcess($tenantRepairPlan);
        $store->writeJson(
            'source-workflow-tenant-repair-plan.json',
            $this->workflowTenantPlanSummary($tenantRepairPlan)
        );
        $assigneeRepairPlan = AssigneeRepair::audit(
            $sourcePdo,
            $sourceDatabase,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $tenantByProcess
        );
        $store->writeJson(
            'source-assignee-repair-plan.json',
            $this->assigneePlanSummary($assigneeRepairPlan)
        );
        $migrationCodeBundleSha256 = $this->migrationCodeBundleSha256(
            $options->string('workflow-converter')
        );
        $tableNames = array_keys($sourceManifest['tables']);
        sort($tableNames);
        $sourceChecksumsAtPlan = DatabaseManifest::tableChecksums($sourcePdo, $sourceDatabase, $tableNames);
        $store->writeJson('source-checksums-at-plan.json', $sourceChecksumsAtPlan);
        $planSha256 = hash('sha256', json_encode([
            'sourceSchema' => $sourceManifest['schemaSha256'],
            'templateSchema' => $templateManifest['schemaSha256'],
            'sourceRows' => $sourceManifest['rowCounts'],
            'sourceChecksums' => $sourceChecksumsAtPlan,
            'orphans' => $orphans,
            'detachedBytearrays' => DetachedBytearrayPolicy::summary($detachedBytearrayPlan),
            'detachedOperationLogs' => DetachedOperationLogPolicy::summary($detachedOperationLogPlan),
            'workflowVariables' => $pendingVariables,
            'workflowTenantRepairs' => $tenantRepairPlan,
            'assigneeRepairs' => $assigneeRepairPlan,
            'migrationCodeBundleSha256' => $migrationCodeBundleSha256,
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
        $targetDatabaseAvailable = !$this->databaseExists($targetAdminPdo, $targetDatabase);
        $quarantineDatabaseAvailable = !$this->databaseExists($targetAdminPdo, $quarantineDatabase);
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
            'targetDatabaseAvailable' => $targetDatabaseAvailable,
            'quarantineDatabaseAvailable' => $quarantineDatabaseAvailable,
            'confirmToken' => $confirmToken,
            'planSha256' => $planSha256,
            'requiredFreezeToken' => MigrationSafety::FREEZE_TOKEN,
            'schemaComparison' => $comparison,
            'orphanCount' => count($orphans),
            'orphanIsolationEligibility' => $orphanEligibility,
            'orphanAllowlistValidated' => $allowlistValidated,
            'orphanCandidateManifest' => $candidatePath,
            'detachedBytearrayRows' => $detachedBytearrayPlan['candidateRows'],
            'detachedBytearrayRoots' => $detachedBytearrayPlan['distinctRoots'],
            'businessLinkedDetachedBytearrayRows' => $detachedBytearrayPlan['businessLinkedRows'],
            'businessLinkedDetachedBytearrayRoots' => $detachedBytearrayPlan['businessLinkedRoots'],
            'detachedOperationLogRows' => $detachedOperationLogPlan['candidateRows'],
            'detachedOperationLogProcesses' => $detachedOperationLogPlan['distinctProcesses'],
            'workflowVariablesAwaitingExternalConversion' => count($pendingVariables),
            'workflowConverterConfigured' => $options->string('workflow-converter') !== '',
            'workflowConverterRemoteTarget' => $converterConnection['remote'],
            'workflowConverterRemoteGateSatisfied' => !$converterRemoteBlocked,
            'workflowTenantHistoryProcessCount' => $tenantRepairPlan['historyProcessCount'],
            'workflowTenantActiveProcessCount' => $tenantRepairPlan['activeProcessCount'],
            'assigneeRepairCount' => $assigneeRepairPlan['repairCount'],
        ];
        $store->writeJson('source-workflow-variable-candidates.json', [
            'planSha256' => $planSha256,
            'candidateCount' => count($pendingVariables),
            'candidates' => $pendingVariables,
        ]);
        if ($apply && !hash_equals($confirmToken, $options->string('confirm-token'))) {
            throw new RuntimeException('apply confirmation token does not match the current plan-bound dry-run');
        }
        $preflightManifest = $preflight;
        if ($apply) {
            unset(
                $preflightManifest['confirmToken'],
                $preflightManifest['requiredFreezeToken']
            );
        }
        $store->writeJson('preflight.json', $preflightManifest);

        if (!$apply) {
            return $preflight + [
                'readyForApply' => count($orphans) === self::AUDITED_ORPHANS
                    && $allowlistValidated
                    && $targetDatabaseAvailable
                    && $quarantineDatabaseAvailable
                    && ($pendingVariables === [] || $options->string('workflow-converter') !== '')
                    && !$converterRemoteBlocked
                    && $detachedOperationLogPlan['candidateRows'] === self::AUDITED_DETACHED_OPERATION_LOG_ROWS
                    && $detachedOperationLogPlan['distinctProcesses'] === self::AUDITED_DETACHED_OPERATION_LOG_PROCESSES
                    && $tenantRepairPlan['historyProcessCount'] === self::AUDITED_HISTORY_PROCESSES
                    && $tenantRepairPlan['activeProcessCount'] === self::AUDITED_ACTIVE_PROCESSES
                    && $assigneeRepairPlan['repairCount'] === self::AUDITED_ASSIGNEE_REPAIRS,
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
        ManifestStore::secureDirectory($dumpDirectory);
        $schemaDump = $dumpDirectory . DIRECTORY_SEPARATOR . 'template-schema.sql';
        $dataDump = $dumpDirectory . DIRECTORY_SEPARATOR . 'source-data.sql';
        $mysqldump = $options->string('mysqldump-bin', 'mysqldump');
        $mysql = $options->string('mysql-bin', 'mysql');
        $sourceChecksumsBefore = $sourceChecksumsAtPlan;
        $store->writeJson('source-checksums-before-dump.json', $sourceChecksumsBefore);

        $this->commands->run(DumpPolicy::schemaDumpCommand(
            $mysqldump,
            $target,
            $templateDatabase,
            $schemaDump
        ));
        ManifestStore::secureFile($schemaDump);
        $schemaDumpAudit = DumpPolicy::validateSchemaDump($schemaDump);
        $this->commands->run(DumpPolicy::dataDumpCommand(
            $mysqldump,
            $source,
            $sourceDatabase,
            $dataDump,
            $tableNames
        ));
        ManifestStore::secureFile($dataDump);
        $dataDumpAudit = DumpPolicy::validateDataDump($dataDump, $tableNames);
        $store->writeJson('dump-audit.json', [
            'schema' => $schemaDumpAudit,
            'data' => $dataDumpAudit,
        ]);

        $sourceAfterDump = DatabaseManifest::capture($sourcePdo, $sourceDatabase, true);
        if (($sourceAfterDump['schemaSha256'] ?? '') !== ($sourceManifest['schemaSha256'] ?? '')) {
            throw new RuntimeException('source schema changed during dump; the source is not frozen');
        }
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
        $detachedAfterDump = DetachedBytearrayPolicy::audit(
            $sourcePdo,
            $sourceDatabase,
            $ignoredOrphanProcessIds
        );
        DetachedBytearrayPolicy::assertSamePlan($detachedAfterDump, $detachedBytearrayPlan);
        $detachedOperationLogAfterDump = DetachedOperationLogPolicy::audit(
            $sourcePdo,
            $sourceDatabase,
            $ignoredOrphanProcessIds
        );
        DetachedOperationLogPolicy::assertSamePlan(
            $detachedOperationLogAfterDump,
            $detachedOperationLogPlan
        );

        $templateAfterDump = DatabaseManifest::capture($targetAdminPdo, $templateDatabase, false);
        if (($templateAfterDump['schemaSha256'] ?? '') !== ($templateManifest['schemaSha256'] ?? '')) {
            throw new RuntimeException('PHP template schema changed during dump');
        }
        $this->assertMigrationCodeBundleUnchanged($migrationCodeBundleSha256, $workflowConverter);

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
        $importedOrphanProcessIds = array_values(array_unique(array_filter(
            array_map(static fn (array $item): string => trim((string)($item['processId'] ?? '')), $importedOrphans),
            static fn (string $value): bool => $value !== ''
        )));
        sort($importedOrphanProcessIds);
        $importedDetachedPlan = DetachedBytearrayPolicy::audit(
            $targetPdo,
            $targetDatabase,
            $importedOrphanProcessIds
        );
        DetachedBytearrayPolicy::assertSamePlan($importedDetachedPlan, $detachedBytearrayPlan);
        $store->writeJson(
            'target-detached-bytearray-plan-before-quarantine.json',
            DetachedBytearrayPolicy::summary($importedDetachedPlan)
        );
        $importedDetachedOperationLogPlan = DetachedOperationLogPolicy::audit(
            $targetPdo,
            $targetDatabase,
            $importedOrphanProcessIds
        );
        DetachedOperationLogPolicy::assertSamePlan(
            $importedDetachedOperationLogPlan,
            $detachedOperationLogPlan
        );
        $store->writeJson(
            'target-detached-operation-log-plan-before-quarantine.json',
            DetachedOperationLogPolicy::summary($importedDetachedOperationLogPlan)
        );

        $quarantineManager = new QuarantineManager(
            $targetPdo,
            $targetDatabase,
            $quarantineDatabase,
            $runId
        );
        $quarantineAudit = $quarantineManager->quarantine($importedOrphans);
        $store->writeJson('quarantine-audit.json', $quarantineAudit);
        $postOrphanDetachedOperationLogPlan = DetachedOperationLogPolicy::audit(
            $targetPdo,
            $targetDatabase,
            []
        );
        DetachedOperationLogPolicy::assertSamePlan(
            $postOrphanDetachedOperationLogPlan,
            $detachedOperationLogPlan
        );
        $detachedOperationLogAudit = $quarantineManager->quarantineDetachedOperationLogs(
            $detachedOperationLogPlan
        );
        $quarantineManager->assertDetachedOperationLogQuarantine($detachedOperationLogPlan);
        $store->writeJson(
            'detached-operation-log-quarantine-audit.json',
            $detachedOperationLogAudit
        );
        $postOrphanDetachedPlan = DetachedBytearrayPolicy::audit($targetPdo, $targetDatabase, []);
        DetachedBytearrayPolicy::assertSamePlan($postOrphanDetachedPlan, $detachedBytearrayPlan);
        $detachedBytearrayAudit = $quarantineManager->quarantineDetachedBytearrays($detachedBytearrayPlan);
        $quarantineManager->assertDetachedBytearrayQuarantine($detachedBytearrayPlan);
        $store->writeJson('detached-bytearray-quarantine-audit.json', $detachedBytearrayAudit);

        $targetTenantRepairPlan = WorkflowTenantRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_HISTORY_PROCESSES,
            self::AUDITED_ACTIVE_PROCESSES,
            self::AUDITED_ASSIGNEE_REPAIRS
        );
        if ($targetTenantRepairPlan !== $tenantRepairPlan) {
            throw new RuntimeException('imported workflow tenant repair plan differs from the frozen source');
        }
        $store->writeJson(
            'target-workflow-tenant-repair-plan.json',
            $this->workflowTenantPlanSummary($targetTenantRepairPlan)
        );

        $targetAssigneeRepairPlan = AssigneeRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $tenantByProcess
        );
        if ($targetAssigneeRepairPlan !== $assigneeRepairPlan) {
            throw new RuntimeException('imported assignee repair plan differs from the frozen source');
        }
        $store->writeJson(
            'target-assignee-repair-plan.json',
            $this->assigneePlanSummary($targetAssigneeRepairPlan)
        );

        $targetPendingVariables = WorkflowVariableGate::pending($targetPdo, $targetDatabase);
        $store->writeJson('target-workflow-variable-candidates-before-conversion.json', [
            'candidateCount' => count($targetPendingVariables),
            'candidates' => $targetPendingVariables,
        ]);
        if ($targetPendingVariables !== $pendingVariables) {
            throw new RuntimeException('imported workflow variable/byte-array candidate digest differs from the frozen source');
        }
        if ($targetPendingVariables !== []) {
            $this->assertMigrationCodeBundleUnchanged($migrationCodeBundleSha256, $workflowConverter);
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
            $this->assertMigrationCodeBundleUnchanged($migrationCodeBundleSha256, $workflowConverter);
        }

        if (WorkflowTenantRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_HISTORY_PROCESSES,
            self::AUDITED_ACTIVE_PROCESSES,
            self::AUDITED_ASSIGNEE_REPAIRS
        ) !== $tenantRepairPlan) {
            throw new RuntimeException('workflow tenant repair plan changed during workflow conversion');
        }
        if (AssigneeRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $tenantByProcess
        ) !== $assigneeRepairPlan) {
            throw new RuntimeException('assignee repair plan changed during workflow conversion');
        }

        $tenantRepairAudit = WorkflowTenantRepair::apply(
            $targetPdo,
            $targetDatabase,
            $quarantineDatabase,
            $runId,
            self::AUDITED_HISTORY_PROCESSES,
            self::AUDITED_ACTIVE_PROCESSES,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $tenantRepairPlan
        );
        $store->writeJson('workflow-tenant-repair-audit.json', $tenantRepairAudit);
        $tenantRepairValidation = WorkflowTenantRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_HISTORY_PROCESSES,
            self::AUDITED_ACTIVE_PROCESSES,
            self::AUDITED_ASSIGNEE_REPAIRS
        );
        WorkflowTenantRepair::assertApplied($tenantRepairValidation, $tenantRepairPlan);
        $store->writeJson(
            'workflow-tenant-repair-validation.json',
            $this->workflowTenantPlanSummary($tenantRepairValidation)
        );

        if (AssigneeRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $tenantByProcess
        ) !== $assigneeRepairPlan) {
            throw new RuntimeException('assignee repair plan changed during workflow tenant repair');
        }

        $assigneeAudit = AssigneeRepair::apply(
            $targetPdo,
            $targetDatabase,
            $quarantineDatabase,
            $runId,
            self::AUDITED_ASSIGNEE_REPAIRS,
            $tenantByProcess,
            $assigneeRepairPlan
        );
        $store->writeJson('assignee-repair-audit.json', $this->assigneePlanSummary($assigneeAudit));

        $this->assertMigrationCodeBundleUnchanged($migrationCodeBundleSha256, $workflowConverter);
        $installerAudit = $this->runInstallers(
            $options->string('php-bin', PHP_BINARY),
            $target,
            $targetDatabase
        );
        $store->writeJson('installer-audit.json', $installerAudit);
        $this->assertMigrationCodeBundleUnchanged($migrationCodeBundleSha256, $workflowConverter);
        $quarantineManager->assertDetachedOperationLogQuarantine($detachedOperationLogPlan);
        $quarantineManager->assertDetachedBytearrayQuarantine($detachedBytearrayPlan);
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
        $sourceRowCounts = $sourceManifest['rowCounts'] ?? null;
        if (!is_array($sourceRowCounts)
            || !array_key_exists('act_ge_bytearray', $sourceRowCounts)
            || !array_key_exists('rootBytearrayRowsDeleted', $quarantineAudit)
            || !array_key_exists('rowCount', $detachedBytearrayAudit)
        ) {
            throw new RuntimeException('final act_ge_bytearray row-count inputs are incomplete');
        }
        $sourceBytearrayRows = (int)$sourceRowCounts['act_ge_bytearray'];
        $orphanBytearrayRows = (int)$quarantineAudit['rootBytearrayRowsDeleted'];
        $detachedBytearrayRows = (int)$detachedBytearrayAudit['rowCount'];
        if ($sourceBytearrayRows < 0 || $orphanBytearrayRows < 0 || $detachedBytearrayRows < 0) {
            throw new RuntimeException('final act_ge_bytearray row-count inputs are invalid');
        }
        $expectedFinalBytearrayRows = $sourceBytearrayRows
            - $orphanBytearrayRows
            - $detachedBytearrayRows;
        $actualFinalBytearrayRows = (int)($finalManifest['rowCounts']['act_ge_bytearray'] ?? -2);
        if ($expectedFinalBytearrayRows < 0 || $actualFinalBytearrayRows !== $expectedFinalBytearrayRows) {
            throw new RuntimeException('final act_ge_bytearray row count differs from the frozen isolation plan');
        }
        $orphanOperationLogAudit = $quarantineAudit['tables']['act_hi_op_log'] ?? null;
        if (!array_key_exists('act_hi_op_log', $sourceRowCounts)
            || !is_array($orphanOperationLogAudit)
            || !array_key_exists('rowCount', $orphanOperationLogAudit)
            || !array_key_exists('rowCount', $detachedOperationLogAudit)
        ) {
            throw new RuntimeException('final act_hi_op_log row-count inputs are incomplete');
        }
        $sourceOperationLogRows = (int)$sourceRowCounts['act_hi_op_log'];
        $orphanOperationLogRows = (int)$orphanOperationLogAudit['rowCount'];
        $detachedOperationLogRows = (int)$detachedOperationLogAudit['rowCount'];
        if ($sourceOperationLogRows < 0 || $orphanOperationLogRows < 0 || $detachedOperationLogRows < 0) {
            throw new RuntimeException('final act_hi_op_log row-count inputs are invalid');
        }
        $expectedFinalOperationLogRows = $sourceOperationLogRows
            - $orphanOperationLogRows
            - $detachedOperationLogRows;
        $actualFinalOperationLogRows = (int)($finalManifest['rowCounts']['act_hi_op_log'] ?? -2);
        if ($expectedFinalOperationLogRows < 0
            || $actualFinalOperationLogRows !== $expectedFinalOperationLogRows
        ) {
            throw new RuntimeException('final act_hi_op_log row count differs from the frozen isolation plan');
        }
        $quarantineManager->assertDetachedOperationLogQuarantine($detachedOperationLogPlan);
        $quarantineManager->assertDetachedBytearrayQuarantine($detachedBytearrayPlan);
        $finalTenantAudit = WorkflowTenantRepair::audit(
            $targetPdo,
            $targetDatabase,
            self::AUDITED_HISTORY_PROCESSES,
            self::AUDITED_ACTIVE_PROCESSES,
            0
        );
        WorkflowTenantRepair::assertApplied($finalTenantAudit, $tenantRepairPlan);
        $store->writeJson(
            'target-workflow-tenant-final.json',
            $this->workflowTenantPlanSummary($finalTenantAudit)
        );
        $store->writeJson('target-final.json', $finalManifest);
        $this->assertMigrationCodeBundleUnchanged($migrationCodeBundleSha256, $workflowConverter);
        $this->removeSqlStaging($dumpDirectory, [$schemaDump, $dataDump]);

        $summary = $preflight + [
            'readyForApply' => true,
            'writesPerformed' => true,
            'targetCreatedFromTemplateSchemaOnly' => true,
            'sourceCreateOrDropImported' => false,
            'quarantinedTasks' => $quarantineAudit['taskCount'],
            'quarantinedDetachedOperationLogs' => $detachedOperationLogAudit['rowCount'],
            'quarantinedDetachedBytearrays' => $detachedBytearrayAudit['rowCount'],
            'quarantinedBusinessLinkedBytearrays' => $detachedBytearrayAudit['businessLinkedRows'],
            'workflowTenantHistoryProcesses' => $tenantRepairAudit['historyProcessCount'],
            'workflowTenantActiveProcesses' => $tenantRepairAudit['activeProcessCount'],
            'workflowTenantRowsUpdated' => $tenantRepairAudit['rowsUpdated'],
            'assigneesRepaired' => $assigneeAudit['repairCount'],
            'installerIdempotencyVerified' => true,
            'finalSchemaSha256' => $finalManifest['schemaSha256'],
            'manifestDirectory' => $manifestBase,
        ];
        unset(
            $summary['confirmToken'],
            $summary['requiredFreezeToken'],
            $summary['planSha256']
        );
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
        if ($this->databaseExists($pdo, $database)) {
            throw new RuntimeException("refusing to reuse existing migration database {$database}");
        }
    }

    private function databaseExists(PDO $pdo, string $database): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        $statement->execute([$database]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private function workflowTenantPlanSummary(array $plan): array
    {
        return [
            'historyProcessCount' => (int)($plan['historyProcessCount'] ?? -1),
            'activeProcessCount' => (int)($plan['activeProcessCount'] ?? -1),
            'blankAssigneeCount' => (int)($plan['blankAssigneeCount'] ?? -1),
            'tables' => $plan['tables'] ?? [],
            'processMappingCount' => is_array($plan['processes'] ?? null) ? count($plan['processes']) : -1,
            'planSha256' => $this->arraySha256($plan),
        ];
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private function assigneePlanSummary(array $plan): array
    {
        return [
            'repairCount' => (int)($plan['repairCount'] ?? -1),
            'planSha256' => $this->arraySha256($plan),
        ];
    }

    /** @param array<string, mixed> $value */
    private function arraySha256(array $value): string
    {
        return hash('sha256', json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    /** @param list<string> $files */
    private function removeSqlStaging(string $directory, array $files): void
    {
        foreach ($files as $file) {
            if (!is_file($file) || !unlink($file)) {
                throw new RuntimeException('unable to remove private SQL staging file after verified import');
            }
        }
        $entries = scandir($directory);
        if ($entries === false || array_values(array_diff($entries, ['.', '..'])) !== []) {
            throw new RuntimeException('private SQL staging directory is not empty after cleanup');
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('unable to remove private SQL staging directory after verified import');
        }
    }

    private function safeManifestPath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('migration manifest directory is empty');
        }
        $absolute = preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) === 1
            ? $path
            : $this->projectRoot . DIRECTORY_SEPARATOR . $path;
        $normalizedPath = $this->canonicalBoundaryPath($absolute);
        $normalized = strtolower(rtrim(str_replace('\\', '/', $normalizedPath), '/'));
        $privateRoot = strtolower(rtrim(str_replace('\\', '/', $this->canonicalBoundaryPath(
            $this->projectRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backup'
        )), '/'));
        if ($normalized === $privateRoot || !str_starts_with($normalized, $privateRoot . '/')) {
            throw new RuntimeException('migration manifests and SQL dumps must use a dedicated directory under runtime/backup');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $wrapperPath = getenv('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY');
            if (!is_string($wrapperPath)
                || $wrapperPath === ''
                || strtolower(rtrim(str_replace('\\', '/', $this->canonicalBoundaryPath($wrapperPath)), '/')) !== $normalized
            ) {
                throw new RuntimeException('Windows migration runs must use the ACL-initializing PowerShell wrapper');
            }
        }

        return $normalizedPath;
    }

    private function migrationCodeBundleSha256(string $workflowConverter): string
    {
        $paths = [
            'migration-entry' => $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'migrate-legacy-database.php',
            'migration-library' => __FILE__,
            'migration-wrapper' => $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'run-database-migration-rehearsal.ps1',
            'workflow-converter' => $workflowConverter,
            'workflow-decoder' => $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'JavaSerializationDecoder.php',
            'workflow-exception' => $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'WorkflowVariableMigrationException.php',
            'workflow-pdo-store' => $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'PdoWorkflowVariableMigrationStore.php',
            'workflow-service' => $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'WorkflowVariableMigrationService.php',
            'workflow-store-contract' => $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'WorkflowVariableMigrationStore.php',
            'installer-target' => $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'installer-target.php',
            'installer-travel-days' => $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'install-sale-project-travel-days.php',
            'installer-delivery-plan' => $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'install-sale-project-delivery-plan.php',
            'installer-after-sales' => $this->projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'install-after-sales-module.php',
        ];
        ksort($paths);
        $digests = [];
        foreach ($paths as $label => $path) {
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                throw new RuntimeException("migration code bundle file is unavailable: {$label}");
            }
            $digest = hash_file('sha256', $path);
            if (!is_string($digest) || preg_match('/^[0-9a-f]{64}$/', $digest) !== 1) {
                throw new RuntimeException("migration code bundle digest failed: {$label}");
            }
            $digests[$label] = $digest;
        }

        return hash('sha256', json_encode(
            $digests,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    private function assertMigrationCodeBundleUnchanged(string $expectedSha256, string $workflowConverter): void
    {
        $currentSha256 = $this->migrationCodeBundleSha256($workflowConverter);
        if (!hash_equals($expectedSha256, $currentSha256)) {
            throw new RuntimeException('migration code bundle changed during apply');
        }
    }

    private function canonicalBoundaryPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            $root = strtoupper(substr($path, 0, 2)) . '/';
            $remaining = substr($path, 3);
        } elseif (str_starts_with($path, '/')) {
            $root = '/';
            $remaining = ltrim($path, '/');
        } else {
            throw new RuntimeException('migration path must be absolute after project-root resolution');
        }

        $rootReal = realpath($root);
        $current = str_replace('\\', '/', is_string($rootReal) ? $rootReal : $root);
        $tail = [];
        foreach (explode('/', $remaining) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($tail !== []) {
                    array_pop($tail);
                    continue;
                }
                $parent = str_replace('\\', '/', dirname($current));
                if ($parent !== '.' && $parent !== $current) {
                    $parentReal = realpath($parent);
                    $current = str_replace('\\', '/', is_string($parentReal) ? $parentReal : $parent);
                }
                continue;
            }
            if ($tail === []) {
                $candidate = rtrim($current, '/') . '/' . $segment;
                if (file_exists($candidate) || is_link($candidate)) {
                    $resolved = realpath($candidate);
                    if (!is_string($resolved)) {
                        throw new RuntimeException('migration path contains an unresolved filesystem link');
                    }
                    $current = str_replace('\\', '/', $resolved);
                    continue;
                }
            }
            $tail[] = $segment;
        }

        $resolved = rtrim($current, '/');
        if ($tail !== []) {
            $resolved .= '/' . implode('/', $tail);
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $resolved);
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
