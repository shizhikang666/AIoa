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

            Db::name('return_order')->insert([
                'ID' => $id,
                'PROJECT_ID' => $projectId,
                'AMOUNT' => $amount,
                'STATE' => $this->stateForAmount($amount),
                'PROCESS_ID' => $this->textInput($input, 'processId', false, 80),
                'REMARK' => $this->textInput($input, 'remark', false, 65535),
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
            $this->recalculateProjectReturnTotals($projectId, $tenantId, $now, $currentUserId);

            return $this->detail($id, $payload);
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
            if ($this->hasReturnRefundExpenditure([$id], $tenantId)) {
                throw new RuntimeException('return order already has refund expenditure', 400);
            }

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
                if (array_key_exists($key, $input)) {
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

            Db::name('return_order')->where('ID', $id)->update($updates);

            if ($hasProductList) {
                $items = $this->validatedProductList($productList, $projectId, $tenantId, $id);
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
            if ($this->hasReturnRefundExpenditure($ids, $tenantId)) {
                throw new RuntimeException('return order already has refund expenditure', 400);
            }

            foreach ($rows as $row) {
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
