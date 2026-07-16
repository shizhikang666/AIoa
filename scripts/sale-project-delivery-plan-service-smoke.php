<?php

declare(strict_types=1);

use app\service\auth\RbacService;
use app\service\biz\SaleProjectDeliveryPlanService;
use app\service\biz\SaleProjectService;
use app\service\workflow\WorkflowRuntimeService;
use think\facade\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

(new think\App(dirname(__DIR__)))->initialize();

const DELIVERY_PLAN_TABLE = 'biz_sale_project_delivery_plan';

/**
 * This smoke test intentionally uses committed, uniquely named fixtures so that
 * the service's own transaction boundary can be verified. Every fixture is
 * removed in finally. No existing business row is updated or deleted.
 */

function smokeFail(string $message): never
{
    throw new RuntimeException($message);
}

function assertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        smokeFail($label);
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        smokeFail(sprintf(
            '%s expected=%s actual=%s',
            $label,
            json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
    }
}

function assertDecimal(mixed $expected, mixed $actual, string $label): void
{
    if (abs((float)$expected - (float)$actual) > 0.000001) {
        smokeFail(sprintf('%s expected=%s actual=%s', $label, (string)$expected, (string)$actual));
    }
}

function assertThrows(callable $callback, int $expectedCode, string $label): Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ((int)$exception->getCode() !== $expectedCode) {
            smokeFail(sprintf(
                '%s expected exception code=%d actual=%d message=%s',
                $label,
                $expectedCode,
                (int)$exception->getCode(),
                $exception->getMessage()
            ));
        }

        return $exception;
    }

    smokeFail($label . ' expected an exception');
}

function fixtureId(string $prefix): string
{
    $length = 20 - strlen($prefix);
    if ($length < 8) {
        smokeFail('fixture id prefix is too long');
    }

    return $prefix . substr(bin2hex(random_bytes(10)), 0, $length);
}

/**
 * @return array<string, string>
 */
function readEnvFile(string $path): array
{
    if (!is_file($path)) {
        smokeFail('missing local .env file');
    }

    $result = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $trimmed, 2);
        $value = trim($value);
        if (strlen($value) >= 2
            && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $result[trim($key)] = $value;
    }

    return $result;
}

/**
 * @param array<string, mixed> $conditions
 */
function rowCount(string $table, array $conditions): int
{
    $query = Db::name($table);
    foreach ($conditions as $column => $value) {
        $query->where($column, $value);
    }

    return (int)$query->count();
}

/**
 * @param array<string, mixed> $conditions
 * @return array<string, mixed>
 */
function requiredRow(string $table, array $conditions, string $label): array
{
    $query = Db::name($table);
    foreach ($conditions as $column => $value) {
        $query->where($column, $value);
    }
    $row = $query->find();
    if (!is_array($row) || $row === []) {
        smokeFail($label . ' row not found');
    }

    return $row;
}

/**
 * @param array<string, string> $ids
 */
function cleanupFixtures(array $ids): void
{
    $projectIds = [$ids['badProject'], $ids['planProject'], $ids['legacyProject']];
    $warehouseIds = [$ids['warehouseA'], $ids['warehouseB'], $ids['legacyWarehouse']];
    $productIds = [$ids['planProduct'], $ids['legacyProduct']];

    Db::transaction(function () use ($ids, $projectIds, $warehouseIds, $productIds): void {
        $invoiceIds = Db::name('biz_sale_project_invoice')
            ->whereIn('PROJECT_ID', $projectIds)
            ->column('ID');
        if ($invoiceIds !== []) {
            Db::name('biz_sale_project_invoice_item')->whereIn('INVOICE_ID', $invoiceIds)->delete();
        }

        Db::name('delivery_record')->whereIn('OBJECT_ID', $projectIds)->delete();
        Db::name('biz_sale_project_invoice')->whereIn('PROJECT_ID', $projectIds)->delete();
        if ((new SaleProjectDeliveryPlanService())->tableExists()) {
            Db::name(DELIVERY_PLAN_TABLE)->whereIn('PROJECT_ID', $projectIds)->delete();
        }

        $itemIds = Db::name('biz_sale_project_product_item')
            ->whereIn('PROJECT_ID', $projectIds)
            ->column('ID');
        if ($itemIds !== []) {
            Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', $itemIds)->delete();
        }
        Db::name('biz_sale_project_product_item')->whereIn('PROJECT_ID', $projectIds)->delete();
        Db::name('biz_file_relation')->whereIn('OBJECT_ID', $projectIds)->delete();
        Db::name('biz_sale_project_invoicing')->whereIn('PROJECT_ID', $projectIds)->delete();
        Db::name('biz_sale_project')->whereIn('ID', $projectIds)->delete();
        Db::name('inventory')->whereIn('WAREHOUSES_ID', $warehouseIds)->delete();
        Db::name('inventory')->whereIn('PRODUCT_ID', $productIds)->delete();
        Db::name('warehouses')->whereIn('ID', $warehouseIds)->delete();
        Db::name('biz_product')->whereIn('ID', $productIds)->delete();
        Db::name('dev_file')->where('ID', $ids['file'])->delete();
        Db::name('customer')->where('ID', $ids['customer'])->delete();
    });
}

/**
 * @param array<string, string> $ids
 * @param array<string, mixed> $user
 */
function insertFixtures(array $ids, array $user, string $tenantId, string $orgId): void
{
    $now = date('Y-m-d H:i:s');
    $userId = (string)$user['ID'];
    $org = $orgId !== '' ? $orgId : null;

    Db::transaction(function () use ($ids, $tenantId, $now, $userId, $org): void {
        Db::name('customer')->insert([
            'ID' => $ids['customer'],
            'NAME' => $ids['prefix'] . ' customer',
            'CUSTOM_TYPE' => 'OLD',
            'ORG' => $org,
            'USER' => $userId,
            'STATUS' => 'ENABLE',
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => $now,
            'CREATE_USER' => $userId,
            'TENANT_ID' => $tenantId,
            'VERSION' => 0,
            'DEAL_AMOUNT' => '0.00',
        ]);

        foreach ([
            [$ids['planProduct'], $ids['prefix'] . ' planned product'],
            [$ids['legacyProduct'], $ids['prefix'] . ' legacy product'],
        ] as [$productId, $productName]) {
            Db::name('biz_product')->insert([
                'ID' => $productId,
                'PRODUCT_NAME' => $productName,
                'PRODUCT_CATEGORY' => 'SMOKE',
                'SAFETY_STOCK' => 0,
                'PURCHASE_PRICE' => '10.00',
                'SALE_PRICE' => '20.00',
                'MIN_PRICE' => '8.00',
                'CATEGORY' => 'SINGLE_PRODUCT',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'TENANT_ID' => $tenantId,
                'SPECS' => 'smoke',
                'ORG' => $org,
                'status' => 'ENABLE',
            ]);
        }

        foreach ([
            [$ids['warehouseA'], $ids['prefix'] . ' warehouse A', 'WHA'],
            [$ids['warehouseB'], $ids['prefix'] . ' warehouse B', 'WHB'],
            [$ids['legacyWarehouse'], $ids['prefix'] . ' legacy warehouse', 'WHL'],
        ] as [$warehouseId, $warehouseName, $codePrefix]) {
            Db::name('warehouses')->insert([
                'ID' => $warehouseId,
                'NAME' => $warehouseName,
                'CODE' => $codePrefix . substr($ids['seed'], 0, 12),
                'ADDRESS' => $ids['prefix'] . ' warehouse address',
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'TENANT_ID' => $tenantId,
                'ORG' => $org,
            ]);
        }

        foreach ([
            [$ids['inventoryA'], $ids['warehouseA'], $ids['planProduct'], '100'],
            [$ids['inventoryB'], $ids['warehouseB'], $ids['planProduct'], '100'],
            [$ids['legacyInventory'], $ids['legacyWarehouse'], $ids['legacyProduct'], '50'],
        ] as [$inventoryId, $warehouseId, $productId, $count]) {
            Db::name('inventory')->insert([
                'ID' => $inventoryId,
                'WAREHOUSES_ID' => $warehouseId,
                'PRODUCT_ID' => $productId,
                'CURRENT_COUNT' => $count,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ]);
        }

        Db::name('dev_file')->insert([
            'ID' => $ids['file'],
            'ENGINE' => 'LOCAL',
            'BUCKET' => 'defaultBucketName',
            'NAME' => $ids['prefix'] . '.txt',
            'SUFFIX' => 'txt',
            'SIZE_KB' => 1,
            'SIZE_INFO' => '1KB',
            'OBJ_NAME' => $ids['prefix'] . '.txt',
            'STORAGE_PATH' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . $ids['prefix'] . '.txt',
            'DOWNLOAD_PATH' => '/api/dev/file/download?id=' . $ids['file'],
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => $now,
            'CREATE_USER' => $userId,
            'TENANT_ID' => $tenantId,
        ]);

        foreach ([
            [$ids['badProject'], 'PENDING_APPROVAL', $ids['prefix'] . ' rollback project'],
            [$ids['planProject'], 'PENDING_APPROVAL', $ids['prefix'] . ' delivery-plan project'],
            [$ids['legacyProject'], 'WAIT_DELIVER', $ids['prefix'] . ' legacy project'],
        ] as [$projectId, $state, $projectName]) {
            Db::name('biz_sale_project')->insert([
                'ID' => $projectId,
                'CUSTOMER' => $ids['customer'],
                'PROJECT_NAME' => $projectName,
                'PROJECT_STATE' => $state,
                'PLAY_STATE' => 'UNPAID',
                'VISIBILITY' => 'PRIVATE',
                'INIT_PRICE' => '0.00',
                'TOTAL_PRICE' => '0.00',
                'AMOUNT_COLLECTED' => '0.00',
                'PROJECT_CATEGORY' => 'DEFAULT',
                'USER' => $userId,
                'ORG' => $org,
                'REMARK' => $ids['prefix'],
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'CONSIGNEE' => 'Legacy ' . $ids['seed'],
                'PHONE' => '18800000000',
                'UNIT' => $ids['prefix'] . ' legacy unit',
                'ADDRESS' => $ids['prefix'] . ' legacy address',
                'FREIGHT_CATEGORY' => 'BUYER_PAY',
                'FREIGHT' => '1.00',
                'LOGISTICS_CATEGORY' => 'EXPRESS',
                'DELIVERY_NOTE' => $ids['prefix'],
                'DEAL_AMOUNT' => '0.00',
                'HISTORY_AMOUNT' => '0.00',
                'TOTAL_RETURN_AMOUNT' => '0.00',
                'TOTAL_REFUND_AMOUNT' => '0.00',
            ]);
        }

        Db::name('biz_sale_project_product_item')->insert([
            'ID' => $ids['legacyItem'],
            'PROJECT_ID' => $ids['legacyProject'],
            'PRODUCT_ID' => $ids['legacyProduct'],
            'CATEGORY' => 'INIT',
            'STATE' => 'WAIT_DELIVER',
            'NUMBER' => '3',
            'DELIVERY' => '0',
            'UNIT_PRICE' => '20.00',
            'DISCOUNT_RATE' => '0.00',
            'PRICE' => '60.00',
            'REMARK' => $ids['prefix'],
            'EXT_JSON' => null,
            'DELETE_FLAG' => 'NOT_DELETE',
            'CREATE_TIME' => $now,
            'CREATE_USER' => $userId,
            'TENANT_ID' => $tenantId,
            'VERSION' => 0,
            'PROJECT_REISSUE_ORDER_ID' => '',
            'MARK' => '',
        ]);
    });
}

/**
 * @return array<int, array<string, mixed>>
 */
function deliveryPlans(array $ids, int $secondAmount = 4): array
{
    return [
        [
            'planNo' => 1,
            'consignee' => 'Plan A ' . $ids['seed'],
            'unit' => $ids['prefix'] . ' unit A',
            'phone' => '18800000001',
            'address' => $ids['prefix'] . ' address A',
            'freightCategory' => null,
            'freight' => null,
            'logisticsCategory' => 'EXPRESS',
            'remark' => $ids['prefix'] . ' plan A',
            'productItemList' => [[
                'productId' => $ids['planProduct'],
                'amount' => 6,
                'remark' => 'first shipment',
            ]],
        ],
        [
            'planNo' => 2,
            'consignee' => 'Plan B ' . $ids['seed'],
            'unit' => $ids['prefix'] . ' unit B',
            'phone' => '18800000002',
            'address' => $ids['prefix'] . ' address B',
            'freightCategory' => 'SELLER_PAY',
            'freight' => '22.22',
            'logisticsCategory' => 'EXPRESS',
            'remark' => $ids['prefix'] . ' plan B',
            'productItemList' => [[
                'productId' => $ids['planProduct'],
                'amount' => $secondAmount,
                'remark' => 'second shipment',
            ]],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function initVariables(array $ids, string $projectId, string $tenantId, string $userId, array $plans): array
{
    return [
        'bizSaleProjectId' => $projectId,
        'projectId' => $projectId,
        'productList' => [[
            'productId' => $ids['planProduct'],
            'number' => 10,
            'unitPrice' => '20.00',
            'discountRate' => '0.00',
            'price' => '200.00',
            'remark' => $ids['prefix'] . ' product line',
        ]],
        'deliveryPlanList' => $plans,
        'fileIdList' => [$ids['file']],
        'consignee' => $plans[0]['consignee'],
        'phone' => $plans[0]['phone'],
        'unit' => $plans[0]['unit'],
        'address' => $plans[0]['address'],
        'logisticsCategory' => $plans[0]['logisticsCategory'],
        'deliveryNote' => $ids['prefix'] . ' delivery plans',
        'freight' => '33.33',
        'freightCategory' => $plans[0]['freightCategory'] ?? null,
        'accountId' => $ids['account'],
        'payerCategory' => 'FULL_PAYMENT',
        'initPrice' => '200.00',
        'rebateAmount' => '0.00',
        'travelDays' => '0',
        'completionDate' => '2026-07-16 10:11:12',
        'isInvoicing' => false,
        'initiator' => $userId,
        'tenantId' => $tenantId,
    ];
}

/**
 * @param array<string, mixed> $plan
 * @return array<string, mixed>
 */
function shipmentInput(
    array $ids,
    array $plan,
    string $projectId,
    string $itemId,
    string $productId,
    string $warehouseId,
    int $amount,
    string $requestId,
    string $trackingSuffix
): array {
    return [
        'projectId' => $projectId,
        'deliveryPlanId' => (string)$plan['ID'],
        'requestId' => $requestId,
        'approveUserIdList' => [],
        'copyUserIdList' => [],
        'fileIdList' => [],
        'projectProductItemList' => [[
            'projectProductItemId' => $itemId,
            'productId' => $productId,
            'warehousesId' => $warehouseId,
            'amount' => $amount,
            'remark' => $ids['prefix'] . ' shipment ' . $trackingSuffix,
        ]],
        // Address fields remain trusted from the plan. Freight and warehouse are
        // actual-shipment values and must be allowed to override plan defaults.
        'consignee' => $ids['prefix'] . ' client value must not win',
        'unit' => $ids['prefix'] . ' client unit must not win',
        'phone' => '19900000000',
        'address' => $ids['prefix'] . ' client address must not win',
        'freightCategory' => 'BUYER_PAY',
        'freight' => '12.34',
        'logisticsCategory' => 'EXPRESS',
        'logisticsId' => substr('TRACK' . $trackingSuffix . $ids['seed'], 0, 20),
        'freightTime' => '2026-07-16 12:13:14',
        'remark' => $ids['prefix'] . ' actual shipment ' . $trackingSuffix,
    ];
}

/**
 * @return array<string, mixed>
 */
function legacyShipmentInput(array $ids): array
{
    return [
        'projectId' => $ids['legacyProject'],
        'requestId' => 'legacy-' . $ids['seed'],
        'approveUserIdList' => [],
        'copyUserIdList' => [],
        'fileIdList' => [],
        'projectProductItemList' => [[
            'projectProductItemId' => $ids['legacyItem'],
            'productId' => $ids['legacyProduct'],
            'warehousesId' => $ids['legacyWarehouse'],
            'amount' => 2,
            'remark' => $ids['prefix'] . ' legacy shipment',
        ]],
        'consignee' => $ids['prefix'] . ' legacy consignee',
        'unit' => $ids['prefix'] . ' legacy unit',
        'phone' => '18800000003',
        'address' => $ids['prefix'] . ' legacy address',
        'freightCategory' => 'BUYER_PAY',
        'freight' => '1.00',
        'logisticsCategory' => 'EXPRESS',
        'logisticsId' => substr('LEGACY' . $ids['seed'], 0, 20),
        'freightTime' => '2026-07-16 13:14:15',
        'remark' => $ids['prefix'] . ' legacy shipment',
    ];
}

$env = readEnvFile(dirname(__DIR__) . '/.env');
$account = trim((string)($env['LOCAL_SUPER_ADMIN_ACCOUNT'] ?? ''));
if ($account === '') {
    smokeFail('LOCAL_SUPER_ADMIN_ACCOUNT is required in the ignored .env');
}

$user = Db::name('sys_user')->where('ACCOUNT', $account)->find();
if (!is_array($user) || $user === []) {
    smokeFail('local smoke account not found');
}
$userId = (string)$user['ID'];
$tenantId = trim((string)($user['TENANT_ID'] ?? '')) ?: '1';
$orgId = trim((string)($user['ORG_ID'] ?? ''));
$payload = (new RbacService())->buildForUser($user);
$payload['user_id'] = $userId;
$payload['tenant_id'] = $tenantId;
$payload['org_id'] = $orgId;

$seed = substr(bin2hex(random_bytes(8)), 0, 12);
$ids = [
    'seed' => $seed,
    'prefix' => 'codex-delivery-plan-' . $seed,
    'customer' => fixtureId('C'),
    'badProject' => fixtureId('PB'),
    'planProject' => fixtureId('PP'),
    'legacyProject' => fixtureId('PL'),
    'planProduct' => fixtureId('PD'),
    'legacyProduct' => fixtureId('PG'),
    'warehouseA' => fixtureId('WA'),
    'warehouseB' => fixtureId('WB'),
    'legacyWarehouse' => fixtureId('WL'),
    'inventoryA' => fixtureId('IA'),
    'inventoryB' => fixtureId('IB'),
    'legacyInventory' => fixtureId('IL'),
    'legacyItem' => fixtureId('LI'),
    'file' => fixtureId('F'),
    'account' => fixtureId('A'),
];

$planService = new SaleProjectDeliveryPlanService();
if (!$planService->tableExists()) {
    smokeFail('delivery plan table is missing; run scripts/install-sale-project-delivery-plan.php first');
}
$nullableColumnRows = Db::query(
    "SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS "
    . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . DELIVERY_PLAN_TABLE . "' "
    . "AND COLUMN_NAME IN ('FREIGHT', 'FREIGHT_CATEGORY')"
);
$nullableColumns = [];
foreach ($nullableColumnRows as $nullableColumnRow) {
    $nullableColumns[(string)$nullableColumnRow['COLUMN_NAME']] = (string)$nullableColumnRow['IS_NULLABLE'];
}
assertSameValue('YES', $nullableColumns['FREIGHT'] ?? null, 'delivery plan freight column must allow null');
assertSameValue('YES', $nullableColumns['FREIGHT_CATEGORY'] ?? null, 'delivery plan freight category column must allow null');
$saleProjectService = new SaleProjectService($planService);
$workflowService = new WorkflowRuntimeService(saleProjectService: $saleProjectService);

cleanupFixtures($ids);

try {
    insertFixtures($ids, $user, $tenantId, $orgId);

    $productListForValidation = [[
        'productId' => $ids['planProduct'],
        'number' => 10,
    ]];
    $normalized = $planService->normalizeAndValidate(deliveryPlans($ids), $productListForValidation);
    assertSameValue(2, count($normalized), 'split delivery plan normalization count');
    assertSameValue(null, $normalized[0]['freightCategory'], 'null planned freight category is preserved');
    assertSameValue(null, $normalized[0]['freight'], 'null planned freight is preserved');

    $blankFreightPlans = deliveryPlans($ids);
    unset($blankFreightPlans[0]['freightCategory'], $blankFreightPlans[0]['freight']);
    $blankFreightPlans[1]['freightCategory'] = '';
    $blankFreightPlans[1]['freight'] = '';
    $blankFreightNormalized = $planService->normalizeAndValidate($blankFreightPlans, $productListForValidation);
    assertSameValue(null, $blankFreightNormalized[0]['freightCategory'], 'omitted planned freight category is allowed');
    assertSameValue(null, $blankFreightNormalized[0]['freight'], 'omitted planned freight is allowed');
    assertSameValue(null, $blankFreightNormalized[1]['freightCategory'], 'blank planned freight category becomes null');
    assertSameValue(null, $blankFreightNormalized[1]['freight'], 'blank planned freight becomes null');

    $negativeFreightPlans = deliveryPlans($ids);
    $negativeFreightPlans[0]['freight'] = '-0.01';
    assertThrows(
        fn (): array => $planService->normalizeAndValidate($negativeFreightPlans, $productListForValidation),
        400,
        'negative planned freight must still be rejected'
    );

    $duplicatePlans = deliveryPlans($ids);
    $duplicatePlans[0]['productItemList'][] = [
        'productId' => $ids['planProduct'],
        'amount' => 1,
    ];
    assertThrows(
        fn (): array => $planService->normalizeAndValidate($duplicatePlans, $productListForValidation),
        400,
        'duplicate product within one plan must be rejected'
    );
    assertThrows(
        fn (): array => $planService->normalizeAndValidate(deliveryPlans($ids, 3), $productListForValidation),
        400,
        'delivery plan aggregate quantity mismatch must be rejected'
    );

    // Failed deal conversion must roll back product rows, file relations, plans,
    // project state changes, invoices, delivery records and inventory effects.
    assertThrows(
        fn (): array => $saleProjectService->applyProjectInitFromWorkflow(
            initVariables($ids, $ids['badProject'], $tenantId, $userId, deliveryPlans($ids, 3)),
            fixtureId('PX'),
            $tenantId,
            $userId
        ),
        400,
        'invalid delivery plans must roll back the deal transaction'
    );
    $badProject = requiredRow('biz_sale_project', ['ID' => $ids['badProject']], 'rollback project');
    assertSameValue('PENDING_APPROVAL', (string)$badProject['PROJECT_STATE'], 'rollback project state');
    assertSameValue(0, rowCount('biz_sale_project_product_item', ['PROJECT_ID' => $ids['badProject']]), 'rollback product item count');
    assertSameValue(0, rowCount(DELIVERY_PLAN_TABLE, ['PROJECT_ID' => $ids['badProject']]), 'rollback delivery plan count');
    assertSameValue(0, rowCount('biz_file_relation', ['OBJECT_ID' => $ids['badProject']]), 'rollback file relation count');
    assertSameValue(0, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['badProject']]), 'rollback invoice count');
    assertSameValue(0, rowCount('delivery_record', ['OBJECT_ID' => $ids['badProject']]), 'rollback delivery record count');

    $initialA = requiredRow('inventory', ['ID' => $ids['inventoryA']], 'initial inventory A');
    $initialB = requiredRow('inventory', ['ID' => $ids['inventoryB']], 'initial inventory B');
    assertDecimal(100, $initialA['CURRENT_COUNT'], 'initial inventory A count');
    assertDecimal(100, $initialB['CURRENT_COUNT'], 'initial inventory B count');

    // Successful deal conversion creates exactly two pending plans, preserves the
    // first plan in legacy project fields, totals freight, and does not ship stock.
    $initResult = $saleProjectService->applyProjectInitFromWorkflow(
        initVariables($ids, $ids['planProject'], $tenantId, $userId, deliveryPlans($ids)),
        fixtureId('PI'),
        $tenantId,
        $userId
    );
    assertSameValue('WAIT_DELIVER', (string)$initResult['projectState'], 'planned project state after deal');

    $projectItemRows = Db::name('biz_sale_project_product_item')
        ->where('PROJECT_ID', $ids['planProject'])
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->select()
        ->toArray();
    assertSameValue(1, count($projectItemRows), 'planned project item count');
    $projectItem = $projectItemRows[0];
    $projectItemId = (string)$projectItem['ID'];

    $planRows = Db::name(DELIVERY_PLAN_TABLE)
        ->where('PROJECT_ID', $ids['planProject'])
        ->where('DELETE_FLAG', 'NOT_DELETE')
        ->order('PLAN_NO', 'asc')
        ->select()
        ->toArray();
    assertSameValue(2, count($planRows), 'created delivery plan count');
    assertSameValue('WAIT_DELIVER', (string)$planRows[0]['STATUS'], 'plan A initial status');
    assertSameValue('WAIT_DELIVER', (string)$planRows[1]['STATUS'], 'plan B initial status');
    assertSameValue($ids['prefix'] . ' address A', (string)$planRows[0]['ADDRESS'], 'plan A address');
    assertSameValue($ids['prefix'] . ' address B', (string)$planRows[1]['ADDRESS'], 'plan B address');
    assertSameValue(null, $planRows[0]['FREIGHT_CATEGORY'], 'plan A blank freight category persists as null');
    assertSameValue(null, $planRows[0]['FREIGHT'], 'plan A blank freight persists as null');
    assertSameValue('SELLER_PAY', (string)$planRows[1]['FREIGHT_CATEGORY'], 'plan B planned freight category');
    assertDecimal('22.22', $planRows[1]['FREIGHT'], 'plan B freight');

    foreach ($planRows as $index => $planRow) {
        $items = json_decode((string)$planRow['ITEM_JSON'], true, flags: JSON_THROW_ON_ERROR);
        assertSameValue(1, count($items), 'plan item JSON count ' . $index);
        assertSameValue($projectItemId, (string)$items[0]['projectProductItemId'], 'plan item mapped project item id ' . $index);
        assertDecimal($index === 0 ? 6 : 4, $items[0]['amount'], 'plan item amount ' . $index);
    }

    $listedPlans = $planService->listByProject($ids['planProject'], $tenantId);
    assertSameValue(2, count($listedPlans), 'listed delivery plan count');
    assertSameValue(null, $listedPlans[0]['freightCategory'], 'listed plan A freight category remains null');
    assertSameValue(null, $listedPlans[0]['freight'], 'listed plan A freight remains null');
    foreach ($listedPlans as $index => $listedPlan) {
        assertSameValue(
            $listedPlan['productItemList'] ?? null,
            $listedPlan['productList'] ?? null,
            'listed plan product aliases must remain identical ' . $index
        );
    }

    $plannedProject = requiredRow('biz_sale_project', ['ID' => $ids['planProject']], 'planned project');
    assertSameValue($ids['prefix'] . ' address A', (string)$plannedProject['ADDRESS'], 'legacy project address uses first plan');
    assertSameValue(null, $plannedProject['FREIGHT_CATEGORY'], 'legacy project projection keeps first blank freight category null');
    assertDecimal('22.22', $plannedProject['FREIGHT'], 'legacy project freight totals only known plan values');
    assertSameValue(0, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'deal must not create invoice');
    assertSameValue(0, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'deal must not create delivery record');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'post-deal inventory A')['CURRENT_COUNT'], 'deal must not decrement inventory A');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'post-deal inventory B')['CURRENT_COUNT'], 'deal must not decrement inventory B');

    $pendingSummary = $planService->pendingSummary($ids['planProject'], $tenantId);
    assertSameValue(2, (int)($pendingSummary['pendingCount'] ?? -1), 'pending delivery plan summary count');
    assertSameValue(true, (bool)($pendingSummary['hasPending'] ?? false), 'pending delivery plan summary flag');

    // Planned freight can be blank, but the warehouse must supply final freight
    // data for the real shipment invoice.
    $missingFreightCategoryInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        6,
        'missing-freight-category-' . $seed,
        'NOFC'
    );
    unset($missingFreightCategoryInput['freightCategory']);
    assertThrows(
        fn (): array => $workflowService->startProjectDeliveryProcess($missingFreightCategoryInput, $payload),
        400,
        'actual shipment freight category remains required'
    );

    $missingFreightInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        6,
        'missing-freight-' . $seed,
        'NOFR'
    );
    unset($missingFreightInput['freight']);
    assertThrows(
        fn (): array => $workflowService->startProjectDeliveryProcess($missingFreightInput, $payload),
        400,
        'actual shipment freight remains required'
    );

    $negativeFreightInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        6,
        'negative-freight-' . $seed,
        'NEGFR'
    );
    $negativeFreightInput['freight'] = '-0.01';
    assertThrows(
        fn (): array => $workflowService->startProjectDeliveryProcess($negativeFreightInput, $payload),
        400,
        'actual shipment negative freight must be rejected'
    );
    assertSameValue(0, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'invalid actual freight creates no invoice');
    assertSameValue(0, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'invalid actual freight creates no delivery record');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after invalid actual freight')['CURRENT_COUNT'], 'invalid actual freight leaves inventory A');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'inventory B after invalid actual freight')['CURRENT_COUNT'], 'invalid actual freight leaves inventory B');

    // Once a new project has pending plans, omitting deliveryPlanId must not
    // silently fall back to the historical free-form shipment path.
    $bypassInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        6,
        'missing-plan-' . $seed,
        'NOPLAN'
    );
    unset($bypassInput['deliveryPlanId']);
    assertThrows(
        fn (): array => $workflowService->startProjectDeliveryProcess($bypassInput, $payload),
        400,
        'new project normal shipment must select a delivery plan'
    );
    assertSameValue(0, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'missing plan id invoice rollback');
    assertSameValue(0, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'missing plan id delivery rollback');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after missing plan id')['CURRENT_COUNT'], 'missing plan id inventory rollback');

    // A client cannot change a planned quantity while shipping.
    $wrongAmountInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        5,
        'wrong-amount-' . $seed,
        'WRONG'
    );
    assertThrows(
        fn (): array => $workflowService->startProjectDeliveryProcess($wrongAmountInput, $payload),
        400,
        'shipment amount differing from plan must be rejected'
    );
    assertSameValue(0, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'wrong amount invoice rollback');
    assertSameValue('WAIT_DELIVER', (string)requiredRow(DELIVERY_PLAN_TABLE, ['ID' => $planRows[0]['ID']], 'plan A after wrong amount')['STATUS'], 'wrong amount keeps plan pending');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after wrong amount')['CURRENT_COUNT'], 'wrong amount inventory rollback');

    // Preparing a plan does not lock in a warehouse. The operator can change
    // the warehouse before submitting, and only the final choice is durable.
    $planAWarehouseAInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        6,
        'preview-warehouse-a-' . $seed,
        'PREVA'
    );
    $preparedWarehouseA = $planService->prepareShipment(
        (string)$planRows[0]['ID'],
        $ids['planProject'],
        $planAWarehouseAInput['projectProductItemList'],
        $tenantId
    );
    assertSameValue(
        $ids['warehouseA'],
        (string)$preparedWarehouseA['projectProductItemList'][0]['warehousesId'],
        'first warehouse preview'
    );

    $planAInput = shipmentInput(
        $ids,
        $planRows[0],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseB'],
        6,
        'shared-request-' . $seed,
        'A'
    );
    $preparedWarehouseB = $planService->prepareShipment(
        (string)$planRows[0]['ID'],
        $ids['planProject'],
        $planAInput['projectProductItemList'],
        $tenantId
    );
    assertSameValue(
        $ids['warehouseB'],
        (string)$preparedWarehouseB['projectProductItemList'][0]['warehousesId'],
        'changed warehouse preview'
    );
    assertSameValue('WAIT_DELIVER', (string)requiredRow(DELIVERY_PLAN_TABLE, ['ID' => $planRows[0]['ID']], 'plan A after warehouse previews')['STATUS'], 'warehouse previews do not ship plan');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after warehouse previews')['CURRENT_COUNT'], 'warehouse previews leave inventory A');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'inventory B after warehouse previews')['CURRENT_COUNT'], 'warehouse previews leave inventory B');

    $planAResult = $workflowService->startProjectDeliveryProcess($planAInput, $payload);
    assertSameValue(true, (bool)$planAResult['autoApproved'], 'plan A auto approval');
    assertSameValue(1, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'plan A invoice count');
    assertSameValue(1, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'plan A delivery record count');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after plan A')['CURRENT_COUNT'], 'superseded warehouse A stays untouched');
    assertDecimal(94, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'inventory B after plan A')['CURRENT_COUNT'], 'final warehouse B is decremented');

    $planARow = requiredRow(DELIVERY_PLAN_TABLE, ['ID' => $planRows[0]['ID']], 'plan A after shipment');
    assertSameValue('SHIPPED', (string)$planARow['STATUS'], 'plan A shipped status');
    assertSameValue((string)$planAResult['invoiceId'], (string)$planARow['INVOICE_ID'], 'plan A invoice link');
    assertSameValue((string)$planAResult['processInstanceId'], (string)$planARow['PROCESS_ID'], 'plan A process link');
    assertSameValue('BUYER_PAY', (string)$planARow['FREIGHT_CATEGORY'], 'plan A final freight category is written back');
    assertDecimal('12.34', $planARow['FREIGHT'], 'plan A final freight is written back');

    $planAInvoice = requiredRow('biz_sale_project_invoice', ['ID' => $planAResult['invoiceId']], 'plan A invoice');
    assertSameValue((string)$planRows[0]['ADDRESS'], (string)$planAInvoice['ADDRESS'], 'plan A invoice address comes from plan');
    assertSameValue((string)$planRows[0]['CONSIGNEE'], (string)$planAInvoice['CONSIGNEE'], 'plan A invoice consignee comes from plan');
    assertSameValue('BUYER_PAY', (string)$planAInvoice['FREIGHT_CATEGORY'], 'plan A invoice uses actual freight category');
    assertDecimal('12.34', $planAInvoice['FREIGHT'], 'plan A invoice uses actual freight');
    $planAInvoiceItem = requiredRow('biz_sale_project_invoice_item', ['INVOICE_ID' => $planAResult['invoiceId']], 'plan A invoice item');
    assertSameValue($ids['warehouseB'], (string)$planAInvoiceItem['WAREHOUSES_ID'], 'plan A invoice item uses final warehouse B');
    $planADeliveryRecord = requiredRow('delivery_record', ['PROCESS_ID' => $planAResult['processInstanceId']], 'plan A delivery record');
    assertSameValue($ids['warehouseB'], (string)$planADeliveryRecord['WAREHOUSES_ID'], 'plan A delivery record uses final warehouse B');
    assertDecimal(6, requiredRow('biz_sale_project_product_item', ['ID' => $projectItemId], 'project item after plan A')['DELIVERY'], 'plan A delivered quantity');
    $projectAfterPlanA = requiredRow('biz_sale_project', ['ID' => $ids['planProject']], 'project after plan A');
    assertSameValue('PARTIALLY_SHIPPED', (string)$projectAfterPlanA['PROJECT_STATE'], 'project state after first plan');
    assertDecimal('34.56', $projectAfterPlanA['FREIGHT'], 'project freight combines plan A final and plan B planned values');
    assertSameValue('BUYER_PAY', (string)$projectAfterPlanA['FREIGHT_CATEGORY'], 'project freight category follows first effective plan after plan A');

    // Force a failure after invoice/item/delivery writes have begun. The existing
    // stock code rejects a logically deleted unique inventory row. Every earlier
    // side effect and the plan status must roll back together.
    Db::name('inventory')->where('ID', $ids['inventoryA'])->update(['DELETE_FLAG' => 'DELETED']);
    $planBInput = shipmentInput(
        $ids,
        $planRows[1],
        $ids['planProject'],
        $projectItemId,
        $ids['planProduct'],
        $ids['warehouseA'],
        4,
        'shared-request-' . $seed,
        'B'
    );
    $planBInput['freightCategory'] = 'BUYER_PAY';
    $planBInput['freight'] = '34.56';
    assertThrows(
        fn (): array => $workflowService->startProjectDeliveryProcess($planBInput, $payload),
        409,
        'failed inventory mutation must roll back the whole plan shipment'
    );
    assertSameValue(1, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'failed plan B invoice rollback');
    assertSameValue(1, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'failed plan B delivery record rollback');
    assertDecimal(6, requiredRow('biz_sale_project_product_item', ['ID' => $projectItemId], 'project item after failed plan B')['DELIVERY'], 'failed plan B item quantity rollback');
    $projectAfterFailedPlanB = requiredRow('biz_sale_project', ['ID' => $ids['planProject']], 'project after failed plan B');
    assertSameValue('PARTIALLY_SHIPPED', (string)$projectAfterFailedPlanB['PROJECT_STATE'], 'failed plan B project state rollback');
    assertDecimal('34.56', $projectAfterFailedPlanB['FREIGHT'], 'failed plan B rolls back project freight projection');
    assertSameValue('BUYER_PAY', (string)$projectAfterFailedPlanB['FREIGHT_CATEGORY'], 'failed plan B rolls back project freight category projection');
    $planBAfterFailure = requiredRow(DELIVERY_PLAN_TABLE, ['ID' => $planRows[1]['ID']], 'plan B after failed shipment');
    assertSameValue('WAIT_DELIVER', (string)$planBAfterFailure['STATUS'], 'failed plan B remains pending');
    assertTrue(trim((string)($planBAfterFailure['INVOICE_ID'] ?? '')) === '', 'failed plan B has no invoice link');
    assertTrue(trim((string)($planBAfterFailure['PROCESS_ID'] ?? '')) === '', 'failed plan B has no process link');
    assertSameValue('SELLER_PAY', (string)$planBAfterFailure['FREIGHT_CATEGORY'], 'failed plan B keeps planned freight category');
    assertDecimal('22.22', $planBAfterFailure['FREIGHT'], 'failed plan B keeps planned freight');
    assertDecimal(100, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after failed shipment')['CURRENT_COUNT'], 'failed plan B inventory rollback');
    assertDecimal(94, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'inventory B after failed shipment')['CURRENT_COUNT'], 'failed plan B leaves prior shipment stock');

    Db::name('inventory')->where('ID', $ids['inventoryA'])->update(['DELETE_FLAG' => 'NOT_DELETE']);
    $planBResult = $workflowService->startProjectDeliveryProcess($planBInput, $payload);
    assertSameValue(true, (bool)$planBResult['autoApproved'], 'plan B auto approval');
    assertTrue(
        (string)$planAResult['processInstanceId'] !== (string)$planBResult['processInstanceId'],
        'same client request id on different plans must produce different process ids'
    );
    assertSameValue(2, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'two plans create two invoices');
    assertSameValue(2, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'two plans create two delivery records');
    assertDecimal(96, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after plan B')['CURRENT_COUNT'], 'plan B final warehouse A is decremented');
    assertDecimal(94, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'inventory B after plan B')['CURRENT_COUNT'], 'plan B leaves plan A warehouse stock unchanged');
    assertDecimal(10, requiredRow('biz_sale_project_product_item', ['ID' => $projectItemId], 'project item after plan B')['DELIVERY'], 'two-plan delivered quantity');
    assertSameValue('SHIPPED', (string)requiredRow('biz_sale_project_product_item', ['ID' => $projectItemId], 'project item final state')['STATE'], 'project item final state');
    $projectAfterPlanB = requiredRow('biz_sale_project', ['ID' => $ids['planProject']], 'planned project final state');
    assertSameValue('SHIPPED', (string)$projectAfterPlanB['PROJECT_STATE'], 'planned project final state');
    assertDecimal('46.90', $projectAfterPlanB['FREIGHT'], 'project freight totals both final shipment values');
    assertSameValue('BUYER_PAY', (string)$projectAfterPlanB['FREIGHT_CATEGORY'], 'project freight category follows first effective plan after plan B');

    $planBRow = requiredRow(DELIVERY_PLAN_TABLE, ['ID' => $planRows[1]['ID']], 'plan B after shipment');
    assertSameValue('SHIPPED', (string)$planBRow['STATUS'], 'plan B shipped status');
    assertSameValue((string)$planBResult['invoiceId'], (string)$planBRow['INVOICE_ID'], 'plan B invoice link');
    assertSameValue('BUYER_PAY', (string)$planBRow['FREIGHT_CATEGORY'], 'plan B actual freight category overwrites planned value');
    assertDecimal('34.56', $planBRow['FREIGHT'], 'plan B actual freight overwrites planned value');
    $planBInvoice = requiredRow('biz_sale_project_invoice', ['ID' => $planBResult['invoiceId']], 'plan B invoice');
    assertSameValue('BUYER_PAY', (string)$planBInvoice['FREIGHT_CATEGORY'], 'plan B invoice uses actual freight category');
    assertDecimal('34.56', $planBInvoice['FREIGHT'], 'plan B invoice uses actual freight');
    $planBInvoiceItem = requiredRow('biz_sale_project_invoice_item', ['INVOICE_ID' => $planBResult['invoiceId']], 'plan B invoice item');
    assertSameValue($ids['warehouseA'], (string)$planBInvoiceItem['WAREHOUSES_ID'], 'plan B invoice item uses final warehouse A');
    $planBDeliveryRecord = requiredRow('delivery_record', ['PROCESS_ID' => $planBResult['processInstanceId']], 'plan B delivery record');
    assertSameValue($ids['warehouseA'], (string)$planBDeliveryRecord['WAREHOUSES_ID'], 'plan B delivery record uses final warehouse A');

    // Retrying the same client request must return the same durable result without
    // another invoice, delivery record, quantity increment, or stock decrement.
    $retryResult = $workflowService->startProjectDeliveryProcess($planBInput, $payload);
    assertSameValue((string)$planBResult['processInstanceId'], (string)$retryResult['processInstanceId'], 'plan B idempotent process id');
    assertSameValue((string)$planBResult['invoiceId'], (string)$retryResult['invoiceId'], 'plan B idempotent invoice id');
    assertSameValue(2, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['planProject']]), 'idempotent invoice count');
    assertSameValue(2, rowCount('delivery_record', ['OBJECT_ID' => $ids['planProject']]), 'idempotent delivery record count');
    assertDecimal(96, requiredRow('inventory', ['ID' => $ids['inventoryA']], 'inventory A after retry')['CURRENT_COUNT'], 'idempotent inventory A count');
    assertDecimal(94, requiredRow('inventory', ['ID' => $ids['inventoryB']], 'inventory B after retry')['CURRENT_COUNT'], 'idempotent inventory B count');
    assertDecimal(10, requiredRow('biz_sale_project_product_item', ['ID' => $projectItemId], 'project item after retry')['DELIVERY'], 'idempotent delivered quantity');
    $projectAfterRetry = requiredRow('biz_sale_project', ['ID' => $ids['planProject']], 'project after idempotent retry');
    assertDecimal('46.90', $projectAfterRetry['FREIGHT'], 'idempotent retry keeps project freight projection');
    assertSameValue('BUYER_PAY', (string)$projectAfterRetry['FREIGHT_CATEGORY'], 'idempotent retry keeps project freight category projection');

    // A historical project with no plan rows must continue down the original
    // free-form delivery path.
    assertSameValue(0, rowCount(DELIVERY_PLAN_TABLE, ['PROJECT_ID' => $ids['legacyProject']]), 'legacy project has no plans');
    $legacyResult = $workflowService->startProjectDeliveryProcess(legacyShipmentInput($ids), $payload);
    assertSameValue(true, (bool)$legacyResult['autoApproved'], 'legacy delivery auto approval');
    assertSameValue(1, rowCount('biz_sale_project_invoice', ['PROJECT_ID' => $ids['legacyProject']]), 'legacy project invoice count');
    assertSameValue(1, rowCount('delivery_record', ['OBJECT_ID' => $ids['legacyProject']]), 'legacy project delivery record count');
    assertDecimal(48, requiredRow('inventory', ['ID' => $ids['legacyInventory']], 'legacy inventory after shipment')['CURRENT_COUNT'], 'legacy inventory decrement');
    assertDecimal(2, requiredRow('biz_sale_project_product_item', ['ID' => $ids['legacyItem']], 'legacy item after shipment')['DELIVERY'], 'legacy delivered quantity');
    assertSameValue('PARTIALLY_SHIPPED', (string)requiredRow('biz_sale_project', ['ID' => $ids['legacyProject']], 'legacy project after shipment')['PROJECT_STATE'], 'legacy project state');

    fwrite(STDOUT, "sale project delivery plan service smoke passed\n");
} finally {
    cleanupFixtures($ids);
}
