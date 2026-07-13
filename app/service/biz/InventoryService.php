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
    private const DELETED = 'DELETED';
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
p.status AS PRODUCT_STATUS,
w.NAME AS WAREHOUSE_NAME,
w.CODE AS WAREHOUSE_CODE,
w.ADDRESS AS WAREHOUSE_ADDRESS
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

    public function add(array $input, array $payload = []): array
    {
        $warehouseId = $this->requiredInput($input, 'warehousesId');
        $productIds = $this->productIds($input);

        return Db::transaction(function () use ($input, $payload, $warehouseId, $productIds): array {
            $warehouse = $this->activeWarehouse($warehouseId, $payload);
            $this->assertWarehouseWritable($warehouse, $payload, 'add inventory');

            $tenantId = $this->inventoryTenantId($input, $payload, $warehouse);
            $products = $this->activeProducts($productIds, $tenantId);
            if (count($products) !== count($productIds)) {
                throw new RuntimeException('inventory products contain missing rows', 400);
            }
            foreach ($productIds as $productId) {
                $this->assertProductWritable($products[$productId], $payload, 'add inventory');
            }

            $existingRows = $this->inventoryRowsForWarehouse($warehouseId, $productIds, $tenantId);
            foreach ($existingRows as $existingRow) {
                $deleteFlag = (string)($existingRow['DELETE_FLAG'] ?? '');
                if ($deleteFlag !== '' && $deleteFlag !== self::NOT_DELETE) {
                    throw new RuntimeException('inventory row already deleted', 409);
                }
            }

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $existingByProduct = [];
            foreach ($existingRows as $existingRow) {
                $existingByProduct[(string)$existingRow['PRODUCT_ID']] = $existingRow;
            }

            $insertedIds = [];
            $updated = 0;
            foreach ($productIds as $productId) {
                if (isset($existingByProduct[$productId])) {
                    Db::name('inventory')
                        ->where('ID', (string)$existingByProduct[$productId]['ID'])
                        ->update([
                            'CURRENT_COUNT' => Db::raw('COALESCE(CURRENT_COUNT, 0)'),
                            'UPDATE_TIME' => $now,
                            'UPDATE_USER' => $userId !== '' ? $userId : null,
                            'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                        ]);
                    $updated++;

                    continue;
                }

                $id = $this->newId();
                Db::name('inventory')->insert([
                    'ID' => $id,
                    'WAREHOUSES_ID' => $warehouseId,
                    'PRODUCT_ID' => $productId,
                    'CURRENT_COUNT' => '0',
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'CREATE_TIME' => $now,
                    'CREATE_USER' => $userId !== '' ? $userId : null,
                    'UPDATE_TIME' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $tenantId,
                    'VERSION' => 0,
                ]);
                $insertedIds[] = $id;
            }

            return [
                'count' => count($productIds),
                'inserted' => count($insertedIds),
                'updated' => $updated,
                'ids' => $insertedIds,
            ];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->stringList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $rows = $this->activeInventoryRowsByIds($idList, $payload);
            if (count($rows) !== count($idList)) {
                throw new RuntimeException('inventory not found', 404);
            }

            foreach ($idList as $id) {
                $row = $rows[$id] ?? null;
                if ($row === null) {
                    throw new RuntimeException('inventory not found', 404);
                }

                if (abs((float)($row['CURRENT_COUNT'] ?? 0)) > 0.000001) {
                    throw new RuntimeException('inventory with current count cannot be deleted', 400);
                }

                $warehouse = $this->activeWarehouse((string)$row['WAREHOUSES_ID'], $payload);
                $this->assertWarehouseWritable($warehouse, $payload, 'delete inventory');
                $product = $this->activeProductForDelete((string)$row['PRODUCT_ID'], trim((string)($row['TENANT_ID'] ?? '')));
                $this->assertProductWritable($product, $payload, 'delete inventory');
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('inventory')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return [
                'ids' => $idList,
                'count' => $updated,
            ];
        });
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
            ->leftJoin('warehouses w', 'w.ID = i.WAREHOUSES_ID')
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
            'warehouseName' => $this->value($row, 'WAREHOUSE_NAME', 'warehouseName'),
            'warehouseCode' => $this->value($row, 'WAREHOUSE_CODE', 'warehouseCode'),
            'warehouseAddress' => $this->value($row, 'WAREHOUSE_ADDRESS', 'warehouseAddress'),
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

    private function activeWarehouse(string $warehouseId, array $payload): array
    {
        $query = Db::name('warehouses')->where('ID', $warehouseId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('warehouse not found', 404);
        }

        return $row;
    }

    private function assertWarehouseWritable(array $warehouse, array $payload, string $action): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $warehouseOrg = trim((string)($warehouse['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && $warehouseOrg !== '' && in_array($warehouseOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $ownerUserId = trim((string)($warehouse['USER'] ?? ''));
        if ($currentUserId !== '' && $ownerUserId === $currentUserId) {
            return;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, array<string, mixed>>
     */
    private function activeProducts(array $productIds, string $tenantId): array
    {
        $query = Db::name('biz_product')
            ->whereIn('ID', $productIds)
            ->where('status', self::ENABLE);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,ORG,CREATE_USER,TENANT_ID,status')
            ->select()
            ->toArray();

        $products = [];
        foreach ($rows as $row) {
            $products[(string)$row['ID']] = $row;
        }

        return $products;
    }

    private function assertProductWritable(array $product, array $payload, string $action): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $productOrg = trim((string)($product['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && $productOrg !== '' && in_array($productOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($product['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @param array<int, string> $productIds
     * @return array<int, array<string, mixed>>
     */
    private function inventoryRowsForWarehouse(string $warehouseId, array $productIds, string $tenantId): array
    {
        $query = Db::name('inventory')
            ->where('WAREHOUSES_ID', $warehouseId)
            ->whereIn('PRODUCT_ID', $productIds);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query
            ->field('ID,PRODUCT_ID,DELETE_FLAG,CURRENT_COUNT,VERSION,TENANT_ID')
            ->lock(true)
            ->select()
            ->toArray();
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function activeInventoryRowsByIds(array $ids, array $payload): array
    {
        $query = Db::name('inventory')->whereIn('ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,WAREHOUSES_ID,PRODUCT_ID,CURRENT_COUNT,TENANT_ID,VERSION')
            ->lock(true)
            ->select()
            ->toArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string)$row['ID']] = $row;
        }

        return $indexed;
    }

    private function activeProductForDelete(string $productId, string $tenantId): array
    {
        $query = Db::name('biz_product')->where('ID', $productId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->field('ID,ORG,CREATE_USER,TENANT_ID,status')->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('product not found', 404);
        }

        return $row;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function productIds(array $input): array
    {
        $source = $input['productIds'] ?? $input['productIdList'] ?? null;
        if ($source === null && array_key_exists('productId', $input)) {
            $source = [$input['productId']];
        }
        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source) || $source === []) {
            throw new RuntimeException('missing productIds', 400);
        }

        $ids = array_values(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $source)));
        if ($ids === []) {
            throw new RuntimeException('missing productIds', 400);
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('duplicate productId', 400);
        }

        return $ids;
    }

    /**
     * @param array<int, mixed> $source
     * @return array<int, string>
     */
    private function stringList(array $source): array
    {
        $values = [];
        foreach ($source as $entry) {
            if (is_array($entry)) {
                $entry = $entry['id'] ?? $entry['ID'] ?? '';
            }
            foreach (explode(',', (string)$entry) as $part) {
                $value = trim($part);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function inventoryTenantId(array $input, array $payload, array $warehouse): string
    {
        $warehouseTenantId = trim((string)($warehouse['TENANT_ID'] ?? ''));
        $requestedTenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($warehouseTenantId !== '' && $requestedTenantId !== '' && $warehouseTenantId !== $requestedTenantId) {
            throw new RuntimeException('tenant mismatch', 403);
        }

        $tenantId = $warehouseTenantId !== '' ? $warehouseTenantId : $requestedTenantId;

        return $tenantId !== '' ? $tenantId : '1';
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
            $ids = array_merge($ids, array_map(static function (mixed $scope): string {
                if (!is_array($scope)) {
                    return '';
                }

                return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
            }, $scopes));
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $ids
        ))));
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

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
