<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only inventory queries compatible with Java InventoryController.
 */
class InventoryService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ENABLE = 'ENABLE';
    private const INVENTORY_FIELDS = <<<SQL
i.ID AS ID,
i.WAREHOUSES_ID AS WAREHOUSES_ID,
i.PRODUCT_ID AS PRODUCT_ID,
i.CURRENT_COUNT AS CURRENT_COUNT,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
i.VERSION AS VERSION,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.SAFETY_STOCK AS SAFETY_STOCK,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.MIN_PRICE AS MIN_PRICE,
p.CATEGORY AS CATEGORY,
p.SPECS AS SPECS,
p.status AS PRODUCT_STATUS
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'i.ID',
        'warehousesId' => 'i.WAREHOUSES_ID',
        'productId' => 'i.PRODUCT_ID',
        'currentCount' => 'i.CURRENT_COUNT',
        'createTime' => 'i.CREATE_TIME',
        'updateTime' => 'i.UPDATE_TIME',
        'tenantId' => 'i.TENANT_ID',
        'version' => 'i.VERSION',
        'productName' => 'p.PRODUCT_NAME',
        'productCategory' => 'p.PRODUCT_CATEGORY',
        'safetyStock' => 'p.SAFETY_STOCK',
        'purchasePrice' => 'p.PURCHASE_PRICE',
        'salePrice' => 'p.SALE_PRICE',
        'minPrice' => 'p.MIN_PRICE',
        'category' => 'p.CATEGORY',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->inventoryQuery($filters, $payload, true, true)->count();
        $rows = $this->applySort($this->inventoryQuery($filters, $payload, true, true), $filters)
            ->field(self::INVENTORY_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->inventoryRows($rows),
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
        $rows = $this->applySort($this->inventoryQuery($filters, $payload, true, true), $filters)
            ->field(self::INVENTORY_FIELDS)
            ->select()
            ->toArray();

        return $this->inventoryRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->inventoryQuery(['id' => $id], $payload, false, false)
            ->field(self::INVENTORY_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('inventory not found', 404);
        }

        return $this->inventoryRows([$row])[0];
    }

    private function inventoryQuery(array $filters, array $payload, bool $requireWarehouse, bool $enabledProductsOnly)
    {
        $warehouseId = trim((string)($filters['warehousesId'] ?? $filters['warehouseId'] ?? ''));
        if ($requireWarehouse && $warehouseId === '') {
            throw new RuntimeException('missing warehousesId', 400);
        }

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($warehouseId !== '') {
            $this->assertWarehouseExists($warehouseId, $tenantId);
        }

        $query = Db::name('inventory')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->where(function ($query): void {
                $query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($enabledProductsOnly) {
            $query->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })->where('p.status', self::ENABLE);
        }

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
            if ($enabledProductsOnly) {
                $query->where('p.TENANT_ID', $tenantId);
            }
        }

        if (!empty($filters['id'])) {
            $query->where('i.ID', (string)$filters['id']);
        }

        if ($warehouseId !== '') {
            $query->where('i.WAREHOUSES_ID', $warehouseId);
        }

        if (!empty($filters['productId'])) {
            $query->where('i.PRODUCT_ID', (string)$filters['productId']);
        }

        if (!empty($filters['productName'])) {
            $query->whereLike('p.PRODUCT_NAME', '%' . trim((string)$filters['productName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('p.PRODUCT_NAME', $keyword)
                    ->whereOr('p.SPECS', 'like', $keyword);
            });
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('i.ID', 'asc');
        }

        return $query->order('p.PRODUCT_NAME', 'asc')->order('i.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function inventoryRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->inventoryRow($row), $rows);
    }

    private function inventoryRow(array $row): array
    {
        $inventory = [
            'id' => $this->value($row, 'ID', 'id'),
            'warehousesId' => $this->value($row, 'WAREHOUSES_ID', 'warehousesId'),
            'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
            'currentCount' => $this->decimal($this->value($row, 'CURRENT_COUNT', 'currentCount')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => $this->integer($this->value($row, 'VERSION', 'version')),
        ];

        return [
            ...$inventory,
            'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
            'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
            'safetyStock' => $this->decimal($this->value($row, 'SAFETY_STOCK', 'safetyStock')),
            'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
            'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
            'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'specs' => $this->value($row, 'SPECS', 'specs'),
            'status' => $this->value($row, 'PRODUCT_STATUS', 'status'),
            'inventory' => $inventory,
        ];
    }

    private function assertWarehouseExists(string $warehouseId, string $tenantId = ''): void
    {
        $query = Db::name('warehouses')
            ->where('ID', $warehouseId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if (!$query->find()) {
            throw new RuntimeException('warehouse not found', 404);
        }
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
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
