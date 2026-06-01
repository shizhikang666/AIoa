<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only return-order queries compatible with Java ReturnOrderController.
 */
class ReturnOrderService
{
    private const NOT_DELETE = 'NOT_DELETE';
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
