<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Return-order queries and direct master/detail maintenance compatible with
 * the Java return-order data model.
 */
class ReturnOrderService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const STATE_UNSETTLED = 'Unsettled';
    private const STATE_ALREADY_SETTLED = 'AlreadySettled';
    private const ITEM_STATE_SHIPPED = 'SHIPPED';
    private const RETURN_AND_REFUND = 'ReturnAndRefund';
    private const PROCESS_SALE_PROJECT_PRODUCT_RETURN = 'Process_sale_project_product_return';
    private const DELIVERY_CATEGORY_IN = 'IN';
    private const WAREHOUSE_STATE_WAIT_RECEIVE = 'WAIT_RECEIVE';
    private const WAREHOUSE_STATE_RECEIVED = 'RECEIVED';
    private const REFUND_STATE_NOT_READY = 'NOT_READY';
    private const REFUND_STATE_WAIT_REFUND = 'WAIT_REFUND';
    private const REFUND_STATE_PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    private const REFUND_STATE_REFUNDED = 'REFUNDED';
    private const REFUND_STATE_NOT_REQUIRED = 'NOT_REQUIRED';
    private const RETURNABLE_PROJECT_STATES = ['PARTIALLY_SHIPPED', 'SHIPPED', 'COMPLETED'];
    private const ORDER_FIELDS = <<<SQL
r.ID AS ID,
r.PROJECT_ID AS PROJECT_ID,
r.AMOUNT AS AMOUNT,
r.STATE AS STATE,
r.PROCESS_ID AS PROCESS_ID,
r.REMARK AS REMARK,
r.WAREHOUSES_ID AS WAREHOUSES_ID,
r.LOGISTICS_CATEGORY AS LOGISTICS_CATEGORY,
r.LOGISTICS_ID AS LOGISTICS_ID,
r.USER AS USER,
r.ORG AS ORG,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.EXT_JSON AS EXT_JSON,
r.TENANT_ID AS TENANT_ID,
p.PROJECT_NAME AS PROJECT_NAME,
w.NAME AS WAREHOUSE_NAME,
w.USER AS WAREHOUSE_USER,
w.ORG AS WAREHOUSE_ORG,
u.NAME AS HEAD_NAME,
org.NAME AS ORG_NAME,
p.AMOUNT_COLLECTED AS PROJECT_AMOUNT_COLLECTED,
p.TOTAL_PRICE AS PROJECT_TOTAL_PRICE,
p.TOTAL_RETURN_AMOUNT AS PROJECT_TOTAL_RETURN_AMOUNT
SQL;
    private const ITEM_FIELDS = <<<SQL
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
pi.CATEGORY AS PROJECT_PRODUCT_CATEGORY,
pi.STATE AS PROJECT_PRODUCT_STATE,
pi.NUMBER AS PROJECT_PRODUCT_NUMBER,
pi.DELIVERY AS PROJECT_PRODUCT_DELIVERY,
pi.UNIT_PRICE AS UNIT_PRICE,
pi.DISCOUNT_RATE AS DISCOUNT_RATE,
pi.PRICE AS PRICE,
pi.REMARK AS PROJECT_PRODUCT_REMARK,
bp.PRODUCT_NAME AS PRODUCT_NAME,
bp.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
bp.CATEGORY AS PRODUCT_MASTER_CATEGORY,
bp.SPECS AS SPECS,
bp.PURCHASE_PRICE AS PURCHASE_PRICE,
bp.SALE_PRICE AS SALE_PRICE,
bp.MIN_PRICE AS MIN_PRICE
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'r.ID',
        'projectId' => 'r.PROJECT_ID',
        'projectName' => 'p.PROJECT_NAME',
        'amount' => 'r.AMOUNT',
        'state' => 'r.STATE',
        'processId' => 'r.PROCESS_ID',
        'warehousesId' => 'r.WAREHOUSES_ID',
        'warehouseName' => 'w.NAME',
        'headName' => 'u.NAME',
        'org' => 'r.ORG',
        'orgName' => 'org.NAME',
        'createTime' => 'r.CREATE_TIME',
        'updateTime' => 'r.UPDATE_TIME',
        'tenantId' => 'r.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->orderQuery($filters, $payload)->count();
        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        $records = $this->enrichOrderLifecycle($this->orderRows($rows), $payload);

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(array $filters = [], array $payload = []): array
    {
        if (trim((string)($filters['projectId'] ?? '')) === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->select()
            ->toArray();
        $orders = $this->enrichOrderLifecycle($this->orderRows($rows), $payload);
        $itemsByOrderId = $this->itemsByOrderIds(array_column($orders, 'id'), $payload);

        foreach ($orders as &$order) {
            $order['productList'] = $itemsByOrderId[(string)$order['id']] ?? [];
        }
        unset($order);

        return $orders;
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->orderQuery(['id' => $id], $payload)
            ->field(self::ORDER_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('return order not found', 404);
        }

        $order = $this->enrichOrderLifecycle($this->orderRows([$row]), $payload)[0];
        $order['productList'] = $this->itemRowsByOrderId($id, $payload);

        return $order;
    }

    public function add(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInput($input, 'projectId');
        $warehouseId = $this->requiredInput($input, 'warehousesId');
        $productList = $this->productList($input, true);
        $returnOptions = $this->returnOptionsFromInput($input, $input['extJson'] ?? null);

        return Db::transaction(function () use ($input, $payload, $projectId, $warehouseId, $productList, $returnOptions): array {
            $project = $this->assertProjectWritable($projectId, $payload, 'add');
            $this->assertProjectReturnable($project);
            $tenantId = $this->tenantId($input, $payload, $project);
            $warehouse = $this->activeWarehouse($warehouseId, $tenantId);
            $this->assertWarehouseWritable($warehouse, $payload, 'add return order');
            $items = $this->validatedProductList($productList, $projectId, $tenantId, null);
            $amount = $this->calculatedReturnAmount($items);
            $this->assertSubmittedReturnAmount($input['amount'] ?? null, $amount);
            $this->assertReturnAmountWithinProjectTotal($project, $amount, $tenantId, null);

            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $ownerUserId = $this->ownerUserId($input, $payload, $project);
            $ownerOrgId = $this->ownerOrgId($input, $payload, $project);
            $processId = $this->textInput($input, 'processId', false, 80);
            if ($this->workflowProcessExists((string)$processId, $tenantId)) {
                throw new RuntimeException('return order has workflow records', 400);
            }
            $remark = $this->textInput($input, 'remark', false, 65535);

            Db::name('return_order')->insert([
                'ID' => $id,
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $amount,
                'STATE' => $this->stateForReturnRequirement($amount, $returnOptions['refundRequired']),
                'PROCESS_ID' => $processId,
                'REMARK' => $remark,
                'WAREHOUSES_ID' => $warehouseId,
                'LOGISTICS_CATEGORY' => $this->textInput($input, 'logisticsCategory', false, 50) ?? '',
                'LOGISTICS_ID' => $this->textInput($input, 'logisticsId', false, 50) ?? '',
                'USER' => $ownerUserId !== '' ? $ownerUserId : null,
                'ORG' => $ownerOrgId !== '' ? $ownerOrgId : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'EXT_JSON' => $returnOptions['extJson'],
                'TENANT_ID' => $tenantId,
            ]);

            $this->insertItems($id, $items, $tenantId, $now, $currentUserId);

            return $this->detail($id, $payload);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     * @return array<string, mixed>
     */
    public function workflowProjectReturnStartInfo(
        string $projectId,
        string $warehouseId,
        array $productList,
        array $payload = [],
        string $tenantId = ''
    ): array {
        return Db::transaction(function () use ($projectId, $warehouseId, $productList, $payload, $tenantId): array {
            $project = $this->assertProjectWritable($projectId, $payload, 'start project return workflow');
            $this->assertProjectReturnable($project);
            $effectiveTenantId = $this->tenantId($tenantId !== '' ? ['tenantId' => $tenantId] : [], $payload, $project);
            $warehouse = $this->activeWarehouse($warehouseId, $effectiveTenantId);
            $this->assertWarehouseWritable($warehouse, $payload, 'start project return workflow');
            $items = $this->validatedProductList($productList, $projectId, $effectiveTenantId, null);

            $project['PRODUCT_ITEM_COUNT'] = count($items);
            $project['WAREHOUSE_ID'] = $warehouseId;
            $project['RETURN_AMOUNT'] = $this->calculatedReturnAmount($items);
            $this->assertReturnAmountWithinProjectTotal(
                $project,
                (string)$project['RETURN_AMOUNT'],
                $effectiveTenantId,
                null
            );

            return $project;
        });
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function applyProjectReturnFromWorkflow(
        array $variables,
        string $processInstanceId,
        string $tenantId = '',
        string $currentUserId = ''
    ): array {
        $projectId = $this->workflowProjectId($variables);

        return Db::transaction(function () use ($variables, $processInstanceId, $tenantId, $currentUserId, $projectId): array {
            $project = $this->workflowProjectForUpdate($projectId, $tenantId);
            $this->assertProjectReturnable($project);
            $effectiveTenantId = trim((string)($tenantId !== '' ? $tenantId : ($project['TENANT_ID'] ?? '')));
            if ($effectiveTenantId === '') {
                $effectiveTenantId = '1';
            }

            $existingOrder = $this->activeReturnOrderByProcess($processInstanceId, $effectiveTenantId);
            if ($existingOrder !== null) {
                return $this->workflowReturnSummary((string)$existingOrder['ID'], $projectId, $processInstanceId, $effectiveTenantId);
            }

            $warehouseId = $this->workflowRequiredString($variables['warehousesId'] ?? $variables['warehouseId'] ?? null, 'warehousesId', 20);
            $this->activeWarehouse($warehouseId, $effectiveTenantId);
            $productList = $this->workflowList($variables['productList'] ?? null, 'productList');
            $items = $this->validatedProductList($productList, $projectId, $effectiveTenantId, null);
            $amount = $this->calculatedReturnAmount($items);
            $this->assertReturnAmountWithinProjectTotal($project, $amount, $effectiveTenantId, null);
            $initiator = trim((string)($variables['initiator'] ?? ''));
            if ($initiator === '') {
                throw new RuntimeException('missing initiator', 400);
            }

            $now = date('Y-m-d H:i:s');
            $auditUser = $currentUserId !== '' ? $currentUserId : $initiator;
            $ownerOrgId = $this->userOrgId($initiator, $effectiveTenantId);
            if ($ownerOrgId === '') {
                $ownerOrgId = trim((string)($project['ORG'] ?? ''));
            }
            $returnOrderId = $this->newId();
            $remark = $this->workflowOptionalText($variables['remark'] ?? null, 'remark', 65535);
            $returnOptions = $this->returnOptionsFromInput($variables, $variables['extJson'] ?? null);

            Db::name('return_order')->insert([
                'ID' => $returnOrderId,
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $amount,
                'STATE' => $this->stateForReturnRequirement($amount, $returnOptions['refundRequired']),
                'PROCESS_ID' => $processInstanceId,
                'REMARK' => $remark,
                'WAREHOUSES_ID' => $warehouseId,
                'LOGISTICS_CATEGORY' => $this->workflowOptionalText($variables['logisticsCategory'] ?? null, 'logisticsCategory', 50) ?? '',
                'LOGISTICS_ID' => $this->workflowOptionalText($variables['logisticsId'] ?? null, 'logisticsId', 50) ?? '',
                'USER' => $initiator,
                'ORG' => $ownerOrgId !== '' ? $ownerOrgId : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $auditUser !== '' ? $auditUser : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'EXT_JSON' => $returnOptions['extJson'],
                'TENANT_ID' => $effectiveTenantId,
            ]);

            $this->insertItems($returnOrderId, $items, $effectiveTenantId, $now, $auditUser);

            return $this->workflowReturnSummary($returnOrderId, $projectId, $processInstanceId, $effectiveTenantId);
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $hasProductList = array_key_exists('productList', $input);
        $productList = $hasProductList ? $this->productList($input, true) : [];

        return Db::transaction(function () use ($input, $payload, $id, $hasProductList, $productList): array {
            $current = $this->activeOrderForWrite($id, $payload);
            $tenantId = $this->tenantId($input, $payload, $current);
            $this->assertDirectReturnOrderWritable($current, $tenantId);

            $currentProjectId = (string)$current['PROJECT_ID'];
            $projectId = trim((string)($input['projectId'] ?? $input['project_id'] ?? $currentProjectId));
            if ($projectId === '') {
                throw new RuntimeException('missing projectId', 400);
            }
            if ($projectId !== $currentProjectId && !$hasProductList) {
                throw new RuntimeException('productList is required when changing projectId', 400);
            }

            $project = $this->assertProjectWritable($projectId, $payload, 'edit');
            $this->assertProjectReturnable($project);
            if ($projectId !== $currentProjectId) {
                $this->assertProjectWritable($currentProjectId, $payload, 'edit');
            }
            $tenantId = $this->tenantId($input, $payload, $project);

            $warehouseId = trim((string)($input['warehousesId'] ?? $input['warehouses_id'] ?? $current['WAREHOUSES_ID'] ?? ''));
            if ($warehouseId === '') {
                throw new RuntimeException('missing warehousesId', 400);
            }
            $warehouse = $this->activeWarehouse($warehouseId, $tenantId);
            $this->assertWarehouseWritable($warehouse, $payload, 'edit return order');

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $items = $hasProductList
                ? $this->validatedProductList($productList, $projectId, $tenantId, $id)
                : $this->currentReturnItemsForDelivery($id, $tenantId);
            $amount = $this->calculatedReturnAmount($items);
            $this->assertSubmittedReturnAmount($input['amount'] ?? null, $amount);
            $this->assertReturnAmountWithinProjectTotal($project, $amount, $tenantId, $id);
            $returnOptions = $this->returnOptionsFromInput($input, $current['EXT_JSON'] ?? null);

            $updates = [
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $amount,
                'STATE' => $this->stateForReturnRequirement($amount, $returnOptions['refundRequired']),
                'WAREHOUSES_ID' => $warehouseId,
                'EXT_JSON' => $returnOptions['extJson'],
                'TENANT_ID' => $tenantId,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            ];

            foreach ([
                'processId' => ['PROCESS_ID', 80],
                'remark' => ['REMARK', 65535],
                'logisticsCategory' => ['LOGISTICS_CATEGORY', 50],
                'logisticsId' => ['LOGISTICS_ID', 50],
            ] as $key => [$column, $maxLength]) {
                if (array_key_exists($key, $input) || array_key_exists($this->snakeKey($key), $input)) {
                    $updates[$column] = $this->textInput($input, $key, false, $maxLength);
                    if (in_array($column, ['LOGISTICS_CATEGORY', 'LOGISTICS_ID'], true) && $updates[$column] === null) {
                        $updates[$column] = '';
                    }
                }
            }

            if (array_key_exists('user', $input)) {
                $ownerUserId = $this->ownerUserId($input, $payload, $project);
                $updates['USER'] = $ownerUserId !== '' ? $ownerUserId : null;
            }
            if (array_key_exists('org', $input)) {
                $ownerOrgId = $this->ownerOrgId($input, $payload, $project);
                $updates['ORG'] = $ownerOrgId !== '' ? $ownerOrgId : null;
            }
            if ($this->workflowProcessExists((string)($updates['PROCESS_ID'] ?? $current['PROCESS_ID'] ?? ''), $tenantId)) {
                throw new RuntimeException('return order has workflow records', 400);
            }

            Db::name('return_order')->where('ID', $id)->update($updates);

            if ($hasProductList) {
                Db::name('return_order_item')
                    ->where('RETURN_ORDER_ID', $id)
                    ->where(function ($query): void {
                        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                    })
                    ->update([
                        'DELETE_FLAG' => self::DELETED,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    ]);
                $this->insertItems($id, $items, $tenantId, $now, $currentUserId);
            }

            return $this->detail($id, $payload);
        });
    }

    public function delete(array $input, array $payload = []): array
    {
        $ids = $this->normalizeIdList($input['idList'] ?? $input['ids'] ?? $input['id'] ?? $input);
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($ids, $payload): array {
            $rows = $this->activeOrdersForWrite($ids, $payload);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('return order not found', 404);
            }

            $tenantIds = array_values(array_unique(array_map(static fn (array $row): string => (string)($row['TENANT_ID'] ?? ''), $rows)));
            $tenantId = count($tenantIds) === 1 ? $tenantIds[0] : '';

            foreach ($rows as $row) {
                $this->assertDirectReturnOrderWritable($row, (string)($row['TENANT_ID'] ?? $tenantId));
                $this->assertProjectWritable((string)$row['PROJECT_ID'], $payload, 'delete');
            }

            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $deleteData = [
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            ];

            $updated = Db::name('return_order')
                ->whereIn('ID', $ids)
                ->update($deleteData);
            Db::name('return_order_item')
                ->whereIn('RETURN_ORDER_ID', $ids)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update($deleteData);

            return ['ids' => $ids, 'count' => $updated];
        });
    }

    public function warehouseReceive(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $order = $this->activeOrderForWarehouseReceive($id, $payload);
            $tenantId = $this->tenantId($input, $payload, $order);
            $warehouseId = trim((string)($order['WAREHOUSES_ID'] ?? ''));
            if ($warehouseId === '') {
                throw new RuntimeException('return warehouse not found', 404);
            }

            $warehouse = $this->activeWarehouse($warehouseId, $tenantId);
            $this->assertWarehouseWritable($warehouse, $payload, 'receive return order');
            if ($this->hasReturnDeliveryRecords([$id], $tenantId)) {
                return $this->detail($id, $payload);
            }

            $items = $this->currentReturnItemsForDelivery($id, $tenantId);
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            if ($currentUserId === '') {
                throw new RuntimeException('missing warehouse receiver', 400);
            }
            $processId = trim((string)($order['PROCESS_ID'] ?? ''));
            if ($processId === '') {
                $processId = $id;
            }
            $remark = $this->textInput($input, 'remark', false, 65535)
                ?? trim((string)($order['REMARK'] ?? ''));

            $this->createReturnDeliveryRecordsAndIncreaseInventory(
                $id,
                $warehouseId,
                $items,
                $processId,
                $tenantId,
                $currentUserId,
                $currentUserId,
                $now,
                $remark
            );
            Db::name('return_order')->where('ID', $id)->update([
                'STATE' => $this->orderRefundRequired($order)
                    ? (string)($order['STATE'] ?? self::STATE_UNSETTLED)
                    : self::STATE_ALREADY_SETTLED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId,
            ]);
            $this->recalculateProjectReturnTotals((string)$order['PROJECT_ID'], $tenantId, $now, $currentUserId);
            (new SaleProjectService())->refreshProjectPaymentStatusFromWorkflow(
                (string)$order['PROJECT_ID'],
                $tenantId,
                $currentUserId
            );

            return $this->detail($id, $payload);
        });
    }

    /**
     * Validates a finance refund while locking its return order and project.
     *
     * @return array<string, mixed>
     */
    public function assertReturnRefundAllowed(
        string $returnOrderId,
        string $requestedAmount,
        string $tenantId = '',
        string $financeUserId = ''
    ): array
    {
        $returnOrderId = trim($returnOrderId);
        if ($returnOrderId === '') {
            throw new RuntimeException('missing return order id', 400);
        }
        $requestedCents = $this->moneyCents($requestedAmount);
        if ($requestedCents <= 0) {
            throw new RuntimeException('refund amount must be greater than zero', 400);
        }

        $orderQuery = Db::name('return_order')->where('ID', $returnOrderId);
        $this->whereNotDeleted($orderQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $orderQuery->where('TENANT_ID', $tenantId);
        }
        $order = $orderQuery->lock(true)->find();
        if (!is_array($order) || $order === []) {
            throw new RuntimeException('return order not found', 404);
        }

        $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($order['TENANT_ID'] ?? ''));
        if (!$this->orderRefundRequired($order)) {
            throw new RuntimeException('return order does not require refund', 400);
        }
        $assignedTreasurer = $this->returnOrderOptions($order['EXT_JSON'] ?? null)['treasurer'];
        $financeUserId = trim($financeUserId);
        if ($assignedTreasurer !== '' && $financeUserId !== '' && $assignedTreasurer !== $financeUserId) {
            throw new RuntimeException('return order finance approver mismatch', 400);
        }
        if (!$this->hasReturnDeliveryRecords([$returnOrderId], $effectiveTenantId)) {
            throw new RuntimeException('warehouse has not received this return order', 400);
        }

        $projectId = trim((string)($order['PROJECT_ID'] ?? ''));
        $projectQuery = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($projectQuery, 'DELETE_FLAG');
        if ($effectiveTenantId !== '') {
            $projectQuery->where('TENANT_ID', $effectiveTenantId);
        }
        $project = $projectQuery->lock(true)->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        $orderAmountCents = $this->moneyCents($order['AMOUNT'] ?? 0);
        $orderRefundedCents = $this->moneyCents($this->returnRefundExpenditureTotal($returnOrderId, $effectiveTenantId));
        $orderRemainingCents = max(0, $orderAmountCents - $orderRefundedCents);
        $projectRefundCapacityCents = max(
            0,
            $this->moneyCents($project['AMOUNT_COLLECTED'] ?? 0)
                - $this->moneyCents($project['TOTAL_PRICE'] ?? 0)
                - $this->moneyCents($project['TOTAL_RETURN_AMOUNT'] ?? 0)
        );
        $maximumCents = min($orderRemainingCents, $projectRefundCapacityCents);
        if ($requestedCents > $maximumCents) {
            throw new RuntimeException(
                'refund amount exceeds refundable amount ' . $this->moneyFromCents($maximumCents),
                400
            );
        }

        return [
            'id' => $returnOrderId,
            'projectId' => $projectId,
            'tenantId' => $effectiveTenantId,
            'treasurer' => $assignedTreasurer,
            'maximumRefundAmount' => $this->moneyFromCents($maximumCents),
        ];
    }

    private function orderQuery(array $filters, array $payload)
    {
        $query = Db::name('return_order')
            ->alias('r')
            ->leftJoin('biz_sale_project p', 'p.ID = r.PROJECT_ID')
            ->leftJoin('warehouses w', 'w.ID = r.WAREHOUSES_ID')
            ->leftJoin('settlement_account project_account', 'project_account.ID = p.ACCOUNT_ID')
            ->leftJoin('sys_user u', 'u.ID = r.USER')
            ->leftJoin('sys_org org', 'org.ID = r.ORG')
            ->where(function ($query): void {
                $query->whereNull('r.DELETE_FLAG')->whereOr('r.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'r.ID',
            'projectId' => 'r.PROJECT_ID',
            'state' => 'r.STATE',
            'processId' => 'r.PROCESS_ID',
            'warehousesId' => 'r.WAREHOUSES_ID',
            'user' => 'r.USER',
            'org' => 'r.ORG',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        $warehouseState = strtoupper(trim((string)($filters['warehouseState'] ?? '')));
        if ($warehouseState === self::WAREHOUSE_STATE_RECEIVED) {
            $query->whereRaw($this->warehouseReceiptExistsSql());
        } elseif ($warehouseState === self::WAREHOUSE_STATE_WAIT_RECEIVE) {
            $query->whereRaw('NOT ' . $this->warehouseReceiptExistsSql());
        }

        if (array_key_exists('amount', $filters) && trim((string)$filters['amount']) !== '') {
            $query->where('r.AMOUNT', (float)$filters['amount']);
        }

        if (!empty($filters['remark'])) {
            $query->whereLike('r.REMARK', '%' . trim((string)$filters['remark']) . '%');
        }

        if (!empty($filters['projectName'])) {
            $query->whereLike('p.PROJECT_NAME', '%' . trim((string)$filters['projectName']) . '%');
        }

        if (!empty($filters['warehouseName'])) {
            $query->whereLike('w.NAME', '%' . trim((string)$filters['warehouseName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                '(p.PROJECT_NAME LIKE ? OR r.REMARK LIKE ? OR w.NAME LIKE ? OR u.NAME LIKE ? OR r.PROCESS_ID LIKE ? OR r.LOGISTICS_ID LIKE ?)',
                [$keyword, $keyword, $keyword, $keyword, $keyword, $keyword]
            );
        }

        $this->applyCreateTimeRange($query, $filters);
        $this->applyDataScope($query, $filters, $payload);

        return $query;
    }

    private function applyDataScope($query, array $filters, array $payload): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('r.ORG', $orgIds);
            }

            return;
        }

        $scope = $payload['data_scope_org_ids'] ?? [];
        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }
        if (is_array($scope)) {
            $scope = array_values(array_filter(array_map(static fn ($id): string => trim((string)$id), $scope)));
            if ($scope !== []) {
                $query->where(function ($query) use ($scope): void {
                    $query->whereIn('r.ORG', $scope)
                        ->whereOr(function ($query) use ($scope): void {
                            $query->whereIn('w.ORG', $scope);
                        })
                        ->whereOr(function ($query) use ($scope): void {
                            $query->whereIn('project_account.org', $scope);
                        });
                });

                return;
            }
        }

        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId !== '') {
            $query->where(function ($query) use ($userId): void {
                $query->where('r.USER', $userId)
                    ->whereOr('w.USER', $userId)
                    ->whereOr('project_account.CREATE_USER', $userId);
            });
        }
    }

    private function applyCreateTimeRange($query, array $filters): void
    {
        $start = trim((string)($filters['startCreateTime'] ?? ''));
        $end = trim((string)($filters['endCreateTime'] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime('r.CREATE_TIME', $start, $end);
        } elseif ($start !== '') {
            $query->whereTime('r.CREATE_TIME', '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime('r.CREATE_TIME', '<=', $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('r.ID', 'asc');
        }

        return $query->order('r.ID', 'asc');
    }

    private function warehouseReceiptExistsSql(): string
    {
        return <<<SQL
EXISTS (
    SELECT 1
    FROM delivery_record return_receipt
    WHERE return_receipt.OBJECT_ID = r.ID
      AND return_receipt.TENANT_ID = r.TENANT_ID
      AND return_receipt.CATEGORY = 'IN'
      AND return_receipt.PROCESS_CATEGORY = 'Process_sale_project_product_return'
      AND (return_receipt.DELETE_FLAG IS NULL OR return_receipt.DELETE_FLAG = 'NOT_DELETE')
)
SQL;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function orderRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->orderRow($row), $rows);
    }

    private function orderRow(array $row): array
    {
        $extJson = $this->value($row, 'EXT_JSON', 'extJson');
        $returnOptions = $this->returnOrderOptions($extJson);

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
            'projectName' => $this->value($row, 'PROJECT_NAME', 'projectName'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'state' => $this->value($row, 'STATE', 'state'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'warehousesId' => $this->value($row, 'WAREHOUSES_ID', 'warehousesId'),
            'warehouseName' => $this->value($row, 'WAREHOUSE_NAME', 'warehouseName'),
            'warehouseUser' => $this->value($row, 'WAREHOUSE_USER', 'warehouseUser'),
            'warehouseOrg' => $this->value($row, 'WAREHOUSE_ORG', 'warehouseOrg'),
            'logisticsCategory' => $this->value($row, 'LOGISTICS_CATEGORY', 'logisticsCategory'),
            'logisticsId' => $this->value($row, 'LOGISTICS_ID', 'logisticsId'),
            'user' => $this->value($row, 'USER', 'user'),
            'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'projectAmountCollected' => $this->decimal($this->value($row, 'PROJECT_AMOUNT_COLLECTED', 'projectAmountCollected')),
            'projectTotalPrice' => $this->decimal($this->value($row, 'PROJECT_TOTAL_PRICE', 'projectTotalPrice')),
            'projectTotalReturnAmount' => $this->decimal($this->value($row, 'PROJECT_TOTAL_RETURN_AMOUNT', 'projectTotalReturnAmount')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'extJson' => $extJson,
            'refundRequired' => $returnOptions['refundRequired'],
            'treasurer' => $returnOptions['treasurer'],
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    private function enrichOrderLifecycle(array $orders, array $payload): array
    {
        $orderIds = array_values(array_filter(array_map(
            static fn (array $order): string => trim((string)($order['id'] ?? '')),
            $orders
        )));
        if ($orderIds === []) {
            return $orders;
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $receiptQuery = Db::name('delivery_record')
            ->alias('d')
            ->leftJoin('sys_user receiver', 'receiver.ID = d.OPERATOR')
            ->whereIn('d.OBJECT_ID', $orderIds)
            ->where('d.CATEGORY', self::DELIVERY_CATEGORY_IN)
            ->where('d.PROCESS_CATEGORY', self::PROCESS_SALE_PROJECT_PRODUCT_RETURN)
            ->field('d.OBJECT_ID,d.DELIVERY_TIME,d.OPERATOR,d.REMARK,receiver.NAME AS RECEIVER_NAME');
        $this->whereNotDeleted($receiptQuery, 'd.DELETE_FLAG');
        if ($tenantId !== '') {
            $receiptQuery->where('d.TENANT_ID', $tenantId);
        }
        $receipts = [];
        foreach ($receiptQuery->order('d.DELIVERY_TIME', 'asc')->order('d.ID', 'asc')->select()->toArray() as $row) {
            $orderId = (string)($row['OBJECT_ID'] ?? '');
            $receipts[$orderId] ??= [
                'receivedAt' => $row['DELIVERY_TIME'] ?? null,
                'receiver' => $row['OPERATOR'] ?? null,
                'receiverName' => $row['RECEIVER_NAME'] ?? null,
                'receiptRemark' => $row['REMARK'] ?? null,
            ];
        }

        $refundQuery = Db::name('biz_expenditure_record')
            ->whereIn('OBJECT_ID', $orderIds)
            ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
        $this->whereNotDeleted($refundQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $refundQuery->where('TENANT_ID', $tenantId);
        }
        $refundAmounts = [];
        foreach ($refundQuery
            ->field('OBJECT_ID,SUM(AMOUNT) AS REFUND_AMOUNT')
            ->group('OBJECT_ID')
            ->select()
            ->toArray() as $row) {
            $refundAmounts[(string)($row['OBJECT_ID'] ?? '')] = (float)($row['REFUND_AMOUNT'] ?? 0);
        }

        $treasurerIds = array_values(array_unique(array_filter(array_map(
            static fn (array $order): string => trim((string)($order['treasurer'] ?? '')),
            $orders
        ))));
        $treasurerNames = [];
        if ($treasurerIds !== []) {
            $userQuery = Db::name('sys_user')->whereIn('ID', $treasurerIds);
            $this->whereNotDeleted($userQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $userQuery->where('TENANT_ID', $tenantId);
            }
            foreach ($userQuery->field('ID,NAME')->select()->toArray() as $user) {
                $treasurerNames[(string)($user['ID'] ?? '')] = (string)($user['NAME'] ?? '');
            }
        }

        foreach ($orders as &$order) {
            $orderId = (string)($order['id'] ?? '');
            $receipt = $receipts[$orderId] ?? null;
            $received = $receipt !== null;
            $refundAmount = (float)($refundAmounts[$orderId] ?? 0);
            $orderAmount = (float)($order['amount'] ?? 0);
            $refundRequired = (bool)($order['refundRequired'] ?? true);
            $projectOutstandingRefund = max(
                0.0,
                (float)($order['projectAmountCollected'] ?? 0)
                    - (float)($order['projectTotalPrice'] ?? 0)
                    - (float)($order['projectTotalReturnAmount'] ?? 0)
            );
            $remainingOrderAmount = max(0.0, $orderAmount - $refundAmount);
            $refundableAmount = $received && $refundRequired
                ? min($remainingOrderAmount, $projectOutstandingRefund)
                : 0.0;

            $refundState = self::REFUND_STATE_NOT_READY;
            if ($received && !$refundRequired) {
                $refundState = self::REFUND_STATE_NOT_REQUIRED;
            } elseif ($received && $remainingOrderAmount <= 0.000001) {
                $refundState = self::REFUND_STATE_REFUNDED;
            } elseif ($received && $refundableAmount <= 0.000001) {
                $refundState = self::REFUND_STATE_NOT_REQUIRED;
            } elseif ($received && $refundAmount > 0.000001) {
                $refundState = self::REFUND_STATE_PARTIALLY_REFUNDED;
            } elseif ($received) {
                $refundState = self::REFUND_STATE_WAIT_REFUND;
            }

            $order['warehouseState'] = $received ? self::WAREHOUSE_STATE_RECEIVED : self::WAREHOUSE_STATE_WAIT_RECEIVE;
            $order['warehouseReceivedAt'] = $receipt['receivedAt'] ?? null;
            $order['warehouseReceiver'] = $receipt['receiver'] ?? null;
            $order['warehouseReceiverName'] = $receipt['receiverName'] ?? null;
            $order['warehouseReceiptRemark'] = $receipt['receiptRemark'] ?? null;
            $order['refundAmount'] = $this->decimal($refundAmount) ?? 0;
            $order['remainingReturnAmount'] = $this->decimal($remainingOrderAmount) ?? 0;
            $order['refundableAmount'] = $this->decimal($refundableAmount) ?? 0;
            $order['refundState'] = $refundState;
            $order['treasurerName'] = $treasurerNames[(string)($order['treasurer'] ?? '')] ?? '';
            $order['businessState'] = $this->returnOrderBusinessState($order['warehouseState'], $refundState);
            $order['canWarehouseReceive'] = $this->canReceiveForWarehouse($order, $payload);
        }
        unset($order);

        return $orders;
    }

    private function canReceiveForWarehouse(array $order, array $payload): bool
    {
        if ($this->canSeeAll($payload)) {
            return true;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '' && $userId === trim((string)($order['warehouseUser'] ?? ''))) {
            return true;
        }

        $warehouseOrg = trim((string)($order['warehouseOrg'] ?? ''));

        return $warehouseOrg !== '' && in_array($warehouseOrg, $this->scopeOrgIds($payload), true);
    }

    private function returnOrderBusinessState(string $warehouseState, string $refundState): string
    {
        if ($warehouseState !== self::WAREHOUSE_STATE_RECEIVED) {
            return 'WAIT_WAREHOUSE_RECEIPT';
        }
        if ($refundState === self::REFUND_STATE_WAIT_REFUND) {
            return 'WAIT_FINANCE_REFUND';
        }
        if ($refundState === self::REFUND_STATE_PARTIALLY_REFUNDED) {
            return 'PARTIALLY_REFUNDED';
        }

        return 'COMPLETED';
    }

    /**
     * @param array<int, string|null> $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function itemsByOrderIds(array $orderIds, array $payload): array
    {
        $ids = array_values(array_filter(array_map(static fn ($id): string => trim((string)$id), $orderIds)));
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $rows = $this->itemBaseQuery($tenantId)
            ->whereIn('i.RETURN_ORDER_ID', $ids)
            ->order('i.ID', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($this->itemRows($rows) as $row) {
            $result[(string)$row['returnOrderId']][] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemRowsByOrderId(string $orderId, array $payload): array
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $rows = $this->itemBaseQuery($tenantId)
            ->where('i.RETURN_ORDER_ID', $orderId)
            ->order('i.ID', 'asc')
            ->select()
            ->toArray();

        return $this->itemRows($rows);
    }

    private function itemBaseQuery(string $tenantId)
    {
        $query = Db::name('return_order_item')
            ->alias('i')
            ->leftJoin('biz_sale_project_product_item pi', 'pi.ID = i.PROJECT_PRODUCT_ITEM_ID')
            ->leftJoin('biz_product bp', 'bp.ID = pi.PRODUCT_ID')
            ->field(self::ITEM_FIELDS)
            ->where(function ($query): void {
                $query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function itemRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'returnOrderId' => $this->value($row, 'RETURN_ORDER_ID', 'returnOrderId'),
                'projectProductItemId' => $this->value($row, 'PROJECT_PRODUCT_ITEM_ID', 'projectProductItemId'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
                'category' => $this->value($row, 'PRODUCT_MASTER_CATEGORY', 'category'),
                'specs' => $this->value($row, 'SPECS', 'specs'),
                'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
                'projectProductCategory' => $this->value($row, 'PROJECT_PRODUCT_CATEGORY', 'projectProductCategory'),
                'projectProductState' => $this->value($row, 'PROJECT_PRODUCT_STATE', 'projectProductState'),
                'projectProductNumber' => $this->decimal($this->value($row, 'PROJECT_PRODUCT_NUMBER', 'projectProductNumber')),
                'projectProductDelivery' => $this->decimal($this->value($row, 'PROJECT_PRODUCT_DELIVERY', 'projectProductDelivery')),
                'unitPrice' => $this->decimal($this->value($row, 'UNIT_PRICE', 'unitPrice')),
                'discountRate' => $this->decimal($this->value($row, 'DISCOUNT_RATE', 'discountRate')),
                'price' => $this->decimal($this->value($row, 'PRICE', 'price')),
                'projectProductRemark' => $this->value($row, 'PROJECT_PRODUCT_REMARK', 'projectProductRemark'),
                'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
                'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
                'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            ];
        }, $rows);
    }

    private function activeOrderForWrite(string $id, array $payload): array
    {
        $rows = $this->activeOrdersForWrite([$id], $payload);
        if ($rows === []) {
            throw new RuntimeException('return order not found', 404);
        }

        return $rows[0];
    }

    private function activeOrderForWarehouseReceive(string $id, array $payload): array
    {
        $query = Db::name('return_order')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $order = $query->lock(true)->find();
        if (!is_array($order) || $order === []) {
            throw new RuntimeException('return order not found', 404);
        }

        return $order;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeOrdersForWrite(array $ids, array $payload): array
    {
        $query = Db::name('return_order')
            ->alias('r')
            ->leftJoin('biz_sale_project p', 'p.ID = r.PROJECT_ID')
            ->whereIn('r.ID', $ids);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }
        $this->applyWriteScope($query, $payload, 'r');

        return $query
            ->field('r.*')
            ->lock(true)
            ->select()
            ->toArray();
    }

    private function applyWriteScope($query, array $payload, string $alias): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn("{$alias}.ORG", $scopeOrgIds);

            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where("{$alias}.USER", $userId);
        }
    }

    private function assertProjectWritable(string $projectId, array $payload, string $action): array
    {
        $query = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $project = $query->lock(true)->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        if ($this->canSeeAll($payload)) {
            return $project;
        }

        $projectOrg = trim((string)($project['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            if (!in_array($projectOrg, $scopeOrgIds, true)) {
                throw new RuntimeException("no permission to {$action} return order", 403);
            }

            return $project;
        }

        $userId = $this->currentUserId($payload);
        if ($userId === '' || trim((string)($project['USER'] ?? '')) !== $userId) {
            throw new RuntimeException("no permission to {$action} return order", 403);
        }

        return $project;
    }

    private function assertProjectReturnable(array $project): void
    {
        $state = trim((string)($project['PROJECT_STATE'] ?? ''));
        if (!in_array($state, self::RETURNABLE_PROJECT_STATES, true)) {
            throw new RuntimeException('sale project is not returnable', 400);
        }
    }

    private function activeWarehouse(string $warehouseId, string $tenantId): array
    {
        $query = Db::name('warehouses')->where('ID', $warehouseId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $warehouse = $query->field('ID,USER,ORG,TENANT_ID')->find();
        if (!is_array($warehouse) || $warehouse === []) {
            throw new RuntimeException('warehouse not found', 404);
        }

        return $warehouse;
    }

    private function assertWarehouseWritable(array $warehouse, array $payload, string $action): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $warehouseOrg = trim((string)($warehouse['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $warehouseOrg !== '' && in_array($warehouseOrg, $scopeOrgIds, true)) {
            return;
        }

        $userId = $this->currentUserId($payload);
        $ownerUserId = trim((string)($warehouse['USER'] ?? ''));
        if ($userId !== '' && $ownerUserId === $userId) {
            return;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function insertItems(string $orderId, array $items, string $tenantId, string $now, string $currentUserId): void
    {
        foreach ($items as $item) {
            Db::name('return_order_item')->insert([
                'ID' => $this->newId(),
                'RETURN_ORDER_ID' => $orderId,
                'PROJECT_PRODUCT_ITEM_ID' => $item['projectProductItemId'],
                'AMOUNT' => $item['amount'],
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function returnDeliveryRecordRows(
        string $returnOrderId,
        string $warehouseId,
        array $items,
        string $processInstanceId,
        string $tenantId,
        string $operator,
        string $auditUser,
        string $now,
        string $remark
    ): array {
        $itemIds = array_map(static fn (array $item): string => (string)$item['projectProductItemId'], $items);
        $relationQuery = Db::name('sale_project_product_item_relation')
            ->whereIn('OBJECT_ID', $itemIds)
            ->field('OBJECT_ID,TARGET_ID,NUMBER');
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
            $relations = $relationsByItemId[(string)$item['projectProductItemId']] ?? [];
            if ($relations === []) {
                $productId = trim((string)$item['productId']);
                $merged[$productId] ??= [
                    'productId' => $productId,
                    'amount' => 0.0,
                ];
                $merged[$productId]['amount'] += (float)$item['amount'];
                continue;
            }

            foreach ($relations as $relation) {
                $targetProductId = trim((string)($relation['TARGET_ID'] ?? ''));
                if ($targetProductId === '') {
                    throw new RuntimeException('invalid sale project product item relation', 400);
                }
                $relationNumber = $this->positiveQuantity($relation['NUMBER'] ?? null, 'saleProjectProductItemRelation.number');
                $merged[$targetProductId] ??= [
                    'productId' => $targetProductId,
                    'amount' => 0.0,
                ];
                $merged[$targetProductId]['amount'] += (float)$item['amount'] * (float)$relationNumber;
            }
        }

        $this->assertActiveProducts(array_column($merged, 'productId'), $tenantId);
        $deliveryRemark = $this->truncateText($remark, 255);

        $rows = [];
        foreach ($merged as $record) {
            $rows[] = [
                'ID' => $this->newId(),
                'WAREHOUSES_ID' => $warehouseId,
                'PROCESS_ID' => $processInstanceId,
                'PRODUCT_ID' => $record['productId'],
                'AMOUNT' => $this->decimalStorage($record['amount']),
                'CATEGORY' => self::DELIVERY_CATEGORY_IN,
                'PROCESS_CATEGORY' => self::PROCESS_SALE_PROJECT_PRODUCT_RETURN,
                'OPERATOR' => $operator,
                'REMARK' => $deliveryRemark,
                'DELIVERY_TIME' => $now,
                'CREATE_TIME' => $now,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_USER' => $auditUser !== '' ? $auditUser : null,
                'UPDATE_TIME' => null,
                'EXT_JSON' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
                'OBJECT_ID' => $returnOrderId,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{deliveryRecordCount: int, inventoryUpdateCount: int}
     */
    private function createReturnDeliveryRecordsAndIncreaseInventory(
        string $returnOrderId,
        string $warehouseId,
        array $items,
        string $processId,
        string $tenantId,
        string $operator,
        string $auditUser,
        string $now,
        string $remark
    ): array {
        $deliveryRows = $this->returnDeliveryRecordRows(
            $returnOrderId,
            $warehouseId,
            $items,
            $processId,
            $tenantId,
            $operator,
            $auditUser,
            $now,
            $remark
        );
        if ($deliveryRows === []) {
            return ['deliveryRecordCount' => 0, 'inventoryUpdateCount' => 0];
        }

        Db::name('delivery_record')->insertAll($deliveryRows);

        $inventoryIds = [];
        foreach ($deliveryRows as $row) {
            $inventoryIds[] = $this->increaseInventory(
                $warehouseId,
                (string)$row['PRODUCT_ID'],
                $tenantId,
                (string)$row['AMOUNT'],
                $now,
                $auditUser
            );
        }

        return [
            'deliveryRecordCount' => count($deliveryRows),
            'inventoryUpdateCount' => count(array_unique($inventoryIds)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     * @return array<int, array<string, mixed>>
     */
    private function validatedProductList(array $productList, string $projectId, string $tenantId, ?string $excludeOrderId): array
    {
        $normalized = [];
        $ids = [];
        $seen = [];
        foreach ($productList as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid productList', 400);
            }

            $projectProductItemId = trim((string)($item['projectProductItemId'] ?? $item['project_product_item_id'] ?? ''));
            if ($projectProductItemId === '') {
                throw new RuntimeException("missing productList.{$index}.projectProductItemId", 400);
            }
            if (isset($seen[$projectProductItemId])) {
                throw new RuntimeException('duplicate projectProductItemId', 400);
            }
            $seen[$projectProductItemId] = true;
            $ids[] = $projectProductItemId;

            $normalized[] = [
                'projectProductItemId' => $projectProductItemId,
                'amount' => $this->itemAmount($item['amount'] ?? null, "productList.{$index}.amount"),
                'productId' => trim((string)($item['productId'] ?? $item['product_id'] ?? '')),
            ];
        }

        $query = Db::name('biz_sale_project_product_item')
            ->whereIn('ID', $ids)
            ->where('PROJECT_ID', $projectId)
            ->where('STATE', self::ITEM_STATE_SHIPPED);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,PROJECT_ID,PRODUCT_ID,NUMBER,PRICE,STATE,TENANT_ID')
            ->lock(true)
            ->select()
            ->toArray();
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('sale project product item not found', 404);
        }

        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(string)$row['ID']] = $row;
        }

        $alreadyReturned = $this->existingReturnedAmounts($ids, $tenantId, $excludeOrderId);
        foreach ($normalized as &$item) {
            $row = $rowsById[$item['projectProductItemId']] ?? null;
            if (!is_array($row)) {
                throw new RuntimeException('sale project product item not found', 404);
            }
            if ($item['productId'] !== '' && $item['productId'] !== (string)($row['PRODUCT_ID'] ?? '')) {
                throw new RuntimeException('invalid productId', 400);
            }

            $limit = (float)($row['NUMBER'] ?? 0);
            $existing = (float)($alreadyReturned[$item['projectProductItemId']] ?? 0);
            $requested = (float)$item['amount'];
            if ($existing + $requested > $limit + 0.000001) {
                throw new RuntimeException('return amount exceeds project product amount', 400);
            }
            if ($limit <= 0.0) {
                throw new RuntimeException('invalid project product amount', 400);
            }
            $item['productId'] = (string)($row['PRODUCT_ID'] ?? $item['productId']);
            $item['returnAmountCents'] = (int)round(
                $this->moneyCents($row['PRICE'] ?? 0) * $requested / $limit
            );
        }
        unset($item);

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function calculatedReturnAmount(array $items): string
    {
        $cents = 0;
        foreach ($items as $item) {
            if (array_key_exists('returnAmountCents', $item)) {
                $cents += (int)$item['returnAmountCents'];
                continue;
            }

            $projectItemId = trim((string)($item['projectProductItemId'] ?? ''));
            $row = Db::name('biz_sale_project_product_item')
                ->where('ID', $projectItemId)
                ->field('NUMBER,PRICE')
                ->lock(true)
                ->find();
            if (!is_array($row) || $row === [] || (float)($row['NUMBER'] ?? 0) <= 0.0) {
                throw new RuntimeException('sale project product item not found', 404);
            }
            $cents += (int)round(
                $this->moneyCents($row['PRICE'] ?? 0)
                    * (float)($item['amount'] ?? 0)
                    / (float)$row['NUMBER']
            );
        }

        return $this->moneyFromCents($cents);
    }

    private function assertSubmittedReturnAmount(mixed $submittedAmount, string $calculatedAmount): void
    {
        if ($submittedAmount === null || trim((string)$submittedAmount) === '') {
            return;
        }
        if ($this->moneyCents($submittedAmount) !== $this->moneyCents($calculatedAmount)) {
            throw new RuntimeException('return amount does not match selected products', 400);
        }
    }

    private function assertReturnAmountWithinProjectTotal(
        array $project,
        string $newAmount,
        string $tenantId,
        ?string $excludeOrderId
    ): void {
        $projectId = trim((string)($project['ID'] ?? ''));
        $returnQuery = Db::name('return_order')->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($returnQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $returnQuery->where('TENANT_ID', $tenantId);
        }
        if ($excludeOrderId !== null && $excludeOrderId !== '') {
            $returnQuery->where('ID', '<>', $excludeOrderId);
        }

        $reissueQuery = Db::name('biz_sale_project_reissue_order')->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($reissueQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $reissueQuery->where('TENANT_ID', $tenantId);
        }

        $maximumCents = $this->moneyCents($project['INIT_PRICE'] ?? 0)
            + $this->moneyCents($reissueQuery->sum('AMOUNT') ?? 0);
        $requestedCents = $this->moneyCents($returnQuery->sum('AMOUNT') ?? 0)
            + $this->moneyCents($newAmount);
        if ($requestedCents > $maximumCents) {
            throw new RuntimeException('return amount exceeds sale project total price', 400);
        }
    }

    /**
     * @param array<int, string> $projectProductItemIds
     * @return array<string, float>
     */
    private function existingReturnedAmounts(array $projectProductItemIds, string $tenantId, ?string $excludeOrderId): array
    {
        if ($projectProductItemIds === []) {
            return [];
        }

        $query = Db::name('return_order_item')
            ->alias('i')
            ->join('return_order r', 'r.ID = i.RETURN_ORDER_ID')
            ->whereIn('i.PROJECT_PRODUCT_ITEM_ID', $projectProductItemIds);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }
        if ($excludeOrderId !== null && $excludeOrderId !== '') {
            $query->where('i.RETURN_ORDER_ID', '<>', $excludeOrderId);
        }

        $rows = $query
            ->field('i.PROJECT_PRODUCT_ITEM_ID, SUM(i.AMOUNT) AS TOTAL_AMOUNT')
            ->group('i.PROJECT_PRODUCT_ITEM_ID')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['PROJECT_PRODUCT_ITEM_ID']] = (float)($row['TOTAL_AMOUNT'] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<int, array{projectProductItemId: string, amount: string, productId: string}>
     */
    private function currentReturnItemsForDelivery(string $orderId, string $tenantId): array
    {
        $query = Db::name('return_order_item')
            ->alias('i')
            ->join('biz_sale_project_product_item pi', 'pi.ID = i.PROJECT_PRODUCT_ITEM_ID')
            ->where('i.RETURN_ORDER_ID', $orderId)
            ->field('i.PROJECT_PRODUCT_ITEM_ID, i.AMOUNT, pi.PRODUCT_ID, pi.NUMBER, pi.PRICE')
            ->lock(true);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'pi.DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $rows = $query->select()->toArray();
        if ($rows === []) {
            throw new RuntimeException('return order product item not found', 404);
        }

        return array_map(function (array $row): array {
            $number = (float)($row['NUMBER'] ?? 0);
            if ($number <= 0.0) {
                throw new RuntimeException('invalid project product amount', 400);
            }
            $amount = (float)($row['AMOUNT'] ?? 0);

            return [
                'projectProductItemId' => (string)($row['PROJECT_PRODUCT_ITEM_ID'] ?? ''),
                'amount' => number_format($amount, 2, '.', ''),
                'productId' => (string)($row['PRODUCT_ID'] ?? ''),
                'returnAmountCents' => (int)round($this->moneyCents($row['PRICE'] ?? 0) * $amount / $number),
            ];
        }, $rows);
    }

    /**
     * @param array<int, string> $orderIds
     */
    private function hasReturnRefundExpenditure(array $orderIds, string $tenantId): bool
    {
        if ($orderIds === []) {
            return false;
        }

        $query = Db::name('biz_expenditure_record')
            ->whereIn('OBJECT_ID', $orderIds)
            ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count() > 0;
    }

    /**
     * @param array<int, string> $orderIds
     */
    private function hasReturnDeliveryRecords(array $orderIds, string $tenantId): bool
    {
        if ($orderIds === []) {
            return false;
        }

        $query = Db::name('delivery_record')
            ->whereIn('OBJECT_ID', $orderIds)
            ->where('CATEGORY', self::DELIVERY_CATEGORY_IN)
            ->where('PROCESS_CATEGORY', self::PROCESS_SALE_PROJECT_PRODUCT_RETURN);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count() > 0;
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
    private function assertDirectReturnOrderWritable(array $order, string $tenantId): void
    {
        if ($this->workflowProcessExists((string)($order['PROCESS_ID'] ?? ''), $tenantId)) {
            throw new RuntimeException('return order has workflow records', 400);
        }
        $id = trim((string)($order['ID'] ?? ''));
        if ($id !== '' && $this->hasReturnDeliveryRecords([$id], $tenantId)) {
            throw new RuntimeException('warehouse has received this return order', 400);
        }
        if ($id !== '' && $this->hasReturnRefundExpenditure([$id], $tenantId)) {
            throw new RuntimeException('return order has refund records', 400);
        }
    }

    /**
     * @param array<int, string> $orderIds
     * @return array{expenditureCount: int, statementCount: int, accountUpdateCount: int}
     */
    private function reverseReturnRefundFinance(array $orderIds, string $tenantId, string $now, string $currentUserId): array
    {
        if ($orderIds === []) {
            return ['expenditureCount' => 0, 'statementCount' => 0, 'accountUpdateCount' => 0];
        }

        $query = Db::name('biz_expenditure_record')
            ->whereIn('OBJECT_ID', $orderIds)
            ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $expenditures = $query
            ->field('ID,OBJECT_ID,TARGET_ID,SERIAL_ID,AMOUNT,TENANT_ID')
            ->lock(true)
            ->select()
            ->toArray();
        if ($expenditures === []) {
            return ['expenditureCount' => 0, 'statementCount' => 0, 'accountUpdateCount' => 0];
        }

        $accountIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['TARGET_ID'] ?? '')),
            $expenditures
        ))));
        if ($accountIds === []) {
            throw new RuntimeException('return refund account not found', 404);
        }
        sort($accountIds);

        $accountQuery = Db::name('settlement_account')->whereIn('ID', $accountIds);
        $this->whereNotDeleted($accountQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $accountQuery->where('TENANT_ID', $tenantId);
        }
        $accounts = $accountQuery
            ->field('ID,CURRENT_AMOUNT,TENANT_ID')
            ->lock(true)
            ->select()
            ->toArray();
        if (count($accounts) !== count($accountIds)) {
            throw new RuntimeException('return refund account not found', 404);
        }

        $accountCentsById = [];
        foreach ($accounts as $account) {
            $accountCentsById[(string)$account['ID']] = $this->moneyCents($account['CURRENT_AMOUNT'] ?? '0');
        }

        foreach ($expenditures as $expenditure) {
            $accountId = trim((string)($expenditure['TARGET_ID'] ?? ''));
            if ($accountId === '' || !array_key_exists($accountId, $accountCentsById)) {
                throw new RuntimeException('return refund account not found', 404);
            }
            $accountCentsById[$accountId] += $this->moneyCents($expenditure['AMOUNT'] ?? '0');
        }

        $statementIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['SERIAL_ID'] ?? '')),
            $expenditures
        ))));
        $statementCount = 0;
        if ($statementIds !== []) {
            $statementQuery = Db::name('settlement_account_statement')
                ->whereIn('ID', $statementIds)
                ->where('SETTLEMENT_TYPE', 'EXPEND')
                ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
            $this->whereNotDeleted($statementQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $statementQuery->where('TENANT_ID', $tenantId);
            }
            $statements = $statementQuery
                ->field('ID')
                ->lock(true)
                ->select()
                ->toArray();
            if (count($statements) !== count($statementIds)) {
                throw new RuntimeException('return refund statement not found', 404);
            }
            $statementCount = count($statements);
        }

        foreach ($accountCentsById as $accountId => $currentAmountCents) {
            Db::name('settlement_account')
                ->where('ID', $accountId)
                ->update([
                    'CURRENT_AMOUNT' => $this->moneyFromCents($currentAmountCents),
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                ]);
        }

        $deleteData = [
            'DELETE_FLAG' => self::DELETED,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
        ];
        Db::name('biz_expenditure_record')
            ->whereIn('ID', array_column($expenditures, 'ID'))
            ->update($deleteData);
        if ($statementIds !== []) {
            Db::name('settlement_account_statement')
                ->whereIn('ID', $statementIds)
                ->update($deleteData);
        }

        return [
            'expenditureCount' => count($expenditures),
            'statementCount' => $statementCount,
            'accountUpdateCount' => count($accountCentsById),
        ];
    }

    /**
     * @param array<int, string> $orderIds
     * @return array{deliveryRecordCount: int, inventoryUpdateCount: int}
     */
    private function reverseReturnDeliveryRecordsAndInventory(array $orderIds, string $tenantId, string $now, string $currentUserId): array
    {
        if ($orderIds === []) {
            return ['deliveryRecordCount' => 0, 'inventoryUpdateCount' => 0];
        }

        $query = Db::name('delivery_record')
            ->whereIn('OBJECT_ID', $orderIds)
            ->where('CATEGORY', self::DELIVERY_CATEGORY_IN)
            ->where('PROCESS_CATEGORY', self::PROCESS_SALE_PROJECT_PRODUCT_RETURN);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $deliveryRows = $query
            ->field('ID,WAREHOUSES_ID,PRODUCT_ID,AMOUNT,TENANT_ID')
            ->lock(true)
            ->select()
            ->toArray();
        if ($deliveryRows === []) {
            return ['deliveryRecordCount' => 0, 'inventoryUpdateCount' => 0];
        }

        $inventoryIds = [];
        foreach ($deliveryRows as $row) {
            $inventoryIds[] = $this->decreaseInventory(
                (string)($row['WAREHOUSES_ID'] ?? ''),
                (string)($row['PRODUCT_ID'] ?? ''),
                trim((string)($row['TENANT_ID'] ?? $tenantId)),
                (string)($row['AMOUNT'] ?? '0'),
                $now,
                $currentUserId
            );
        }

        Db::name('delivery_record')
            ->whereIn('ID', array_column($deliveryRows, 'ID'))
            ->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            ]);

        return [
            'deliveryRecordCount' => count($deliveryRows),
            'inventoryUpdateCount' => count(array_unique($inventoryIds)),
        ];
    }

    public function applyReturnRefundExpenditure(string $returnOrderId, string $tenantId = '', string $currentUserId = ''): array
    {
        $returnOrderId = trim($returnOrderId);
        if ($returnOrderId === '') {
            throw new RuntimeException('missing return order id', 400);
        }

        return Db::transaction(function () use ($returnOrderId, $tenantId, $currentUserId): array {
            $query = Db::name('return_order')->where('ID', $returnOrderId);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $query->where('TENANT_ID', $tenantId);
            }

            $order = $query->lock(true)->find();
            if (!is_array($order) || $order === []) {
                throw new RuntimeException('return order not found', 404);
            }

            $effectiveTenantId = $tenantId !== '' ? $tenantId : trim((string)($order['TENANT_ID'] ?? ''));
            if (!$this->orderRefundRequired($order)) {
                throw new RuntimeException('return order does not require refund', 400);
            }
            if (!$this->hasReturnDeliveryRecords([$returnOrderId], $effectiveTenantId)) {
                throw new RuntimeException('warehouse has not received this return order', 400);
            }
            $refundTotal = $this->returnRefundExpenditureTotal($returnOrderId, $effectiveTenantId);
            $orderAmount = (float)$this->moneyAmount($order['AMOUNT'] ?? 0, 'amount', true);
            if ($refundTotal > $orderAmount + 0.000001) {
                throw new RuntimeException('return refund amount exceeds return order amount', 400);
            }

            $now = date('Y-m-d H:i:s');
            $state = abs($refundTotal - $orderAmount) <= 0.000001 || $orderAmount <= 0.0
                ? self::STATE_ALREADY_SETTLED
                : self::STATE_UNSETTLED;

            Db::name('return_order')
                ->where('ID', $returnOrderId)
                ->update([
                    'STATE' => $state,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                ]);

            $projectId = (string)($order['PROJECT_ID'] ?? '');
            if ($projectId !== '') {
                $this->recalculateProjectReturnTotals($projectId, $effectiveTenantId, $now, $currentUserId);
                (new SaleProjectService())->refreshProjectPaymentStatusFromWorkflow(
                    $projectId,
                    $effectiveTenantId,
                    $currentUserId
                );
            }

            return [
                'id' => $returnOrderId,
                'projectId' => $projectId,
                'state' => $state,
                'refundAmount' => number_format($refundTotal, 2, '.', ''),
                'orderAmount' => number_format($orderAmount, 2, '.', ''),
            ];
        });
    }

    private function returnRefundExpenditureTotal(string $returnOrderId, string $tenantId): float
    {
        $query = Db::name('biz_expenditure_record')
            ->where('OBJECT_ID', $returnOrderId)
            ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (float)$query->sum('AMOUNT');
    }

    private function recalculateProjectReturnTotals(string $projectId, string $tenantId, string $now, string $currentUserId): void
    {
        $query = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $project = $query->lock(true)->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        $reissueQuery = Db::name('biz_sale_project_reissue_order')->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($reissueQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $reissueQuery->where('TENANT_ID', $tenantId);
        }

        $returnQuery = Db::name('return_order')->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($returnQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $returnQuery->where('TENANT_ID', $tenantId);
        }

        $returnRows = $returnQuery->field('ID,AMOUNT,EXT_JSON')->select()->toArray();
        $receivedOrderIds = $this->receivedReturnOrderIds(
            array_values(array_filter(array_map(
                static fn (array $row): string => trim((string)($row['ID'] ?? '')),
                $returnRows
            ))),
            $tenantId
        );
        $receivedLookup = array_fill_keys($receivedOrderIds, true);
        $returnRows = array_values(array_filter(
            $returnRows,
            static fn (array $row): bool => isset($receivedLookup[(string)($row['ID'] ?? '')])
        ));
        $refundRequiredRows = array_values(array_filter(
            $returnRows,
            fn (array $row): bool => $this->orderRefundRequired($row)
        ));
        $totalRefundAmount = array_reduce(
            $refundRequiredRows,
            static fn (float $carry, array $row): float => $carry + (float)($row['AMOUNT'] ?? 0),
            0.0
        );
        $refundRequiredOrderIds = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['ID'] ?? '')),
            $refundRequiredRows
        )));
        $totalReturnAmount = 0.0;
        if ($refundRequiredOrderIds !== []) {
            $expenditureQuery = Db::name('biz_expenditure_record')
                ->whereIn('OBJECT_ID', $refundRequiredOrderIds)
                ->where('SETTLEMENT_CATEGORY', self::RETURN_AND_REFUND);
            $this->whereNotDeleted($expenditureQuery, 'DELETE_FLAG');
            if ($tenantId !== '') {
                $expenditureQuery->where('TENANT_ID', $tenantId);
            }
            $totalReturnAmount = (float)$expenditureQuery->sum('AMOUNT');
        }

        $initPrice = (float)($project['INIT_PRICE'] ?? 0);
        $reissueAmount = (float)$reissueQuery->sum('AMOUNT');
        Db::name('biz_sale_project')
            ->where('ID', $projectId)
            ->update([
                'TOTAL_RETURN_AMOUNT' => number_format($totalReturnAmount, 2, '.', ''),
                'TOTAL_REFUND_AMOUNT' => number_format($totalRefundAmount, 2, '.', ''),
                'TOTAL_PRICE' => number_format($initPrice + $reissueAmount - $totalRefundAmount, 2, '.', ''),
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            ]);
    }

    /**
     * @param array<int, string> $orderIds
     * @return array<int, string>
     */
    private function receivedReturnOrderIds(array $orderIds, string $tenantId): array
    {
        if ($orderIds === []) {
            return [];
        }

        $query = Db::name('delivery_record')
            ->whereIn('OBJECT_ID', $orderIds)
            ->where('CATEGORY', self::DELIVERY_CATEGORY_IN)
            ->where('PROCESS_CATEGORY', self::PROCESS_SALE_PROJECT_PRODUCT_RETURN);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return array_values(array_unique(array_map(
            static fn ($id): string => trim((string)$id),
            $query->column('OBJECT_ID')
        )));
    }

    private function workflowProjectForUpdate(string $projectId, string $tenantId): array
    {
        $query = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $project = $query->lock(true)->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $project;
    }

    private function activeReturnOrderByProcess(string $processInstanceId, string $tenantId): ?array
    {
        $query = Db::name('return_order')->where('PROCESS_ID', $processInstanceId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->lock(true)->find();

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowReturnSummary(
        string $returnOrderId,
        string $projectId,
        string $processInstanceId,
        string $tenantId
    ): array {
        $itemQuery = Db::name('return_order_item')->where('RETURN_ORDER_ID', $returnOrderId);
        $this->whereNotDeleted($itemQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $itemQuery->where('TENANT_ID', $tenantId);
        }

        $deliveryQuery = Db::name('delivery_record')
            ->where('PROCESS_ID', $processInstanceId)
            ->where('OBJECT_ID', $returnOrderId)
            ->where('PROCESS_CATEGORY', self::PROCESS_SALE_PROJECT_PRODUCT_RETURN);
        $this->whereNotDeleted($deliveryQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $deliveryQuery->where('TENANT_ID', $tenantId);
        }

        $projectQuery = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($projectQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $projectQuery->where('TENANT_ID', $tenantId);
        }
        $project = $projectQuery->field('TOTAL_REFUND_AMOUNT,TOTAL_RETURN_AMOUNT,TOTAL_PRICE,PROJECT_STATE')->find();
        $project = is_array($project) ? $project : [];

        $summary = [
            'id' => $returnOrderId,
            'projectId' => $projectId,
            'returnOrderId' => $returnOrderId,
            'productItemCount' => (int)$itemQuery->count(),
            'deliveryRecordCount' => (int)$deliveryQuery->count(),
            'totalRefundAmount' => $project['TOTAL_REFUND_AMOUNT'] ?? null,
            'totalReturnAmount' => $project['TOTAL_RETURN_AMOUNT'] ?? null,
            'totalPrice' => $project['TOTAL_PRICE'] ?? null,
            'projectState' => $project['PROJECT_STATE'] ?? null,
        ];
        return $summary;
    }

    private function workflowProjectId(array $variables): string
    {
        $projectId = trim((string)($variables['projectId'] ?? $variables['bizSaleProjectId'] ?? ''));
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        return $projectId;
    }

    private function workflowRequiredString(mixed $value, string $label, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            throw new RuntimeException("missing {$label}", 400);
        }
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length > $maxLength) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return $text;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflowList(mixed $value, string $label): array
    {
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                throw new RuntimeException("missing {$label}", 400);
            }
            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("invalid {$label}", 400);
            }
            $value = $decoded;
        }

        if (!is_array($value) || $value === []) {
            throw new RuntimeException("missing {$label}", 400);
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException("invalid {$label} item", 400);
            }
            $items[] = $item;
        }

        return $items;
    }

    private function workflowOptionalText(mixed $value, string $label, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length > $maxLength) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return $text;
    }

    /**
     * @param array<int, string> $productIds
     */
    private function assertActiveProducts(array $productIds, string $tenantId): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $productIds
        ))));
        if ($ids === []) {
            return;
        }

        $query = Db::name('biz_product')->whereIn('ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if ((int)$query->count() !== count($ids)) {
            throw new RuntimeException('product not found', 404);
        }
    }

    private function increaseInventory(string $warehouseId, string $productId, string $tenantId, string $amount, string $now, string $userId): string
    {
        $query = Db::name('inventory')
            ->where('WAREHOUSES_ID', $warehouseId)
            ->where('PRODUCT_ID', $productId);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $inventory = $query
            ->field('ID,CURRENT_COUNT,DELETE_FLAG,VERSION')
            ->lock(true)
            ->find();

        if (is_array($inventory) && $inventory !== []) {
            $deleteFlag = trim((string)($inventory['DELETE_FLAG'] ?? ''));
            if ($deleteFlag !== '' && $deleteFlag !== self::NOT_DELETE) {
                throw new RuntimeException('inventory unique key conflicts with deleted row', 409);
            }

            $next = (float)($inventory['CURRENT_COUNT'] ?? 0) + (float)$amount;
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
            'CURRENT_COUNT' => $this->decimalStorage($amount),
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

    private function decreaseInventory(string $warehouseId, string $productId, string $tenantId, string $amount, string $now, string $userId): string
    {
        $warehouseId = trim($warehouseId);
        $productId = trim($productId);
        if ($warehouseId === '' || $productId === '') {
            throw new RuntimeException('invalid return delivery record', 400);
        }

        $query = Db::name('inventory')
            ->where('WAREHOUSES_ID', $warehouseId)
            ->where('PRODUCT_ID', $productId);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $inventory = $query
            ->field('ID,CURRENT_COUNT,DELETE_FLAG,VERSION')
            ->lock(true)
            ->find();
        if (!is_array($inventory) || $inventory === []) {
            throw new RuntimeException('inventory not found for return reverse', 404);
        }

        $deleteFlag = trim((string)($inventory['DELETE_FLAG'] ?? ''));
        if ($deleteFlag !== '' && $deleteFlag !== self::NOT_DELETE) {
            throw new RuntimeException('inventory unique key conflicts with deleted row', 409);
        }

        $next = (float)($inventory['CURRENT_COUNT'] ?? 0) - (float)$amount;
        if ($next < -0.000001) {
            throw new RuntimeException('inventory reverse would underflow', 400);
        }
        if ($next < 0) {
            $next = 0.0;
        }

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

    private function userOrgId(string $userId, string $tenantId): string
    {
        $userId = trim($userId);
        if ($userId === '') {
            return '';
        }

        $query = Db::name('sys_user')->where('ID', $userId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return trim((string)($query->value('ORG_ID') ?? ''));
    }

    private function positiveQuantity(mixed $value, string $label): string
    {
        if ($value === null || $value === '' || !is_numeric($value) || (float)$value <= 0) {
            throw new RuntimeException("invalid {$label}", 400);
        }

        return $this->decimalStorage((float)$value);
    }

    private function decimalStorage(string|float $value): string
    {
        return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.') ?: '0';
    }

    private function truncateText(string $text, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length <= $maxLength) {
            return $text;
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $maxLength) : substr($text, 0, $maxLength);
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productList(array $input, bool $required): array
    {
        $value = $input['productList'] ?? $input['product_list'] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                throw new RuntimeException('missing productList', 400);
            }

            return [];
        }
        if (!is_array($value) || $value === []) {
            throw new RuntimeException('invalid productList', 400);
        }

        return array_values($value);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeIdList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            if (is_array($item)) {
                return trim((string)($item['id'] ?? $item['ID'] ?? ''));
            }

            return trim((string)$item);
        }, $value))));
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function moneyAmount(mixed $value, string $key, bool $required): string
    {
        if (($value === null || $value === '') && $required) {
            throw new RuntimeException("missing {$key}", 400);
        }
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (!is_numeric($value) || (float)$value < 0) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function itemAmount(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }
        if (!is_numeric($value) || (float)$value <= 0) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function textInput(array $input, string $key, bool $required, int $maxLength): ?string
    {
        $value = trim((string)($input[$key] ?? $input[$this->snakeKey($key)] ?? ''));
        if ($value === '') {
            if ($required) {
                throw new RuntimeException("missing {$key}", 400);
            }

            return null;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $maxLength) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return $value;
    }

    /**
     * @return array{refundRequired: bool, treasurer: string, extJson: string}
     */
    private function returnOptionsFromInput(array $input, mixed $existingExtJson = null): array
    {
        $options = $this->returnOrderOptions($existingExtJson);
        $extJson = $this->decodedExtJson($existingExtJson);

        if (array_key_exists('refundRequired', $input) || array_key_exists('refund_required', $input)) {
            $refundRequired = $this->booleanValue($input['refundRequired'] ?? $input['refund_required'] ?? null);
            if ($refundRequired === null) {
                throw new RuntimeException('invalid refundRequired', 400);
            }
            $options['refundRequired'] = $refundRequired;
        }

        if (array_key_exists('treasurer', $input)) {
            $options['treasurer'] = $this->userIdValue($input['treasurer']);
        }
        if (!$options['refundRequired']) {
            $options['treasurer'] = '';
        }

        $extJson['refundRequired'] = $options['refundRequired'];
        if ($options['treasurer'] !== '') {
            $extJson['treasurer'] = $options['treasurer'];
        } else {
            unset($extJson['treasurer']);
        }

        $encoded = json_encode($extJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('invalid return order options', 400);
        }

        return $options + ['extJson' => $encoded];
    }

    /**
     * Legacy return orders did not store the choice and retain the original
     * refund-required behavior.
     *
     * @return array{refundRequired: bool, treasurer: string}
     */
    private function returnOrderOptions(mixed $extJson): array
    {
        $data = $this->decodedExtJson($extJson);
        $refundRequired = $this->booleanValue($data['refundRequired'] ?? null);

        return [
            'refundRequired' => $refundRequired ?? true,
            'treasurer' => $this->userIdValue($data['treasurer'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedExtJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function orderRefundRequired(array $order): bool
    {
        if (array_key_exists('refundRequired', $order)) {
            return $this->booleanValue($order['refundRequired']) ?? true;
        }

        return $this->returnOrderOptions($order['EXT_JSON'] ?? $order['extJson'] ?? null)['refundRequired'];
    }

    private function userIdValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? $value['userId'] ?? $value['value'] ?? $value['key'] ?? '';
        }

        return trim((string)($value ?? ''));
    }

    private function booleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    private function tenantId(array $input, array $payload, array $row): string
    {
        $values = [
            trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? '')),
            trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '')),
            trim((string)($row['TENANT_ID'] ?? $row['tenant_id'] ?? $row['tenantId'] ?? '')),
        ];
        $values = array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
        if (count($values) > 1) {
            throw new RuntimeException('tenant mismatch', 403);
        }

        return $values[0] ?? '1';
    }

    private function ownerUserId(array $input, array $payload, array $project): string
    {
        $requested = trim((string)($input['user'] ?? $input['USER'] ?? ''));
        $currentUserId = $this->currentUserId($payload);
        if ($requested !== '') {
            if ($this->canSeeAll($payload) || ($currentUserId !== '' && $requested === $currentUserId)) {
                return $requested;
            }

            throw new RuntimeException('no permission to set return order user', 403);
        }

        return $currentUserId !== '' ? $currentUserId : trim((string)($project['USER'] ?? ''));
    }

    private function ownerOrgId(array $input, array $payload, array $project): string
    {
        $requested = trim((string)($input['org'] ?? $input['ORG'] ?? ''));
        $payloadOrgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($requested !== '') {
            if ($this->canSeeAll($payload) || $requested === $payloadOrgId || in_array($requested, $this->scopeOrgIds($payload), true)) {
                return $requested;
            }

            throw new RuntimeException('no permission to set return order org', 403);
        }

        $projectOrg = trim((string)($project['ORG'] ?? ''));
        if ($projectOrg !== '') {
            return $projectOrg;
        }

        return $payloadOrgId;
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $ids = [];
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct)) {
            $ids = array_merge($ids, $direct);
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (is_array($scope)) {
                    $ids[] = $scope['orgId'] ?? $scope['org_id'] ?? '';
                }
            }
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
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

    private function stateForReturnRequirement(string $amount, bool $refundRequired): string
    {
        return !$refundRequired || (float)$amount <= 0.0
            ? self::STATE_ALREADY_SETTLED
            : self::STATE_UNSETTLED;
    }

    private function moneyCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('invalid amount', 400);
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

    private function snakeKey(string $key): string
    {
        return strtolower((string)preg_replace('/[A-Z]/', '_$0', $key));
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
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
