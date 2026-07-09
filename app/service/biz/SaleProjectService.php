<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only sale-project queries compatible with Java BizSaleProjectController.
 */
class SaleProjectService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const PROJECT_PLAY = 'PROJECT_PLAY';
    private const RETURN_AND_REFUND = 'ReturnAndRefund';
    private const PUBLIC_VISIBILITY = 'PUBLIC';
    private const PRIVATE_VISIBILITY = 'PRIVATE';
    private const FOLLOW_STATE = 'FOLLOW';
    private const PENDING_APPROVAL_STATE = 'PENDING_APPROVAL';
    private const WAIT_DELIVER_STATE = 'WAIT_DELIVER';
    private const DISCARD_STATE = 'DISCARD';
    private const PAID_PLAY_STATE = 'PAID';
    private const UNPAID_PLAY_STATE = 'UNPAID';
    private const PARTIALLY_PAID_PLAY_STATE = 'PARTIALLY_PAID';
    private const SHIPPED_STATE = 'SHIPPED';
    private const COMPLETED_STATE = 'COMPLETED';
    private const PARTIALLY_SHIPPED_STATE = 'PARTIALLY_SHIPPED';
    private const SHIPPED_PRODUCT_ITEM_STATE = 'SHIPPED';
    private const PART_WAIT_DELIVER_PRODUCT_ITEM_STATE = 'PART_WAIT_DELIVER';
    private const PROCESS_SALE_PROJECT_DELIVERY = 'Process_sale_project_delivery';
    private const DELIVERY_CATEGORY_OUT = 'OUT';
    private const INIT_PRICE_FIELD_LABEL = "\u{6210}\u{4EA4}\u{91D1}\u{989D}";
    private const PURCHASE_ORDER_SETTLEMENT_COMPLETED = 'COMPLETED';
    private const DIRECT_PROJECT_CATEGORY = 'DIRECT';
    private const PUBLIC_FOR_REIMBURSEMENT = 'PUBLIC_FOR_REIMBURSEMENT';
    private const CUSTOMER_TYPE_OLD = 'OLD';
    private const CUSTOMER_STATUS_ENABLE = 'ENABLE';
    private const USER_STATUS_ENABLE = 'ENABLE';
    private const PRODUCT_STATUS_ENABLE = 'ENABLE';
    private const PRODUCT_ITEM_CATEGORY_INIT = 'INIT';
    private const PRODUCT_ITEM_CATEGORY_REISSUE_ORDER = 'REISSUE_ORDER';
    private const PRODUCT_ITEM_STATE_WAIT_DELIVER = 'WAIT_DELIVER';
    private const KIT_PRODUCT = 'KIT_PRODUCT';
    private const KIT_PRODUCT_DATA = 'KIT_PRODUCT_DATA';
    private const SALE_PROJECT_FILE_CATEGORY = 'SALE_PROJECT';
    private const INVOICING_STATE_WAIT = 'INVOICING_STATE_WAIT';
    private const INVOICING_CATEGORIES = ['SpecialTicket', 'GeneralTicket'];

    private const PROJECT_FIELDS = <<<SQL
p.ID AS ID,
p.CUSTOMER AS CUSTOMER,
p.PROJECT_NAME AS PROJECT_NAME,
p.PROJECT_STATE AS PROJECT_STATE,
p.PLAY_STATE AS PLAY_STATE,
p.VISIBILITY AS VISIBILITY,
p.INIT_PRICE AS INIT_PRICE,
p.TOTAL_PRICE AS TOTAL_PRICE,
p.AMOUNT_COLLECTED AS AMOUNT_COLLECTED,
p.PROJECT_CATEGORY AS PROJECT_CATEGORY,
p.USER AS PROJECT_USER,
p.ORG AS ORG,
p.REMARK AS REMARK,
p.DELETE_FLAG AS DELETE_FLAG,
p.CREATE_TIME AS CREATE_TIME,
p.CREATE_USER AS CREATE_USER,
p.UPDATE_TIME AS UPDATE_TIME,
p.UPDATE_USER AS UPDATE_USER,
p.TENANT_ID AS TENANT_ID,
p.VERSION AS VERSION,
p.CONSIGNEE AS CONSIGNEE,
p.PHONE AS PHONE,
p.UNIT AS UNIT,
p.ADDRESS AS ADDRESS,
p.LOGISTICS_CATEGORY AS LOGISTICS_CATEGORY,
p.DELIVERY_NOTE AS DELIVERY_NOTE,
p.PROCESS_ID AS PROCESS_ID,
p.SPECIMEN_CATEGORY AS SPECIMEN_CATEGORY,
p.SPECIMEN_NAME AS SPECIMEN_NAME,
p.AREA AS AREA,
p.DETAILS_ADDRESS AS DETAILS_ADDRESS,
p.PROJECT_CODE AS PROJECT_CODE,
p.ACCOUNT_ID AS ACCOUNT_ID,
p.PAYER_CATEGORY AS PAYER_CATEGORY,
p.FREIGHT AS FREIGHT,
p.FREIGHT_CATEGORY AS FREIGHT_CATEGORY,
p.COMPLETION_DATE AS COMPLETION_DATE,
p.REBATE_AMOUNT AS REBATE_AMOUNT,
p.DEAL_AMOUNT AS DEAL_AMOUNT,
p.HISTORY_AMOUNT AS HISTORY_AMOUNT,
p.TOTAL_RETURN_AMOUNT AS TOTAL_RETURN_AMOUNT,
p.TOTAL_REFUND_AMOUNT AS TOTAL_REFUND_AMOUNT,
p.REPEAL_CONTENT AS REPEAL_CONTENT,
p.special_type AS SPECIAL_TYPE,
c.NAME AS CUSTOMER_NAME,
c.ADDRESS AS CUSTOMER_ADDRESS,
c.SOURCE_TYPE AS CUSTOMER_SOURCE_TYPE,
c.CUSTOM_TYPE AS CUSTOM_TYPE,
u.NAME AS HEAD_NAME,
u.PHONE AS HEAD_PHONE,
org.NAME AS ORG_NAME,
a.ACCOUNT_NAME AS ACCOUNT_NAME
SQL;

    private const PRODUCT_ITEM_FIELDS = <<<SQL
i.ID AS ID,
i.PROJECT_ID AS PROJECT_ID,
i.PRODUCT_ID AS PRODUCT_ID,
i.CATEGORY AS CATEGORY,
i.STATE AS STATE,
i.NUMBER AS NUMBER,
i.DELIVERY AS DELIVERY,
i.UNIT_PRICE AS UNIT_PRICE,
i.DISCOUNT_RATE AS DISCOUNT_RATE,
i.PRICE AS PRICE,
i.REMARK AS REMARK,
i.EXT_JSON AS EXT_JSON,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
i.VERSION AS VERSION,
i.PROJECT_REISSUE_ORDER_ID AS PROJECT_REISSUE_ORDER_ID,
i.MARK AS MARK,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.CATEGORY AS PRODUCT_SYS_CATEGORY,
p.SPECS AS SPECS,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.MIN_PRICE AS MIN_PRICE
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'p.ID',
        'customer' => 'p.CUSTOMER',
        'customerName' => 'c.NAME',
        'projectName' => 'p.PROJECT_NAME',
        'projectCode' => 'p.PROJECT_CODE',
        'projectState' => 'p.PROJECT_STATE',
        'playState' => 'p.PLAY_STATE',
        'visibility' => 'p.VISIBILITY',
        'initPrice' => 'p.INIT_PRICE',
        'totalPrice' => 'p.TOTAL_PRICE',
        'amountCollected' => 'p.AMOUNT_COLLECTED',
        'projectCategory' => 'p.PROJECT_CATEGORY',
        'user' => 'p.USER',
        'headName' => 'u.NAME',
        'org' => 'p.ORG',
        'orgName' => 'org.NAME',
        'completionDate' => 'p.COMPLETION_DATE',
        'createTime' => 'p.CREATE_TIME',
        'updateTime' => 'p.UPDATE_TIME',
        'tenantId' => 'p.TENANT_ID',
        'specialType' => 'p.special_type',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        return $this->pageResult($filters, $payload);
    }

    public function casePage(array $filters = [], array $payload = []): array
    {
        $filters['isCase'] = true;

        return $this->pageResult($filters, $payload);
    }

    public function operationPage(array $filters = [], array $payload = []): array
    {
        return $this->pageResult($filters, $payload);
    }

    public function publicPage(array $filters = [], array $payload = []): array
    {
        $filters['visibility'] = self::PUBLIC_VISIBILITY;

        return $this->pageResult($filters, $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDetail(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->projectQuery($filters, $payload), $filters)
            ->field(self::PROJECT_FIELDS)
            ->select()
            ->toArray();
        $projects = $this->projectRows($rows);

        return $this->detailsForProjects($projects, $payload);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field(self::PROJECT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            if (!$this->canReadProjectFromWorkflow($id, $payload)) {
                throw new RuntimeException('sale project not found', 404);
            }

            $row = $this->projectQuery(['id' => $id], $payload, false)
                ->field(self::PROJECT_FIELDS)
                ->find();
            if (!is_array($row) || $row === []) {
                throw new RuntimeException('sale project not found', 404);
            }
        }

        return $this->detailsForProjects($this->projectRows([$row]), $payload)[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function product(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field('p.ID AS ID')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $this->productItemsByProjectIds([$id], $payload)[$id] ?? [];
    }

    public function cost(string $id, array $payload = []): int|float
    {
        $total = 0.0;
        foreach ($this->costDetails($id, $payload)['items'] as $item) {
            $total += $this->number($item['amount'] ?? 0) * $this->number($item['avgUnitAmount'] ?? 0);
        }

        return $this->decimal($total) ?? 0;
    }

    public function costDetails(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field('p.ID AS ID')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        $productItems = $this->productItemsByProjectIds([$id], $payload)[$id] ?? [];
        $returnOrders = $this->returnOrdersWithProductList($id, $payload);
        $productAmounts = [];
        $productNames = [];
        $projectProductItemsById = [];

        foreach ($productItems as $item) {
            $projectProductItemsById[(string)$item['id']] = $item;
            $this->addProductCostAmount($productAmounts, $productNames, $item, 1);
        }

        foreach ($returnOrders as $returnOrder) {
            foreach ($returnOrder['productList'] ?? [] as $returnItem) {
                $item = $projectProductItemsById[(string)($returnItem['projectProductItemId'] ?? '')] ?? null;
                if ($item === null) {
                    continue;
                }

                $this->addProductCostAmount($productAmounts, $productNames, $item, -1);
            }
        }

        $avgUnitAmounts = $this->averagePurchaseUnitAmounts(array_keys($productAmounts), $payload);
        $items = [];
        foreach ($productAmounts as $productId => $amount) {
            $items[] = [
                'transMap' => [],
                'productId' => $productId,
                'productName' => $productNames[$productId] ?? null,
                'amount' => $this->decimal($amount) ?? 0,
                'avgUnitAmount' => $this->decimal($avgUnitAmounts[$productId] ?? 0) ?? 0,
            ];
        }

        return [
            'items' => $items,
            'productItems' => $productItems,
            'returnOrders' => $returnOrders,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function add(array $input, array $payload = []): array
    {
        $data = $this->saleProjectAddInput($input);
        $productList = $this->submittedProductList($input);

        return Db::transaction(function () use ($input, $payload, $data, $productList): array {
            $customer = $this->assertCustomerWritableForProject((string)$data['CUSTOMER'], $payload);
            $currentUser = $this->currentUserRow($payload);
            $userId = $this->currentUserId($payload);
            $orgId = trim((string)($currentUser['ORG_ID'] ?? $payload['org_id'] ?? $payload['orgId'] ?? ''));
            if ($orgId === '') {
                $orgId = trim((string)($customer['ORG'] ?? ''));
            }
            if ($userId === '') {
                $userId = trim((string)($customer['USER'] ?? ''));
            }

            $projectId = $this->newId();
            $now = date('Y-m-d H:i:s');
            $tenantId = $this->writeTenantId($input, $payload, $customer);
            $this->ensureCustomerBusinessLicense($customer, $input, $tenantId, $userId, $now);
            $row = [
                'ID' => $projectId,
                'CUSTOMER' => (string)$data['CUSTOMER'],
                'PROJECT_NAME' => (string)$data['PROJECT_NAME'],
                'PROJECT_STATE' => self::FOLLOW_STATE,
                'PLAY_STATE' => self::UNPAID_PLAY_STATE,
                'VISIBILITY' => self::PRIVATE_VISIBILITY,
                'INIT_PRICE' => '0.00',
                'TOTAL_PRICE' => '0.00',
                'AMOUNT_COLLECTED' => '0.00',
                'PROJECT_CATEGORY' => (string)$data['PROJECT_CATEGORY'],
                'USER' => $userId !== '' ? $userId : null,
                'ORG' => $orgId !== '' ? $orgId : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'DEAL_AMOUNT' => 0,
                'HISTORY_AMOUNT' => '0.00',
                'TOTAL_RETURN_AMOUNT' => '0.00',
                'TOTAL_REFUND_AMOUNT' => '0.00',
            ];
            foreach (['REMARK', 'AREA', 'DETAILS_ADDRESS', 'PROJECT_CODE', 'SPECIMEN_CATEGORY', 'SPECIMEN_NAME'] as $column) {
                if (array_key_exists($column, $data)) {
                    $row[$column] = $data[$column];
                }
            }

            Db::name('biz_sale_project')->insert($row);
            if ($productList !== null) {
                $this->syncProductItems($projectId, $productList, $tenantId, $payload, $now, $userId);
            }

            return ['id' => $projectId];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function edit(array $input, array $payload = []): ?array
    {
        [$projectId, $updates] = $this->saleProjectEditInput($input);
        $productList = $this->submittedProductList($input);

        return Db::transaction(function () use ($projectId, $updates, $payload, $productList): ?array {
            $project = $this->projectQuery(['id' => $projectId], $payload)
                ->field('p.ID AS ID, p.PROJECT_STATE AS PROJECT_STATE, p.TENANT_ID AS TENANT_ID')
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }
            if ((string)($project['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is not FOLLOW', 400);
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $updates['UPDATE_TIME'] = $now;
            $updates['UPDATE_USER'] = $currentUserId ?: null;
            $updates['VERSION'] = Db::raw('VERSION + 1');

            $updateQuery = Db::name('biz_sale_project')
                ->where('ID', $projectId)
                ->where('PROJECT_STATE', self::FOLLOW_STATE);
            $this->whereNotDeleted($updateQuery, 'DELETE_FLAG');
            $tenantId = trim((string)($project['TENANT_ID'] ?? ''));
            if ($tenantId !== '') {
                $updateQuery->where('TENANT_ID', $tenantId);
            }
            $updateQuery->update($updates);
            if ($productList !== null) {
                $this->syncProductItems($projectId, $productList, $tenantId, $payload, $now, $currentUserId);
            }

            return null;
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function addProductItem(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInputString($input, ['projectId', 'PROJECT_ID'], 'projectId');
        $this->assertMaxLength($projectId, 'projectId', 20);

        return Db::transaction(function () use ($input, $payload, $projectId): array {
            $project = $this->standaloneProjectForProductItemWrite($projectId, $payload);
            if ((string)($project['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is not FOLLOW', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $project);
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $itemInput = $input;
            unset($itemInput['id'], $itemInput['ID']);
            $itemInput['projectId'] = $projectId;

            $items = $this->normalizedProductItems([$itemInput], $tenantId, [], $payload);
            $item = $items[0];
            $itemId = $this->newId();
            $this->insertProductItem($projectId, $itemId, $item, $tenantId, $now, $currentUserId);
            $this->replaceProductItemRelations($itemId, $item['children'], $tenantId, $now, $currentUserId);

            return [
                'id' => $itemId,
                'projectId' => $projectId,
                'productId' => $item['productId'],
                'relationCount' => count($item['children']),
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function addDeliveryInvoice(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInputString($input, ['projectId', 'PROJECT_ID', 'bizSaleProjectId'], 'projectId');
        $this->assertMaxLength($projectId, 'projectId', 20);
        $processId = $this->requiredInputString($input, ['processId', 'PROCESS_ID'], 'processId');
        $this->assertMaxLength($processId, 'processId', 80);
        $productItemList = $this->deliveryProductItemListInput($input);

        return Db::transaction(function () use ($input, $payload, $projectId, $processId, $productItemList): array {
            $project = $this->projectQuery(['id' => $projectId], $payload)
                ->field(
                    'p.ID AS ID,' .
                    'p.PROJECT_STATE AS PROJECT_STATE,' .
                    'p.PLAY_STATE AS PLAY_STATE,' .
                    'p.TENANT_ID AS TENANT_ID'
                )
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }
            if ((string)($project['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is FOLLOW', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $project);
            $existingQuery = Db::name('biz_sale_project_invoice')->where('PROCESS_ID', $processId);
            $this->whereNotDeleted($existingQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $existingQuery->where('TENANT_ID', $tenantId);
            }
            if ((int)$existingQuery->count() > 0) {
                throw new RuntimeException('sale project invoice processId already exists', 400);
            }

            $items = $this->workflowDeliveryItems($projectId, $productItemList, $tenantId, true);
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            if ($currentUserId === '') {
                $currentUserId = $this->optionalInputString($input, ['createdUser', 'CREATE_USER', 'createUser']) ?? '';
            }

            $operator = $this->requiredInputString($input, ['operator', 'OPERATOR'], 'operator');
            $this->assertMaxLength($operator, 'operator', 20);
            $freightTime = $this->workflowDate($this->inputValue($input, ['freightTime', 'FREIGHT_TIME']), 'freightTime');
            $invoiceId = $this->newId();

            Db::name('biz_sale_project_invoice')->insert([
                'ID' => $invoiceId,
                'PROJECT_ID' => $projectId,
                'PROCESS_ID' => $processId,
                'CONSIGNEE' => $this->workflowRequiredText($this->inputValue($input, ['consignee', 'CONSIGNEE']), 'consignee', 255),
                'LOGISTICS_CATEGORY' => $this->workflowRequiredText($this->inputValue($input, ['logisticsCategory', 'LOGISTICS_CATEGORY']), 'logisticsCategory', 20),
                'PHONE' => $this->workflowRequiredText($this->inputValue($input, ['phone', 'PHONE']), 'phone', 255),
                'LOGISTICS_ID' => $this->workflowRequiredText($this->inputValue($input, ['logisticsId', 'LOGISTICS_ID']), 'logisticsId', 20),
                'FREIGHT' => $this->workflowMoney($this->inputValue($input, ['freight', 'FREIGHT']), 'freight', false),
                'FREIGHT_TIME' => $freightTime,
                'FREIGHT_CATEGORY' => $this->workflowRequiredText($this->inputValue($input, ['freightCategory', 'FREIGHT_CATEGORY']), 'freightCategory', 20),
                'UNIT' => $this->workflowRequiredText($this->inputValue($input, ['unit', 'UNIT']), 'unit', 100),
                'ADDRESS' => $this->workflowRequiredText($this->inputValue($input, ['address', 'ADDRESS']), 'address', 100),
                'REMARK' => $this->workflowOptionalText($this->inputValue($input, ['remark', 'REMARK']), 'remark', 4000),
                'CREATE_TIME' => $now,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'EXT_JSON' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : (string)($project['TENANT_ID'] ?? '1'),
                'OPERATOR' => $operator,
            ]);

            $invoiceItemRows = [];
            foreach ($items as $item) {
                $invoiceItemRows[] = [
                    'ID' => $this->newId(),
                    'INVOICE_ID' => $invoiceId,
                    'PROJECT_PRODUCT_ITEM_ID' => $item['projectProductItemId'],
                    'WAREHOUSES_ID' => $item['warehousesId'],
                    'AMOUNT' => $item['amount'],
                    'REMARK' => $item['remark'] ?? '',
                    'CREATE_TIME' => $now,
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'UPDATE_TIME' => null,
                    'EXT_JSON' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $tenantId !== '' ? $tenantId : (string)($project['TENANT_ID'] ?? '1'),
                ];
            }
            Db::name('biz_sale_project_invoice_item')->insertAll($invoiceItemRows);

            foreach ($items as $item) {
                $projectItem = $item['projectItem'];
                $nextDelivery = (float)$projectItem['DELIVERY'] + (float)$item['amount'];
                $number = (float)$projectItem['NUMBER'];
                $nextState = abs($nextDelivery - $number) < 0.000001
                    ? self::SHIPPED_PRODUCT_ITEM_STATE
                    : self::PART_WAIT_DELIVER_PRODUCT_ITEM_STATE;

                $itemUpdate = Db::name('biz_sale_project_product_item')
                    ->where('ID', (string)$projectItem['ID'])
                    ->where('PROJECT_ID', $projectId);
                $this->whereNotDeleted($itemUpdate, 'DELETE_FLAG');
                if ($tenantId !== '') {
                    $itemUpdate->where('TENANT_ID', $tenantId);
                }
                $itemUpdate->update([
                    'DELIVERY' => $this->decimalStorage($nextDelivery),
                    'STATE' => $nextState,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);
            }

            $projectState = $this->correctedProjectState(
                (string)($project['PLAY_STATE'] ?? ''),
                $this->allProductItemsShipped($projectId, $tenantId)
            );
            $projectUpdate = Db::name('biz_sale_project')->where('ID', $projectId);
            $this->whereNotDeleted($projectUpdate, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $projectUpdate->where('TENANT_ID', $tenantId);
            }
            $projectUpdate->update([
                'PROJECT_STATE' => $projectState,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
            ]);

            return [
                'id' => $invoiceId,
                'projectId' => $projectId,
                'invoiceId' => $invoiceId,
                'invoiceItemCount' => count($invoiceItemRows),
                'projectState' => $projectState,
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function editDeliveryInvoice(array $input, array $payload = []): array
    {
        $id = $this->requiredInputString($input, ['id', 'ID'], 'id');
        $this->assertMaxLength($id, 'id', 20);
        $projectId = $this->requiredInputString($input, ['projectId', 'PROJECT_ID', 'bizSaleProjectId'], 'projectId');
        $this->assertMaxLength($projectId, 'projectId', 20);
        $processId = $this->requiredInputString($input, ['processId', 'PROCESS_ID'], 'processId');
        $this->assertMaxLength($processId, 'processId', 80);

        return Db::transaction(function () use ($input, $payload, $id, $projectId, $processId): array {
            $invoice = $this->deliveryInvoiceForWrite($id, $payload);
            if ((string)$invoice['PROJECT_ID'] !== $projectId) {
                throw new RuntimeException('invalid projectId', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $invoice);
            $existingQuery = Db::name('biz_sale_project_invoice')
                ->where('PROCESS_ID', $processId)
                ->where('ID', '<>', $id);
            $this->whereNotDeleted($existingQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $existingQuery->where('TENANT_ID', $tenantId);
            }
            if ((int)$existingQuery->count() > 0) {
                throw new RuntimeException('sale project invoice processId already exists', 400);
            }

            $oldProcessId = (string)($invoice['PROCESS_ID'] ?? '');
            if ($processId !== $oldProcessId && $this->invoiceHasDeliveryRecords($oldProcessId, $tenantId)) {
                throw new RuntimeException('sale project invoice has delivery records', 400);
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            Db::name('biz_sale_project_invoice')
                ->where('ID', $id)
                ->update([
                    'PROCESS_ID' => $processId,
                    'CONSIGNEE' => $this->workflowRequiredText($this->inputValue($input, ['consignee', 'CONSIGNEE']), 'consignee', 255),
                    'LOGISTICS_CATEGORY' => $this->workflowRequiredText($this->inputValue($input, ['logisticsCategory', 'LOGISTICS_CATEGORY']), 'logisticsCategory', 20),
                    'PHONE' => $this->workflowRequiredText($this->inputValue($input, ['phone', 'PHONE']), 'phone', 255),
                    'LOGISTICS_ID' => $this->workflowRequiredText($this->inputValue($input, ['logisticsId', 'LOGISTICS_ID']), 'logisticsId', 20),
                    'FREIGHT' => $this->workflowMoney($this->inputValue($input, ['freight', 'FREIGHT']), 'freight', false),
                    'FREIGHT_TIME' => $this->workflowDate($this->inputValue($input, ['freightTime', 'FREIGHT_TIME']), 'freightTime'),
                    'FREIGHT_CATEGORY' => $this->workflowRequiredText($this->inputValue($input, ['freightCategory', 'FREIGHT_CATEGORY']), 'freightCategory', 20),
                    'UNIT' => $this->workflowRequiredText($this->inputValue($input, ['unit', 'UNIT']), 'unit', 100),
                    'ADDRESS' => $this->workflowRequiredText($this->inputValue($input, ['address', 'ADDRESS']), 'address', 100),
                    'REMARK' => $this->workflowRequiredText($this->inputValue($input, ['remark', 'REMARK']), 'remark', 4000),
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                ]);

            return [
                'id' => $id,
                'projectId' => $projectId,
                'processId' => $processId,
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function deleteDeliveryInvoice(array $input, array $payload = []): array
    {
        $ids = $this->deliveryInvoiceDeleteInput($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $invoicesById = $this->deliveryInvoiceRowsForWrite($ids, $payload);
            $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
            foreach ($invoicesById as $invoice) {
                $tenantId = trim((string)($invoice['TENANT_ID'] ?? $invoice['PROJECT_TENANT_ID'] ?? ''));
                if ($payloadTenantId !== '' && $tenantId !== $payloadTenantId) {
                    throw new RuntimeException('tenant mismatch', 403);
                }
                if ($this->invoiceHasDeliveryRecords((string)($invoice['PROCESS_ID'] ?? ''), $tenantId)) {
                    throw new RuntimeException('sale project invoice has delivery records', 400);
                }
            }

            $itemQuery = Db::name('biz_sale_project_invoice_item')
                ->whereIn('INVOICE_ID', $ids)
                ->field('ID, INVOICE_ID, PROJECT_PRODUCT_ITEM_ID, AMOUNT, TENANT_ID')
                ->lock(true);
            $this->whereNotDeleted($itemQuery, 'DELETE_FLAG');
            if ($payloadTenantId !== '') {
                $itemQuery->where('TENANT_ID', $payloadTenantId);
            }
            $invoiceItems = $itemQuery->select()->toArray();

            $projectItemIds = [];
            foreach ($invoiceItems as $item) {
                $projectItemIds[] = (string)$item['PROJECT_PRODUCT_ITEM_ID'];
            }
            $projectItemIds = array_values(array_unique(array_filter($projectItemIds)));

            $projectItemsById = [];
            if ($projectItemIds !== []) {
                $projectItemQuery = Db::name('biz_sale_project_product_item')
                    ->whereIn('ID', $projectItemIds)
                    ->field('ID, PROJECT_ID, NUMBER, DELIVERY, STATE, TENANT_ID, VERSION')
                    ->lock(true);
                $this->whereNotDeleted($projectItemQuery, 'DELETE_FLAG');
                if ($payloadTenantId !== '') {
                    $projectItemQuery->where('TENANT_ID', $payloadTenantId);
                }
                foreach ($projectItemQuery->select()->toArray() as $row) {
                    $projectItemsById[(string)$row['ID']] = $row;
                }
                if (count($projectItemsById) !== count($projectItemIds)) {
                    throw new RuntimeException('project product item not found', 404);
                }
            }

            $reverseByProjectItemId = [];
            $projectRowsById = [];
            foreach ($invoicesById as $invoice) {
                $projectRowsById[(string)$invoice['PROJECT_ID']] = $invoice;
            }
            foreach ($invoiceItems as $item) {
                $invoiceId = (string)$item['INVOICE_ID'];
                $invoice = $invoicesById[$invoiceId] ?? null;
                if (!is_array($invoice)) {
                    throw new RuntimeException('sale project invoice item not found', 404);
                }
                $projectItemId = (string)$item['PROJECT_PRODUCT_ITEM_ID'];
                $projectItem = $projectItemsById[$projectItemId] ?? null;
                if (!is_array($projectItem)) {
                    throw new RuntimeException('project product item not found', 404);
                }
                if ((string)$projectItem['PROJECT_ID'] !== (string)$invoice['PROJECT_ID']) {
                    throw new RuntimeException('invalid invoice item project product item', 400);
                }

                $reverseByProjectItemId[$projectItemId] = ($reverseByProjectItemId[$projectItemId] ?? 0.0) + (float)$item['AMOUNT'];
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            foreach ($reverseByProjectItemId as $projectItemId => $amount) {
                $projectItem = $projectItemsById[$projectItemId];
                $nextDelivery = (float)$projectItem['DELIVERY'] - (float)$amount;
                if ($nextDelivery < -0.000001) {
                    throw new RuntimeException('delivery reverse would underflow project product item', 400);
                }
                if ($nextDelivery < 0.000001) {
                    $nextDelivery = 0.0;
                }
                $number = (float)$projectItem['NUMBER'];
                if ($nextDelivery <= 0.000001) {
                    $nextState = self::PRODUCT_ITEM_STATE_WAIT_DELIVER;
                } elseif ($nextDelivery + 0.000001 >= $number) {
                    $nextState = self::SHIPPED_PRODUCT_ITEM_STATE;
                } else {
                    $nextState = self::PART_WAIT_DELIVER_PRODUCT_ITEM_STATE;
                }

                Db::name('biz_sale_project_product_item')
                    ->where('ID', $projectItemId)
                    ->update([
                        'DELIVERY' => $this->decimalStorage($nextDelivery),
                        'STATE' => $nextState,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                        'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                    ]);
            }

            Db::name('biz_sale_project_invoice_item')
                ->whereIn('INVOICE_ID', $ids)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                ]);
            Db::name('biz_sale_project_invoice')
                ->whereIn('ID', $ids)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                ]);

            $projectStates = [];
            foreach ($projectRowsById as $projectId => $project) {
                $tenantId = trim((string)($project['TENANT_ID'] ?? $project['PROJECT_TENANT_ID'] ?? ''));
                $projectState = $this->correctedProjectStateAfterDeliveryCorrection(
                    $projectId,
                    $tenantId,
                    (string)($project['PROJECT_PLAY_STATE'] ?? '')
                );
                $projectQuery = Db::name('biz_sale_project')->where('ID', $projectId);
                $this->whereNotDeleted($projectQuery, 'DELETE_FLAG');
                if ($tenantId !== '') {
                    $projectQuery->where('TENANT_ID', $tenantId);
                }
                $projectQuery->update([
                    'PROJECT_STATE' => $projectState,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);
                $projectStates[$projectId] = $projectState;
            }

            return [
                'ids' => $ids,
                'count' => count($ids),
                'invoiceItemCount' => count($invoiceItems),
                'projectStates' => $projectStates,
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function addReissueOrder(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInputString($input, ['projectId', 'PROJECT_ID', 'bizSaleProjectId'], 'projectId');
        $this->assertMaxLength($projectId, 'projectId', 20);
        $processId = $this->requiredInputString($input, ['processId', 'PROCESS_ID'], 'processId');
        $this->assertMaxLength($processId, 'processId', 80);
        $amount = $this->requiredMoneyString($input, ['amount', 'AMOUNT'], 'amount');
        $this->assertMaxLength($amount, 'amount', 32);
        $remark = $this->hasInputKey($input, ['remark', 'REMARK'])
            ? $this->nullableText($this->inputValue($input, ['remark', 'REMARK']), 'remark', 255)
            : null;
        $productList = $this->submittedProductList($input);
        if ($productList === null || $productList === []) {
            throw new RuntimeException('missing productList', 400);
        }

        return Db::transaction(function () use ($input, $payload, $projectId, $processId, $amount, $remark, $productList): array {
            $project = $this->projectQuery(['id' => $projectId], $payload)
                ->field(
                    'p.ID AS ID,' .
                    'p.PROJECT_STATE AS PROJECT_STATE,' .
                    'p.PLAY_STATE AS PLAY_STATE,' .
                    'p.INIT_PRICE AS INIT_PRICE,' .
                    'p.HISTORY_AMOUNT AS HISTORY_AMOUNT,' .
                    'p.TOTAL_RETURN_AMOUNT AS TOTAL_RETURN_AMOUNT,' .
                    'p.TOTAL_REFUND_AMOUNT AS TOTAL_REFUND_AMOUNT,' .
                    'p.TENANT_ID AS TENANT_ID'
                )
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }
            if ((string)($project['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is FOLLOW', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $project);
            if ($this->workflowProcessExists($processId, $tenantId)) {
                throw new RuntimeException('sale project reissue order has workflow records', 400);
            }

            $existingQuery = Db::name('biz_sale_project_reissue_order')->where('PROCESS_ID', $processId);
            $this->whereNotDeleted($existingQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $existingQuery->where('TENANT_ID', $tenantId);
            }
            if ((int)$existingQuery->count() > 0) {
                throw new RuntimeException('sale project reissue order processId already exists', 400);
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            if ($currentUserId === '') {
                $currentUserId = $this->optionalInputString($input, ['createdUser', 'CREATE_USER', 'createUser']) ?? '';
            }
            $items = $this->normalizedProductItems($productList, $tenantId, [], $payload);
            $reissueOrderId = $this->newId();

            Db::name('biz_sale_project_reissue_order')->insert([
                'ID' => $reissueOrderId,
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $this->moneyFromCents($this->moneyCents($amount)),
                'PROCESS_ID' => $processId,
                'REMARK' => $remark,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : (string)($project['TENANT_ID'] ?? '1'),
            ]);

            $counts = $this->insertReissueProductItems(
                $projectId,
                $reissueOrderId,
                $items,
                $tenantId,
                $now,
                $currentUserId
            );

            $totalPriceCents = 0;
            $totalRefundCents = 0;
            $totalReturnCents = 0;
            $statusFields = [];
            try {
                [$totalPriceCents, $totalRefundCents, $totalReturnCents] = $this->correctedProjectTotals(
                    $projectId,
                    $tenantId,
                    $this->moneyCents($project['INIT_PRICE'] ?? '0'),
                    $this->moneyCents($project['TOTAL_REFUND_AMOUNT'] ?? '0'),
                    $this->moneyCents($project['TOTAL_RETURN_AMOUNT'] ?? '0')
                );
                $statusFields = $this->projectPaymentStatusFields(
                    $projectId,
                    $tenantId,
                    $totalPriceCents,
                    $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0')
                );
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() !== 'amount collected exceeds sale project total price') {
                    throw $exception;
                }
                $statusFields = [
                    'AMOUNT_COLLECTED' => $this->moneyFromCents(
                        $this->sumProjectPaymentRecordCents($projectId, $tenantId)
                        + $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0')
                    ),
                    'PLAY_STATE' => (string)($project['PLAY_STATE'] ?? self::UNPAID_PLAY_STATE),
                    'PROJECT_STATE' => $this->correctedProjectState(
                        (string)($project['PLAY_STATE'] ?? self::UNPAID_PLAY_STATE),
                        $this->allProductItemsShipped($projectId, $tenantId)
                    ),
                ];
            }

            $projectUpdate = array_merge($statusFields, [
                'TOTAL_RETURN_AMOUNT' => $this->moneyFromCents($totalReturnCents),
                'TOTAL_REFUND_AMOUNT' => $this->moneyFromCents($totalRefundCents),
                'TOTAL_PRICE' => $this->moneyFromCents($totalPriceCents),
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'VERSION' => Db::raw('VERSION + 1'),
            ]);
            $projectUpdateQuery = Db::name('biz_sale_project')->where('ID', $projectId);
            $this->whereNotDeleted($projectUpdateQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $projectUpdateQuery->where('TENANT_ID', $tenantId);
            }
            $projectUpdateQuery->update($projectUpdate);

            return [
                'id' => $reissueOrderId,
                'projectId' => $projectId,
                'reissueOrderId' => $reissueOrderId,
                'productItemCount' => $counts['productItemCount'],
                'relationCount' => $counts['relationCount'],
                'projectState' => $projectUpdate['PROJECT_STATE'],
                'playState' => $projectUpdate['PLAY_STATE'],
                'totalPrice' => $projectUpdate['TOTAL_PRICE'],
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function editReissueOrder(array $input, array $payload = []): array
    {
        $id = $this->requiredInputString($input, ['id', 'ID'], 'id');
        $this->assertMaxLength($id, 'id', 20);
        $projectId = $this->requiredInputString($input, ['projectId', 'PROJECT_ID', 'bizSaleProjectId'], 'projectId');
        $this->assertMaxLength($projectId, 'projectId', 20);
        $processId = $this->requiredInputString($input, ['processId', 'PROCESS_ID'], 'processId');
        $this->assertMaxLength($processId, 'processId', 80);
        $amount = $this->requiredMoneyString($input, ['amount', 'AMOUNT'], 'amount');
        $this->assertMaxLength($amount, 'amount', 32);
        $remark = $this->hasInputKey($input, ['remark', 'REMARK'])
            ? $this->nullableText($this->inputValue($input, ['remark', 'REMARK']), 'remark', 255)
            : null;
        $productList = $this->submittedProductList($input);
        if ($productList === null || $productList === []) {
            throw new RuntimeException('missing productList', 400);
        }

        return Db::transaction(function () use ($input, $payload, $id, $projectId, $processId, $amount, $remark, $productList): array {
            $order = $this->reissueOrderForWrite($id, $payload);
            if ((string)$order['PROJECT_ID'] !== $projectId) {
                throw new RuntimeException('invalid projectId', 400);
            }
            if ((string)($order['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is FOLLOW', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $order);
            $this->assertDirectReissueOrderWritable($order, $tenantId);
            if ($processId !== (string)($order['PROCESS_ID'] ?? '') && $this->workflowProcessExists($processId, $tenantId)) {
                throw new RuntimeException('sale project reissue order has workflow records', 400);
            }

            $existingQuery = Db::name('biz_sale_project_reissue_order')
                ->where('PROCESS_ID', $processId)
                ->where('ID', '<>', $id);
            $this->whereNotDeleted($existingQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $existingQuery->where('TENANT_ID', $tenantId);
            }
            if ((int)$existingQuery->count() > 0) {
                throw new RuntimeException('sale project reissue order processId already exists', 400);
            }

            $existingProductItems = $this->reissueProductItemsForOrderIds([$id], $tenantId);
            $this->assertReissueProductItemsWritable($existingProductItems, $tenantId);
            $existingItemIds = $this->stringList(array_column($existingProductItems, 'ID'));
            $items = $this->normalizedProductItems($productList, $tenantId, [], $payload);

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $orderUpdate = Db::name('biz_sale_project_reissue_order')
                ->where('ID', $id)
                ->where('PROJECT_ID', $projectId);
            $this->whereNotDeleted($orderUpdate, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $orderUpdate->where('TENANT_ID', $tenantId);
            }
            $orderUpdate->update([
                'PROCESS_ID' => $processId,
                'AMOUNT' => $this->moneyFromCents($this->moneyCents($amount)),
                'REMARK' => $remark,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            ]);

            $this->softDeleteProductItemRelations($existingItemIds, $tenantId, $now, $currentUserId);
            $this->softDeleteProductItems($existingItemIds, $tenantId, $now, $currentUserId);
            $counts = $this->insertReissueProductItems($projectId, $id, $items, $tenantId, $now, $currentUserId);
            $projectUpdate = $this->recalculateReissueProjectTotals($order, $projectId, $tenantId, $now, $currentUserId, false);

            return [
                'id' => $id,
                'projectId' => $projectId,
                'reissueOrderId' => $id,
                'productItemCount' => $counts['productItemCount'],
                'relationCount' => $counts['relationCount'],
                'projectState' => $projectUpdate['PROJECT_STATE'],
                'playState' => $projectUpdate['PLAY_STATE'],
                'totalPrice' => $projectUpdate['TOTAL_PRICE'],
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function deleteReissueOrder(array $input, array $payload = []): array
    {
        $ids = $this->reissueOrderDeleteInput($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $ordersById = $this->reissueOrderRowsForWrite($ids, $payload);
            $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
            $projectRowsById = [];
            foreach ($ordersById as $order) {
                $tenantId = trim((string)($order['TENANT_ID'] ?? $order['PROJECT_TENANT_ID'] ?? ''));
                if ($payloadTenantId !== '' && $tenantId !== $payloadTenantId) {
                    throw new RuntimeException('tenant mismatch', 403);
                }
                if ((string)($order['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
                    throw new RuntimeException('sale project state is FOLLOW', 400);
                }
                $this->assertDirectReissueOrderWritable($order, $tenantId);
                $projectRowsById[(string)$order['PROJECT_ID']] = $order;
            }

            $productItems = $this->reissueProductItemsForOrderIds($ids, $payloadTenantId);
            $itemIds = $this->stringList(array_column($productItems, 'ID'));
            $this->assertReissueProductItemsWritable($productItems, $payloadTenantId);
            $relationCount = $this->reissueProductItemRelationCountByItemIds($itemIds, $payloadTenantId);

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $this->softDeleteProductItemRelations($itemIds, $payloadTenantId, $now, $currentUserId);
            $this->softDeleteProductItems($itemIds, $payloadTenantId, $now, $currentUserId);

            $orderDelete = Db::name('biz_sale_project_reissue_order')->whereIn('ID', $ids);
            $this->whereNotDeleted($orderDelete, 'DELETE_FLAG');
            if ($payloadTenantId !== '') {
                $orderDelete->where('TENANT_ID', $payloadTenantId);
            }
            $orderDelete->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            ]);

            $projectStates = [];
            $projectUpdates = [];
            foreach ($projectRowsById as $projectId => $project) {
                $tenantId = trim((string)($project['TENANT_ID'] ?? $project['PROJECT_TENANT_ID'] ?? ''));
                $projectUpdate = $this->recalculateReissueProjectTotals($project, $projectId, $tenantId, $now, $currentUserId, true);
                $projectStates[$projectId] = $projectUpdate['PROJECT_STATE'];
                $projectUpdates[$projectId] = [
                    'projectState' => $projectUpdate['PROJECT_STATE'],
                    'playState' => $projectUpdate['PLAY_STATE'],
                    'totalPrice' => $projectUpdate['TOTAL_PRICE'],
                ];
            }

            return [
                'ids' => $ids,
                'count' => count($ids),
                'productItemCount' => count($itemIds),
                'relationCount' => $relationCount,
                'projectStates' => $projectStates,
                'projects' => $projectUpdates,
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function editProductItem(array $input, array $payload = []): array
    {
        $itemId = $this->requiredInputString($input, ['id', 'ID'], 'id');
        $this->assertMaxLength($itemId, 'id', 20);

        return Db::transaction(function () use ($input, $payload, $itemId): array {
            $existing = $this->standaloneProductItemForWrite($itemId, $payload);
            if ((string)($existing['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is not FOLLOW', 400);
            }

            $submittedProjectId = $this->optionalInputString($input, ['projectId', 'PROJECT_ID']);
            $projectId = (string)($existing['PROJECT_ID'] ?? '');
            if ($submittedProjectId !== null && $submittedProjectId !== $projectId) {
                throw new RuntimeException('invalid projectId', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $existing);
            $productId = $this->optionalInputString($input, ['productId', 'PRODUCT_ID', 'targetId', 'TARGET_ID'])
                ?? (string)($existing['PRODUCT_ID'] ?? '');
            $productChanged = $productId !== (string)($existing['PRODUCT_ID'] ?? '');

            $itemInput = [
                'id' => $itemId,
                'projectId' => $projectId,
                'productId' => $productId,
                'number' => $this->hasInputKey($input, ['number', 'NUMBER']) ? $this->inputValue($input, ['number', 'NUMBER']) : ($existing['NUMBER'] ?? null),
                'unitPrice' => $this->hasInputKey($input, ['unitPrice', 'UNIT_PRICE']) ? $this->inputValue($input, ['unitPrice', 'UNIT_PRICE']) : ($existing['UNIT_PRICE'] ?? null),
                'discountRate' => $this->hasInputKey($input, ['discountRate', 'DISCOUNT_RATE']) ? $this->inputValue($input, ['discountRate', 'DISCOUNT_RATE']) : ($existing['DISCOUNT_RATE'] ?? null),
                'price' => $this->hasInputKey($input, ['price', 'PRICE']) ? $this->inputValue($input, ['price', 'PRICE']) : ($existing['PRICE'] ?? null),
                'remark' => $this->hasInputKey($input, ['remark', 'REMARK']) ? $this->inputValue($input, ['remark', 'REMARK']) : ($existing['REMARK'] ?? null),
            ];

            if ($this->hasInputKey($input, ['children'])) {
                $itemInput['children'] = $this->inputValue($input, ['children']);
            } elseif (!$productChanged) {
                $itemInput['children'] = $this->currentProductItemChildren($itemId, $tenantId);
            }

            $items = $this->normalizedProductItems([$itemInput], $tenantId, [$itemId => $existing], $payload);
            $item = $items[0];
            $referencedIds = $this->referencedProductItemIds([$itemId], $tenantId);
            $hasDelivery = (float)($existing['DELIVERY'] ?? 0) > 0.000001;
            $childrenChanged = $this->productItemChildrenChanged($itemId, $item['children'], $tenantId);
            if ((isset($referencedIds[$itemId]) || $hasDelivery)
                && ($this->protectedProductItemChanged($existing, $item) || $childrenChanged)) {
                throw new RuntimeException('sale project product item is referenced', 400);
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            if (isset($referencedIds[$itemId]) || $hasDelivery) {
                $this->updateProductItemRemarkOnly($itemId, $item, $tenantId, $now, $currentUserId);
            } else {
                $this->updateProductItem($itemId, $item, $tenantId, $now, $currentUserId);
                $this->replaceProductItemRelations($itemId, $item['children'], $tenantId, $now, $currentUserId);
            }

            return [
                'id' => $itemId,
                'projectId' => $projectId,
                'productId' => $item['productId'],
                'relationCount' => count($item['children']),
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function deleteProductItems(array $input, array $payload = []): array
    {
        $ids = $this->standaloneProductItemDeleteInput($input);

        return Db::transaction(function () use ($input, $payload, $ids): array {
            $rowsById = $this->standaloneProductItemsForWrite($ids, $payload);
            $referencedIds = $this->referencedProductItemIds($ids, '');
            $idsByTenant = [];
            foreach ($ids as $id) {
                $row = $rowsById[$id];
                $this->writeTenantId($input, $payload, $row);
                if ((string)($row['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                    throw new RuntimeException('sale project state is not FOLLOW', 400);
                }
                if (isset($referencedIds[$id]) || (float)($row['DELIVERY'] ?? 0) > 0.000001) {
                    throw new RuntimeException('sale project product item is referenced', 400);
                }

                $tenantId = trim((string)($row['TENANT_ID'] ?? ''));
                $idsByTenant[$tenantId][] = $id;
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            foreach ($idsByTenant as $tenantId => $tenantIds) {
                $this->softDeleteProductItemRelations($tenantIds, (string)$tenantId, $now, $currentUserId);
                $this->softDeleteProductItems($tenantIds, (string)$tenantId, $now, $currentUserId);
            }

            return [
                'count' => count($ids),
                'ids' => $ids,
            ];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function historyAdd(array $input, array $payload = []): array
    {
        $projectName = $this->requiredInputString($input, ['projectName', 'PROJECT_NAME'], 'projectName');
        $customerName = $this->requiredInputString($input, ['customerName', 'CUSTOMER_NAME'], 'customerName');
        $targetUserId = $this->requiredInputString($input, ['user', 'USER', 'userId', 'USER_ID'], 'user');
        $initPrice = $this->requiredMoneyString($input, ['initPrice', 'INIT_PRICE'], 'initPrice');
        $historyAmount = $this->requiredMoneyString($input, ['historyAmount', 'HISTORY_AMOUNT'], 'historyAmount');
        $completionDate = $this->completionDateInput($input);

        $this->assertMaxLength($projectName, 'projectName', 255);
        $this->assertMaxLength($customerName, 'customerName', 255);
        $this->assertMaxLength($targetUserId, 'user', 20);
        if ($this->moneyCents($initPrice) <= 0) {
            throw new RuntimeException('initPrice must be greater than zero', 400);
        }

        return Db::transaction(function () use ($input, $payload, $projectName, $customerName, $targetUserId, $initPrice, $historyAmount, $completionDate): array {
            $targetUser = $this->assertUserWritable($targetUserId, $payload);
            $tenantId = $this->writeTenantId($input, $payload, $targetUser);
            $orgId = trim((string)($targetUser['ORG_ID'] ?? ''));
            $customerId = $this->createHistoryCustomer($customerName, $targetUserId, $orgId, $tenantId, $completionDate, $payload);
            $projectId = $this->newId();
            $initPriceCents = $this->moneyCents($initPrice);
            $historyAmountCents = $this->moneyCents($historyAmount);
            $statusFields = $this->projectPaymentStatusFields($projectId, $tenantId, $initPriceCents, $historyAmountCents);
            $currentUserId = $this->currentUserId($payload);

            Db::name('biz_sale_project')->insert(array_merge([
                'ID' => $projectId,
                'CUSTOMER' => $customerId,
                'PROJECT_NAME' => $projectName,
                'PROJECT_STATE' => self::SHIPPED_STATE,
                'VISIBILITY' => self::PRIVATE_VISIBILITY,
                'INIT_PRICE' => $this->moneyFromCents($initPriceCents),
                'TOTAL_PRICE' => $this->moneyFromCents($initPriceCents),
                'PROJECT_CATEGORY' => self::DIRECT_PROJECT_CATEGORY,
                'USER' => $targetUserId,
                'ORG' => $orgId !== '' ? $orgId : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $completionDate,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : $targetUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'COMPLETION_DATE' => $completionDate,
                'DEAL_AMOUNT' => 0,
                'HISTORY_AMOUNT' => $this->moneyFromCents($historyAmountCents),
                'TOTAL_RETURN_AMOUNT' => '0.00',
                'TOTAL_REFUND_AMOUNT' => '0.00',
            ], $statusFields));

            return ['id' => $projectId, 'customerId' => $customerId];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function specialAdd(array $input, array $payload = []): array
    {
        $projectName = $this->requiredInputString($input, ['projectName', 'PROJECT_NAME'], 'projectName');
        $customerName = $this->requiredInputString($input, ['customerName', 'CUSTOMER_NAME'], 'customerName');
        $orgId = $this->requiredInputString($input, ['orgId', 'ORG_ID', 'org', 'ORG'], 'orgId');
        $initPrice = $this->requiredMoneyString($input, ['initPrice', 'INIT_PRICE'], 'initPrice');
        $completionDate = $this->completionDateInput($input);

        $this->assertMaxLength($projectName, 'projectName', 255);
        $this->assertMaxLength($customerName, 'customerName', 255);
        $this->assertMaxLength($orgId, 'orgId', 20);

        return Db::transaction(function () use ($input, $payload, $projectName, $customerName, $orgId, $initPrice, $completionDate): array {
            $org = $this->assertOrgWritable($orgId, $payload);
            $currentUserId = $this->currentUserId($payload);
            if ($currentUserId === '') {
                throw new RuntimeException('missing current user', 400);
            }

            $tenantId = $this->writeTenantId($input, $payload, $org);
            $customerId = $this->createHistoryCustomer($customerName, $currentUserId, $orgId, $tenantId, $completionDate, $payload);
            $projectId = $this->newId();
            $initPriceCents = $this->moneyCents($initPrice);
            $statusFields = $this->projectPaymentStatusFields($projectId, $tenantId, $initPriceCents, 0);

            Db::name('biz_sale_project')->insert(array_merge([
                'ID' => $projectId,
                'CUSTOMER' => $customerId,
                'PROJECT_NAME' => $projectName,
                'PROJECT_STATE' => self::SHIPPED_STATE,
                'VISIBILITY' => self::PRIVATE_VISIBILITY,
                'INIT_PRICE' => $this->moneyFromCents($initPriceCents),
                'TOTAL_PRICE' => $this->moneyFromCents($initPriceCents),
                'PROJECT_CATEGORY' => self::DIRECT_PROJECT_CATEGORY,
                'USER' => $currentUserId,
                'ORG' => $orgId,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $completionDate,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'COMPLETION_DATE' => $completionDate,
                'DEAL_AMOUNT' => 0,
                'HISTORY_AMOUNT' => '0.00',
                'TOTAL_RETURN_AMOUNT' => '0.00',
                'TOTAL_REFUND_AMOUNT' => '0.00',
                'special_type' => self::PUBLIC_FOR_REIMBURSEMENT,
            ], $statusFields));

            return ['id' => $projectId, 'customerId' => $customerId];
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function delete(array $input, array $payload = []): ?array
    {
        $ids = $this->saleProjectIdListInput($input);

        return Db::transaction(function () use ($ids, $payload): ?array {
            $rows = $this->projectQuery([], $payload)
                ->whereIn('p.ID', $ids)
                ->field('p.ID AS ID, p.PROJECT_NAME AS PROJECT_NAME, p.PROJECT_STATE AS PROJECT_STATE, p.TENANT_ID AS TENANT_ID')
                ->lock(true)
                ->select()
                ->toArray();
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('sale project not found', 404);
            }

            $tenantId = '';
            foreach ($rows as $row) {
                if ((string)($row['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                    throw new RuntimeException('sale project state is not FOLLOW', 400);
                }
                $rowTenantId = trim((string)($row['TENANT_ID'] ?? ''));
                if ($tenantId === '' && $rowTenantId !== '') {
                    $tenantId = $rowTenantId;
                }
            }

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $updateQuery = Db::name('biz_sale_project')
                ->whereIn('ID', $ids)
                ->where('PROJECT_STATE', self::FOLLOW_STATE);
            $this->whereNotDeleted($updateQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $updateQuery->where('TENANT_ID', $tenantId);
            }
            $updateQuery->update([
                'PROJECT_STATE' => self::DISCARD_STATE,
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'VERSION' => Db::raw('VERSION + 1'),
            ]);

            return null;
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function editAmount(array $input, array $payload = []): ?array
    {
        $projectId = $this->requiredInputString($input, ['id', 'ID', 'projectId', 'PROJECT_ID'], 'id');
        $initPriceInput = $this->requiredMoneyString($input, ['initPrice', 'INIT_PRICE'], 'initPrice');
        $remarkValue = $this->inputValue($input, ['remark', 'REMARK']);
        $remark = $remarkValue === null ? null : trim((string)$remarkValue);

        $this->assertMaxLength($projectId, 'id', 20);
        $this->assertMaxLength($initPriceInput, 'initPrice', 32);
        if ($remark !== null) {
            $this->assertMaxLength($remark, 'remark', 200);
        }

        return Db::transaction(function () use ($projectId, $initPriceInput, $remark, $payload): ?array {
            $project = $this->projectQuery(['id' => $projectId], $payload)
                ->field(
                    'p.ID AS ID,' .
                    'p.INIT_PRICE AS INIT_PRICE,' .
                    'p.TOTAL_PRICE AS TOTAL_PRICE,' .
                    'p.AMOUNT_COLLECTED AS AMOUNT_COLLECTED,' .
                    'p.PLAY_STATE AS PLAY_STATE,' .
                    'p.PROJECT_STATE AS PROJECT_STATE,' .
                    'p.HISTORY_AMOUNT AS HISTORY_AMOUNT,' .
                    'p.TOTAL_RETURN_AMOUNT AS TOTAL_RETURN_AMOUNT,' .
                    'p.TOTAL_REFUND_AMOUNT AS TOTAL_REFUND_AMOUNT,' .
                    'p.TENANT_ID AS TENANT_ID'
                )
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }

            $tenantId = trim((string)($project['TENANT_ID'] ?? ''));
            $initPriceCents = $this->moneyCents($initPriceInput);
            $oldInitPrice = $this->moneyFromCents($this->moneyCents($project['INIT_PRICE'] ?? '0'));
            $oldTotalPriceCents = $this->moneyCents($project['TOTAL_PRICE'] ?? '0');
            $historyAmountCents = $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0');

            $allShipped = $this->allProductItemsShipped($projectId, $tenantId);
            $projectState = $this->correctedProjectState((string)($project['PLAY_STATE'] ?? ''), $allShipped);

            $paymentRecordCents = $this->sumProjectPaymentRecordCents($projectId, $tenantId);
            $amountCollectedCents = $paymentRecordCents + $historyAmountCents;
            if ($amountCollectedCents > $oldTotalPriceCents) {
                throw new RuntimeException('amount collected exceeds sale project total price', 400);
            }

            if ($oldTotalPriceCents > $amountCollectedCents) {
                $playState = $this->hasProjectPaymentRecords($projectId, $tenantId)
                    ? self::PARTIALLY_PAID_PLAY_STATE
                    : self::UNPAID_PLAY_STATE;
            } else {
                $playState = self::PAID_PLAY_STATE;
            }

            if ($allShipped) {
                $projectState = $playState === self::PAID_PLAY_STATE ? self::COMPLETED_STATE : self::SHIPPED_STATE;
            }

            [$totalPriceCents, $totalRefundCents, $totalReturnCents] = $this->correctedProjectTotals(
                $projectId,
                $tenantId,
                $initPriceCents,
                $this->moneyCents($project['TOTAL_REFUND_AMOUNT'] ?? '0'),
                $this->moneyCents($project['TOTAL_RETURN_AMOUNT'] ?? '0')
            );

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $updates = [
                'INIT_PRICE' => $this->moneyFromCents($initPriceCents),
                'AMOUNT_COLLECTED' => $this->moneyFromCents($amountCollectedCents),
                'PLAY_STATE' => $playState,
                'PROJECT_STATE' => $projectState,
                'TOTAL_RETURN_AMOUNT' => $this->moneyFromCents($totalReturnCents),
                'TOTAL_REFUND_AMOUNT' => $this->moneyFromCents($totalRefundCents),
                'TOTAL_PRICE' => $this->moneyFromCents($totalPriceCents),
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'VERSION' => Db::raw('VERSION + 1'),
            ];

            $updateQuery = Db::name('biz_sale_project')
                ->where('ID', $projectId);
            $this->whereNotDeleted($updateQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $updateQuery->where('TENANT_ID', $tenantId);
            }
            $updateQuery->update($updates);

            Db::name('sales_project_field_change_log')->insert([
                'ID' => $this->newId(),
                'OBJECT_ID' => $projectId,
                'FIELD_NAME' => 'INIT_PRICE',
                'FIELD_LABEL' => self::INIT_PRICE_FIELD_LABEL,
                'BEFORE_VALUE' => $oldInitPrice,
                'AFTER_VALUE' => $initPriceInput,
                'CHANGE_REASON' => $remark,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
            ]);

            return null;
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function editDeal(array $input, array $payload = []): ?array
    {
        $projectId = $this->requiredInputString($input, ['id', 'ID', 'projectId', 'PROJECT_ID'], 'id');
        $this->assertMaxLength($projectId, 'id', 20);

        $updates = [];
        foreach ([
            'unit' => ['UNIT', 100],
            'address' => ['ADDRESS', 100],
            'logisticsCategory' => ['LOGISTICS_CATEGORY', 50],
            'consignee' => ['CONSIGNEE', 40],
            'phone' => ['PHONE', 40],
            'remark' => ['REMARK', null],
            'freightCategory' => ['FREIGHT_CATEGORY', 100],
            'deliveryNote' => ['DELIVERY_NOTE', null],
        ] as $key => [$column, $maxLength]) {
            if (!$this->hasInputKey($input, [$key, $column])) {
                continue;
            }

            $value = $this->nullableScalarInput($input, [$key, $column], $key);
            if ($value !== null && $maxLength !== null) {
                $this->assertMaxLength($value, $key, $maxLength);
            }
            $updates[$column] = $value;
        }

        if ($this->hasInputKey($input, ['freight', 'FREIGHT'])) {
            $freightValue = $this->inputValue($input, ['freight', 'FREIGHT']);
            if (is_array($freightValue) || is_object($freightValue) || is_bool($freightValue)) {
                throw new RuntimeException('invalid freight', 400);
            }
            if ($freightValue === null || trim((string)$freightValue) === '') {
                $updates['FREIGHT'] = null;
            } else {
                $freight = $this->requiredMoneyString($input, ['freight', 'FREIGHT'], 'freight');
                $this->assertMaxLength($freight, 'freight', 32);
                $updates['FREIGHT'] = $this->moneyFromCents($this->moneyCents($freight));
            }
        }

        return Db::transaction(function () use ($projectId, $updates, $payload): ?array {
            $project = $this->projectQuery(['id' => $projectId], $payload)
                ->field('p.ID AS ID, p.TENANT_ID AS TENANT_ID')
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }

            $tenantId = trim((string)($project['TENANT_ID'] ?? ''));
            $updates['UPDATE_TIME'] = date('Y-m-d H:i:s');
            $updates['UPDATE_USER'] = $this->currentUserId($payload) ?: null;
            $updates['VERSION'] = Db::raw('VERSION + 1');

            $updateQuery = Db::name('biz_sale_project')
                ->where('ID', $projectId);
            $this->whereNotDeleted($updateQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $updateQuery->where('TENANT_ID', $tenantId);
            }
            $updateQuery->update($updates);

            return null;
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function editVisibility(array $input, array $payload = []): ?array
    {
        $projectId = $this->requiredInputString($input, ['projectId', 'PROJECT_ID', 'id', 'ID'], 'projectId');
        $visibility = strtoupper($this->requiredInputString($input, ['visibilityState', 'VISIBILITY_STATE', 'visibility', 'VISIBILITY'], 'visibilityState'));
        $this->assertVisibility($visibility);

        $hasSpecimenCategory = $this->hasInputKey($input, ['specimenCategory', 'SPECIMEN_CATEGORY']);
        $hasSpecimenName = $this->hasInputKey($input, ['specimenName', 'SPECIMEN_NAME']);
        $specimenCategory = $hasSpecimenCategory ? trim((string)$this->inputValue($input, ['specimenCategory', 'SPECIMEN_CATEGORY'])) : null;
        $specimenName = $hasSpecimenName ? trim((string)$this->inputValue($input, ['specimenName', 'SPECIMEN_NAME'])) : null;

        if ($visibility === self::PUBLIC_VISIBILITY && ($specimenCategory === null || $specimenCategory === '')) {
            throw new RuntimeException('missing specimenCategory', 400);
        }

        $this->assertMaxLength($projectId, 'projectId', 20);
        $this->assertMaxLength($visibility, 'visibilityState', 32);
        if ($specimenCategory !== null) {
            $this->assertMaxLength($specimenCategory, 'specimenCategory', 255);
        }
        if ($specimenName !== null) {
            $this->assertMaxLength($specimenName, 'specimenName', 255);
        }

        return Db::transaction(function () use ($projectId, $visibility, $payload, $hasSpecimenCategory, $hasSpecimenName, $specimenCategory, $specimenName): ?array {
            $row = $this->projectQuery(['id' => $projectId], $payload)
                ->field('p.ID AS ID')
                ->lock(true)
                ->find();
            if (!is_array($row) || $row === []) {
                throw new RuntimeException('sale project not found', 404);
            }

            $updates = [
                'VISIBILITY' => $visibility,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->currentUserId($payload) ?: null,
                'VERSION' => Db::raw('VERSION + 1'),
            ];

            if ($visibility === self::PUBLIC_VISIBILITY || $hasSpecimenCategory) {
                $updates['SPECIMEN_CATEGORY'] = $specimenCategory;
            }
            if ($visibility === self::PUBLIC_VISIBILITY || $hasSpecimenName) {
                $updates['SPECIMEN_NAME'] = $specimenName;
            }

            Db::name('biz_sale_project')
                ->where('ID', $projectId)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update($updates);

            return null;
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function cancel(array $input, array $payload = []): ?array
    {
        $projectId = $this->requiredInputString($input, ['id', 'ID', 'projectId', 'PROJECT_ID'], 'id');
        $this->assertMaxLength($projectId, 'id', 20);

        return Db::transaction(function () use ($projectId, $payload): ?array {
            $project = $this->projectQuery(['id' => $projectId], $payload)
                ->field(
                    'p.ID AS ID,' .
                    'p.PROJECT_STATE AS PROJECT_STATE,' .
                    'p.TENANT_ID AS TENANT_ID'
                )
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }

            if ((string)($project['PROJECT_STATE'] ?? '') !== self::WAIT_DELIVER_STATE) {
                throw new RuntimeException('sale project state is not WAIT_DELIVER', 400);
            }

            $tenantId = trim((string)($project['TENANT_ID'] ?? ''));
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $auditUser = $userId !== '' ? $userId : null;

            $invoicingQuery = Db::name('biz_sale_project_invoicing')
                ->where('PROJECT_ID', $projectId);
            $this->whereNotDeleted($invoicingQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $invoicingQuery->where('TENANT_ID', $tenantId);
            }
            $invoicingQuery->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $auditUser,
            ]);

            $projectUpdateQuery = Db::name('biz_sale_project')
                ->where('ID', $projectId);
            $this->whereNotDeleted($projectUpdateQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $projectUpdateQuery->where('TENANT_ID', $tenantId);
            }
            $projectUpdateQuery->update([
                'PROJECT_STATE' => self::FOLLOW_STATE,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $auditUser,
                'VERSION' => Db::raw('VERSION + 1'),
            ]);

            return null;
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function repeal(array $input, array $payload = []): ?array
    {
        [$ids, $repealContent] = $this->repealInput($input);

        return Db::transaction(function () use ($ids, $repealContent, $payload): ?array {
            $rows = $this->projectQuery([], $payload)
                ->whereIn('p.ID', $ids)
                ->field('p.ID AS ID, p.PROJECT_NAME AS PROJECT_NAME, p.PROJECT_STATE AS PROJECT_STATE, p.TENANT_ID AS TENANT_ID')
                ->lock(true)
                ->select()
                ->toArray();
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('sale project not found', 404);
            }

            $tenantId = '';
            foreach ($rows as $row) {
                if ((string)($row['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                    throw new RuntimeException('sale project state is not FOLLOW', 400);
                }
                $rowTenantId = trim((string)($row['TENANT_ID'] ?? ''));
                if ($tenantId === '' && $rowTenantId !== '') {
                    $tenantId = $rowTenantId;
                }
            }

            $updateQuery = Db::name('biz_sale_project')
                ->whereIn('ID', $ids);
            $this->whereNotDeleted($updateQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $updateQuery->where('TENANT_ID', $tenantId);
            }
            $updateQuery->update([
                'PROJECT_STATE' => self::DISCARD_STATE,
                'REPEAL_CONTENT' => $repealContent,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                'VERSION' => Db::raw('VERSION + 1'),
            ]);

            return null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function markProjectPendingApproval(string $projectId, array $payload = [], string $tenantId = ''): array
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            throw new RuntimeException('missing bizSaleProjectId', 400);
        }
        $this->assertMaxLength($projectId, 'bizSaleProjectId', 20);
        $filters = ['id' => $projectId];
        if ($tenantId !== '') {
            $filters['tenantId'] = $tenantId;
        }

        return Db::transaction(function () use ($projectId, $payload, $filters, $tenantId): array {
            $project = $this->projectQuery($filters, $payload)
                ->field('p.ID AS ID, p.PROJECT_NAME AS PROJECT_NAME, p.PROJECT_STATE AS PROJECT_STATE, p.TENANT_ID AS TENANT_ID')
                ->lock(true)
                ->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }
            if ((string)($project['PROJECT_STATE'] ?? '') !== self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is not FOLLOW', 400);
            }

            $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
            $query = Db::name('biz_sale_project')->where('ID', $projectId);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ($effectiveTenantId !== '') {
                $query->where('TENANT_ID', $effectiveTenantId);
            }
            $query->update([
                'PROJECT_STATE' => self::PENDING_APPROVAL_STATE,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                'VERSION' => Db::raw('VERSION + 1'),
            ]);

            $project['PROJECT_STATE'] = self::PENDING_APPROVAL_STATE;

            return $project;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowProjectStartInfo(string $projectId, array $payload = [], string $tenantId = ''): array
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }
        $this->assertMaxLength($projectId, 'projectId', 20);

        $filters = ['id' => $projectId];
        if ($tenantId !== '') {
            $filters['tenantId'] = $tenantId;
        }
        $project = $this->projectQuery($filters, $payload)
            ->field(
                'p.ID AS ID,' .
                'p.CUSTOMER AS CUSTOMER,' .
                'p.PROJECT_NAME AS PROJECT_NAME,' .
                'p.PROJECT_STATE AS PROJECT_STATE,' .
                'p.PLAY_STATE AS PLAY_STATE,' .
                'p.TOTAL_PRICE AS TOTAL_PRICE,' .
                'p.AMOUNT_COLLECTED AS AMOUNT_COLLECTED,' .
                'p.HISTORY_AMOUNT AS HISTORY_AMOUNT,' .
                'p.TENANT_ID AS TENANT_ID'
            )
            ->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $project
     */
    public function assertProjectPaymentAmountWithinRemaining(
        string $projectId,
        mixed $amount,
        array $project,
        string $tenantId = ''
    ): void {
        $amountCents = $this->moneyCents($amount);
        if ($amountCents <= 0) {
            throw new RuntimeException('amount must be greater than 0', 400);
        }

        $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
        $totalPriceCents = $this->moneyCents($project['TOTAL_PRICE'] ?? '0');
        $historyAmountCents = $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0');
        $receivedCents = $this->sumProjectPaymentRecordCents($projectId, $effectiveTenantId) + $historyAmountCents;
        if ($receivedCents + $amountCents > $totalPriceCents) {
            throw new RuntimeException('amount collected exceeds sale project total price', 400);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $projectProductItemList
     * @return array<string, mixed>
     */
    public function workflowProjectDeliveryStartInfo(
        string $projectId,
        array $projectProductItemList,
        array $payload = [],
        string $tenantId = ''
    ): array {
        $projectId = trim($projectId);
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }
        $this->assertMaxLength($projectId, 'projectId', 20);

        $filters = ['id' => $projectId];
        if ($tenantId !== '') {
            $filters['tenantId'] = $tenantId;
        }
        $project = $this->projectQuery($filters, $payload)
            ->field(
                'p.ID AS ID,' .
                'p.PROJECT_NAME AS PROJECT_NAME,' .
                'p.PROJECT_STATE AS PROJECT_STATE,' .
                'p.PLAY_STATE AS PLAY_STATE,' .
                'p.TENANT_ID AS TENANT_ID'
            )
            ->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }
        if ((string)($project['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
            throw new RuntimeException('sale project state is FOLLOW', 400);
        }

        $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
        $items = $this->workflowDeliveryItems($projectId, $projectProductItemList, $effectiveTenantId, false);
        $project['PROJECT_PRODUCT_ITEM_COUNT'] = count($items);

        return $project;
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     * @return array<string, mixed>
     */
    public function workflowProjectReissueStartInfo(
        string $projectId,
        array $productList,
        array $payload = [],
        string $tenantId = ''
    ): array {
        $project = $this->workflowProjectStartInfo($projectId, $payload, $tenantId);
        if ((string)($project['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
            throw new RuntimeException('sale project state is FOLLOW', 400);
        }

        $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
        $items = $this->normalizedProductItems($productList, $effectiveTenantId, [], $payload);
        $project['PRODUCT_ITEM_COUNT'] = count($items);

        return $project;
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function rejectProjectInitFromWorkflow(array $variables, string $tenantId = '', string $currentUserId = ''): array
    {
        $projectId = $this->workflowProjectId($variables);

        return Db::transaction(function () use ($projectId, $tenantId, $currentUserId): array {
            $project = $this->workflowProjectForUpdate($projectId, $tenantId);
            $state = (string)($project['PROJECT_STATE'] ?? '');
            if ($state === self::PENDING_APPROVAL_STATE) {
                $query = Db::name('biz_sale_project')->where('ID', $projectId);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
                if ($effectiveTenantId !== '') {
                    $query->where('TENANT_ID', $effectiveTenantId);
                }
                $query->update([
                    'PROJECT_STATE' => self::FOLLOW_STATE,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'VERSION' => Db::raw('VERSION + 1'),
                ]);
                $state = self::FOLLOW_STATE;
            } elseif ($state !== self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is not PENDING_APPROVAL', 400);
            }

            return [
                'id' => $projectId,
                'projectId' => $projectId,
                'projectState' => $state,
            ];
        });
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function applyProjectReissueFromWorkflow(
        array $variables,
        string $processInstanceId,
        string $tenantId = '',
        string $currentUserId = ''
    ): array {
        $projectId = $this->workflowProjectId($variables);

        return Db::transaction(function () use ($variables, $processInstanceId, $tenantId, $currentUserId, $projectId): array {
            $project = $this->workflowProjectForUpdate($projectId, $tenantId);
            if ((string)($project['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is FOLLOW', 400);
            }

            $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
            $existingOrderQuery = Db::name('biz_sale_project_reissue_order')->where('PROCESS_ID', $processInstanceId);
            $this->whereNotDeleted($existingOrderQuery, 'DELETE_FLAG');
            if ($effectiveTenantId !== '') {
                $existingOrderQuery->where('TENANT_ID', $effectiveTenantId);
            }
            $existingOrder = $existingOrderQuery->field('ID')->find();
            if (is_array($existingOrder) && $existingOrder !== []) {
                $reissueOrderId = (string)$existingOrder['ID'];

                return [
                    'id' => $reissueOrderId,
                    'projectId' => $projectId,
                    'reissueOrderId' => $reissueOrderId,
                    'productItemCount' => $this->reissueProductItemCount($reissueOrderId, $projectId, $effectiveTenantId),
                    'relationCount' => $this->reissueProductItemRelationCount($reissueOrderId, $projectId, $effectiveTenantId),
                ];
            }

            $productList = $this->workflowList($variables['productList'] ?? null, 'productList');
            if ($productList === []) {
                throw new RuntimeException('missing productList', 400);
            }

            $now = date('Y-m-d H:i:s');
            $auditUser = $currentUserId !== '' ? $currentUserId : trim((string)($variables['initiator'] ?? ''));
            $productPayload = $effectiveTenantId !== '' ? ['tenant_id' => $effectiveTenantId] : [];
            $items = $this->normalizedProductItems($productList, $effectiveTenantId, [], $productPayload);
            $reissueOrderId = $this->newId();

            Db::name('biz_sale_project_reissue_order')->insert([
                'ID' => $reissueOrderId,
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $this->workflowMoney($variables['amount'] ?? null, 'amount', false),
                'PROCESS_ID' => $processInstanceId,
                'REMARK' => $this->workflowOptionalText($variables['remark'] ?? null, 'remark', 255),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $auditUser !== '' ? $auditUser : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $effectiveTenantId !== '' ? $effectiveTenantId : (string)($project['TENANT_ID'] ?? '1'),
            ]);

            $counts = $this->insertReissueProductItems(
                $projectId,
                $reissueOrderId,
                $items,
                $effectiveTenantId,
                $now,
                $auditUser
            );

            return [
                'id' => $reissueOrderId,
                'projectId' => $projectId,
                'reissueOrderId' => $reissueOrderId,
                'productItemCount' => $counts['productItemCount'],
                'relationCount' => $counts['relationCount'],
            ];
        });
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function applyProjectInitFromWorkflow(
        array $variables,
        string $processInstanceId,
        string $tenantId = '',
        string $currentUserId = ''
    ): array {
        $projectId = $this->workflowProjectId($variables);

        return Db::transaction(function () use ($variables, $processInstanceId, $tenantId, $currentUserId, $projectId): array {
            $project = $this->workflowProjectForUpdate($projectId, $tenantId);
            if ((string)($project['PROCESS_ID'] ?? '') === $processInstanceId
                && in_array((string)($project['PROJECT_STATE'] ?? ''), [self::WAIT_DELIVER_STATE, self::SHIPPED_STATE], true)) {
                return [
                    'id' => $projectId,
                    'projectId' => $projectId,
                    'projectState' => (string)$project['PROJECT_STATE'],
                ];
            }
            if ((string)($project['PROJECT_STATE'] ?? '') !== self::PENDING_APPROVAL_STATE) {
                throw new RuntimeException('sale project state is not PENDING_APPROVAL', 400);
            }

            $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
            $productList = $this->workflowList($variables['productList'] ?? null, 'productList');
            $fileIds = $this->workflowStringList($variables['fileIdList'] ?? []);
            if ($productList === []) {
                throw new RuntimeException('missing productList', 400);
            }
            if ($fileIds === []) {
                throw new RuntimeException('missing fileIdList', 400);
            }

            $now = date('Y-m-d H:i:s');
            $auditUser = $currentUserId !== '' ? $currentUserId : trim((string)($variables['initiator'] ?? ''));
            $productPayload = $effectiveTenantId !== '' ? ['tenant_id' => $effectiveTenantId] : [];
            $this->syncProductItems($projectId, $productList, $effectiveTenantId, $productPayload, $now, $auditUser);
            $fileRelationCount = $this->insertSaleProjectWorkflowFileRelations($projectId, $fileIds, $effectiveTenantId, $auditUser, $now);

            $initPrice = $this->workflowMoney($variables['initPrice'] ?? null, 'initPrice', false);
            $rebateAmount = $this->workflowMoney($variables['rebateAmount'] ?? null, 'rebateAmount', false);
            $freight = $this->workflowOptionalMoney($variables['freight'] ?? null, 'freight');
            $completionDate = $this->workflowDate($variables['completionDate'] ?? null, 'completionDate');
            $dealAmount = $this->incrementCustomerDealAmount((string)($project['CUSTOMER'] ?? ''), $effectiveTenantId);
            $projectState = $productList === [] ? self::SHIPPED_STATE : self::WAIT_DELIVER_STATE;

            $updates = [
                'CONSIGNEE' => $this->workflowRequiredText($variables['consignee'] ?? null, 'consignee', 80),
                'PHONE' => $this->workflowRequiredText($variables['phone'] ?? null, 'phone', 50),
                'UNIT' => $this->workflowRequiredText($variables['unit'] ?? null, 'unit', 80),
                'ADDRESS' => $this->workflowRequiredText($variables['address'] ?? null, 'address', 255),
                'LOGISTICS_CATEGORY' => $this->workflowOptionalText($variables['logisticsCategory'] ?? null, 'logisticsCategory', 80),
                'DELIVERY_NOTE' => $this->workflowOptionalText($variables['deliveryNote'] ?? null, 'deliveryNote', 500),
                'FREIGHT' => $freight,
                'FREIGHT_CATEGORY' => $this->workflowRequiredText($variables['freightCategory'] ?? null, 'freightCategory', 80),
                'ACCOUNT_ID' => $this->workflowRequiredText($variables['accountId'] ?? null, 'accountId', 20),
                'PAYER_CATEGORY' => $this->workflowRequiredText($variables['payerCategory'] ?? null, 'payerCategory', 80),
                'INIT_PRICE' => $initPrice,
                'TOTAL_PRICE' => $initPrice,
                'COMPLETION_DATE' => $completionDate,
                'REBATE_AMOUNT' => $rebateAmount,
                'DEAL_AMOUNT' => $dealAmount,
                'PROCESS_ID' => $processInstanceId,
                'PROJECT_STATE' => $projectState,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $auditUser !== '' ? $auditUser : null,
                'VERSION' => Db::raw('VERSION + 1'),
            ];

            $query = Db::name('biz_sale_project')->where('ID', $projectId);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ($effectiveTenantId !== '') {
                $query->where('TENANT_ID', $effectiveTenantId);
            }
            $query->update($updates);

            $invoicingCount = 0;
            if ($this->workflowBoolean($variables['isInvoicing'] ?? null, 'isInvoicing')) {
                $this->insertWorkflowInvoicing(
                    $this->workflowAssoc($variables['invoicingInfo'] ?? null, 'invoicingInfo'),
                    $projectId,
                    $processInstanceId,
                    $project,
                    $effectiveTenantId,
                    $auditUser,
                    $now
                );
                $invoicingCount = 1;
            }

            return [
                'id' => $projectId,
                'projectId' => $projectId,
                'projectState' => $projectState,
                'productItemCount' => count($productList),
                'fileRelationCount' => $fileRelationCount,
                'invoicingCount' => $invoicingCount,
            ];
        });
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function applyProjectDeliveryFromWorkflow(
        array $variables,
        string $processInstanceId,
        string $tenantId = '',
        string $currentUserId = ''
    ): array {
        $projectId = $this->workflowProjectId($variables);

        return Db::transaction(function () use ($variables, $processInstanceId, $tenantId, $currentUserId, $projectId): array {
            $project = $this->workflowProjectForUpdate($projectId, $tenantId);
            if ((string)($project['PROJECT_STATE'] ?? '') === self::FOLLOW_STATE) {
                throw new RuntimeException('sale project state is FOLLOW', 400);
            }

            $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
            $existingInvoiceQuery = Db::name('biz_sale_project_invoice')->where('PROCESS_ID', $processInstanceId);
            $this->whereNotDeleted($existingInvoiceQuery, 'DELETE_FLAG');
            if ($effectiveTenantId !== '') {
                $existingInvoiceQuery->where('TENANT_ID', $effectiveTenantId);
            }
            $existingInvoice = $existingInvoiceQuery->field('ID')->find();
            if (is_array($existingInvoice) && $existingInvoice !== []) {
                $invoiceId = (string)$existingInvoice['ID'];

                return [
                    'id' => $projectId,
                    'projectId' => $projectId,
                    'projectState' => (string)($project['PROJECT_STATE'] ?? ''),
                    'invoiceId' => $invoiceId,
                    'invoiceItemCount' => (int)Db::name('biz_sale_project_invoice_item')->where('INVOICE_ID', $invoiceId)->count(),
                    'deliveryRecordCount' => (int)Db::name('delivery_record')->where('PROCESS_ID', $processInstanceId)->count(),
                    'inventoryUpdateCount' => 0,
                ];
            }

            $projectProductItemList = $this->workflowList($variables['projectProductItemList'] ?? null, 'projectProductItemList');
            $items = $this->workflowDeliveryItems($projectId, $projectProductItemList, $effectiveTenantId, true);
            $now = date('Y-m-d H:i:s');
            $auditUser = $currentUserId !== '' ? $currentUserId : trim((string)($variables['initiator'] ?? ''));
            $operator = trim((string)($variables['initiator'] ?? $auditUser));
            if ($operator === '') {
                throw new RuntimeException('missing initiator', 400);
            }

            $invoiceId = $this->newId();
            $freightTime = $this->workflowDate($variables['freightTime'] ?? null, 'freightTime');
            Db::name('biz_sale_project_invoice')->insert([
                'ID' => $invoiceId,
                'PROJECT_ID' => $projectId,
                'PROCESS_ID' => $processInstanceId,
                'CONSIGNEE' => $this->workflowRequiredText($variables['consignee'] ?? null, 'consignee', 255),
                'LOGISTICS_CATEGORY' => $this->workflowRequiredText($variables['logisticsCategory'] ?? null, 'logisticsCategory', 20),
                'PHONE' => $this->workflowRequiredText($variables['phone'] ?? null, 'phone', 255),
                'LOGISTICS_ID' => $this->workflowRequiredText($variables['logisticsId'] ?? null, 'logisticsId', 20),
                'FREIGHT' => $this->workflowMoney($variables['freight'] ?? null, 'freight', false),
                'FREIGHT_TIME' => $freightTime,
                'FREIGHT_CATEGORY' => $this->workflowRequiredText($variables['freightCategory'] ?? null, 'freightCategory', 20),
                'UNIT' => $this->workflowRequiredText($variables['unit'] ?? null, 'unit', 100),
                'ADDRESS' => $this->workflowRequiredText($variables['address'] ?? null, 'address', 100),
                'REMARK' => $this->workflowOptionalText($variables['remark'] ?? null, 'remark', 4000),
                'CREATE_TIME' => $now,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_USER' => $auditUser !== '' ? $auditUser : null,
                'UPDATE_TIME' => null,
                'EXT_JSON' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $effectiveTenantId !== '' ? $effectiveTenantId : (string)($project['TENANT_ID'] ?? '1'),
                'OPERATOR' => $operator,
            ]);

            $invoiceItemRows = [];
            foreach ($items as $item) {
                $invoiceItemRows[] = [
                    'ID' => $this->newId(),
                    'INVOICE_ID' => $invoiceId,
                    'PROJECT_PRODUCT_ITEM_ID' => $item['projectProductItemId'],
                    'WAREHOUSES_ID' => $item['warehousesId'],
                    'AMOUNT' => $item['amount'],
                    'REMARK' => $item['remark'] ?? '',
                    'CREATE_TIME' => $now,
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'CREATE_USER' => $auditUser !== '' ? $auditUser : null,
                    'UPDATE_TIME' => null,
                    'EXT_JSON' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $effectiveTenantId !== '' ? $effectiveTenantId : (string)($project['TENANT_ID'] ?? '1'),
                ];
            }
            Db::name('biz_sale_project_invoice_item')->insertAll($invoiceItemRows);

            foreach ($items as $item) {
                $projectItem = $item['projectItem'];
                $nextDelivery = (float)$projectItem['DELIVERY'] + (float)$item['amount'];
                $number = (float)$projectItem['NUMBER'];
                $nextState = abs($nextDelivery - $number) < 0.000001
                    ? self::SHIPPED_PRODUCT_ITEM_STATE
                    : self::PART_WAIT_DELIVER_PRODUCT_ITEM_STATE;

                Db::name('biz_sale_project_product_item')
                    ->where('ID', (string)$projectItem['ID'])
                    ->where('PROJECT_ID', $projectId)
                    ->update([
                        'DELIVERY' => $this->decimalStorage($nextDelivery),
                        'STATE' => $nextState,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $auditUser !== '' ? $auditUser : null,
                        'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                    ]);
            }

            $remark = $this->workflowOptionalText($variables['remark'] ?? null, 'remark', 255) ?? '';
            $deliveryRows = $this->projectDeliveryRecordRows(
                $projectId,
                $items,
                $processInstanceId,
                $freightTime,
                $effectiveTenantId,
                $operator,
                $auditUser,
                $now,
                $remark
            );
            if ($deliveryRows !== []) {
                Db::name('delivery_record')->insertAll($deliveryRows);
            }

            $inventoryIds = [];
            foreach ($deliveryRows as $deliveryRow) {
                $inventoryIds[] = $this->decreaseInventory(
                    (string)$deliveryRow['WAREHOUSES_ID'],
                    (string)$deliveryRow['PRODUCT_ID'],
                    $effectiveTenantId,
                    (string)$deliveryRow['AMOUNT'],
                    $now,
                    $auditUser
                );
            }

            $projectState = $this->correctedProjectState(
                (string)($project['PLAY_STATE'] ?? ''),
                $this->allProductItemsShipped($projectId, $effectiveTenantId)
            );
            $projectQuery = Db::name('biz_sale_project')->where('ID', $projectId);
            $this->whereNotDeleted($projectQuery, 'DELETE_FLAG');
            if ($effectiveTenantId !== '') {
                $projectQuery->where('TENANT_ID', $effectiveTenantId);
            }
            $projectQuery->update([
                'PROJECT_STATE' => $projectState,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $auditUser !== '' ? $auditUser : null,
                'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
            ]);

            return [
                'id' => $projectId,
                'projectId' => $projectId,
                'projectState' => $projectState,
                'invoiceId' => $invoiceId,
                'invoiceItemCount' => count($invoiceItemRows),
                'deliveryRecordCount' => count($deliveryRows),
                'inventoryUpdateCount' => count(array_unique($inventoryIds)),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshProjectPaymentStatusFromWorkflow(string $projectId, string $tenantId = '', string $currentUserId = ''): array
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        return Db::transaction(function () use ($projectId, $tenantId, $currentUserId): array {
            $project = $this->workflowProjectForUpdate($projectId, $tenantId);
            $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
            $totalPriceCents = $this->moneyCents($project['TOTAL_PRICE'] ?? '0');
            $historyAmountCents = $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0');
            $statusFields = $this->projectPaymentStatusFields($projectId, $effectiveTenantId, $totalPriceCents, $historyAmountCents);

            $updates = array_merge($statusFields, [
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'VERSION' => Db::raw('VERSION + 1'),
            ]);

            $query = Db::name('biz_sale_project')->where('ID', $projectId);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ($effectiveTenantId !== '') {
                $query->where('TENANT_ID', $effectiveTenantId);
            }
            $query->update($updates);

            return [
                'id' => $projectId,
                'projectId' => $projectId,
                'projectState' => $statusFields['PROJECT_STATE'],
                'playState' => $statusFields['PLAY_STATE'],
                'amountCollected' => $statusFields['AMOUNT_COLLECTED'],
            ];
        });
    }

    private function pageResult(array $filters, array $payload): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->projectQuery($filters, $payload)->count('DISTINCT p.ID');
        $rows = $this->applySort($this->projectQuery($filters, $payload), $filters)
            ->field(self::PROJECT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();
        $records = $this->projectRows($rows);
        $returnOrders = $this->relatedRowsByIds('return_order', 'PROJECT_ID', array_column($records, 'id'), $payload, 'CREATE_TIME');

        foreach ($records as &$record) {
            $record['returnOrders'] = $returnOrders[(string)$record['id']] ?? [];
        }
        unset($record);

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function projectQuery(array $filters, array $payload, bool $applyDataScope = true)
    {
        $query = Db::name('biz_sale_project')
            ->alias('p')
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->leftJoin('sys_user u', 'u.ID = p.USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->leftJoin('settlement_account a', 'a.ID = p.ACCOUNT_ID');

        if ($this->truthy($filters['isCase'] ?? false)) {
            $query->join('sale_project_rate rate', 'rate.PROJECT_ID = p.ID', 'INNER')->distinct(true);
        }

        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        if (array_key_exists('showDiscard', $filters) && !$this->truthy($filters['showDiscard'])) {
            $query->where('p.PROJECT_STATE', '<>', self::DISCARD_STATE);
        }

        foreach ([
            'id' => 'p.ID',
            'customer' => 'p.CUSTOMER',
            'visibility' => 'p.VISIBILITY',
            'projectCategory' => 'p.PROJECT_CATEGORY',
            'accountId' => 'p.ACCOUNT_ID',
            'payerCategory' => 'p.PAYER_CATEGORY',
            'freightCategory' => 'p.FREIGHT_CATEGORY',
            'processId' => 'p.PROCESS_ID',
            'specialType' => 'p.special_type',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'projectName' => 'p.PROJECT_NAME',
            'projectCode' => 'p.PROJECT_CODE',
            'remark' => 'p.REMARK',
            'customerName' => 'c.NAME',
            'consignee' => 'p.CONSIGNEE',
            'phone' => 'p.PHONE',
            'area' => 'p.AREA',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        $this->whereInIfPresent($query, 'p.PROJECT_STATE', $this->stringList($filters['projectState'] ?? []));
        $this->whereInIfPresent($query, 'p.PLAY_STATE', $this->stringList($filters['playState'] ?? []));
        $this->whereInIfPresent($query, 'c.SOURCE_TYPE', $this->stringList($filters['customerSourceType'] ?? []));

        if (array_key_exists('kickback', $filters) && trim((string)$filters['kickback']) !== '') {
            $this->truthy($filters['kickback'])
                ? $query->where('p.REBATE_AMOUNT', '>=', 1)
                : $query->where('p.REBATE_AMOUNT', '<=', 0);
        }

        if (!empty($filters['user'])) {
            $this->applyUserFilter($query, (string)$filters['user']);
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                '(p.PROJECT_NAME LIKE ? OR p.PROJECT_CODE LIKE ? OR p.REMARK LIKE ? OR c.NAME LIKE ? OR u.NAME LIKE ? OR p.PHONE LIKE ?)',
                [$keyword, $keyword, $keyword, $keyword, $keyword, $keyword]
            );
        }

        if (array_key_exists('totalPrice', $filters) && trim((string)$filters['totalPrice']) !== '') {
            $query->whereLike('p.TOTAL_PRICE', '%' . trim((string)$filters['totalPrice']) . '%');
        }

        if (array_key_exists('amountCollected', $filters) && trim((string)$filters['amountCollected']) !== '') {
            $query->whereLike('p.AMOUNT_COLLECTED', '%' . trim((string)$filters['amountCollected']) . '%');
        }

        $this->applyCreateTimeRange($query, $filters);
        $this->applyCompletionTimeRange($query, $filters);
        if ($applyDataScope) {
            $this->applyDataScope($query, $filters, $payload);
        }

        return $query;
    }

    private function canReadProjectFromWorkflow(string $projectId, array $payload): bool
    {
        $projectId = trim($projectId);
        $userId = $this->currentUserId($payload);
        if ($projectId === '' || $userId === '') {
            return false;
        }

        $processIds = $this->workflowProcessIdsForProject($projectId);
        if ($processIds === []) {
            return false;
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $processIds = array_values(array_filter(array_map(
                'strval',
                Db::name('act_hi_procinst')
                    ->whereIn('PROC_INST_ID_', $processIds)
                    ->where('TENANT_ID_', $tenantId)
                    ->column('PROC_INST_ID_')
            )));
            if ($processIds === []) {
                return false;
            }
        }

        if ((int)Db::name('act_hi_procinst')
            ->whereIn('PROC_INST_ID_', $processIds)
            ->where('START_USER_ID_', $userId)
            ->count() > 0) {
            return true;
        }

        if ((int)Db::name('act_ru_task')
            ->whereIn('PROC_INST_ID_', $processIds)
            ->where('ASSIGNEE_', $userId)
            ->count() > 0) {
            return true;
        }

        if ((int)Db::name('act_hi_taskinst')
            ->whereIn('PROC_INST_ID_', $processIds)
            ->where('ASSIGNEE_', $userId)
            ->count() > 0) {
            return true;
        }

        return $this->workflowVariableListContains(
            $processIds,
            ['approveUserIdList', 'copyUserIdList', 'ccUserIdList'],
            $userId
        );
    }

    /**
     * @return array<int, string>
     */
    private function workflowProcessIdsForProject(string $projectId): array
    {
        $processIds = [];
        foreach (['act_ru_variable', 'act_hi_varinst'] as $table) {
            $rows = Db::name($table)
                ->whereIn('NAME_', ['projectId', 'bizSaleProjectId'])
                ->where(function ($query) use ($projectId): void {
                    $query->where('TEXT_', $projectId)
                        ->whereOr('TEXT2_', $projectId);
                    if (is_numeric($projectId)) {
                        $query->whereOr('LONG_', (int)$projectId);
                    }
                })
                ->column('PROC_INST_ID_');
            foreach ($rows as $processId) {
                $processId = trim((string)$processId);
                if ($processId !== '') {
                    $processIds[] = $processId;
                }
            }
        }

        return array_values(array_unique($processIds));
    }

    /**
     * @param array<int, string> $processIds
     * @param array<int, string> $names
     */
    private function workflowVariableListContains(array $processIds, array $names, string $userId): bool
    {
        if ($processIds === [] || $names === [] || $userId === '') {
            return false;
        }

        foreach (['act_ru_variable', 'act_hi_varinst'] as $table) {
            $rows = Db::name($table)
                ->whereIn('PROC_INST_ID_', $processIds)
                ->whereIn('NAME_', $names)
                ->field('TEXT_,TEXT2_,LONG_')
                ->select()
                ->toArray();

            foreach ($rows as $row) {
                if (in_array($userId, $this->workflowVariableStringList($row), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function workflowVariableStringList(array $row): array
    {
        $text = (string)($row['TEXT_'] ?? '');
        if ($text !== '') {
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->stringList($decoded);
            }

            return $this->stringList($text);
        }

        if ((string)($row['TEXT2_'] ?? '') === '!emptyString!') {
            return [];
        }

        if (array_key_exists('LONG_', $row) && $row['LONG_'] !== null) {
            return [(string)$row['LONG_']];
        }

        return [];
    }

    private function applyUserFilter($query, string $user): void
    {
        $user = trim($user);
        if ($user === '') {
            return;
        }

        $userIds = Db::name('sys_user')
            ->whereLike('NAME', '%' . $user . '%')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID');

        if ($userIds !== []) {
            $query->whereIn('p.USER', array_map('strval', $userIds));

            return;
        }

        $query->where('p.USER', $user);
    }

    private function applyDataScope($query, array $filters, array $payload): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('p.ORG', $orgIds);
            }

            return;
        }

        if ($this->canSeeAll($payload)) {
            return;
        }

        $scope = $payload['data_scope_org_ids'] ?? [];
        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }
        if (is_array($scope)) {
            $scope = array_values(array_filter(array_map(static fn ($id): string => trim((string)$id), $scope)));
            if ($scope !== []) {
                $query->whereIn('p.ORG', $scope);

                return;
            }
        }

        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId !== '') {
            $query->where('p.USER', $userId);
        }
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $scope = $payload['data_scope_org_ids'] ?? [];
        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }
        if (!is_array($scope)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($id): string => trim((string)$id), $scope)));
    }

    private function applyCreateTimeRange($query, array $filters): void
    {
        $this->applyTimeRange($query, 'p.CREATE_TIME', $filters['startCreateTime'] ?? '', $filters['endCreateTime'] ?? '');
    }

    private function applyCompletionTimeRange($query, array $filters): void
    {
        $this->applyTimeRange($query, 'p.COMPLETION_DATE', $filters['startCompletionTime'] ?? '', $filters['endCompletionTime'] ?? '');
    }

    private function applyTimeRange($query, string $column, mixed $startValue, mixed $endValue): void
    {
        $start = trim((string)$startValue);
        $end = trim((string)$endValue);
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        } elseif ($start !== '') {
            $query->whereTime($column, '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime($column, '<=', $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('p.ID', 'asc');
        }

        return $query->order('p.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @return array<int, array<string, mixed>>
     */
    private function detailsForProjects(array $projects, array $payload): array
    {
        $projectIds = array_values(array_filter(array_map(static fn (array $row): string => (string)($row['id'] ?? ''), $projects)));
        if ($projectIds === []) {
            return [];
        }

        $productItems = $this->productItemsByProjectIds($projectIds, $payload);
        $invoiceList = $this->invoiceRowsByProjectIds($projectIds, $payload);
        $invoicingList = $this->relatedRowsByIds('biz_sale_project_invoicing', 'PROJECT_ID', $projectIds, $payload, 'CREATE_TIME');
        $paymentRecords = $this->paymentRowsByProjectIds($projectIds, $payload);
        $followUps = $this->relatedRowsByIds('sale_project_follow_up', 'PROJECT_ID', $projectIds, $payload, 'FOLLOW_UP_TIME');
        $changeLogs = $this->relatedRowsByIds('sales_project_field_change_log', 'OBJECT_ID', $projectIds, $payload, 'CREATE_TIME');
        $returnOrders = $this->relatedRowsByIds('return_order', 'PROJECT_ID', $projectIds, $payload, 'CREATE_TIME');

        return array_map(function (array $project) use (
            $productItems,
            $invoiceList,
            $invoicingList,
            $paymentRecords,
            $followUps,
            $changeLogs,
            $returnOrders
        ): array {
            $projectId = (string)$project['id'];

            return [
                'bizSaleProject' => $project,
                'productItems' => $productItems[$projectId] ?? [],
                'invoicingList' => $invoicingList[$projectId] ?? [],
                'invoiceList' => $invoiceList[$projectId] ?? [],
                'paymentRecords' => $paymentRecords[$projectId] ?? [],
                'saleProjectFollowUps' => $followUps[$projectId] ?? [],
                'changeLogs' => $changeLogs[$projectId] ?? [],
                'returnOrders' => $returnOrders[$projectId] ?? [],
            ];
        }, $projects);
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function productItemsByProjectIds(array $projectIds, array $payload): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->field(self::PRODUCT_ITEM_FIELDS)
            ->whereIn('i.PROJECT_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $rows = $query->order('i.ID', 'asc')->select()->toArray();
        $items = $this->productItemRows($rows);
        $children = $this->childrenByItemIds(array_column($items, 'id'), $payload);
        $result = [];

        foreach ($items as $item) {
            $item['children'] = $children[(string)$item['id']] ?? [];
            $result[(string)$item['projectId']][] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, string> $itemIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function childrenByItemIds(array $itemIds, array $payload): array
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->leftJoin('biz_product p', 'p.ID = r.TARGET_ID')
            ->field('r.*, p.PRODUCT_NAME AS PRODUCT_NAME, p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, p.CATEGORY AS PRODUCT_SYS_CATEGORY, p.SPECS AS SPECS, p.PURCHASE_PRICE AS PURCHASE_PRICE, p.SALE_PRICE AS SALE_PRICE, p.MIN_PRICE AS MIN_PRICE')
            ->whereIn('r.OBJECT_ID', $ids);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('r.ID', 'asc')->select()->toArray() as $row) {
            $child = $this->normalizeRow($row);
            foreach (['purchasePrice', 'salePrice', 'minPrice'] as $decimalField) {
                $child[$decimalField] = $this->decimal($child[$decimalField] ?? null);
            }
            if (empty($child['extJson'])) {
                $child['extJson'] = json_encode([
                    'product' => [
                        'id' => $child['targetId'] ?? null,
                        'productName' => $child['productName'] ?? null,
                        'productCategory' => $child['productCategory'] ?? null,
                        'category' => $child['productSysCategory'] ?? null,
                        'specs' => $child['specs'] ?? null,
                        'purchasePrice' => $child['purchasePrice'] ?? null,
                        'salePrice' => $child['salePrice'] ?? null,
                        'minPrice' => $child['minPrice'] ?? null,
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }
            $result[(string)($child['objectId'] ?? '')][] = $child;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function returnOrdersWithProductList(string $projectId, array $payload): array
    {
        $orders = $this->relatedRowsByIds('return_order', 'PROJECT_ID', [$projectId], $payload, 'CREATE_TIME')[$projectId] ?? [];
        $itemsByOrderId = $this->returnOrderItemsByOrderIds(array_column($orders, 'id'), $payload);

        foreach ($orders as &$order) {
            $order['productList'] = $itemsByOrderId[(string)($order['id'] ?? '')] ?? [];
        }
        unset($order);

        return $orders;
    }

    /**
     * @param array<int, string|null> $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function returnOrderItemsByOrderIds(array $orderIds, array $payload): array
    {
        $ids = $this->stringList($orderIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('return_order_item')
            ->alias('i')
            ->leftJoin('biz_sale_project_product_item pi', 'pi.ID = i.PROJECT_PRODUCT_ITEM_ID')
            ->leftJoin('biz_product bp', 'bp.ID = pi.PRODUCT_ID')
            ->field(<<<SQL
i.ID AS ID,
i.RETURN_ORDER_ID AS RETURN_ORDER_ID,
i.PROJECT_PRODUCT_ITEM_ID AS PROJECT_PRODUCT_ITEM_ID,
i.AMOUNT AS AMOUNT,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
pi.PROJECT_ID AS PROJECT_ID,
pi.PRODUCT_ID AS PRODUCT_ID,
bp.PRODUCT_NAME AS PRODUCT_NAME,
bp.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
bp.CATEGORY AS PRODUCT_SYS_CATEGORY,
bp.SPECS AS SPECS,
bp.PURCHASE_PRICE AS PURCHASE_PRICE,
bp.SALE_PRICE AS SALE_PRICE,
bp.MIN_PRICE AS MIN_PRICE
SQL)
            ->whereIn('i.RETURN_ORDER_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('i.ID', 'asc')->select()->toArray() as $row) {
            $item = $this->normalizeRow($row);
            foreach (['amount', 'purchasePrice', 'salePrice', 'minPrice'] as $decimalField) {
                $item[$decimalField] = $this->decimal($item[$decimalField] ?? null);
            }
            $result[(string)($item['returnOrderId'] ?? '')][] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, float> $productAmounts
     * @param array<string, string|null> $productNames
     */
    private function addProductCostAmount(array &$productAmounts, array &$productNames, array $item, int $direction): void
    {
        $itemNumber = $this->number($item['number'] ?? 0);
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];

        if ($children === []) {
            $productId = trim((string)($item['productId'] ?? ''));
            if ($productId === '') {
                return;
            }

            $productAmounts[$productId] = ($productAmounts[$productId] ?? 0.0) + ($direction * $itemNumber);
            $productNames[$productId] ??= $item['productName'] ?? null;

            return;
        }

        foreach ($children as $child) {
            $productId = trim((string)($child['targetId'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $amount = $this->number($child['number'] ?? 0) * $itemNumber;
            $productAmounts[$productId] = ($productAmounts[$productId] ?? 0.0) + ($direction * $amount);
            $productNames[$productId] ??= $child['productName'] ?? null;
        }
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, float>
     */
    private function averagePurchaseUnitAmounts(array $productIds, array $payload): array
    {
        $ids = $this->stringList($productIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('biz_purchase_order_item')
            ->alias('i')
            ->leftJoin('biz_purchase_order o', 'o.ID = i.PURCHASE_ORDER_ID')
            ->field('i.PRODUCT_ID AS PRODUCT_ID, i.UNIT_AMOUNT AS UNIT_AMOUNT')
            ->whereIn('i.PRODUCT_ID', $ids)
            ->where('o.SETTLEMENT_STATUS', self::PURCHASE_ORDER_SETTLEMENT_COMPLETED);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'o.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $groups = [];
        foreach ($query->select()->toArray() as $row) {
            $productId = trim((string)($row['PRODUCT_ID'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $groups[$productId][] = $this->number($row['UNIT_AMOUNT'] ?? 0);
        }

        $result = [];
        foreach ($groups as $productId => $amounts) {
            $count = count($amounts);
            $result[$productId] = $count === 0 ? 0.0 : round(array_sum($amounts) / $count, 2);
        }

        $missingIds = array_values(array_diff($ids, array_keys($result)));
        if ($missingIds !== []) {
            $productQuery = Db::name('biz_product')
                ->whereIn('ID', $missingIds);
            $this->whereNotDeleted($productQuery, 'DELETE_FLAG');

            if ($tenantId !== '') {
                $productQuery->where('TENANT_ID', $tenantId);
            }

            foreach ($productQuery->column('PURCHASE_PRICE', 'ID') as $productId => $purchasePrice) {
                $result[(string)$productId] = $this->number($purchasePrice);
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function invoiceRowsByProjectIds(array $projectIds, array $payload): array
    {
        $invoices = $this->relatedRowsByIds('biz_sale_project_invoice', 'PROJECT_ID', $projectIds, $payload, 'CREATE_TIME');
        $flat = [];
        foreach ($invoices as $rows) {
            array_push($flat, ...$rows);
        }

        $itemsByInvoiceId = $this->relatedRowsByIds('biz_sale_project_invoice_item', 'INVOICE_ID', array_column($flat, 'id'), $payload, 'CREATE_TIME');
        foreach ($invoices as &$rows) {
            foreach ($rows as &$invoice) {
                $invoice['invoiceItems'] = $itemsByInvoiceId[(string)$invoice['id']] ?? [];
            }
            unset($invoice);
        }
        unset($rows);

        return $invoices;
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function paymentRowsByProjectIds(array $projectIds, array $payload): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('biz_payment_record')
            ->alias('r')
            ->leftJoin('settlement_account a', 'a.ID = r.TARGET_ID')
            ->field('r.*, a.ACCOUNT_NAME AS ACCOUNT_NAME')
            ->whereIn('r.OBJECT_ID', $ids)
            ->where('r.SETTLEMENT_CATEGORY', self::PROJECT_PLAY);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('r.CREATE_TIME', 'asc')->select()->toArray() as $row) {
            $record = $this->normalizeRow($row);
            $result[(string)($record['objectId'] ?? '')][] = $record;
        }

        return $result;
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function relatedRowsByIds(string $table, string $foreignKey, array $ids, array $payload, string $orderField): array
    {
        $ids = $this->stringList($ids);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name($table)->whereIn($foreignKey, $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $result = [];
        $groupKey = $this->camelKey($foreignKey);
        foreach ($query->order($orderField, 'asc')->select()->toArray() as $row) {
            $normalized = $this->normalizeRow($row);
            $result[(string)($normalized[$groupKey] ?? '')][] = $normalized;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function projectRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'customer' => $this->value($row, 'CUSTOMER', 'customer'),
                'customerName' => $this->value($row, 'CUSTOMER_NAME', 'customerName'),
                'customerAddress' => $this->value($row, 'CUSTOMER_ADDRESS', 'customerAddress'),
                'customerSourceType' => $this->value($row, 'CUSTOMER_SOURCE_TYPE', 'customerSourceType'),
                'customType' => $this->value($row, 'CUSTOM_TYPE', 'customType'),
                'projectName' => $this->value($row, 'PROJECT_NAME', 'projectName'),
                'projectState' => $this->value($row, 'PROJECT_STATE', 'projectState'),
                'playState' => $this->value($row, 'PLAY_STATE', 'playState'),
                'visibility' => $this->value($row, 'VISIBILITY', 'visibility'),
                'initPrice' => $this->decimal($this->value($row, 'INIT_PRICE', 'initPrice')),
                'totalPrice' => $this->decimal($this->value($row, 'TOTAL_PRICE', 'totalPrice')),
                'amountCollected' => $this->decimal($this->value($row, 'AMOUNT_COLLECTED', 'amountCollected')),
                'projectCategory' => $this->value($row, 'PROJECT_CATEGORY', 'projectCategory'),
                'user' => $this->value($row, 'PROJECT_USER', 'user'),
                'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
                'headPhone' => $this->value($row, 'HEAD_PHONE', 'headPhone'),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'consignee' => $this->value($row, 'CONSIGNEE', 'consignee'),
                'phone' => $this->value($row, 'PHONE', 'phone'),
                'unit' => $this->value($row, 'UNIT', 'unit'),
                'address' => $this->value($row, 'ADDRESS', 'address'),
                'logisticsCategory' => $this->value($row, 'LOGISTICS_CATEGORY', 'logisticsCategory'),
                'deliveryNote' => $this->value($row, 'DELIVERY_NOTE', 'deliveryNote'),
                'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
                'specimenCategory' => $this->value($row, 'SPECIMEN_CATEGORY', 'specimenCategory'),
                'specimenName' => $this->value($row, 'SPECIMEN_NAME', 'specimenName'),
                'area' => $this->value($row, 'AREA', 'area'),
                'detailsAddress' => $this->value($row, 'DETAILS_ADDRESS', 'detailsAddress'),
                'projectCode' => $this->value($row, 'PROJECT_CODE', 'projectCode'),
                'accountId' => $this->value($row, 'ACCOUNT_ID', 'accountId'),
                'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
                'payerCategory' => $this->value($row, 'PAYER_CATEGORY', 'payerCategory'),
                'freight' => $this->decimal($this->value($row, 'FREIGHT', 'freight')),
                'freightCategory' => $this->value($row, 'FREIGHT_CATEGORY', 'freightCategory'),
                'completionDate' => $this->value($row, 'COMPLETION_DATE', 'completionDate'),
                'rebateAmount' => $this->decimal($this->value($row, 'REBATE_AMOUNT', 'rebateAmount')),
                'dealAmount' => $this->decimal($this->value($row, 'DEAL_AMOUNT', 'dealAmount')),
                'historyAmount' => $this->decimal($this->value($row, 'HISTORY_AMOUNT', 'historyAmount')),
                'totalReturnAmount' => $this->decimal($this->value($row, 'TOTAL_RETURN_AMOUNT', 'totalReturnAmount')),
                'totalRefundAmount' => $this->decimal($this->value($row, 'TOTAL_REFUND_AMOUNT', 'totalRefundAmount')),
                'repealContent' => $this->value($row, 'REPEAL_CONTENT', 'repealContent'),
                'specialType' => $this->value($row, 'SPECIAL_TYPE', 'specialType'),
            ];
        }, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function productItemRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'category' => $this->value($row, 'CATEGORY', 'category'),
                'state' => $this->value($row, 'STATE', 'state'),
                'number' => $this->decimal($this->value($row, 'NUMBER', 'number')),
                'delivery' => $this->decimal($this->value($row, 'DELIVERY', 'delivery')),
                'unitPrice' => $this->decimal($this->value($row, 'UNIT_PRICE', 'unitPrice')),
                'discountRate' => $this->decimal($this->value($row, 'DISCOUNT_RATE', 'discountRate')),
                'price' => $this->decimal($this->value($row, 'PRICE', 'price')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'projectReissueOrderId' => $this->value($row, 'PROJECT_REISSUE_ORDER_ID', 'projectReissueOrderId'),
                'mark' => $this->value($row, 'MARK', 'mark'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
                'productSysCategory' => $this->value($row, 'PRODUCT_SYS_CATEGORY', 'productSysCategory'),
                'specs' => $this->value($row, 'SPECS', 'specs'),
                'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
                'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
                'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
                'children' => [],
            ];
        }, $rows);
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, array<string, mixed>>|null
     */
    private function submittedProductList(array $input): ?array
    {
        if (!$this->hasInputKey($input, ['productList', 'product_list'])) {
            return null;
        }

        $value = $this->inputValue($input, ['productList', 'product_list']);
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('invalid productList', 400);
            }
            if ($decoded === null) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value) || !$this->isListArray($value)) {
            throw new RuntimeException('invalid productList', 400);
        }

        return array_values($value);
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, array<string, mixed>>
     */
    private function deliveryProductItemListInput(array $input): array
    {
        if (!$this->hasInputKey($input, ['projectProductItemList', 'PROJECT_PRODUCT_ITEM_LIST', 'productItemList', 'productList'])) {
            throw new RuntimeException('missing projectProductItemList', 400);
        }

        $items = $this->workflowList(
            $this->inputValue($input, ['projectProductItemList', 'PROJECT_PRODUCT_ITEM_LIST', 'productItemList', 'productList']),
            'projectProductItemList'
        );
        if ($items === []) {
            throw new RuntimeException('missing projectProductItemList', 400);
        }

        return $items;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function deliveryInvoiceDeleteInput(array $input): array
    {
        $source = null;
        if ($this->isListArray($input)) {
            $source = $input;
        } elseif (isset($input['items']) && is_array($input['items'])) {
            $source = $input['items'];
        } elseif (isset($input['ids'])) {
            $source = $input['ids'];
        } elseif (isset($input['idList'])) {
            $source = $input['idList'];
        } elseif ($this->hasInputKey($input, ['id', 'ID'])) {
            $source = [$input];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing idList', 400);
        }

        $ids = [];
        foreach ($source as $item) {
            if (is_array($item)) {
                $id = $this->requiredInputString($item, ['id', 'ID'], 'id');
            } else {
                $id = trim((string)$item);
                if ($id === '') {
                    throw new RuntimeException('missing id', 400);
                }
            }
            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryInvoiceForWrite(string $id, array $payload): array
    {
        $rows = $this->deliveryInvoiceRowsForWrite([$id], $payload);

        return $rows[$id];
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function deliveryInvoiceRowsForWrite(array $ids, array $payload): array
    {
        $idList = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }
        foreach ($idList as $id) {
            $this->assertMaxLength($id, 'id', 20);
        }

        $query = Db::name('biz_sale_project_invoice')
            ->alias('v')
            ->join('biz_sale_project p', 'p.ID = v.PROJECT_ID', 'INNER')
            ->whereIn('v.ID', $idList)
            ->field(
                'v.ID AS ID,' .
                'v.PROJECT_ID AS PROJECT_ID,' .
                'v.PROCESS_ID AS PROCESS_ID,' .
                'v.TENANT_ID AS TENANT_ID,' .
                'p.PROJECT_STATE AS PROJECT_STATE,' .
                'p.PLAY_STATE AS PROJECT_PLAY_STATE,' .
                'p.TENANT_ID AS PROJECT_TENANT_ID'
            )
            ->lock(true);
        $this->whereNotDeleted($query, 'v.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('v.TENANT_ID', $tenantId);
        }
        $this->applyDataScope($query, [], $payload);

        $rowsById = [];
        foreach ($query->select()->toArray() as $row) {
            $rowsById[(string)$row['ID']] = $row;
        }
        if (count($rowsById) !== count($idList)) {
            throw new RuntimeException('sale project invoice not found', 404);
        }

        return $rowsById;
    }

    private function invoiceHasDeliveryRecords(string $processId, string $tenantId): bool
    {
        $processId = trim($processId);
        if ($processId === '') {
            return false;
        }

        $query = Db::name('delivery_record')->where('PROCESS_ID', $processId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count() > 0;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function reissueOrderDeleteInput(array $input): array
    {
        $source = null;
        if ($this->isListArray($input)) {
            $source = $input;
        } elseif (isset($input['items']) && is_array($input['items'])) {
            $source = $input['items'];
        } elseif (isset($input['ids'])) {
            $source = $input['ids'];
        } elseif (isset($input['idList'])) {
            $source = $input['idList'];
        } elseif ($this->hasInputKey($input, ['id', 'ID'])) {
            $source = [$input];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing idList', 400);
        }

        $ids = [];
        foreach ($source as $item) {
            if (is_array($item)) {
                $id = $this->requiredInputString($item, ['id', 'ID'], 'id');
            } else {
                $id = trim((string)$item);
                if ($id === '') {
                    throw new RuntimeException('missing id', 400);
                }
            }
            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function reissueOrderForWrite(string $id, array $payload): array
    {
        $rows = $this->reissueOrderRowsForWrite([$id], $payload);

        return $rows[$id];
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function reissueOrderRowsForWrite(array $ids, array $payload): array
    {
        $idList = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }
        foreach ($idList as $id) {
            $this->assertMaxLength($id, 'id', 20);
        }

        $query = Db::name('biz_sale_project_reissue_order')
            ->alias('o')
            ->join('biz_sale_project p', 'p.ID = o.PROJECT_ID', 'INNER')
            ->whereIn('o.ID', $idList)
            ->field(
                'o.ID AS ID,' .
                'o.PROJECT_ID AS PROJECT_ID,' .
                'o.PROCESS_ID AS PROCESS_ID,' .
                'o.AMOUNT AS AMOUNT,' .
                'o.TENANT_ID AS TENANT_ID,' .
                'p.PROJECT_STATE AS PROJECT_STATE,' .
                'p.PLAY_STATE AS PROJECT_PLAY_STATE,' .
                'p.INIT_PRICE AS INIT_PRICE,' .
                'p.HISTORY_AMOUNT AS HISTORY_AMOUNT,' .
                'p.TOTAL_RETURN_AMOUNT AS TOTAL_RETURN_AMOUNT,' .
                'p.TOTAL_REFUND_AMOUNT AS TOTAL_REFUND_AMOUNT,' .
                'p.TENANT_ID AS PROJECT_TENANT_ID'
            )
            ->lock(true);
        $this->whereNotDeleted($query, 'o.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('o.TENANT_ID', $tenantId);
        }
        $this->applyDataScope($query, [], $payload);

        $rowsById = [];
        foreach ($query->select()->toArray() as $row) {
            $rowsById[(string)$row['ID']] = $row;
        }
        if (count($rowsById) !== count($idList)) {
            throw new RuntimeException('sale project reissue order not found', 404);
        }

        return $rowsById;
    }

    private function workflowProcessExists(string $processId, string $tenantId): bool
    {
        $processId = trim($processId);
        if ($processId === '') {
            return false;
        }

        $query = Db::name('act_hi_procinst')->where('PROC_INST_ID_', $processId);
        if ($tenantId !== '') {
            $query->where('TENANT_ID_', $tenantId);
        }

        return (int)$query->count() > 0;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function assertDirectReissueOrderWritable(array $order, string $tenantId): void
    {
        if ($this->workflowProcessExists((string)($order['PROCESS_ID'] ?? ''), $tenantId)) {
            throw new RuntimeException('sale project reissue order has workflow records', 400);
        }
    }

    /**
     * @param array<int, string> $orderIds
     * @return array<int, array<string, mixed>>
     */
    private function reissueProductItemsForOrderIds(array $orderIds, string $tenantId): array
    {
        $ids = $this->stringList($orderIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_sale_project_product_item')
            ->whereIn('PROJECT_REISSUE_ORDER_ID', $ids)
            ->field('ID,PROJECT_ID,PRODUCT_ID,CATEGORY,STATE,NUMBER,DELIVERY,TENANT_ID,PROJECT_REISSUE_ORDER_ID')
            ->lock(true);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query->order('ID', 'asc')->select()->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function assertReissueProductItemsWritable(array $items, string $tenantId): void
    {
        $itemIds = $this->stringList(array_column($items, 'ID'));
        $referencedIds = $this->referencedProductItemIds($itemIds, $tenantId);
        foreach ($items as $item) {
            $itemId = (string)($item['ID'] ?? '');
            if ((string)($item['CATEGORY'] ?? '') !== self::PRODUCT_ITEM_CATEGORY_REISSUE_ORDER
                || (float)($item['DELIVERY'] ?? 0) > 0.000001
                || (string)($item['STATE'] ?? '') !== self::PRODUCT_ITEM_STATE_WAIT_DELIVER
                || isset($referencedIds[$itemId])) {
                throw new RuntimeException('sale project reissue product item is referenced', 400);
            }
        }
    }

    /**
     * @param array<int, string> $itemIds
     */
    private function reissueProductItemRelationCountByItemIds(array $itemIds, string $tenantId): int
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return 0;
        }

        $query = Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count();
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, string>
     */
    private function recalculateReissueProjectTotals(
        array $project,
        string $projectId,
        string $tenantId,
        string $now,
        string $currentUserId,
        bool $preferDeliveryCorrection
    ): array {
        $totalPriceCents = 0;
        $totalRefundCents = 0;
        $totalReturnCents = 0;
        $statusFields = [];
        try {
            [$totalPriceCents, $totalRefundCents, $totalReturnCents] = $this->correctedProjectTotals(
                $projectId,
                $tenantId,
                $this->moneyCents($project['INIT_PRICE'] ?? '0'),
                $this->moneyCents($project['TOTAL_REFUND_AMOUNT'] ?? '0'),
                $this->moneyCents($project['TOTAL_RETURN_AMOUNT'] ?? '0')
            );
            $statusFields = $this->projectPaymentStatusFields(
                $projectId,
                $tenantId,
                $totalPriceCents,
                $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0')
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'amount collected exceeds sale project total price') {
                throw $exception;
            }
            $playState = (string)($project['PROJECT_PLAY_STATE'] ?? $project['PLAY_STATE'] ?? self::UNPAID_PLAY_STATE);
            $statusFields = [
                'AMOUNT_COLLECTED' => $this->moneyFromCents(
                    $this->sumProjectPaymentRecordCents($projectId, $tenantId)
                    + $this->moneyCents($project['HISTORY_AMOUNT'] ?? '0')
                ),
                'PLAY_STATE' => $playState,
                'PROJECT_STATE' => $this->correctedProjectState($playState, $this->allProductItemsShipped($projectId, $tenantId)),
            ];
        }

        if ($preferDeliveryCorrection) {
            $statusFields['PROJECT_STATE'] = $this->hasActiveProjectProductItems($projectId, $tenantId)
                ? $this->correctedProjectStateAfterDeliveryCorrection($projectId, $tenantId, $statusFields['PLAY_STATE'])
                : self::WAIT_DELIVER_STATE;
        }

        $projectUpdate = array_merge($statusFields, [
            'TOTAL_RETURN_AMOUNT' => $this->moneyFromCents($totalReturnCents),
            'TOTAL_REFUND_AMOUNT' => $this->moneyFromCents($totalRefundCents),
            'TOTAL_PRICE' => $this->moneyFromCents($totalPriceCents),
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
        ]);
        $projectUpdateQuery = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($projectUpdateQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $projectUpdateQuery->where('TENANT_ID', $tenantId);
        }
        $projectUpdateQuery->update($projectUpdate);

        return $projectUpdate;
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     */
    private function syncProductItems(
        string $projectId,
        array $productList,
        string $tenantId,
        array $payload,
        string $now,
        string $currentUserId
    ): void {
        $existingRows = $this->activeProductItemsForUpdate($projectId, $tenantId);
        $existingById = [];
        foreach ($existingRows as $row) {
            $existingById[(string)$row['ID']] = $row;
        }

        $items = $this->normalizedProductItems($productList, $tenantId, $existingById, $payload);
        $referencedIds = $this->referencedProductItemIds(array_keys($existingById), $tenantId);
        $submittedExistingIds = array_values(array_filter(array_map(
            static fn (array $item): string => (string)($item['id'] ?? ''),
            $items
        )));

        $deleteIds = array_values(array_diff(array_keys($existingById), $submittedExistingIds));
        foreach ($deleteIds as $deleteId) {
            if (isset($referencedIds[$deleteId])) {
                throw new RuntimeException('sale project product item is referenced', 400);
            }
        }

        $this->softDeleteProductItemRelations($deleteIds, $tenantId, $now, $currentUserId);
        $this->softDeleteProductItems($deleteIds, $tenantId, $now, $currentUserId);

        foreach ($items as $item) {
            $itemId = (string)($item['id'] ?? '');
            if ($itemId === '') {
                $newItemId = $this->newId();
                $this->insertProductItem($projectId, $newItemId, $item, $tenantId, $now, $currentUserId);
                $this->replaceProductItemRelations($newItemId, $item['children'], $tenantId, $now, $currentUserId);

                continue;
            }

            $existing = $existingById[$itemId] ?? null;
            if ($existing === null) {
                throw new RuntimeException('sale project product item not found', 404);
            }

            $childrenChanged = $this->productItemChildrenChanged($itemId, $item['children'], $tenantId);
            if (isset($referencedIds[$itemId]) && ($this->protectedProductItemChanged($existing, $item) || $childrenChanged)) {
                throw new RuntimeException('sale project product item is referenced', 400);
            }
            if (isset($referencedIds[$itemId])) {
                $this->updateProductItemRemarkOnly($itemId, $item, $tenantId, $now, $currentUserId);

                continue;
            }

            $this->updateProductItem($itemId, $item, $tenantId, $now, $currentUserId);
            $this->replaceProductItemRelations($itemId, $item['children'], $tenantId, $now, $currentUserId);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeProductItemsForUpdate(string $projectId, string $tenantId): array
    {
        $query = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_ID', $projectId)
            ->lock(true);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query->order('ID', 'asc')->select()->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function standaloneProjectForProductItemWrite(string $projectId, array $payload): array
    {
        $project = $this->projectQuery(['id' => $projectId], $payload)
            ->field('p.ID AS ID, p.PROJECT_STATE AS PROJECT_STATE, p.TENANT_ID AS TENANT_ID')
            ->lock(true)
            ->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    private function standaloneProductItemForWrite(string $itemId, array $payload): array
    {
        $rows = $this->standaloneProductItemsForWrite([$itemId], $payload);

        return $rows[$itemId];
    }

    /**
     * @param array<int, string> $itemIds
     * @return array<string, array<string, mixed>>
     */
    private function standaloneProductItemsForWrite(array $itemIds, array $payload): array
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $query = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->join('biz_sale_project p', 'p.ID = i.PROJECT_ID', 'INNER')
            ->whereIn('i.ID', $ids)
            ->field('i.*, p.PROJECT_STATE AS PROJECT_STATE, p.TENANT_ID AS PROJECT_TENANT_ID')
            ->lock(true);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }
        $this->applyDataScope($query, [], $payload);

        $rowsById = [];
        foreach ($query->select()->toArray() as $row) {
            $rowsById[(string)$row['ID']] = $row;
        }
        if (count($rowsById) !== count($ids)) {
            throw new RuntimeException('sale project product item not found', 404);
        }

        return $rowsById;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function standaloneProductItemDeleteInput(array $input): array
    {
        $source = null;
        if ($this->isListArray($input)) {
            $source = $input;
        } elseif (isset($input['items']) && is_array($input['items'])) {
            $source = $input['items'];
        } elseif (isset($input['ids'])) {
            $source = $input['ids'];
        } elseif (isset($input['idList'])) {
            $source = $input['idList'];
        } elseif ($this->hasInputKey($input, ['id', 'ID'])) {
            $source = [$input];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing idList', 400);
        }

        $ids = [];
        foreach ($source as $item) {
            if (is_array($item)) {
                $id = $this->requiredInputString($item, ['id', 'ID'], 'id');
            } else {
                $id = trim((string)$item);
                if ($id === '') {
                    throw new RuntimeException('missing id', 400);
                }
            }

            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return $ids;
    }

    /**
     * @return array<int, array{productId: string, number: string, remark: ?string, mark: string}>
     */
    private function currentProductItemChildren(string $itemId, string $tenantId): array
    {
        $query = Db::name('sale_project_product_item_relation')
            ->where('OBJECT_ID', $itemId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $children = [];
        foreach ($query->order('ID', 'asc')->select()->toArray() as $row) {
            $targetId = trim((string)($row['TARGET_ID'] ?? ''));
            if ($targetId === '') {
                continue;
            }

            $children[] = [
                'productId' => $targetId,
                'number' => $this->requiredPositiveIntegerString($row['NUMBER'] ?? null, 'children.number'),
                'remark' => $row['REMARK'] === null ? null : (string)$row['REMARK'],
                'mark' => (string)($row['MARK'] ?? ''),
            ];
        }

        return $children;
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     * @param array<string, array<string, mixed>> $existingById
     * @return array<int, array<string, mixed>>
     */
    private function normalizedProductItems(array $productList, string $tenantId, array $existingById, array $payload): array
    {
        $drafts = [];
        $topProductIds = [];
        $seenProductIds = [];
        $seenItemIds = [];

        foreach ($productList as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException("invalid productList.{$index}", 400);
            }

            $rawId = $this->optionalInputString($item, ['id', 'ID']);
            $existingItemId = $rawId !== null && isset($existingById[$rawId]) ? $rawId : null;
            if ($existingItemId !== null) {
                if (isset($seenItemIds[$existingItemId])) {
                    throw new RuntimeException('duplicate product item id', 400);
                }
                $seenItemIds[$existingItemId] = true;
            }

            $productId = $this->optionalInputString($item, ['productId', 'PRODUCT_ID', 'targetId', 'TARGET_ID']);
            if ($productId === null && $existingItemId === null && $rawId !== null) {
                $productId = $rawId;
            }
            if ($productId === null && $existingItemId !== null) {
                $productId = trim((string)($existingById[$existingItemId]['PRODUCT_ID'] ?? ''));
            }
            if ($productId === null || $productId === '') {
                throw new RuntimeException("missing productList.{$index}.productId", 400);
            }
            $this->assertMaxLength($productId, "productList.{$index}.productId", 20);
            if (isset($seenProductIds[$productId])) {
                throw new RuntimeException('duplicate productId', 400);
            }
            $seenProductIds[$productId] = true;
            $topProductIds[] = $productId;

            $drafts[] = [
                'id' => $existingItemId,
                'productId' => $productId,
                'number' => $this->requiredPositiveIntegerString($this->inputValue($item, ['number', 'NUMBER']), "productList.{$index}.number"),
                'unitPrice' => $this->requiredNonNegativeMoneyString($this->inputValue($item, ['unitPrice', 'UNIT_PRICE']), "productList.{$index}.unitPrice"),
                'discountRate' => $this->requiredNonNegativeMoneyString($this->inputValue($item, ['discountRate', 'DISCOUNT_RATE']), "productList.{$index}.discountRate"),
                'price' => $this->requiredNonNegativeMoneyString($this->inputValue($item, ['price', 'PRICE']), "productList.{$index}.price"),
                'remark' => $this->nullableText($this->inputValue($item, ['remark', 'REMARK']), "productList.{$index}.remark", 255),
                'children' => $this->hasInputKey($item, ['children']) ? $this->normalizedProductItemChildren($this->inputValue($item, ['children']), "productList.{$index}.children") : null,
            ];
        }

        $productsById = $this->activeProductRowsByIds($topProductIds, $tenantId, $payload);
        $kitChildrenByProductId = $this->kitChildrenByProductIds($topProductIds, $tenantId);
        $childProductIds = [];

        foreach ($drafts as &$draft) {
            $product = $productsById[$draft['productId']] ?? null;
            if ($product === null) {
                throw new RuntimeException('product not found', 404);
            }

            if ($draft['children'] === null) {
                $draft['children'] = (string)($product['CATEGORY'] ?? '') === self::KIT_PRODUCT
                    ? ($kitChildrenByProductId[$draft['productId']] ?? [])
                    : [];
            }

            foreach ($draft['children'] as $child) {
                $childProductIds[] = (string)$child['productId'];
            }
        }
        unset($draft);

        $childProductsById = $this->activeProductRowsByIds($childProductIds, $tenantId, $payload);

        foreach ($drafts as &$draft) {
            $draft['product'] = $this->productSnapshot($productsById[$draft['productId']]);
            foreach ($draft['children'] as &$child) {
                $childProduct = $childProductsById[(string)$child['productId']] ?? null;
                if ($childProduct === null) {
                    throw new RuntimeException('product not found', 404);
                }
                $child['product'] = $this->productSnapshot($childProduct);
            }
            unset($child);
        }
        unset($draft);

        return $drafts;
    }

    /**
     * @return array<int, array{productId: string, number: string, remark: ?string, mark: string}>
     */
    private function normalizedProductItemChildren(mixed $value, string $label): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("invalid {$label}", 400);
            }
            $value = $decoded ?? [];
        }
        if (!is_array($value) || !$this->isListArray($value)) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        $children = [];
        $seen = [];
        foreach ($value as $index => $child) {
            if (!is_array($child)) {
                throw new RuntimeException("invalid {$label}.{$index}", 400);
            }

            $productId = $this->optionalInputString($child, ['productId', 'PRODUCT_ID', 'targetId', 'TARGET_ID', 'id', 'ID']);
            if ($productId === null || $productId === '') {
                throw new RuntimeException("missing {$label}.{$index}.productId", 400);
            }
            $this->assertMaxLength($productId, "{$label}.{$index}.productId", 20);
            if (isset($seen[$productId])) {
                throw new RuntimeException('duplicate child productId', 400);
            }
            $seen[$productId] = true;

            $children[] = [
                'productId' => $productId,
                'number' => $this->requiredPositiveIntegerString($this->inputValue($child, ['number', 'NUMBER']), "{$label}.{$index}.number"),
                'remark' => $this->nullableText($this->inputValue($child, ['remark', 'REMARK']), "{$label}.{$index}.remark", 255),
                'mark' => $this->nullableText($this->inputValue($child, ['mark', 'MARK']), "{$label}.{$index}.mark", 255) ?? '',
            ];
        }

        return $children;
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, array<string, mixed>>
     */
    private function activeProductRowsByIds(array $productIds, string $tenantId, array $payload): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $productIds))));
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_product')
            ->whereIn('ID', $ids)
            ->where('status', self::PRODUCT_STATUS_ENABLE);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('ORG', $scopeOrgIds);
        }

        $rows = $query->select()->toArray();
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('product not found', 404);
        }

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['ID']] = $row;
        }

        return $result;
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, array<int, array{productId: string, number: string, remark: ?string, mark: string}>>
     */
    private function kitChildrenByProductIds(array $productIds, string $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $productIds))));
        if ($ids === []) {
            return [];
        }

        $query = Db::name('product_relation')
            ->whereIn('OBJECT_ID', $ids)
            ->where('CATEGORY', self::KIT_PRODUCT_DATA);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('ID', 'asc')->select()->toArray() as $row) {
            $targetId = trim((string)($row['TARGET_ID'] ?? ''));
            if ($targetId === '') {
                continue;
            }

            $result[(string)($row['OBJECT_ID'] ?? '')][] = [
                'productId' => $targetId,
                'number' => $this->relationExtJsonNumber((string)($row['EXT_JSON'] ?? '')),
                'remark' => null,
                'mark' => '',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private function referencedProductItemIds(array $itemIds, string $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $itemIds))));
        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach ([
            'biz_sale_project_invoice_item' => 'PROJECT_PRODUCT_ITEM_ID',
            'return_order_item' => 'PROJECT_PRODUCT_ITEM_ID',
        ] as $table => $column) {
            $query = Db::name($table)->whereIn($column, $ids);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $query->where('TENANT_ID', $tenantId);
            }
            foreach ($query->column($column) as $id) {
                $result[(string)$id] = true;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insertProductItem(string $projectId, string $itemId, array $item, string $tenantId, string $now, string $currentUserId): void
    {
        Db::name('biz_sale_project_product_item')->insert([
            'ID' => $itemId,
            'PROJECT_ID' => $projectId,
            'PRODUCT_ID' => $item['productId'],
            'CATEGORY' => self::PRODUCT_ITEM_CATEGORY_INIT,
            'STATE' => self::PRODUCT_ITEM_STATE_WAIT_DELIVER,
            'NUMBER' => $item['number'],
            'DELIVERY' => '0',
            'UNIT_PRICE' => $item['unitPrice'],
            'DISCOUNT_RATE' => $item['discountRate'],
            'PRICE' => $item['price'],
            'REMARK' => $item['remark'],
            'EXT_JSON' => null,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
            'VERSION' => 0,
            'PROJECT_REISSUE_ORDER_ID' => '',
            'MARK' => '',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{productItemCount: int, relationCount: int}
     */
    private function insertReissueProductItems(
        string $projectId,
        string $reissueOrderId,
        array $items,
        string $tenantId,
        string $now,
        string $currentUserId
    ): array {
        $productItemCount = 0;
        $relationCount = 0;
        foreach ($items as $item) {
            $itemId = $this->newId();
            Db::name('biz_sale_project_product_item')->insert([
                'ID' => $itemId,
                'PROJECT_ID' => $projectId,
                'PRODUCT_ID' => $item['productId'],
                'CATEGORY' => self::PRODUCT_ITEM_CATEGORY_REISSUE_ORDER,
                'STATE' => self::PRODUCT_ITEM_STATE_WAIT_DELIVER,
                'NUMBER' => $item['number'],
                'DELIVERY' => '0',
                'UNIT_PRICE' => $item['unitPrice'],
                'DISCOUNT_RATE' => $item['discountRate'],
                'PRICE' => $item['price'],
                'REMARK' => $item['remark'],
                'EXT_JSON' => null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
                'VERSION' => 0,
                'PROJECT_REISSUE_ORDER_ID' => $reissueOrderId,
                'MARK' => '',
            ]);

            $this->replaceProductItemRelations($itemId, $item['children'], $tenantId, $now, $currentUserId);
            $productItemCount++;
            $relationCount += count($item['children']);
        }

        return [
            'productItemCount' => $productItemCount,
            'relationCount' => $relationCount,
        ];
    }

    private function reissueProductItemCount(string $reissueOrderId, string $projectId, string $tenantId): int
    {
        $query = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_REISSUE_ORDER_ID', $reissueOrderId)
            ->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count();
    }

    private function reissueProductItemRelationCount(string $reissueOrderId, string $projectId, string $tenantId): int
    {
        $itemQuery = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_REISSUE_ORDER_ID', $reissueOrderId)
            ->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($itemQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $itemQuery->where('TENANT_ID', $tenantId);
        }
        $itemIds = $this->stringList($itemQuery->column('ID'));
        if ($itemIds === []) {
            return 0;
        }

        $relationQuery = Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', $itemIds);
        $this->whereNotDeleted($relationQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $relationQuery->where('TENANT_ID', $tenantId);
        }

        return (int)$relationQuery->count();
    }

    /**
     * @param array<string, mixed> $item
     */
    private function updateProductItem(string $itemId, array $item, string $tenantId, string $now, string $currentUserId): void
    {
        $query = Db::name('biz_sale_project_product_item')->where('ID', $itemId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $query->update([
            'PRODUCT_ID' => $item['productId'],
            'CATEGORY' => self::PRODUCT_ITEM_CATEGORY_INIT,
            'STATE' => self::PRODUCT_ITEM_STATE_WAIT_DELIVER,
            'NUMBER' => $item['number'],
            'UNIT_PRICE' => $item['unitPrice'],
            'DISCOUNT_RATE' => $item['discountRate'],
            'PRICE' => $item['price'],
            'REMARK' => $item['remark'],
            'EXT_JSON' => null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'VERSION' => Db::raw('VERSION + 1'),
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function updateProductItemRemarkOnly(string $itemId, array $item, string $tenantId, string $now, string $currentUserId): void
    {
        $query = Db::name('biz_sale_project_product_item')->where('ID', $itemId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $query->update([
            'REMARK' => $item['remark'],
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'VERSION' => Db::raw('VERSION + 1'),
        ]);
    }

    /**
     * @param array<int, string> $itemIds
     */
    private function softDeleteProductItems(array $itemIds, string $tenantId, string $now, string $currentUserId): void
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return;
        }

        $query = Db::name('biz_sale_project_product_item')->whereIn('ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        $query->update([
            'DELETE_FLAG' => self::DELETED,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'VERSION' => Db::raw('VERSION + 1'),
        ]);
    }

    /**
     * @param array<int, string> $itemIds
     */
    private function softDeleteProductItemRelations(array $itemIds, string $tenantId, string $now, string $currentUserId): void
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return;
        }

        $query = Db::name('sale_project_product_item_relation')->whereIn('OBJECT_ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        $query->update([
            'DELETE_FLAG' => self::DELETED,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private function replaceProductItemRelations(string $itemId, array $children, string $tenantId, string $now, string $currentUserId): void
    {
        $this->softDeleteProductItemRelations([$itemId], $tenantId, $now, $currentUserId);
        if ($children === []) {
            return;
        }

        $rows = [];
        foreach ($children as $child) {
            $rows[] = [
                'ID' => $this->newId(),
                'OBJECT_ID' => $itemId,
                'TARGET_ID' => $child['productId'],
                'MARK' => $child['mark'] ?? '',
                'NUMBER' => $child['number'],
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'EXT_JSON' => json_encode(['product' => $child['product']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
                'REMARK' => $child['remark'],
            ];
        }

        Db::name('sale_project_product_item_relation')->insertAll($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private function productItemChildrenChanged(string $itemId, array $children, string $tenantId): bool
    {
        $query = Db::name('sale_project_product_item_relation')
            ->where('OBJECT_ID', $itemId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $current = [];
        foreach ($query->select()->toArray() as $row) {
            $current[] = [
                'productId' => (string)($row['TARGET_ID'] ?? ''),
                'number' => (string)($row['NUMBER'] ?? ''),
                'remark' => (string)($row['REMARK'] ?? ''),
            ];
        }

        $next = array_map(static fn (array $child): array => [
            'productId' => (string)$child['productId'],
            'number' => (string)$child['number'],
            'remark' => (string)($child['remark'] ?? ''),
        ], $children);

        $sort = static function (array &$rows): void {
            usort($rows, static fn (array $a, array $b): int => [$a['productId'], $a['number'], $a['remark']] <=> [$b['productId'], $b['number'], $b['remark']]);
        };
        $sort($current);
        $sort($next);

        return $current !== $next;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $item
     */
    private function protectedProductItemChanged(array $existing, array $item): bool
    {
        return trim((string)($existing['PRODUCT_ID'] ?? '')) !== (string)$item['productId']
            || (string)(int)($existing['NUMBER'] ?? 0) !== (string)$item['number']
            || $this->moneyCents($existing['UNIT_PRICE'] ?? '0') !== $this->moneyCents($item['unitPrice'])
            || $this->moneyCents($existing['DISCOUNT_RATE'] ?? '0') !== $this->moneyCents($item['discountRate'])
            || $this->moneyCents($existing['PRICE'] ?? '0') !== $this->moneyCents($item['price']);
    }

    /**
     * @return array<string, mixed>
     */
    private function productSnapshot(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'productName' => $row['PRODUCT_NAME'] ?? null,
            'productCategory' => $row['PRODUCT_CATEGORY'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'specs' => $row['SPECS'] ?? null,
            'purchasePrice' => $this->decimal($row['PURCHASE_PRICE'] ?? null),
            'salePrice' => $this->decimal($row['SALE_PRICE'] ?? null),
            'minPrice' => $this->decimal($row['MIN_PRICE'] ?? null),
        ];
    }

    private function relationExtJsonNumber(string $extJson): string
    {
        $number = '1';
        if (trim($extJson) !== '') {
            $decoded = json_decode($extJson, true);
            if (is_array($decoded) && isset($decoded['number'])) {
                $number = (string)$decoded['number'];
            }
        }

        return $this->requiredPositiveIntegerString($number, 'children.number');
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function optionalInputString(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if ($value === null || $value === '') {
                return null;
            }
            if (is_array($value) || is_object($value) || is_bool($value)) {
                throw new RuntimeException("invalid {$key}", 400);
            }

            $value = trim((string)$value);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function nullableText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        $value = trim((string)$value);
        $this->assertMaxLength($value, $field, $maxLength);

        return $value;
    }

    private function requiredPositiveIntegerString(mixed $value, string $field): string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("missing {$field}", 400);
        }

        $raw = trim((string)$value);
        if (!preg_match('/^\d+(?:\.0+)?$/', $raw)) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        $whole = explode('.', $raw, 2)[0];
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if ($whole === '0' || strlen($whole) > 15) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return $whole;
    }

    private function requiredNonNegativeMoneyString(mixed $value, string $field): string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("missing {$field}", 400);
        }

        $raw = trim((string)$value);
        if ($raw === '' || (!preg_match('/^-?\d+(?:\.\d+)?$/', $raw) && !is_numeric($value))) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        $cents = $this->moneyCents($raw);
        if ($cents < 0) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return $this->moneyFromCents($cents);
    }

    /**
     * @return array<string, mixed>
     */
    private function assertCustomerWritableForProject(string $customerId, array $payload): array
    {
        $customerId = trim($customerId);
        $this->assertMaxLength($customerId, 'customer', 20);

        $query = Db::name('customer')
            ->where('ID', $customerId)
            ->field('ID,ORG,USER,STATUS,TENANT_ID,FILE_ID');
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $payloadTenant = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($payloadTenant !== '') {
            $query->where('TENANT_ID', $payloadTenant);
        }

        $customer = $query->find();
        if (!is_array($customer) || $customer === []) {
            throw new RuntimeException('customer not found', 404);
        }
        $status = trim((string)($customer['STATUS'] ?? ''));
        if ($status !== '' && $status !== self::CUSTOMER_STATUS_ENABLE) {
            throw new RuntimeException('customer status is not ENABLE', 400);
        }

        if ($this->canSeeAll($payload)) {
            return $customer;
        }

        $orgId = trim((string)($customer['ORG'] ?? ''));
        if ($orgId !== '' && in_array($orgId, $this->scopeOrgIds($payload), true)) {
            return $customer;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '' && $userId === trim((string)($customer['USER'] ?? ''))) {
            return $customer;
        }

        throw new RuntimeException('customer is outside data scope', 403);
    }

    private function ensureCustomerBusinessLicense(array $customer, array $input, string $tenantId, string $userId, string $now): void
    {
        $existingFileId = trim((string)($customer['FILE_ID'] ?? ''));
        if ($existingFileId !== '') {
            return;
        }

        $fileId = $this->optionalInputString($input, ['businessLicenseFileId', 'customerFileId', 'licenseFileId']) ?? '';
        if ($fileId === '') {
            throw new RuntimeException('missing businessLicenseFileId', 400);
        }
        $this->assertMaxLength($fileId, 'businessLicenseFileId', 20);
        $this->assertFileExists($fileId, $tenantId);

        Db::name('customer')
            ->where('ID', (string)$customer['ID'])
            ->update([
                'FILE_ID' => $fileId,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'VERSION' => Db::raw('IFNULL(VERSION, 0) + 1'),
            ]);
    }

    private function assertFileExists(string $fileId, string $tenantId): void
    {
        $query = Db::name('dev_file')->where('ID', $fileId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where(function ($query) use ($tenantId): void {
                $query->whereNull('TENANT_ID')->whereOr('TENANT_ID', '=', $tenantId);
            });
        }

        if ((int)$query->count() === 0) {
            throw new RuntimeException('business license file not found', 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assertUserWritable(string $userId, array $payload): array
    {
        $userId = trim($userId);
        $this->assertMaxLength($userId, 'user', 20);

        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ID,ORG_ID,USER_STATUS,TENANT_ID');
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $payloadTenant = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($payloadTenant !== '') {
            $query->where('TENANT_ID', $payloadTenant);
        }

        $user = $query->find();
        if (!is_array($user) || $user === []) {
            throw new RuntimeException('user not found', 404);
        }
        $status = trim((string)($user['USER_STATUS'] ?? ''));
        if ($status !== '' && $status !== self::USER_STATUS_ENABLE) {
            throw new RuntimeException('user status is not ENABLE', 400);
        }

        if ($this->canSeeAll($payload) || $this->currentUserId($payload) === $userId) {
            return $user;
        }

        $orgId = trim((string)($user['ORG_ID'] ?? ''));
        if ($orgId !== '' && in_array($orgId, $this->scopeOrgIds($payload), true)) {
            return $user;
        }

        $currentOrgId = trim((string)($this->currentUserRow($payload)['ORG_ID'] ?? ''));
        if ($currentOrgId !== '' && $orgId !== '' && in_array($orgId, $this->orgAndChildren($currentOrgId), true)) {
            return $user;
        }

        throw new RuntimeException('user is outside data scope', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function assertOrgWritable(string $orgId, array $payload): array
    {
        $orgId = trim($orgId);
        $this->assertMaxLength($orgId, 'orgId', 20);

        $query = Db::name('sys_org')
            ->where('ID', $orgId)
            ->field('ID,TENANT_ID');
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $payloadTenant = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($payloadTenant !== '') {
            $query->where('TENANT_ID', $payloadTenant);
        }

        $org = $query->find();
        if (!is_array($org) || $org === []) {
            throw new RuntimeException('org not found', 404);
        }

        if ($this->canSeeAll($payload) || in_array($orgId, $this->scopeOrgIds($payload), true)) {
            return $org;
        }

        $currentOrgId = trim((string)($this->currentUserRow($payload)['ORG_ID'] ?? ''));
        if ($currentOrgId !== '' && in_array($orgId, $this->orgAndChildren($currentOrgId), true)) {
            return $org;
        }

        throw new RuntimeException('org is outside data scope', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentUserRow(array $payload): array
    {
        $userId = $this->currentUserId($payload);
        $fallback = [
            'ID' => $userId,
            'ORG_ID' => trim((string)($payload['org_id'] ?? $payload['orgId'] ?? '')),
            'TENANT_ID' => trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '')),
        ];
        if ($userId === '') {
            return $fallback;
        }

        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ID,ORG_ID,TENANT_ID,USER_STATUS');
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $row = $query->find();
        if (!is_array($row) || $row === []) {
            return $fallback;
        }

        return array_merge($fallback, $row);
    }

    private function writeTenantId(array $input, array $payload, array $fallback = []): string
    {
        $inputTenant = trim((string)($input['tenantId'] ?? $input['TENANT_ID'] ?? ''));
        $fallbackTenant = trim((string)($fallback['TENANT_ID'] ?? $fallback['tenantId'] ?? ''));
        $payloadTenant = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $tenantId = $inputTenant !== '' ? $inputTenant : ($fallbackTenant !== '' ? $fallbackTenant : $payloadTenant);

        if ($tenantId === '') {
            return '1';
        }
        if ($payloadTenant !== '' && $tenantId !== $payloadTenant) {
            throw new RuntimeException('tenant mismatch', 403);
        }
        if ($fallbackTenant !== '' && $tenantId !== $fallbackTenant) {
            throw new RuntimeException('tenant mismatch', 403);
        }
        $this->assertMaxLength($tenantId, 'tenantId', 20);

        return $tenantId;
    }

    private function createHistoryCustomer(string $customerName, string $userId, ?string $orgId, string $tenantId, string $createTime, array $payload): string
    {
        $customerId = $this->newId();
        $customerName = trim($customerName);
        $orgId = trim((string)$orgId);
        $this->assertMaxLength($customerName, 'customerName', 255);
        $this->assertMaxLength($userId, 'user', 20);
        if ($orgId !== '') {
            $this->assertMaxLength($orgId, 'orgId', 20);
        }

        $currentUserId = $this->currentUserId($payload);
        Db::name('customer')->insert([
            'ID' => $customerId,
            'NAME' => $customerName,
            'CUSTOM_TYPE' => self::CUSTOMER_TYPE_OLD,
            'ORG' => $orgId !== '' ? $orgId : null,
            'USER' => $userId,
            'STATUS' => self::CUSTOMER_STATUS_ENABLE,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $createTime,
            'CREATE_USER' => $currentUserId !== '' ? $currentUserId : $userId,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId,
            'VERSION' => 0,
            'DEAL_AMOUNT' => 0,
            'FIRST_CONTACT_TIME' => $createTime,
        ]);

        return $customerId;
    }

    /**
     * @param array<int, array<string, mixed>> $projectProductItemList
     * @return array<int, array<string, mixed>>
     */
    private function workflowDeliveryItems(string $projectId, array $projectProductItemList, string $tenantId, bool $lock): array
    {
        if ($projectProductItemList === []) {
            throw new RuntimeException('missing projectProductItemList', 400);
        }

        $items = [];
        $itemIds = [];
        $warehouseIds = [];
        foreach ($projectProductItemList as $index => $item) {
            $label = "projectProductItemList.{$index}";
            $itemId = $this->workflowRequiredText(
                $item['projectProductItemId'] ?? $item['PROJECT_PRODUCT_ITEM_ID'] ?? $item['id'] ?? null,
                $label . '.projectProductItemId',
                80
            );
            $productId = $this->workflowOptionalText(
                $item['productId'] ?? $item['PRODUCT_ID'] ?? null,
                $label . '.productId',
                20
            ) ?? '';
            $warehouseId = $this->workflowRequiredText(
                $item['warehousesId'] ?? $item['WAREHOUSES_ID'] ?? null,
                $label . '.warehousesId',
                20
            );

            $items[] = [
                'projectProductItemId' => $itemId,
                'productId' => $productId,
                'warehousesId' => $warehouseId,
                'amount' => $this->workflowPositiveQuantity($item['amount'] ?? $item['AMOUNT'] ?? null, $label . '.amount'),
                'remark' => $this->workflowOptionalText($item['remark'] ?? $item['REMARK'] ?? null, $label . '.remark', 100) ?? '',
            ];
            $itemIds[] = $itemId;
            $warehouseIds[] = $warehouseId;
        }

        if (count(array_unique($itemIds)) !== count($itemIds)) {
            throw new RuntimeException('duplicate projectProductItemId', 400);
        }

        $itemQuery = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_ID', $projectId)
            ->whereIn('ID', $itemIds)
            ->field('ID, PROJECT_ID, PRODUCT_ID, NUMBER, DELIVERY, STATE, TENANT_ID, VERSION');
        $this->whereNotDeleted($itemQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $itemQuery->where('TENANT_ID', $tenantId);
        }
        if ($lock) {
            $itemQuery->lock(true);
        }
        $rowsById = [];
        foreach ($itemQuery->select()->toArray() as $row) {
            $rowsById[(string)$row['ID']] = $row;
        }
        if (count($rowsById) !== count($itemIds)) {
            throw new RuntimeException('project product item not found', 404);
        }

        $uniqueWarehouseIds = array_values(array_unique($warehouseIds));
        $warehouseQuery = Db::name('warehouses')
            ->whereIn('ID', $uniqueWarehouseIds)
            ->field('ID');
        $this->whereNotDeleted($warehouseQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $warehouseQuery->where('TENANT_ID', $tenantId);
        }
        $foundWarehouseIds = array_fill_keys(array_map('strval', $warehouseQuery->column('ID')), true);
        foreach ($uniqueWarehouseIds as $warehouseId) {
            if (!isset($foundWarehouseIds[$warehouseId])) {
                throw new RuntimeException('warehouse not found', 404);
            }
        }

        foreach ($items as &$item) {
            $projectItem = $rowsById[$item['projectProductItemId']];
            if ($item['productId'] === '') {
                $item['productId'] = (string)$projectItem['PRODUCT_ID'];
            } elseif ((string)$projectItem['PRODUCT_ID'] !== $item['productId']) {
                throw new RuntimeException('invalid projectProductItemList productId', 400);
            }

            $remaining = (float)$projectItem['NUMBER'] - (float)$projectItem['DELIVERY'];
            if ((float)$item['amount'] > $remaining + 0.000001) {
                throw new RuntimeException('delivery amount exceeds project product item remaining number', 400);
            }
            $item['projectItem'] = $projectItem;
        }
        unset($item);

        return $items;
    }

    private function workflowPositiveQuantity(mixed $value, string $label): string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("missing {$label}", 400);
        }
        if (!is_numeric($value)) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        $number = (float)$value;
        if ($number <= 0 || abs($number - round($number)) > 0.000001) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return $this->decimalStorage($number);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function projectDeliveryRecordRows(
        string $projectId,
        array $items,
        string $processInstanceId,
        string $freightTime,
        string $tenantId,
        string $operator,
        string $auditUser,
        string $now,
        string $remark
    ): array {
        $itemIds = array_map(static fn (array $item): string => (string)$item['projectProductItemId'], $items);
        $relationQuery = Db::name('sale_project_product_item_relation')
            ->whereIn('OBJECT_ID', $itemIds)
            ->field('OBJECT_ID, TARGET_ID, NUMBER');
        $this->whereNotDeleted($relationQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $relationQuery->where('TENANT_ID', $tenantId);
        }

        $relationsByItemId = [];
        foreach ($relationQuery->select()->toArray() as $relation) {
            $relationsByItemId[(string)$relation['OBJECT_ID']][] = $relation;
        }

        $merged = [];
        foreach ($items as $item) {
            $warehouseId = (string)$item['warehousesId'];
            $relations = $relationsByItemId[(string)$item['projectProductItemId']] ?? [];
            if ($relations === []) {
                $key = $warehouseId . '|' . (string)$item['productId'];
                $merged[$key] ??= [
                    'warehousesId' => $warehouseId,
                    'productId' => (string)$item['productId'],
                    'amount' => 0.0,
                ];
                $merged[$key]['amount'] += (float)$item['amount'];
                continue;
            }

            foreach ($relations as $relation) {
                $targetProductId = trim((string)($relation['TARGET_ID'] ?? ''));
                if ($targetProductId === '') {
                    throw new RuntimeException('invalid sale project product item relation', 400);
                }
                $relationNumber = $this->workflowPositiveQuantity($relation['NUMBER'] ?? null, 'saleProjectProductItemRelation.number');
                $key = $warehouseId . '|' . $targetProductId;
                $merged[$key] ??= [
                    'warehousesId' => $warehouseId,
                    'productId' => $targetProductId,
                    'amount' => 0.0,
                ];
                $merged[$key]['amount'] += (float)$item['amount'] * (float)$relationNumber;
            }
        }

        $rows = [];
        foreach ($merged as $record) {
            $rows[] = [
                'ID' => $this->newId(),
                'WAREHOUSES_ID' => $record['warehousesId'],
                'PROCESS_ID' => $processInstanceId,
                'PRODUCT_ID' => $record['productId'],
                'AMOUNT' => $this->decimalStorage($record['amount']),
                'CATEGORY' => self::DELIVERY_CATEGORY_OUT,
                'PROCESS_CATEGORY' => self::PROCESS_SALE_PROJECT_DELIVERY,
                'OPERATOR' => $operator,
                'REMARK' => $remark,
                'DELIVERY_TIME' => $freightTime,
                'CREATE_TIME' => $now,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_USER' => $auditUser !== '' ? $auditUser : null,
                'UPDATE_TIME' => null,
                'EXT_JSON' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
                'OBJECT_ID' => $projectId,
            ];
        }

        return $rows;
    }

    private function decreaseInventory(
        string $warehouseId,
        string $productId,
        string $tenantId,
        string $amount,
        string $now,
        string $userId
    ): string {
        $query = Db::name('inventory')
            ->where('WAREHOUSES_ID', $warehouseId)
            ->where('PRODUCT_ID', $productId);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        $inventory = $query
            ->field('ID, CURRENT_COUNT, DELETE_FLAG, VERSION')
            ->lock(true)
            ->find();

        if (is_array($inventory) && $inventory !== []) {
            $deleteFlag = trim((string)($inventory['DELETE_FLAG'] ?? ''));
            if ($deleteFlag !== '' && $deleteFlag !== self::NOT_DELETE) {
                throw new RuntimeException('inventory unique key conflicts with deleted row', 409);
            }

            $next = (float)($inventory['CURRENT_COUNT'] ?? 0) - (float)$amount;
            Db::name('inventory')
                ->where('ID', (string)$inventory['ID'])
                ->update([
                    'CURRENT_COUNT' => $this->decimalStorage($next),
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return (string)$inventory['ID'];
        }

        $inventoryId = $this->newId();
        Db::name('inventory')->insert([
            'ID' => $inventoryId,
            'WAREHOUSES_ID' => $warehouseId,
            'PRODUCT_ID' => $productId,
            'CURRENT_COUNT' => $this->decimalStorage(-(float)$amount),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $userId !== '' ? $userId : null,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
            'VERSION' => 0,
        ]);

        return $inventoryId;
    }

    /**
     * @return array<string, string>
     */
    private function projectPaymentStatusFields(string $projectId, string $tenantId, int $totalPriceCents, int $historyAmountCents): array
    {
        $amountCollectedCents = $this->sumProjectPaymentRecordCents($projectId, $tenantId) + $historyAmountCents;
        if ($amountCollectedCents > $totalPriceCents) {
            throw new RuntimeException('amount collected exceeds sale project total price', 400);
        }

        if ($totalPriceCents > $amountCollectedCents) {
            $playState = $this->hasProjectPaymentRecords($projectId, $tenantId)
                ? self::PARTIALLY_PAID_PLAY_STATE
                : self::UNPAID_PLAY_STATE;
        } else {
            $playState = self::PAID_PLAY_STATE;
        }

        $projectState = self::PARTIALLY_SHIPPED_STATE;
        if ($this->allProductItemsShipped($projectId, $tenantId)) {
            $projectState = $playState === self::PAID_PLAY_STATE ? self::COMPLETED_STATE : self::SHIPPED_STATE;
        }

        return [
            'AMOUNT_COLLECTED' => $this->moneyFromCents($amountCollectedCents),
            'PLAY_STATE' => $playState,
            'PROJECT_STATE' => $projectState,
        ];
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    /**
     * @param array<int, string> $values
     */
    private function whereInIfPresent($query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, $values);
        }
    }

    private function correctedProjectState(string $playState, bool $allShipped): string
    {
        if ($allShipped && $playState === self::PAID_PLAY_STATE) {
            return self::COMPLETED_STATE;
        }
        if ($allShipped) {
            return self::SHIPPED_STATE;
        }

        return self::PARTIALLY_SHIPPED_STATE;
    }

    private function correctedProjectStateAfterDeliveryCorrection(string $projectId, string $tenantId, string $playState): string
    {
        if ($this->allProductItemsShipped($projectId, $tenantId)) {
            return $this->correctedProjectState($playState, true);
        }

        if (!$this->anyProductItemsDelivered($projectId, $tenantId)) {
            return self::WAIT_DELIVER_STATE;
        }

        return self::PARTIALLY_SHIPPED_STATE;
    }

    private function hasActiveProjectProductItems(string $projectId, string $tenantId): bool
    {
        $query = Db::name('biz_sale_project_product_item')->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count() > 0;
    }

    private function allProductItemsShipped(string $projectId, string $tenantId): bool
    {
        $query = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_ID', $projectId)
            ->where('STATE', '<>', self::SHIPPED_PRODUCT_ITEM_STATE);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count() === 0;
    }

    private function anyProductItemsDelivered(string $projectId, string $tenantId): bool
    {
        $query = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_ID', $projectId)
            ->where('DELIVERY', '>', 0);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count() > 0;
    }

    private function sumProjectPaymentRecordCents(string $projectId, string $tenantId): int
    {
        $query = $this->projectPaymentRecordQuery($projectId, $tenantId);

        return $this->moneyCents($query->sum('AMOUNT') ?? '0');
    }

    private function hasProjectPaymentRecords(string $projectId, string $tenantId): bool
    {
        return (int)$this->projectPaymentRecordQuery($projectId, $tenantId)->count() > 0;
    }

    private function projectPaymentRecordQuery(string $projectId, string $tenantId)
    {
        $query = Db::name('biz_payment_record')
            ->where('OBJECT_ID', $projectId)
            ->where('SETTLEMENT_CATEGORY', self::PROJECT_PLAY);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function correctedProjectTotals(
        string $projectId,
        string $tenantId,
        int $initPriceCents,
        int $existingTotalRefundCents,
        int $existingTotalReturnCents
    ): array {
        $totalPriceCents = $initPriceCents + $this->sumReissueOrderCents($projectId, $tenantId);
        $totalRefundCents = $existingTotalRefundCents;
        $totalReturnCents = $existingTotalReturnCents;

        $returnOrders = $this->activeReturnOrders($projectId, $tenantId);
        if ($returnOrders !== []) {
            $totalRefundCents = 0;
            $returnOrderIds = [];
            foreach ($returnOrders as $returnOrder) {
                $returnOrderIds[] = (string)($returnOrder['ID'] ?? '');
                $totalRefundCents += $this->moneyCents($returnOrder['AMOUNT'] ?? '0');
            }

            $totalPriceCents -= $totalRefundCents;
            $totalReturnCents = $this->sumReturnExpenditureCents($this->stringList($returnOrderIds), $tenantId);
        }

        return [$totalPriceCents, $totalRefundCents, $totalReturnCents];
    }

    private function sumReissueOrderCents(string $projectId, string $tenantId): int
    {
        $query = Db::name('biz_sale_project_reissue_order')
            ->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $this->moneyCents($query->sum('AMOUNT') ?? '0');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeReturnOrders(string $projectId, string $tenantId): array
    {
        $query = Db::name('return_order')
            ->field('ID, AMOUNT')
            ->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query->select()->toArray();
    }

    /**
     * @param array<int, string> $returnOrderIds
     */
    private function sumReturnExpenditureCents(array $returnOrderIds, string $tenantId): int
    {
        if ($returnOrderIds === []) {
            return 0;
        }

        $query = Db::name('biz_expenditure_record')
            ->whereIn('OBJECT_ID', $returnOrderIds)
            ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $this->moneyCents($query->sum('AMOUNT') ?? '0');
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<string, mixed>
     */
    private function saleProjectAddInput(array $input): array
    {
        $customerId = $this->requiredInputString($input, ['customer', 'CUSTOMER'], 'customer');
        $projectName = $this->requiredInputString($input, ['projectName', 'PROJECT_NAME'], 'projectName');
        $projectCategory = $this->requiredInputString($input, ['projectCategory', 'PROJECT_CATEGORY'], 'projectCategory');
        $this->assertMaxLength($customerId, 'customer', 20);
        $this->assertMaxLength($projectName, 'projectName', 255);
        $this->assertMaxLength($projectCategory, 'projectCategory', 20);

        $data = [
            'CUSTOMER' => $customerId,
            'PROJECT_NAME' => $projectName,
            'PROJECT_CATEGORY' => $projectCategory,
        ];

        foreach ([
            'remark' => ['REMARK', null],
            'area' => ['AREA', 100],
            'detailsAddress' => ['DETAILS_ADDRESS', 100],
            'projectCode' => ['PROJECT_CODE', 100],
            'specimenCategory' => ['SPECIMEN_CATEGORY', 50],
            'specimenName' => ['SPECIMEN_NAME', 50],
        ] as $key => [$column, $maxLength]) {
            if (!$this->hasInputKey($input, [$key, $column])) {
                continue;
            }

            $value = $this->nullableScalarInput($input, [$key, $column], $key);
            if ($value !== null && $maxLength !== null) {
                $this->assertMaxLength($value, $key, $maxLength);
            }
            $data[$column] = $value;
        }

        return $data;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function saleProjectEditInput(array $input): array
    {
        $projectId = $this->requiredInputString($input, ['id', 'ID', 'projectId', 'PROJECT_ID'], 'id');
        $this->assertMaxLength($projectId, 'id', 20);

        $updates = [];
        foreach ([
            'projectName' => ['PROJECT_NAME', 255],
            'projectCategory' => ['PROJECT_CATEGORY', 20],
            'remark' => ['REMARK', null],
            'area' => ['AREA', 100],
            'detailsAddress' => ['DETAILS_ADDRESS', 100],
            'projectCode' => ['PROJECT_CODE', 100],
        ] as $key => [$column, $maxLength]) {
            if (!$this->hasInputKey($input, [$key, $column])) {
                continue;
            }

            $value = $this->nullableScalarInput($input, [$key, $column], $key);
            if ($value !== null && $maxLength !== null) {
                $this->assertMaxLength($value, $key, $maxLength);
            }
            $updates[$column] = $value;
        }

        return [$projectId, $updates];
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function completionDateInput(array $input): string
    {
        $raw = $this->requiredInputString($input, ['completionDate', 'COMPLETION_DATE'], 'completionDate');
        $this->assertMaxLength($raw, 'completionDate', 32);
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            throw new RuntimeException('invalid completionDate', 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredInputString(array $input, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredMoneyString(array $input, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if (is_array($value) || is_object($value) || is_bool($value)) {
                throw new RuntimeException("invalid {$label}", 400);
            }

            $raw = trim((string)$value);
            if ($raw === '') {
                continue;
            }
            if (!preg_match('/^-?\d+(?:\.\d+)?$/', $raw) && !is_numeric($value)) {
                throw new RuntimeException("invalid {$label}", 400);
            }
            if ($this->moneyCents($raw) < 0) {
                throw new RuntimeException("{$label} cannot be negative", 400);
            }

            return $raw;
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function hasInputKey(array $input, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function inputValue(array $input, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return $input[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function nullableScalarInput(array $input, array $keys, string $label): ?string
    {
        $value = $this->inputValue($input, $keys);
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return trim((string)$value);
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function saleProjectIdListInput(array $input): array
    {
        $source = null;
        if ($this->isListArray($input)) {
            $source = $input;
        } elseif (isset($input['items']) && is_array($input['items'])) {
            $source = $input['items'];
        } elseif (isset($input['ids'])) {
            $source = $input['ids'];
        } elseif (isset($input['idList'])) {
            $source = $input['idList'];
        } elseif (isset($input['projectIds'])) {
            $source = $input['projectIds'];
        } elseif ($this->hasInputKey($input, ['id', 'ID', 'projectId', 'PROJECT_ID'])) {
            $source = [$input];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing idList', 400);
        }

        $ids = [];
        foreach ($source as $item) {
            if (is_array($item)) {
                $id = $this->requiredInputString($item, ['id', 'ID', 'projectId', 'PROJECT_ID'], 'id');
            } else {
                $id = trim((string)$item);
                if ($id === '') {
                    throw new RuntimeException('missing id', 400);
                }
            }

            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return $ids;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array{0: array<int, string>, 1: string}
     */
    private function repealInput(array $input): array
    {
        $topRepealContent = $this->inputValue($input, ['repealContent', 'REPEAL_CONTENT']);
        $repealContent = $topRepealContent === null ? '' : trim((string)$topRepealContent);

        $source = null;
        if ($this->isListArray($input)) {
            $source = $input;
        } elseif (isset($input['items']) && is_array($input['items'])) {
            $source = $input['items'];
        } elseif (isset($input['ids'])) {
            $source = $input['ids'];
        } elseif (isset($input['idList'])) {
            $source = $input['idList'];
        } elseif (isset($input['projectIds'])) {
            $source = $input['projectIds'];
        } elseif ($this->hasInputKey($input, ['id', 'ID', 'projectId', 'PROJECT_ID'])) {
            $source = [$input];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing idList', 400);
        }

        $ids = [];
        foreach ($source as $index => $item) {
            if (is_array($item)) {
                $id = $this->requiredInputString($item, ['id', 'ID', 'projectId', 'PROJECT_ID'], 'id');
                if ($topRepealContent === null && (int)$index === 0) {
                    $rowRepealContent = $this->inputValue($item, ['repealContent', 'REPEAL_CONTENT']);
                    $repealContent = $rowRepealContent === null ? '' : trim((string)$rowRepealContent);
                }
            } else {
                $id = trim((string)$item);
                if ($id === '') {
                    throw new RuntimeException('missing id', 400);
                }
            }

            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return [$ids, $repealContent];
    }

    private function assertVisibility(string $visibility): void
    {
        if (!in_array($visibility, [self::PRIVATE_VISIBILITY, self::PUBLIC_VISIBILITY], true)) {
            throw new RuntimeException('unsupported sale project visibility', 400);
        }
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function workflowProjectId(array $variables): string
    {
        $projectId = trim((string)($variables['bizSaleProjectId'] ?? $variables['projectId'] ?? ''));
        if ($projectId === '') {
            throw new RuntimeException('missing bizSaleProjectId', 400);
        }
        $this->assertMaxLength($projectId, 'bizSaleProjectId', 20);

        return $projectId;
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowProjectForUpdate(string $projectId, string $tenantId): array
    {
        $query = Db::name('biz_sale_project')
            ->where('ID', $projectId)
            ->field('ID, CUSTOMER, PROJECT_NAME, PROJECT_STATE, PLAY_STATE, PROCESS_ID, TOTAL_PRICE, HISTORY_AMOUNT, AMOUNT_COLLECTED, TENANT_ID')
            ->lock(true);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $project = $query->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $project;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflowList(mixed $value, string $label): array
    {
        if (is_string($value)) {
            $decoded = json_decode(trim($value), true);
            if (!is_array($decoded)) {
                throw new RuntimeException("invalid {$label}", 400);
            }
            $value = $decoded;
        }
        if (!is_array($value) || !$this->isListArray($value)) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        $items = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException("invalid {$label}.{$index}", 400);
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowAssoc(mixed $value, string $label): array
    {
        if (is_string($value)) {
            $decoded = json_decode(trim($value), true);
            if (!is_array($decoded)) {
                throw new RuntimeException("invalid {$label}", 400);
            }
            $value = $decoded;
        }
        if (!is_array($value) || $this->isListArray($value)) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function workflowStringList(mixed $value): array
    {
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                return [];
            }
            if (str_starts_with($text, '[')) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            } else {
                $value = explode(',', $text);
            }
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? $item['ID'] ?? $item['targetId'] ?? $item['value'] ?? '';
            }
            $item = trim((string)$item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function workflowRequiredText(mixed $value, string $label, int $maxLength): string
    {
        $text = $this->workflowOptionalText($value, $label, $maxLength);
        if ($text === null || $text === '') {
            throw new RuntimeException("missing {$label}", 400);
        }

        return $text;
    }

    private function workflowOptionalText(mixed $value, string $label, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("invalid {$label}", 400);
        }
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $this->assertMaxLength($text, $label, $maxLength);

        return $text;
    }

    private function workflowMoney(mixed $value, string $label, bool $positive): string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("missing {$label}", 400);
        }
        $raw = trim((string)$value);
        if ($raw === '' || !is_numeric($raw)) {
            throw new RuntimeException("invalid {$label}", 400);
        }
        $amount = (float)$raw;
        if ($positive ? $amount <= 0 : $amount < 0) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return number_format($amount, 2, '.', '');
    }

    private function workflowOptionalMoney(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->workflowMoney($value, $label, false);
    }

    private function workflowDate(mixed $value, string $label): string
    {
        if (is_array($value) && isset($value['millis'])) {
            return date('Y-m-d H:i:s', intdiv((int)$value['millis'], 1000));
        }
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            throw new RuntimeException("missing {$label}", 400);
        }
        $timestamp = strtotime($text);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function workflowBoolean(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value !== 0;
        }
        $normalized = strtolower(trim((string)($value ?? '')));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new RuntimeException("invalid {$label}", 400);
    }

    private function incrementCustomerDealAmount(string $customerId, string $tenantId): string
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            throw new RuntimeException('missing customer', 400);
        }
        $query = Db::name('customer')->where('ID', $customerId)->field('ID, DEAL_AMOUNT, TENANT_ID')->lock(true);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        $customer = $query->find();
        if (!is_array($customer) || $customer === []) {
            throw new RuntimeException('customer not found', 404);
        }

        $dealAmount = number_format((float)($customer['DEAL_AMOUNT'] ?? 0) + 1, 2, '.', '');
        Db::name('customer')->where('ID', $customerId)->update([
            'DEAL_AMOUNT' => $dealAmount,
            'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
        ]);

        return $dealAmount;
    }

    /**
     * @param array<int, string> $fileIds
     */
    private function insertSaleProjectWorkflowFileRelations(
        string $projectId,
        array $fileIds,
        string $tenantId,
        string $currentUserId,
        string $now
    ): int {
        $fileIds = $this->workflowStringList($fileIds);
        if ($fileIds === []) {
            return 0;
        }

        $fileQuery = Db::name('dev_file')->whereIn('ID', $fileIds);
        $this->whereNotDeleted($fileQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $fileQuery->where('TENANT_ID', $tenantId);
        }
        $files = $fileQuery->column('NAME', 'ID');
        foreach ($fileIds as $fileId) {
            if (!array_key_exists($fileId, $files)) {
                throw new RuntimeException('file not found', 404);
            }
        }

        $existingQuery = Db::name('biz_file_relation')
            ->where('OBJECT_ID', $projectId)
            ->where('CATEGORY', self::SALE_PROJECT_FILE_CATEGORY)
            ->whereIn('TARGET_ID', $fileIds);
        $this->whereNotDeleted($existingQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $existingQuery->where('TENANT_ID', $tenantId);
        }
        $existing = array_fill_keys(array_map('strval', $existingQuery->column('TARGET_ID')), true);

        $rows = [];
        foreach ($fileIds as $fileId) {
            if (isset($existing[$fileId])) {
                continue;
            }
            $rows[] = [
                'ID' => $this->newId(),
                'OBJECT_ID' => $projectId,
                'TARGET_ID' => $fileId,
                'CATEGORY' => self::SALE_PROJECT_FILE_CATEGORY,
                'FILE_NAME' => $files[$fileId] ?? null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'EXT_JSON' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
            ];
        }

        if ($rows !== []) {
            Db::name('biz_file_relation')->insertAll($rows);
        }

        return count($rows);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $project
     */
    private function insertWorkflowInvoicing(
        array $input,
        string $projectId,
        string $processInstanceId,
        array $project,
        string $tenantId,
        string $currentUserId,
        string $now
    ): void {
        $existingQuery = Db::name('biz_sale_project_invoicing')->where('PROCESS_ID', $processInstanceId);
        $this->whereNotDeleted($existingQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $existingQuery->where('TENANT_ID', $tenantId);
        }
        if ((int)$existingQuery->count() > 0) {
            return;
        }

        $category = $this->workflowRequiredText($input['invoicingCategory'] ?? null, 'invoicingCategory', 50);
        if (!in_array($category, self::INVOICING_CATEGORIES, true)) {
            throw new RuntimeException('invalid invoicingCategory', 400);
        }

        Db::name('biz_sale_project_invoicing')->insert([
            'ID' => $this->newId(),
            'PROJECT_ID' => $projectId,
            'INVOICING_CATEGORY' => $category,
            'PROCESS_ID' => $processInstanceId,
            'REMARK' => $this->workflowOptionalText($input['remark'] ?? null, 'remark', 80),
            'COMPANY_NAME' => $this->workflowRequiredText($input['companyName'] ?? null, 'companyName', 80),
            'CUSTOMER_COMPANY' => $this->workflowRequiredText($input['customerCompany'] ?? null, 'customerCompany', 80),
            'UNIT' => $this->workflowRequiredText($input['unit'] ?? null, 'unit', 80),
            'PHONE' => $this->workflowOptionalText($input['phone'] ?? null, 'phone', 50),
            'TAXPAYER' => $this->workflowRequiredText($input['taxpayer'] ?? null, 'taxpayer', 50),
            'CORPORATE_ACCOUNT' => $this->workflowRequiredText($input['corporateAccount'] ?? null, 'corporateAccount', 80),
            'BANK_NAME' => $this->workflowRequiredText($input['bankName'] ?? null, 'bankName', 80),
            'UNIT_ADDRESS' => $this->workflowRequiredText($input['unitAddress'] ?? null, 'unitAddress', 80),
            'UNIT_PHONE' => $this->workflowOptionalText($input['unitPhone'] ?? null, 'unitPhone', 50),
            'HARVEST_ADDRESS' => $this->workflowOptionalText($input['harvestAddress'] ?? null, 'harvestAddress', 80),
            'AMOUNT' => $this->workflowMoney($input['amount'] ?? null, 'amount', true),
            'INVOICING_STATE' => self::INVOICING_STATE_WAIT,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId !== '' ? $tenantId : (string)($project['TENANT_ID'] ?? '1'),
        ]);
    }

    private function assertMaxLength(string $value, string $label, int $maxLength): void
    {
        if (strlen($value) > $maxLength) {
            throw new RuntimeException("{$label} is too long", 400);
        }
    }

    /**
     * @return array<int, string>
     */
    private function orgAndChildren(string $orgId): array
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return [];
        }

        $rows = Db::name('sys_org')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID'])
            ->select()
            ->toArray();

        $childrenByParent = [];
        foreach ($rows as $row) {
            $childrenByParent[(string)($row['PARENT_ID'] ?? '')][] = (string)$row['ID'];
        }

        $result = [];
        $queue = [$orgId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? [];
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (in_array(strtolower((string)$roleCode), ['superadmin', 'tenantadmin', 'bizadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($item): string => trim((string)$item), $value)));
    }

    private function isListArray(array $input): bool
    {
        $index = 0;
        foreach (array_keys($input) as $key) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function normalizeRow(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $result[$this->camelKey((string)$key)] = $this->normalizeValue($value);
        }

        return $result;
    }

    private function camelKey(string $key): string
    {
        $key = strtolower($key);

        return preg_replace_callback('/_([a-z0-9])/', static fn (array $matches): string => strtoupper($matches[1]), $key) ?? $key;
    }

    private function normalizeValue(mixed $value): mixed
    {
        return $value;
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0.0;
        }

        return (float)$value;
    }

    private function decimalStorage(string|float $value): string
    {
        return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.') ?: '0';
    }

    private function moneyCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = trim((string)$value);
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            if (!is_numeric($value)) {
                throw new RuntimeException('invalid amount', 400);
            }
            $normalized = number_format((float)$value, 2, '.', '');
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $cents = ((int)$whole * 100) + (int)str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function moneyFromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign . (string)intdiv($absolute, 100) . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function value(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }
}
