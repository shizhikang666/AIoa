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
u.NAME AS HEAD_NAME,
org.NAME AS ORG_NAME
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

        return [
            'records' => $this->orderRows($rows),
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
        $orders = $this->orderRows($rows);
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

        $order = $this->orderRows([$row])[0];
        $order['productList'] = $this->itemRowsByOrderId($id, $payload);

        return $order;
    }

    public function add(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInput($input, 'projectId');
        $warehouseId = $this->requiredInput($input, 'warehousesId');
        $amount = $this->moneyAmount($input['amount'] ?? null, 'amount', true);
        $productList = $this->productList($input, true);

        return Db::transaction(function () use ($input, $payload, $projectId, $warehouseId, $amount, $productList): array {
            $project = $this->assertProjectWritable($projectId, $payload, 'add');
            $this->assertProjectReturnable($project);
            $tenantId = $this->tenantId($input, $payload, $project);
            $warehouse = $this->activeWarehouse($warehouseId, $tenantId);
            $this->assertWarehouseWritable($warehouse, $payload, 'add return order');
            $items = $this->validatedProductList($productList, $projectId, $tenantId, null);

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
                'STATE' => $this->stateForAmount($amount),
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
                'EXT_JSON' => $this->jsonInput($input['extJson'] ?? null),
                'TENANT_ID' => $tenantId,
            ]);

            $this->insertItems($id, $items, $tenantId, $now, $currentUserId);
            $this->createReturnDeliveryRecordsAndIncreaseInventory(
                $id,
                $warehouseId,
                $items,
                $processId ?? $id,
                $tenantId,
                $ownerUserId !== '' ? $ownerUserId : $currentUserId,
                $currentUserId,
                $now,
                $remark ?? ''
            );
            $this->recalculateProjectReturnTotals($projectId, $tenantId, $now, $currentUserId);

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
            $amount = $this->moneyAmount($variables['amount'] ?? null, 'amount', true);
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

            Db::name('return_order')->insert([
                'ID' => $returnOrderId,
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $amount,
                'STATE' => $this->stateForAmount($amount),
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
                'EXT_JSON' => null,
                'TENANT_ID' => $effectiveTenantId,
            ]);

            $this->insertItems($returnOrderId, $items, $effectiveTenantId, $now, $auditUser);
            $this->createReturnDeliveryRecordsAndIncreaseInventory(
                $returnOrderId,
                $warehouseId,
                $items,
                $processInstanceId,
                $effectiveTenantId,
                $initiator,
                $auditUser,
                $now,
                $remark ?? ''
            );
            $autoRefund = $this->createWorkflowReturnRefundIfPossible(
                $project,
                $returnOrderId,
                $processInstanceId,
                $effectiveTenantId,
                $auditUser,
                $initiator,
                $amount,
                $now,
                $remark
            );
            if ($autoRefund === null) {
                $this->recalculateProjectReturnTotals($projectId, $effectiveTenantId, $now, $auditUser);
            }

            return $this->workflowReturnSummary($returnOrderId, $projectId, $processInstanceId, $effectiveTenantId, $autoRefund);
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

            $amount = array_key_exists('amount', $input)
                ? $this->moneyAmount($input['amount'], 'amount', true)
                : $this->moneyAmount($current['AMOUNT'] ?? 0, 'amount', true);
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $items = $hasProductList
                ? $this->validatedProductList($productList, $projectId, $tenantId, $id)
                : $this->currentReturnItemsForDelivery($id, $tenantId);

            $updates = [
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $amount,
                'STATE' => $this->stateForAmount($amount),
                'WAREHOUSES_ID' => $warehouseId,
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
            if (array_key_exists('extJson', $input)) {
                $updates['EXT_JSON'] = $this->jsonInput($input['extJson']);
            }

            if ($this->workflowProcessExists((string)($updates['PROCESS_ID'] ?? $current['PROCESS_ID'] ?? ''), $tenantId)) {
                throw new RuntimeException('return order has workflow records', 400);
            }

            $this->reverseReturnRefundFinance([$id], $tenantId, $now, $currentUserId);
            $this->reverseReturnDeliveryRecordsAndInventory([$id], $tenantId, $now, $currentUserId);

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

            $deliveryProcessId = trim((string)($updates['PROCESS_ID'] ?? $current['PROCESS_ID'] ?? ''));
            if ($deliveryProcessId === '') {
                $deliveryProcessId = $id;
            }
            $deliveryOperator = trim((string)($updates['USER'] ?? $current['USER'] ?? ''));
            if ($deliveryOperator === '') {
                $deliveryOperator = $currentUserId;
            }
            $this->createReturnDeliveryRecordsAndIncreaseInventory(
                $id,
                $warehouseId,
                $items,
                $deliveryProcessId,
                $tenantId,
                $deliveryOperator,
                $currentUserId,
                $now,
                (string)($updates['REMARK'] ?? $current['REMARK'] ?? '')
            );

            $this->recalculateProjectReturnTotals($currentProjectId, (string)($current['TENANT_ID'] ?? $tenantId), $now, $currentUserId);
            if ($projectId !== $currentProjectId) {
                $this->recalculateProjectReturnTotals($projectId, $tenantId, $now, $currentUserId);
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

            $this->reverseReturnRefundFinance($ids, $tenantId, $now, $currentUserId);
            $this->reverseReturnDeliveryRecordsAndInventory($ids, $tenantId, $now, $currentUserId);

            $updated = Db::name('return_order')
                ->whereIn('ID', $ids)
                ->update($deleteData);
            Db::name('return_order_item')
                ->whereIn('RETURN_ORDER_ID', $ids)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update($deleteData);

            $projects = [];
            foreach ($rows as $row) {
                $projects[(string)$row['PROJECT_ID']] = (string)($row['TENANT_ID'] ?? $tenantId);
            }
            foreach ($projects as $projectId => $projectTenantId) {
                $this->recalculateProjectReturnTotals($projectId, $projectTenantId, $now, $currentUserId);
            }

            return ['ids' => $ids, 'count' => $updated];
        });
    }

    private function orderQuery(array $filters, array $payload)
    {
        $query = Db::name('return_order')
            ->alias('r')
            ->leftJoin('biz_sale_project p', 'p.ID = r.PROJECT_ID')
            ->leftJoin('warehouses w', 'w.ID = r.WAREHOUSES_ID')
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
                $query->whereIn('r.ORG', $scope);

                return;
            }
        }

        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId !== '') {
            $query->where('r.USER', $userId);
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
            'logisticsCategory' => $this->value($row, 'LOGISTICS_CATEGORY', 'logisticsCategory'),
            'logisticsId' => $this->value($row, 'LOGISTICS_ID', 'logisticsId'),
            'user' => $this->value($row, 'USER', 'user'),
            'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
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
     * @return array<string, mixed>|null
     */
    private function createWorkflowReturnRefundIfPossible(
        array $project,
        string $returnOrderId,
        string $processInstanceId,
        string $tenantId,
        string $auditUser,
        string $initiator,
        string $amount,
        string $now,
        ?string $remark
    ): ?array {
        $accountId = trim((string)($project['ACCOUNT_ID'] ?? ''));
        if ($accountId === '' || (float)$amount <= 0.0) {
            return null;
        }

        $payer = $auditUser !== '' ? $auditUser : $initiator;

        return (new SettlementAccountService())->expensesFromWorkflow(
            [
                'targetId' => $accountId,
                'accountId' => $accountId,
                'settlementCategory' => self::RETURN_AND_REFUND,
                'payer' => $payer,
                'payerTime' => $now,
                'amount' => $amount,
                'objectId' => $returnOrderId,
                'remark' => $remark,
            ],
            $processInstanceId,
            $tenantId,
            $payer,
            self::PROCESS_SALE_PROJECT_PRODUCT_RETURN
        );
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
            ->field('ID,PROJECT_ID,PRODUCT_ID,NUMBER,STATE,TENANT_ID')
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
            $item['productId'] = (string)($row['PRODUCT_ID'] ?? $item['productId']);
        }
        unset($item);

        return $normalized;
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
            ->field('i.PROJECT_PRODUCT_ITEM_ID, i.AMOUNT, pi.PRODUCT_ID')
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

        return array_map(static function (array $row): array {
            return [
                'projectProductItemId' => (string)($row['PROJECT_PRODUCT_ITEM_ID'] ?? ''),
                'amount' => number_format((float)($row['AMOUNT'] ?? 0), 2, '.', ''),
                'productId' => (string)($row['PRODUCT_ID'] ?? ''),
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

        $query = Db::name('delivery_record')->whereIn('OBJECT_ID', $orderIds);
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

        $returnRows = $returnQuery->field('ID,AMOUNT')->select()->toArray();
        $returnOrderIds = array_values(array_filter(array_map(static fn (array $row): string => trim((string)($row['ID'] ?? '')), $returnRows)));
        $totalRefundAmount = array_reduce(
            $returnRows,
            static fn (float $carry, array $row): float => $carry + (float)($row['AMOUNT'] ?? 0),
            0.0
        );
        $totalReturnAmount = 0.0;
        if ($returnOrderIds !== []) {
            $expenditureQuery = Db::name('biz_expenditure_record')
                ->whereIn('OBJECT_ID', $returnOrderIds)
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
        string $tenantId,
        ?array $autoRefund = null
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
        if ($autoRefund !== null) {
            $summary['autoRefund'] = $autoRefund;
        }

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

    private function jsonInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)$value;
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

    private function stateForAmount(string $amount): string
    {
        return (float)$amount <= 0.0 ? self::STATE_ALREADY_SETTLED : self::STATE_UNSETTLED;
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
