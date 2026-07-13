<?php

declare(strict_types=1);

use think\facade\Db;

$appRoot = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : getcwd();
require $appRoot . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

$inspect = in_array('--inspect', $argv, true);
$apply = in_array('--apply', $argv, true);
$backupDir = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backup-dir=')) {
        $backupDir = trim(substr($arg, strlen('--backup-dir=')));
    }
}

if ($apply && ($backupDir === null || $backupDir === '')) {
    fwrite(STDERR, "Refusing --apply without --backup-dir=/absolute/path\n");
    exit(2);
}

$tenantId = '2018244380532912130';
$rootOrgId = '2018244380591632386';
$operatorId = '1543837863788879870';
$marker = 'CODEX_DEMO_20260630';
$tables = [
    'sys_org',
    'sys_user',
    'warehouses',
    'biz_product',
    'inventory',
    'customer',
    'biz_sale_project',
    'settlement_account',
    'biz_collection_receipt',
    'biz_debit_note',
    'biz_payment_record',
    'biz_expenditure_record',
    'biz_payroll',
    'biz_leave_application',
];

function active_count(string $table, string $tenantId): int|string
{
    try {
        $fields = Db::connect()->getFields($table);
        $query = Db::name($table);
        if (isset($fields['TENANT_ID'])) {
            $query->where('TENANT_ID', $tenantId);
        }
        if (isset($fields['DELETE_FLAG'])) {
            $query->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            });
        }

        return $query->count();
    } catch (Throwable $exception) {
        return 'ERR: ' . $exception->getMessage();
    }
}

function sample_rows(string $table, string $tenantId): array
{
    try {
        $fields = Db::connect()->getFields($table);
        $query = Db::name($table);
        if (isset($fields['TENANT_ID'])) {
            $query->where('TENANT_ID', $tenantId);
        }
        if (isset($fields['DELETE_FLAG'])) {
            $query->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            });
        }

        return $query->limit(3)->select()->toArray();
    } catch (Throwable) {
        return [];
    }
}

function table_fields(string $table): array
{
    try {
        return array_keys(Db::connect()->getFields($table));
    } catch (Throwable) {
        return [];
    }
}

function user_id(string $account, string $tenantId, string $fallback): string
{
    $id = Db::name('sys_user')
        ->where('ACCOUNT', $account)
        ->where('TENANT_ID', $tenantId)
        ->value('ID');

    return is_string($id) && $id !== '' ? $id : $fallback;
}

function demo_rows(string $tenantId, string $marker): array
{
    $now = date('Y-m-d H:i:s');
    $ids = [
        'warehouse' => '2026063000100000001',
        'productA' => '2026063000100000002',
        'productB' => '2026063000100000003',
        'inventoryA' => '2026063000100000004',
        'inventoryB' => '2026063000100000005',
        'customerA' => '2026063000100000006',
        'customerB' => '2026063000100000007',
        'projectA' => '2026063000100000008',
        'accountIncome' => '2026063000100000009',
        'accountExpense' => '2026063000100000010',
        'payment' => '2026063000100000011',
        'expenditure' => '2026063000100000012',
        'receipt' => '2026063000100000013',
        'debit' => '2026063000100000014',
        'payrollHr' => '2026063000100000015',
        'payrollFinance' => '2026063000100000016',
        'leaveHr' => '2026063000100000017',
    ];
    $orgs = [
        'root' => '2018244380591632386',
        'hr' => '1781507827814435873',
        'sales' => '1781507845340705631',
        'finance' => '1781507858382802232',
        'tech' => '1781507923771398086',
    ];
    $users = [
        'admin' => user_id('superAdminTwo', $tenantId, '1781507658787303275'),
        'hr' => user_id('cszjb001', $tenantId, '1781592868222879881'),
        'sales' => user_id('csyw001', $tenantId, '1781592975477983296'),
        'finance' => user_id('cscw001', $tenantId, '1781593170882445466'),
        'tech' => user_id('csjs001', $tenantId, '1781593051986437021'),
    ];
    $ext = json_encode(['marker' => $marker, 'purpose' => 'role acceptance demo data'], JSON_UNESCAPED_UNICODE);

    return [
        'warehouses' => [
            $ids['warehouse'] => [
                'ID' => $ids['warehouse'],
                'NAME' => '演示仓库-A',
                'CODE' => 'CODEX-DEMO-WH-A',
                'ADDRESS' => '演示地址：广州仓',
                'SORT_CODE' => 900,
                'USER' => $users['tech'],
                'EXT_JSON' => $ext,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['tech'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['tech'],
                'TENANT_ID' => $tenantId,
                'ORG' => $orgs['tech'],
            ],
        ],
        'biz_product' => [
            $ids['productA'] => [
                'ID' => $ids['productA'],
                'PRODUCT_NAME' => '演示产品-标准套件A',
                'PRODUCT_CATEGORY' => '演示产品',
                'SAFETY_STOCK' => '5',
                'PURCHASE_PRICE' => '800.00',
                'SALE_PRICE' => '1280.00',
                'MIN_PRICE' => '1000.00',
                'CATEGORY' => 'SINGLE_PRODUCT',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['tech'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['tech'],
                'TENANT_ID' => $tenantId,
                'SPECS' => '标准版',
                'ORG' => $orgs['tech'],
                'COVER_IMAGE' => '',
                'RECONCILIATION_TYPE' => '',
                'RECONCILIATION_AMOUNT' => '0.00',
                'status' => 'ENABLE',
            ],
            $ids['productB'] => [
                'ID' => $ids['productB'],
                'PRODUCT_NAME' => '演示产品-增值服务B',
                'PRODUCT_CATEGORY' => '演示产品',
                'SAFETY_STOCK' => '3',
                'PURCHASE_PRICE' => '300.00',
                'SALE_PRICE' => '680.00',
                'MIN_PRICE' => '500.00',
                'CATEGORY' => 'SINGLE_PRODUCT',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['tech'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['tech'],
                'TENANT_ID' => $tenantId,
                'SPECS' => '服务包',
                'ORG' => $orgs['tech'],
                'COVER_IMAGE' => '',
                'RECONCILIATION_TYPE' => '',
                'RECONCILIATION_AMOUNT' => '0.00',
                'status' => 'ENABLE',
            ],
        ],
        'inventory' => [
            $ids['inventoryA'] => [
                'ID' => $ids['inventoryA'],
                'WAREHOUSES_ID' => $ids['warehouse'],
                'PRODUCT_ID' => $ids['productA'],
                'CURRENT_COUNT' => '12',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['tech'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['tech'],
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ],
            $ids['inventoryB'] => [
                'ID' => $ids['inventoryB'],
                'WAREHOUSES_ID' => $ids['warehouse'],
                'PRODUCT_ID' => $ids['productB'],
                'CURRENT_COUNT' => '5',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['tech'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['tech'],
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ],
        ],
        'customer' => [
            $ids['customerA'] => [
                'ID' => $ids['customerA'],
                'NAME' => '演示客户-华南制造',
                'CONTACTS' => '陈经理',
                'PHONE' => '13800000001',
                'DETAILS_ADDRESS' => '广州市演示区 1 号',
                'ADDRESS' => '广东省广州市',
                'SOURCE_TYPE' => 'SELF_DEVELOPED',
                'CUSTOM_TYPE' => 'OLD_CUSTOMER',
                'ORG' => $orgs['sales'],
                'USER' => $users['sales'],
                'STATUS' => 'ENABLE',
                'SORT_CODE' => 900,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['sales'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['sales'],
                'EXT_JSON' => $ext,
                'TENANT_ID' => $tenantId,
                'FILE_ID' => null,
                'VERSION' => 0,
                'DEAL_AMOUNT' => '28800.00',
                'remark' => $marker . ' 销售验收客户',
                'FIRST_CONTACT_TIME' => date('Y-m-d'),
            ],
            $ids['customerB'] => [
                'ID' => $ids['customerB'],
                'NAME' => '演示客户-湾区科技',
                'CONTACTS' => '李总',
                'PHONE' => '13800000002',
                'DETAILS_ADDRESS' => '深圳市演示区 2 号',
                'ADDRESS' => '广东省深圳市',
                'SOURCE_TYPE' => 'INTRODUCTION',
                'CUSTOM_TYPE' => 'NEW_CUSTOMER',
                'ORG' => $orgs['sales'],
                'USER' => $users['sales'],
                'STATUS' => 'ENABLE',
                'SORT_CODE' => 901,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['sales'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['sales'],
                'EXT_JSON' => $ext,
                'TENANT_ID' => $tenantId,
                'FILE_ID' => null,
                'VERSION' => 0,
                'DEAL_AMOUNT' => '0.00',
                'remark' => $marker . ' 待跟进客户',
                'FIRST_CONTACT_TIME' => date('Y-m-d'),
            ],
        ],
        'settlement_account' => [
            $ids['accountIncome'] => [
                'ID' => $ids['accountIncome'],
                'ACCOUNT_NAME' => '演示收款账户',
                'ACCOUNT_NUMBER' => 'DEMO-IN-001',
                'INITIAL_AMOUNT' => '10000.00',
                'CURRENT_AMOUNT' => '38800.00',
                'ACCOUNT_STATUS' => 'ENABLE',
                'SORT_CODE' => 900,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['finance'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['finance'],
                'EXT_JSON' => $ext,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'org' => $orgs['finance'],
                'ARCHIVE_AMOUNT' => '0.00',
                'ARCHIVE_TIME' => null,
            ],
            $ids['accountExpense'] => [
                'ID' => $ids['accountExpense'],
                'ACCOUNT_NAME' => '演示付款账户',
                'ACCOUNT_NUMBER' => 'DEMO-OUT-001',
                'INITIAL_AMOUNT' => '5000.00',
                'CURRENT_AMOUNT' => '3800.00',
                'ACCOUNT_STATUS' => 'ENABLE',
                'SORT_CODE' => 901,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['finance'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['finance'],
                'EXT_JSON' => $ext,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'org' => $orgs['finance'],
                'ARCHIVE_AMOUNT' => '0.00',
                'ARCHIVE_TIME' => null,
            ],
        ],
        'biz_sale_project' => [
            $ids['projectA'] => [
                'ID' => $ids['projectA'],
                'CUSTOMER' => $ids['customerA'],
                'PROJECT_NAME' => '演示项目-薪资系统上线',
                'PROJECT_STATE' => 'WAIT_DELIVER',
                'PLAY_STATE' => 'PARTIALLY_PAID',
                'VISIBILITY' => 'PRIVATE',
                'INIT_PRICE' => '28800.00',
                'TOTAL_PRICE' => '28800.00',
                'AMOUNT_COLLECTED' => '12000.00',
                'PROJECT_CATEGORY' => 'DIRECT',
                'USER' => $users['sales'],
                'ORG' => $orgs['sales'],
                'REMARK' => $marker . ' 销售项目验收',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['sales'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['sales'],
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'CONSIGNEE' => '陈经理',
                'PHONE' => '13800000001',
                'UNIT' => '华南制造',
                'ADDRESS' => '广东省广州市',
                'PROCESS_ID' => 'Process_sys',
                'ACCOUNT_ID' => $ids['accountIncome'],
                'PAYER_CATEGORY' => 'PROJECT_PLAY',
                'FREIGHT_CATEGORY' => 'NONE',
                'FREIGHT' => '0.00',
                'AREA' => '广东省/广州市',
                'DETAILS_ADDRESS' => '演示区 1 号',
                'PROJECT_CODE' => 'DEMO-SP-001',
                'COMPLETION_DATE' => null,
                'REBATE_AMOUNT' => '0.00',
                'DEAL_AMOUNT' => '28800.00',
                'LOGISTICS_CATEGORY' => '',
                'SPECIMEN_CATEGORY' => '',
                'SPECIMEN_NAME' => '',
                'DELIVERY_NOTE' => '',
                'HISTORY_AMOUNT' => '0.00',
                'TOTAL_RETURN_AMOUNT' => '0.00',
                'TOTAL_REFUND_AMOUNT' => '0.00',
                'REPEAL_CONTENT' => '',
                'special_type' => '',
            ],
        ],
        'biz_payment_record' => [
            $ids['payment'] => [
                'ID' => $ids['payment'],
                'OBJECT_ID' => $ids['projectA'],
                'TARGET_ID' => $ids['accountIncome'],
                'SERIAL_ID' => 'DEMO-PAY-001',
                'PROCESS_ID' => 'Process_sys',
                'SETTLEMENT_CATEGORY' => 'PROJECT_PLAY',
                'PAYER' => '演示客户-华南制造',
                'BANK_NAME' => '演示银行',
                'BANK_ACCOUNT' => '6222000000000001',
                'REMARK' => $marker . ' 收款记录',
                'PAYER_TIME' => $now,
                'AMOUNT' => '12000.00',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['finance'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['finance'],
                'TENANT_ID' => $tenantId,
                'USER' => $users['finance'],
                'ORG' => $orgs['finance'],
            ],
        ],
        'biz_expenditure_record' => [
            $ids['expenditure'] => [
                'ID' => $ids['expenditure'],
                'OBJECT_ID' => $ids['projectA'],
                'TARGET_ID' => $ids['accountExpense'],
                'SERIAL_ID' => 'DEMO-EXP-001',
                'PROCESS_ID' => 'Process_sys',
                'SETTLEMENT_CATEGORY' => 'OfficeExpenses',
                'PAYER' => '演示供应商',
                'BANK_NAME' => '演示银行',
                'BANK_ACCOUNT' => '6222000000000002',
                'REMARK' => $marker . ' 支出记录',
                'PAYER_TIME' => $now,
                'AMOUNT' => '1200.00',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['finance'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['finance'],
                'TENANT_ID' => $tenantId,
                'USER' => $users['finance'],
                'ORG' => $orgs['finance'],
            ],
        ],
        'biz_collection_receipt' => [
            $ids['receipt'] => [
                'ID' => $ids['receipt'],
                'PAYMENT_RECORD_ID' => $ids['payment'],
                'REMARK' => $marker . ' 代收款单',
                'PLAY_STATUS' => 'Unsettled',
                'AMOUNT' => '12000.00',
                'SETTLEMENT_AMOUNT' => '0.00',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['finance'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['finance'],
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ],
        ],
        'biz_debit_note' => [
            $ids['debit'] => [
                'ID' => $ids['debit'],
                'EXPENDITURE_RECORD_ID' => $ids['expenditure'],
                'REMARK' => $marker . ' 借支单',
                'PLAY_STATUS' => 'Unsettled',
                'AMOUNT' => '1200.00',
                'SETTLEMENT_AMOUNT' => '0.00',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['finance'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['finance'],
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'ORG' => $orgs['finance'],
                'HISTORY_AMOUNT' => '0.00',
            ],
        ],
        'biz_payroll' => [
            $ids['payrollHr'] => payroll_row($ids['payrollHr'], $users['hr'], $orgs['hr'], $tenantId, $users['hr'], $marker, '2026-06-01', '8600.00', $now),
            $ids['payrollFinance'] => payroll_row($ids['payrollFinance'], $users['finance'], $orgs['finance'], $tenantId, $users['finance'], $marker, '2026-06-01', '9200.00', $now),
        ],
        'biz_leave_application' => [
            $ids['leaveHr'] => [
                'ID' => $ids['leaveHr'],
                'USER_ID' => $users['hr'],
                'PROCESS_ID' => 'DEMO-LEAVE-001',
                'category' => 'leave',
                'AMOUNT' => '1.00',
                'REMARK' => $marker . ' 请假验收',
                'START_TIME' => '2026-06-29 09:00:00',
                'END_TIME' => '2026-06-29 18:00:00',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $users['hr'],
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $users['hr'],
                'TENANT_ID' => $tenantId,
                'OBJECT_ID' => '',
            ],
        ],
    ];
}

function payroll_row(string $id, string $userId, string $orgId, string $tenantId, string $operatorId, string $marker, string $salaryTime, string $actualAmount, string $now): array
{
    $basic = bc_available() ? $actualAmount : $actualAmount;

    return [
        'ID' => $id,
        'SENIORITY_SALARY' => '300.00',
        'PERFORMANCE_SALARY' => '1000.00',
        'WORK_SALARY' => '500.00',
        'BASIC_SALARY' => $basic,
        'RENT_SUBSIDIES' => '200.00',
        'MEAL_ALLOWANCE' => '300.00',
        'DORMITORY_RENT' => '0.00',
        'BASE_AMOUNT' => '0.00',
        'TRANSACTION_VOLUME' => '0.00',
        'RECEIVED_AMOUNT' => '0.00',
        'TAX_FREIGHT' => '0.00',
        'MONTHLY_COMMISSION' => '0.00',
        'BEFORE_RECEIVED_AMOUNT' => '0.00',
        'BEFORE_COMMISSION' => '0.00',
        'RATE_COMMISSION' => '0.00',
        'TOTAL_COMMISSION' => '0.00',
        'MERIT_BONUSES' => '600.00',
        'VACATION' => '0.00',
        'VACATION_SUB_AMOUNT' => '0.00',
        'PAYABLE_AMOUNT' => $actualAmount,
        'PERSONAL_INCOME_TAX' => '0.00',
        'SOCIAL_SECURITY' => '0.00',
        'ACTUAL_AMOUNT' => $actualAmount,
        'SALARY_TIME' => $salaryTime,
        'USER' => $userId,
        'ORG' => $orgId,
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => $now,
        'CREATE_USER' => $operatorId,
        'UPDATE_TIME' => $now,
        'UPDATE_USER' => $operatorId,
        'TENANT_ID' => $tenantId,
        'YEAR_END_BONUS' => '0.00',
        'POST_WAGE' => '0.00',
        'PRIVATE_ACCOUNT' => '0.00',
        'PUBLIC_ACCOUNT' => '0.00',
        'REMARK' => $marker . ' 薪资验收',
    ];
}

function bc_available(): bool
{
    return function_exists('bcadd');
}

function row_exists(string $table, string $id): bool
{
    return Db::name($table)->where('ID', $id)->count() > 0;
}

function filter_row(string $table, array $row): array
{
    $fields = array_flip(table_fields($table));

    return array_intersect_key($row, $fields);
}

function snapshot_path(string $backupDir, string $fileName): string
{
    return rtrim($backupDir, "\\/") . DIRECTORY_SEPARATOR . $fileName;
}

function write_json_file(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        throw new RuntimeException('failed to encode json');
    }

    file_put_contents($path, $json . PHP_EOL);
}

function sql_quote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function backup_snapshot(array $plans): array
{
    $snapshot = ['generatedAt' => date('Y-m-d H:i:s'), 'tables' => []];
    foreach ($plans as $table => $rows) {
        $ids = array_keys($rows);
        $snapshot['tables'][$table] = $ids === []
            ? []
            : Db::name($table)->whereIn('ID', $ids)->select()->toArray();
    }

    return $snapshot;
}

function write_rollback_sql(string $path, array $insertedIds): void
{
    $order = [
        'biz_leave_application',
        'biz_payroll',
        'biz_debit_note',
        'biz_collection_receipt',
        'biz_expenditure_record',
        'biz_payment_record',
        'biz_sale_project',
        'customer',
        'inventory',
        'biz_product',
        'warehouses',
        'settlement_account',
    ];
    $lines = [
        '-- Roll back rows inserted by scripts/demo-tenant-data-init.php',
        '-- Generated at ' . date('Y-m-d H:i:s'),
        'START TRANSACTION;',
    ];
    foreach ($order as $table) {
        $ids = $insertedIds[$table] ?? [];
        if ($ids === []) {
            continue;
        }
        $lines[] = 'DELETE FROM `' . $table . '` WHERE `ID` IN (' . implode(',', array_map('sql_quote', $ids)) . ');';
    }
    $lines[] = 'COMMIT;';

    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

if ($inspect) {
    $summary = [
        'mode' => 'inspect',
        'tenantId' => $tenantId,
        'marker' => $marker,
        'tables' => [],
    ];
    foreach ($tables as $table) {
        $summary['tables'][$table] = [
            'fields' => table_fields($table),
            'activeCount' => active_count($table, $tenantId),
            'sample' => sample_rows($table, $tenantId),
        ];
    }

    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

$plans = demo_rows($tenantId, $marker);
$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'tenantId' => $tenantId,
    'marker' => $marker,
    'tables' => [],
    'totals' => [
        'rowsToInsert' => 0,
        'rowsExisting' => 0,
    ],
    'insertedIds' => [],
    'backupFiles' => [],
];

foreach ($plans as $table => $rows) {
    $tableSummary = [
        'planned' => count($rows),
        'existing' => 0,
        'toInsert' => 0,
        'idsToInsert' => [],
    ];
    foreach ($rows as $id => $row) {
        if (row_exists($table, (string)$id)) {
            $tableSummary['existing']++;
            $summary['totals']['rowsExisting']++;
        } else {
            $tableSummary['toInsert']++;
            $tableSummary['idsToInsert'][] = (string)$id;
            $summary['totals']['rowsToInsert']++;
        }
    }
    $summary['tables'][$table] = $tableSummary;
    $summary['insertedIds'][$table] = [];
}

if ($backupDir !== null && $backupDir !== '') {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        throw new RuntimeException('failed to create backup dir: ' . $backupDir);
    }

    $beforePath = snapshot_path($backupDir, 'before-snapshot.json');
    write_json_file($beforePath, backup_snapshot($plans));
    $summary['backupFiles']['beforeSnapshot'] = $beforePath;
}

$runner = static function () use ($plans, &$summary): void {
    foreach ($plans as $table => $rows) {
        foreach ($rows as $id => $row) {
            if (row_exists($table, (string)$id)) {
                continue;
            }
            Db::name($table)->insert(filter_row($table, $row));
            $summary['insertedIds'][$table][] = (string)$id;
        }
    }
};

if ($apply) {
    Db::transaction($runner);
}

if ($backupDir !== null && $backupDir !== '') {
    $summaryPath = snapshot_path($backupDir, 'apply-summary.json');
    $rollbackPath = snapshot_path($backupDir, 'rollback-inserted.sql');
    $summary['backupFiles']['applySummary'] = $summaryPath;
    $summary['backupFiles']['rollbackSql'] = $rollbackPath;
    write_json_file($summaryPath, $summary);
    write_rollback_sql($rollbackPath, $summary['insertedIds']);
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
