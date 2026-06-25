<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only warehouse delivery-record queries compatible with Java DeliveryRecordController.
 */
class DeliveryRecordService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ENABLE = 'ENABLE';
    private const PROCESS_SYS = 'Process_sys';
    private const CATEGORY_IN = 'IN';
    private const CATEGORY_OUT = 'OUT';
    private const DELIVERY_FIELDS = <<<SQL
d.ID AS ID,
d.WAREHOUSES_ID AS WAREHOUSES_ID,
d.PROCESS_ID AS PROCESS_ID,
d.PRODUCT_ID AS PRODUCT_ID,
d.AMOUNT AS AMOUNT,
d.CATEGORY AS CATEGORY,
d.PROCESS_CATEGORY AS PROCESS_CATEGORY,
d.OPERATOR AS OPERATOR,
d.REMARK AS REMARK,
d.DELIVERY_TIME AS DELIVERY_TIME,
d.CREATE_TIME AS CREATE_TIME,
d.DELETE_FLAG AS DELETE_FLAG,
d.CREATE_USER AS CREATE_USER,
d.UPDATE_TIME AS UPDATE_TIME,
d.EXT_JSON AS EXT_JSON,
d.UPDATE_USER AS UPDATE_USER,
d.TENANT_ID AS TENANT_ID,
d.OBJECT_ID AS OBJECT_ID,
w.NAME AS WAREHOUSES_NAME,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.ORG AS PRODUCT_ORG,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.SAFETY_STOCK AS SAFETY_STOCK,
p.SPECS AS SPECS,
p.MIN_PRICE AS MIN_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
u.NAME AS OPERATOR_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'd.ID',
        'warehousesId' => 'd.WAREHOUSES_ID',
        'processId' => 'd.PROCESS_ID',
        'productId' => 'd.PRODUCT_ID',
        'amount' => 'd.AMOUNT',
        'category' => 'd.CATEGORY',
        'processCategory' => 'd.PROCESS_CATEGORY',
        'operator' => 'd.OPERATOR',
        'deliveryTime' => 'd.DELIVERY_TIME',
        'createTime' => 'd.CREATE_TIME',
        'updateTime' => 'd.UPDATE_TIME',
        'tenantId' => 'd.TENANT_ID',
        'productName' => 'p.PRODUCT_NAME',
        'warehousesName' => 'w.NAME',
        'operatorName' => 'u.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->deliveryQuery($filters, $payload, false)->count();
        $rows = $this->applySort($this->deliveryQuery($filters, $payload, false), $filters)
            ->field(self::DELIVERY_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->deliveryRows($rows),
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
    public function exportOtherCompanyRecordsList(array $filters = [], array $payload = []): array
    {
        $warehouseId = trim((string)($filters['warehousesId'] ?? $filters['warehouseId'] ?? ''));
        $orgId = trim((string)($filters['orgId'] ?? $filters['org'] ?? ''));
        if ($warehouseId === '') {
            throw new RuntimeException('missing warehousesId', 400);
        }
        if ($orgId === '') {
            throw new RuntimeException('missing orgId', 400);
        }

        $orgIds = $this->orgAndChildren($orgId);
        if ($orgIds === []) {
            return [];
        }

        $query = $this->deliveryQuery($filters, $payload, true)
            ->whereIn('p.ORG', $orgIds)
            ->where('d.WAREHOUSES_ID', '<>', $warehouseId);

        $rows = $this->applyDeliveryTimeRange($query, $filters)
            ->field(self::DELIVERY_FIELDS)
            ->order('d.DELIVERY_TIME', 'desc')
            ->order('d.ID', 'asc')
            ->select()
            ->toArray();

        return $this->deliveryRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->deliveryQuery(['id' => $id], $payload, false)
            ->field(self::DELIVERY_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('delivery record not found', 404);
        }

        return $this->deliveryRows([$row])[0];
    }

    public function add(array $input, array $payload = []): array
    {
        $warehouseId = $this->requiredInput($input, 'warehousesId');
        $productId = $this->requiredInput($input, 'productId');
        $targetAmount = $this->requiredDecimal($input, 'amount');
        $deliveryTime = $this->requiredTime($input, 'deliveryTime');
        $remark = array_key_exists('remark', $input) ? trim((string)$input['remark']) : null;

        return Db::transaction(function () use ($warehouseId, $productId, $targetAmount, $deliveryTime, $remark, $payload): array {
            $warehouse = $this->activeWarehouse($warehouseId, $payload);
            $this->assertWarehouseWritable($warehouse, $payload, 'add delivery record');

            $tenantId = $this->tenantId($payload, $warehouse);
            $product = $this->activeProduct($productId, $tenantId);
            $this->assertProductWritable($product, $payload, 'add delivery record');

            $inventory = $this->activeInventoryForUpdate($warehouseId, $productId, $tenantId);
            $currentAmount = (float)$this->decimalString($inventory['CURRENT_COUNT'] ?? '0');
            $targetValue = (float)$targetAmount;
            $diff = round($targetValue - $currentAmount, 6);
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);

            $deliveryId = null;
            $category = null;
            $movementAmount = 0.0;
            if (abs($diff) > 0.000001) {
                $category = $diff > 0 ? self::CATEGORY_IN : self::CATEGORY_OUT;
                $movementAmount = abs($diff);
                $deliveryId = $this->newId();
                Db::name('delivery_record')->insert([
                    'ID' => $deliveryId,
                    'WAREHOUSES_ID' => $warehouseId,
                    'PROCESS_ID' => self::PROCESS_SYS,
                    'PRODUCT_ID' => $productId,
                    'AMOUNT' => $this->decimalStorage($movementAmount),
                    'CATEGORY' => $category,
                    'PROCESS_CATEGORY' => self::PROCESS_SYS,
                    'OPERATOR' => $userId !== '' ? $userId : '0',
                    'REMARK' => $remark ?? '',
                    'DELIVERY_TIME' => $deliveryTime,
                    'CREATE_TIME' => $now,
                    'CREATE_USER' => $userId !== '' ? $userId : null,
                    'UPDATE_TIME' => null,
                    'UPDATE_USER' => null,
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'TENANT_ID' => $tenantId,
                    'OBJECT_ID' => null,
                ]);
            }

            Db::name('inventory')
                ->where('ID', (string)$inventory['ID'])
                ->update([
                    'CURRENT_COUNT' => $this->decimalStorage($targetAmount),
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return [
                'id' => $deliveryId,
                'inventoryId' => (string)$inventory['ID'],
                'category' => $category,
                'amount' => $this->decimal($movementAmount),
                'currentCount' => $this->decimal($targetAmount),
                'count' => $deliveryId === null ? 0 : 1,
            ];
        });
    }

    private function deliveryQuery(array $filters, array $payload, bool $enabledProductsOnly)
    {
        $query = Db::name('delivery_record')
            ->alias('d')
            ->leftJoin('warehouses w', 'w.ID = d.WAREHOUSES_ID')
            ->leftJoin('biz_product p', 'p.ID = d.PRODUCT_ID')
            ->leftJoin('sys_user u', 'u.ID = d.OPERATOR')
            ->where(function ($query): void {
                $query->whereNull('d.DELETE_FLAG')->whereOr('d.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($enabledProductsOnly) {
            $query->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })->where('p.status', self::ENABLE);
        }

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('d.TENANT_ID', $tenantId);
            if ($enabledProductsOnly) {
                $query->where('p.TENANT_ID', $tenantId);
            }
        }

        foreach ([
            'id' => 'd.ID',
            'warehousesId' => 'd.WAREHOUSES_ID',
            'warehouseId' => 'd.WAREHOUSES_ID',
            'processId' => 'd.PROCESS_ID',
            'productId' => 'd.PRODUCT_ID',
            'category' => 'd.CATEGORY',
            'processCategory' => 'd.PROCESS_CATEGORY',
            'operator' => 'd.OPERATOR',
            'objectId' => 'd.OBJECT_ID',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['amount'])) {
            $query->where('d.AMOUNT', (float)$filters['amount']);
        }

        if (!empty($filters['productName'])) {
            $query->whereLike('p.PRODUCT_NAME', '%' . trim((string)$filters['productName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('p.PRODUCT_NAME', $keyword)
                    ->whereOr('w.NAME', 'like', $keyword)
                    ->whereOr('d.PROCESS_ID', 'like', $keyword)
                    ->whereOr('d.OBJECT_ID', 'like', $keyword);
            });
        }

        return $this->applyDeliveryTimeRange($query, $filters);
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

    private function activeProduct(string $productId, string $tenantId): array
    {
        $query = Db::name('biz_product')
            ->where('ID', $productId)
            ->where('status', self::ENABLE);
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

    private function activeInventoryForUpdate(string $warehouseId, string $productId, string $tenantId): array
    {
        $query = Db::name('inventory')
            ->where('WAREHOUSES_ID', $warehouseId)
            ->where('PRODUCT_ID', $productId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query
            ->field('ID,WAREHOUSES_ID,PRODUCT_ID,CURRENT_COUNT,DELETE_FLAG,TENANT_ID,VERSION')
            ->lock(true)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('inventory not found', 404);
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

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function applyDeliveryTimeRange($query, array $filters)
    {
        [$start, $end] = $this->deliveryTimeRange($filters);
        if ($start !== '' && $end !== '') {
            return $query->whereBetweenTime('d.DELIVERY_TIME', $start, $end);
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('d.ID', 'asc');
        }

        return $query->order('d.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function deliveryRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->deliveryRow($row), $rows);
    }

    private function deliveryRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'warehousesId' => $this->value($row, 'WAREHOUSES_ID', 'warehousesId'),
            'warehousesName' => $this->value($row, 'WAREHOUSES_NAME', 'warehousesName'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
            'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'processCategory' => $this->value($row, 'PROCESS_CATEGORY', 'processCategory'),
            'operator' => $this->value($row, 'OPERATOR', 'operator'),
            'operatorName' => $this->value($row, 'OPERATOR_NAME', 'operatorName'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'deliveryTime' => $this->value($row, 'DELIVERY_TIME', 'deliveryTime'),
            'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'productOrg' => $this->value($row, 'PRODUCT_ORG', 'productOrg'),
            'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
            'safetyStock' => $this->decimal($this->value($row, 'SAFETY_STOCK', 'safetyStock')),
            'specs' => $this->value($row, 'SPECS', 'specs'),
            'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
            'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
            'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function deliveryTimeRange(array $filters): array
    {
        $start = trim((string)($filters['deliveryStartTime'] ?? $filters['startDeliveryTime'] ?? ''));
        $end = trim((string)($filters['deliveryEndTime'] ?? $filters['endDeliveryTime'] ?? ''));
        $completionTime = $filters['completionTime'] ?? null;

        if (($start === '' || $end === '') && is_array($completionTime)) {
            $start = trim((string)($completionTime[0] ?? $start));
            $end = trim((string)($completionTime[1] ?? $end));
        }

        return [$start, $end];
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
            $parentId = (string)($row['PARENT_ID'] ?? '');
            $childrenByParent[$parentId][] = (string)$row['ID'];
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function requiredDecimal(array $input, string $key): string
    {
        if (!array_key_exists($key, $input) || $input[$key] === '') {
            throw new RuntimeException("missing {$key}", 400);
        }
        if (!is_numeric($input[$key])) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        $value = $this->decimalString($input[$key]);
        if ((float)$value < 0) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return $value;
    }

    private function requiredTime(array $input, string $key): string
    {
        $value = $this->requiredInput($input, $key);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function decimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.000000';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid decimal', 400);
        }

        return number_format((float)$value, 6, '.', '');
    }

    private function decimalStorage(string|float $value): string
    {
        return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.') ?: '0';
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

    private function tenantId(array $payload, array $warehouse): string
    {
        $warehouseTenantId = trim((string)($warehouse['TENANT_ID'] ?? ''));
        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($warehouseTenantId !== '' && $payloadTenantId !== '' && $warehouseTenantId !== $payloadTenantId) {
            throw new RuntimeException('tenant mismatch', 403);
        }

        $tenantId = $warehouseTenantId !== '' ? $warehouseTenantId : $payloadTenantId;

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
