#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\facade\Db;

try {
    require __DIR__ . '/lib/installer-target.php';
    $installerTarget = installer_target_prepare($argv);
    require dirname(__DIR__) . '/vendor/autoload.php';

    $app = new think\App(dirname(__DIR__));
    $app->initialize();
    installer_target_configure($installerTarget);

$apply = in_array('--apply', $argv, true);
$table = 'biz_sale_project_delivery_plan';

$tableExists = static fn (): bool => Db::query(
    "SELECT 1 AS FOUND FROM information_schema.TABLES "
    . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' LIMIT 1"
) !== [];

$columnDefinitions = [
    'ID' => "varchar(20) NOT NULL",
    'PROJECT_ID' => "varchar(20) NOT NULL COMMENT 'Sale project id'",
    'PLAN_NO' => "int NOT NULL DEFAULT 1 COMMENT 'Plan sequence within project'",
    'STATUS' => "varchar(20) NOT NULL DEFAULT 'WAIT_DELIVER' COMMENT 'WAIT_DELIVER or SHIPPED'",
    'CONSIGNEE' => "varchar(255) NOT NULL COMMENT 'Consignee'",
    'UNIT' => "varchar(100) NOT NULL COMMENT 'Receiving unit'",
    'PHONE' => "varchar(255) NOT NULL COMMENT 'Contact phone'",
    'ADDRESS' => "varchar(100) NOT NULL COMMENT 'Receiving address'",
    'FREIGHT_CATEGORY' => "varchar(20) DEFAULT NULL COMMENT 'Freight payment category'",
    'FREIGHT' => "decimal(15,2) DEFAULT NULL COMMENT 'Freight for this plan'",
    'LOGISTICS_CATEGORY' => "varchar(20) NOT NULL DEFAULT '' COMMENT 'Planned logistics category'",
    'REMARK' => "text DEFAULT NULL COMMENT 'Remark'",
    'ITEM_JSON' => "longtext NOT NULL COMMENT 'Planned project item JSON'",
    'INVOICE_ID' => "varchar(20) DEFAULT NULL COMMENT 'Created shipment invoice id'",
    'PROCESS_ID' => "varchar(80) DEFAULT NULL COMMENT 'Shipment process id'",
    'DELETE_FLAG' => "varchar(20) DEFAULT 'NOT_DELETE' COMMENT 'Soft delete flag'",
    'CREATE_TIME' => "datetime DEFAULT NULL COMMENT 'Created time'",
    'CREATE_USER' => "varchar(20) DEFAULT NULL COMMENT 'Created user'",
    'UPDATE_TIME' => "datetime DEFAULT NULL COMMENT 'Updated time'",
    'UPDATE_USER' => "varchar(20) DEFAULT NULL COMMENT 'Updated user'",
    'TENANT_ID' => "varchar(20) NOT NULL DEFAULT '1' COMMENT 'Tenant id'",
    'VERSION' => "int NOT NULL DEFAULT 0 COMMENT 'Optimistic lock version'",
];

$indexDefinitions = [
    'PRIMARY' => 'ADD PRIMARY KEY (`ID`)',
    'idx_sale_delivery_plan_project' => 'ADD INDEX `idx_sale_delivery_plan_project` (`TENANT_ID`,`PROJECT_ID`,`DELETE_FLAG`,`STATUS`,`PLAN_NO`)',
    'uk_sale_delivery_plan_invoice' => 'ADD UNIQUE INDEX `uk_sale_delivery_plan_invoice` (`TENANT_ID`,`INVOICE_ID`)',
    'idx_sale_delivery_plan_process' => 'ADD INDEX `idx_sale_delivery_plan_process` (`TENANT_ID`,`PROCESS_ID`)',
];

$nullableColumnDefinitions = [
    'FREIGHT_CATEGORY' => $columnDefinitions['FREIGHT_CATEGORY'],
    'FREIGHT' => $columnDefinitions['FREIGHT'],
];

$created = false;
$addedColumns = [];
$alteredColumns = [];
$addedIndexes = [];
$collationChanged = false;

if ($apply && !$tableExists()) {
    Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `biz_sale_project_delivery_plan` (
  `ID` varchar(20) NOT NULL,
  `PROJECT_ID` varchar(20) NOT NULL COMMENT 'Sale project id',
  `PLAN_NO` int NOT NULL DEFAULT 1 COMMENT 'Plan sequence within project',
  `STATUS` varchar(20) NOT NULL DEFAULT 'WAIT_DELIVER' COMMENT 'WAIT_DELIVER or SHIPPED',
  `CONSIGNEE` varchar(255) NOT NULL COMMENT 'Consignee',
  `UNIT` varchar(100) NOT NULL COMMENT 'Receiving unit',
  `PHONE` varchar(255) NOT NULL COMMENT 'Contact phone',
  `ADDRESS` varchar(100) NOT NULL COMMENT 'Receiving address',
  `FREIGHT_CATEGORY` varchar(20) DEFAULT NULL COMMENT 'Freight payment category',
  `FREIGHT` decimal(15,2) DEFAULT NULL COMMENT 'Freight for this plan',
  `LOGISTICS_CATEGORY` varchar(20) NOT NULL DEFAULT '' COMMENT 'Planned logistics category',
  `REMARK` text DEFAULT NULL COMMENT 'Remark',
  `ITEM_JSON` longtext NOT NULL COMMENT 'Planned project item JSON',
  `INVOICE_ID` varchar(20) DEFAULT NULL COMMENT 'Created shipment invoice id',
  `PROCESS_ID` varchar(80) DEFAULT NULL COMMENT 'Shipment process id',
  `DELETE_FLAG` varchar(20) DEFAULT 'NOT_DELETE' COMMENT 'Soft delete flag',
  `CREATE_TIME` datetime DEFAULT NULL COMMENT 'Created time',
  `CREATE_USER` varchar(20) DEFAULT NULL COMMENT 'Created user',
  `UPDATE_TIME` datetime DEFAULT NULL COMMENT 'Updated time',
  `UPDATE_USER` varchar(20) DEFAULT NULL COMMENT 'Updated user',
  `TENANT_ID` varchar(20) NOT NULL DEFAULT '1' COMMENT 'Tenant id',
  `VERSION` int NOT NULL DEFAULT 0 COMMENT 'Optimistic lock version',
  PRIMARY KEY (`ID`),
  KEY `idx_sale_delivery_plan_project` (`TENANT_ID`,`PROJECT_ID`,`DELETE_FLAG`,`STATUS`,`PLAN_NO`),
  UNIQUE KEY `uk_sale_delivery_plan_invoice` (`TENANT_ID`,`INVOICE_ID`),
  KEY `idx_sale_delivery_plan_process` (`TENANT_ID`,`PROCESS_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sale project delivery plan'
SQL);
    $created = true;
}

if ($tableExists()) {
    $columns = [];
    foreach (Db::query("SHOW COLUMNS FROM `{$table}`") as $row) {
        $columns[(string)($row['Field'] ?? $row['FIELD'] ?? '')] = $row;
    }
    if ($apply) {
        foreach ($columnDefinitions as $column => $definition) {
            if (isset($columns[$column])) {
                continue;
            }
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            $addedColumns[] = $column;
        }
        foreach ($nullableColumnDefinitions as $column => $definition) {
            $columnRow = $columns[$column] ?? null;
            if (!is_array($columnRow)) {
                continue;
            }
            $nullable = strtoupper((string)($columnRow['Null'] ?? $columnRow['NULL'] ?? '')) === 'YES';
            $default = $columnRow['Default'] ?? $columnRow['DEFAULT'] ?? null;
            if ($nullable && $default === null) {
                continue;
            }
            Db::execute("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
            $alteredColumns[] = $column;
        }
    }

    $indexes = [];
    foreach (Db::query("SHOW INDEX FROM `{$table}`") as $row) {
        $indexes[(string)($row['Key_name'] ?? $row['KEY_NAME'] ?? '')] = true;
    }
    if ($apply) {
        foreach ($indexDefinitions as $index => $definition) {
            if (isset($indexes[$index])) {
                continue;
            }
            Db::execute("ALTER TABLE `{$table}` {$definition}");
            $addedIndexes[] = $index;
        }
    }

    $collationRows = Db::query(
        "SELECT TABLE_COLLATION FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'"
    );
    $collation = (string)($collationRows[0]['TABLE_COLLATION'] ?? '');
    if ($apply && $collation !== '' && $collation !== 'utf8mb4_general_ci') {
        Db::execute("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $collationChanged = true;
    }
}

$finalTableExists = $tableExists();
$finalColumns = [];
$finalIndexes = [];
$finalCollation = null;
if ($finalTableExists) {
    foreach (Db::query("SHOW COLUMNS FROM `{$table}`") as $row) {
        $finalColumns[(string)($row['Field'] ?? $row['FIELD'] ?? '')] = $row;
    }
    foreach (Db::query("SHOW INDEX FROM `{$table}`") as $row) {
        $finalIndexes[(string)($row['Key_name'] ?? $row['KEY_NAME'] ?? '')] = true;
    }
    $rows = Db::query(
        "SELECT TABLE_COLLATION FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'"
    );
    $finalCollation = $rows[0]['TABLE_COLLATION'] ?? null;
}

$nullableColumnMismatches = [];
foreach (array_keys($nullableColumnDefinitions) as $column) {
    $columnRow = $finalColumns[$column] ?? null;
    if (!is_array($columnRow)) {
        $nullableColumnMismatches[] = $column;
        continue;
    }
    $nullable = strtoupper((string)($columnRow['Null'] ?? $columnRow['NULL'] ?? '')) === 'YES';
    $default = $columnRow['Default'] ?? $columnRow['DEFAULT'] ?? null;
    if (!$nullable || $default !== null) {
        $nullableColumnMismatches[] = $column;
    }
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'table' => $table,
    'tableStatus' => $finalTableExists ? ($created ? 'created' : 'exists') : 'pending',
    'addedColumns' => $addedColumns,
    'alteredColumns' => $alteredColumns,
    'addedIndexes' => $addedIndexes,
    'collationChanged' => $collationChanged,
    'collation' => $finalCollation,
    'missingColumns' => array_values(array_diff(array_keys($columnDefinitions), array_keys($finalColumns))),
    'missingIndexes' => array_values(array_diff(array_keys($indexDefinitions), array_keys($finalIndexes))),
    'nullableColumnMismatches' => $nullableColumnMismatches,
];

    fwrite(STDOUT, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);

    $schemaIsValid = $finalTableExists
        && $summary['missingColumns'] === []
        && $summary['missingIndexes'] === []
        && $summary['nullableColumnMismatches'] === []
        && $finalCollation === 'utf8mb4_general_ci';
    if (!$schemaIsValid && ($apply || $finalTableExists)) {
        fwrite(STDERR, "delivery plan schema validation failed\n");
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'delivery plan schema command failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
