<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only purchase-order queries compatible with Java BizPurchaseOrderController.
 */
class PurchaseOrderService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const GOODS_EXPENDITURE = 'GOODS_EXPENDITURE';
    private const PRODUCT_CATEGORY_SINGLE = 'SINGLE_PRODUCT';
    private const ORDER_FIELDS = <<<SQL
o.ID AS ID,
o.TITLE AS TITLE,
o.SETTLEMENT_STATUS AS SETTLEMENT_STATUS,
o.STORAGE_STATUS AS STORAGE_STATUS,
o.SUPPLIER_ID AS SUPPLIER_ID,
o.INSTANCE_ID AS INSTANCE_ID,
o.DESIRE_PURCHASE_DATE AS DESIRE_PURCHASE_DATE,
o.AMOUNT AS AMOUNT,
o.REMARK AS REMARK,
o.EXT_JSON AS EXT_JSON,
o.DELETE_FLAG AS DELETE_FLAG,
o.CREATE_TIME AS CREATE_TIME,
o.CREATE_USER AS CREATE_USER,
o.UPDATE_TIME AS UPDATE_TIME,
o.UPDATE_USER AS UPDATE_USER,
o.TENANT_ID AS TENANT_ID,
o.VERSION AS VERSION,
o.ORG AS ORG,
org.NAME AS ORG_NAME
SQL;
    private const ITEM_FIELDS = <<<SQL
i.ID AS ID,
i.PURCHASE_ORDER_ID AS PURCHASE_ORDER_ID,
i.STORAGE_STATUS AS STORAGE_STATUS,
i.PRODUCT_ID AS PRODUCT_ID,
i.AMOUNT AS AMOUNT,
i.NUMBER AS NUMBER,
i.UNIT_AMOUNT AS UNIT_AMOUNT,
i.DISCOUNT_RATE AS DISCOUNT_RATE,
i.REMARK AS REMARK,
i.EXT_JSON AS EXT_JSON,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
i.VERSION AS VERSION,
i.FREIGHT_SHARE_AMOUNT AS FREIGHT_SHARE_AMOUNT,
i.UNIT_COST_WITH_FREIGHT AS UNIT_COST_WITH_FREIGHT,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.CATEGORY AS CATEGORY,
p.SPECS AS SPECS,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.MIN_PRICE AS MIN_PRICE
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'o.ID',
        'title' => 'o.TITLE',
        'settlementStatus' => 'o.SETTLEMENT_STATUS',
        'storageStatus' => 'o.STORAGE_STATUS',
        'supplierId' => 'o.SUPPLIER_ID',
        'instanceId' => 'o.INSTANCE_ID',
        'desirePurchaseDate' => 'o.DESIRE_PURCHASE_DATE',
        'amount' => 'o.AMOUNT',
        'createTime' => 'o.CREATE_TIME',
        'updateTime' => 'o.UPDATE_TIME',
        'tenantId' => 'o.TENANT_ID',
        'version' => 'o.VERSION',
        'org' => 'o.ORG',
        'orgName' => 'org.NAME',
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
    public function list(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->select()
            ->toArray();

        return $this->orderRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function detailList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->select()
            ->toArray();
        $orders = $this->orderRows($rows);
        $itemsByOrderId = $this->itemsByOrderIds(array_column($orders, 'id'), $payload);

        foreach ($orders as &$order) {
            $order['orderItems'] = $itemsByOrderId[(string)$order['id']] ?? [];
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
            throw new RuntimeException('purchase order not found', 404);
        }

        return [
            'bizPurchaseOrder' => $this->orderRows([$row])[0],
            'bizPurchaseOrderItemList' => $this->itemRowsByOrderId($id, $payload),
            'bizExpenditureRecordList' => $this->expenditureRows($id, $payload),
        ];
    }

    private function orderQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_purchase_order')
            ->alias('o')
            ->leftJoin('sys_org org', 'org.ID = o.ORG')
            ->where(function ($query): void {
                $query->whereNull('o.DELETE_FLAG')->whereOr('o.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('o.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'o.ID',
            'settlementStatus' => 'o.SETTLEMENT_STATUS',
            'storageStatus' => 'o.STORAGE_STATUS',
            'supplierId' => 'o.SUPPLIER_ID',
            'instanceId' => 'o.INSTANCE_ID',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('o.ORG', $orgIds);
            }
        } elseif (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('o.ORG', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['supplierName'])) {
            $query->whereRaw(
                "(JSON_VALID(o.EXT_JSON) = 1 AND JSON_UNQUOTE(JSON_EXTRACT(o.EXT_JSON, '$.supplier.name')) LIKE ?)",
                ['%' . trim((string)$filters['supplierName']) . '%']
            );
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                "(o.TITLE LIKE ? OR o.REMARK LIKE ? OR (JSON_VALID(o.EXT_JSON) = 1 AND JSON_UNQUOTE(JSON_EXTRACT(o.EXT_JSON, '$.supplier.name')) LIKE ?))",
                [$keyword, $keyword, $keyword]
            );
        }

        if (!empty($filters['productName'])) {
            $orderIds = $this->purchaseOrderIdsByProductName((string)$filters['productName'], $tenantId);
            if ($orderIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('o.ID', $orderIds);
            }
        }

        if (!empty($filters['minAmount'])) {
            $query->where('o.AMOUNT', '>=', (float)$filters['minAmount']);
        }

        if (!empty($filters['maxAmount'])) {
            $query->where('o.AMOUNT', '<=', (float)$filters['maxAmount']);
        }

        $this->applyCreateTimeRange($query, $filters);

        return $query;
    }

    private function applyCreateTimeRange($query, array $filters): void
    {
        $start = trim((string)($filters['startCreateTime'] ?? ''));
        $end = trim((string)($filters['endCreateTime'] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime('o.CREATE_TIME', $start, $end);
        } elseif ($start !== '') {
            $query->whereTime('o.CREATE_TIME', '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime('o.CREATE_TIME', '<=', $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('o.ID', 'asc');
        }

        return $query->order('o.ID', 'asc');
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
        $extJson = (string)($this->value($row, 'EXT_JSON', 'extJson') ?? '');
        $ext = $this->decodeJsonObject($extJson);
        $supplier = is_array($ext['supplier'] ?? null) ? $ext['supplier'] : [];

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'title' => $this->value($row, 'TITLE', 'title'),
            'settlementStatus' => $this->value($row, 'SETTLEMENT_STATUS', 'settlementStatus'),
            'storageStatus' => $this->value($row, 'STORAGE_STATUS', 'storageStatus'),
            'supplierId' => $this->value($row, 'SUPPLIER_ID', 'supplierId'),
            'supplierName' => $supplier['name'] ?? null,
            'supplier' => $supplier,
            'instanceId' => $this->value($row, 'INSTANCE_ID', 'instanceId'),
            'desirePurchaseDate' => $this->value($row, 'DESIRE_PURCHASE_DATE', 'desirePurchaseDate'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'extJson' => $extJson,
            'ext' => $ext,
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => $this->integer($this->value($row, 'VERSION', 'version')),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
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
        $query = $this->itemBaseQuery($tenantId)->whereIn('i.PURCHASE_ORDER_ID', $ids);
        $rows = $query->order('i.ID', 'asc')->select()->toArray();
        $result = [];
        foreach ($this->itemRows($rows) as $row) {
            $result[(string)$row['purchaseOrderId']][] = $row;
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
            ->where('i.PURCHASE_ORDER_ID', $orderId)
            ->order('i.ID', 'asc')
            ->select()
            ->toArray();

        return $this->itemRows($rows);
    }

    private function itemBaseQuery(string $tenantId)
    {
        $query = Db::name('biz_purchase_order_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
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
                'purchaseOrderId' => $this->value($row, 'PURCHASE_ORDER_ID', 'purchaseOrderId'),
                'storageStatus' => $this->value($row, 'STORAGE_STATUS', 'storageStatus'),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'number' => $this->integer($this->value($row, 'NUMBER', 'number')),
                'unitAmount' => $this->decimal($this->value($row, 'UNIT_AMOUNT', 'unitAmount')),
                'discountRate' => $this->decimal($this->value($row, 'DISCOUNT_RATE', 'discountRate')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'freightShareAmount' => $this->decimal($this->value($row, 'FREIGHT_SHARE_AMOUNT', 'freightShareAmount')),
                'unitCostWithFreight' => $this->decimal($this->value($row, 'UNIT_COST_WITH_FREIGHT', 'unitCostWithFreight')),
                'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
                'category' => $this->value($row, 'CATEGORY', 'category'),
                'specs' => $this->value($row, 'SPECS', 'specs'),
                'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
                'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
                'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expenditureRows(string $orderId, array $payload): array
    {
        $query = Db::name('biz_expenditure_record')
            ->where('OBJECT_ID', $orderId)
            ->where('SETTLEMENT_CATEGORY', self::GOODS_EXPENDITURE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query->order('CREATE_TIME', 'asc')->select()->toArray();

        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
                'targetId' => $this->value($row, 'TARGET_ID', 'targetId'),
                'serialId' => $this->value($row, 'SERIAL_ID', 'serialId'),
                'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
                'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
                'payer' => $this->value($row, 'PAYER', 'payer'),
                'bankName' => $this->value($row, 'BANK_NAME', 'bankName'),
                'bankAccount' => $this->value($row, 'BANK_ACCOUNT', 'bankAccount'),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'user' => $this->value($row, 'USER', 'user'),
                'org' => $this->value($row, 'ORG', 'org'),
            ];
        }, $rows);
    }

    /**
     * @return array<int, string>
     */
    private function purchaseOrderIdsByProductName(string $productName, string $tenantId): array
    {
        $keyword = trim($productName);
        if ($keyword === '') {
            return [];
        }

        $productQuery = Db::name('biz_product')
            ->whereLike('PRODUCT_NAME', '%' . $keyword . '%')
            ->where('CATEGORY', self::PRODUCT_CATEGORY_SINGLE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $productQuery->where('TENANT_ID', $tenantId);
        }

        $productIds = array_map('strval', $productQuery->column('ID'));
        if ($productIds === []) {
            return [];
        }

        $itemQuery = Db::name('biz_purchase_order_item')
            ->whereIn('PRODUCT_ID', $productIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $itemQuery->where('TENANT_ID', $tenantId);
        }

        return array_values(array_unique(array_map('strval', $itemQuery->column('PURCHASE_ORDER_ID'))));
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

    private function decodeJsonObject(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
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
