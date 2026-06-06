<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only product queries compatible with Java BizProductController.
 */
class ProductService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ENABLE = 'ENABLE';
    private const DISABLE = 'DISABLE';
    private const KIT_PRODUCT_DATA = 'KIT_PRODUCT_DATA';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'productName' => 'PRODUCT_NAME',
        'productCategory' => 'PRODUCT_CATEGORY',
        'safetyStock' => 'SAFETY_STOCK',
        'purchasePrice' => 'PURCHASE_PRICE',
        'salePrice' => 'SALE_PRICE',
        'minPrice' => 'MIN_PRICE',
        'category' => 'CATEGORY',
        'specs' => 'SPECS',
        'org' => 'ORG',
        'orgId' => 'ORG',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
        'tenantId' => 'TENANT_ID',
        'reconciliationType' => 'RECONCILIATION_TYPE',
        'reconciliationAmount' => 'RECONCILIATION_AMOUNT',
        'status' => 'status',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->productQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->productQuery($filters, $payload, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->productRows($rows),
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
        $rows = $this->applySort($this->productQuery($filters, $payload, false), $filters)
            ->select()
            ->toArray();

        return $this->productRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->productQuery(['id' => $id], $payload, false)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('product not found', 404);
        }

        return [
            'bizProduct' => $this->productRows([$row])[0],
            'productList' => $this->kitProductsForObject($id),
        ];
    }

    /**
     * @param array<int|string, mixed> $input
     * @return array<int, array<string, mixed>>
     */
    public function children(array $input): array
    {
        $objectIds = $this->normalizeIdList($input);
        if ($objectIds === []) {
            return [];
        }

        $relations = Db::name('product_relation')
            ->whereIn('OBJECT_ID', $objectIds)
            ->where('CATEGORY', self::KIT_PRODUCT_DATA)
            ->order('ID', 'asc')
            ->select()
            ->toArray();
        $fallbackProducts = $this->fallbackProductsForRelations($relations);

        return array_values(array_map(function (array $relation) use ($fallbackProducts): array {
            $product = $this->productFromExtJson((string)($relation['EXT_JSON'] ?? ''));
            if ($product === null) {
                $product = $fallbackProducts[(string)($relation['TARGET_ID'] ?? '')] ?? null;
            }

            return [
                'number' => $this->numberFromExtJson((string)($relation['EXT_JSON'] ?? '')),
                'product' => $product,
                'objectId' => $relation['OBJECT_ID'] ?? null,
            ];
        }, $relations));
    }

    public function editStatus(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $status = $this->requiredInput($input, 'status');
        $this->assertStatus($status);

        return Db::transaction(function () use ($id, $status, $payload): array {
            $this->assertProductWritable($id, $payload, 'edit status');
            $updated = Db::name('biz_product')
                ->where('ID', $id)
                ->update(array_merge(
                    ['status' => $status],
                    $this->auditFields($payload)
                ));

            return ['id' => $id, 'count' => $updated];
        });
    }

    public function editReconciliation(array $input, array $payload = []): array
    {
        $ids = $this->normalizeIdList($input['ids'] ?? $input['idList'] ?? $input['id'] ?? []);
        if ($ids === []) {
            throw new RuntimeException('missing ids', 400);
        }

        $reconciliationType = $this->requiredInput($input, 'reconciliationType');
        $this->assertStatus($reconciliationType);
        $reconciliationAmount = $this->nullableDecimal($input['reconciliationAmount'] ?? null, 'reconciliationAmount');

        return Db::transaction(function () use ($ids, $reconciliationType, $reconciliationAmount, $payload): array {
            foreach ($ids as $id) {
                $this->assertProductWritable($id, $payload, 'edit reconciliation');
            }

            $updated = Db::name('biz_product')
                ->whereIn('ID', $ids)
                ->update(array_merge(
                    [
                        'RECONCILIATION_TYPE' => $reconciliationType,
                        'RECONCILIATION_AMOUNT' => $reconciliationAmount,
                    ],
                    $this->auditFields($payload)
                ));

            return ['ids' => $ids, 'count' => $updated];
        });
    }

    private function assertProductWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeProduct($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $productOrg = trim((string)($row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($productOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} this product", 403);
    }

    private function activeProduct(string $id, array $payload): array
    {
        $query = Db::name('biz_product')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
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

    private function auditFields(array $payload): array
    {
        $userId = $this->currentUserId($payload);

        return [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $userId !== '' ? $userId : null,
        ];
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, [self::ENABLE, self::DISABLE], true)) {
            throw new RuntimeException('unsupported product status', 400);
        }
    }

    private function nullableDecimal(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        if ((float)$value < 0) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return (string)$value;
    }

    private function productQuery(array $filters, array $payload, bool $hideDisabledByDefault)
    {
        $query = Db::name('biz_product')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['productName'])) {
            $query->whereLike('PRODUCT_NAME', '%' . trim((string)$filters['productName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('PRODUCT_NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['productCategory'])) {
            $query->where('PRODUCT_CATEGORY', (string)$filters['productCategory']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['startCreateTime']) && !empty($filters['endCreateTime'])) {
            $query->whereBetweenTime('CREATE_TIME', (string)$filters['startCreateTime'], (string)$filters['endCreateTime']);
        }

        $ignoreIds = $this->normalizeIdList($filters['ignoreIdList'] ?? []);
        if ($ignoreIds !== []) {
            $query->whereNotIn('ID', $ignoreIds);
        }

        if (!empty($filters['reconciliationAmount'])) {
            $query->whereLike('RECONCILIATION_AMOUNT', '%' . trim((string)$filters['reconciliationAmount']) . '%');
        }

        if (!empty($filters['reconciliationType'])) {
            if ((string)$filters['reconciliationType'] === self::ENABLE) {
                $query->where('RECONCILIATION_TYPE', self::ENABLE);
            } else {
                $query->where(function ($query): void {
                    $query->whereNull('RECONCILIATION_TYPE')
                        ->whereOr('RECONCILIATION_TYPE', '<>', self::ENABLE);
                });
            }
        }

        if ($hideDisabledByDefault && !$this->truthy($filters['showDisabledProducts'] ?? false)) {
            $query->where('status', self::ENABLE);
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('ORG', $orgIds);
            }
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('ORG', $scopeOrgIds);
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order('ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function productRows(array $rows): array
    {
        $orgNames = $this->orgNames($rows);

        return array_map(fn (array $row): array => $this->productRow($row, $orgNames), $rows);
    }

    /**
     * @param array<string, string> $orgNames
     */
    private function productRow(array $row, array $orgNames = []): array
    {
        $orgId = $this->value($row, 'ORG', 'org');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
            'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
            'safetyStock' => $this->decimal($this->value($row, 'SAFETY_STOCK', 'safetyStock')),
            'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
            'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
            'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'specs' => $this->value($row, 'SPECS', 'specs'),
            'org' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? $this->value($row, 'orgName')) : $this->value($row, 'orgName'),
            'coverImage' => $this->value($row, 'COVER_IMAGE', 'coverImage'),
            'reconciliationType' => $this->value($row, 'RECONCILIATION_TYPE', 'reconciliationType'),
            'reconciliationAmount' => $this->decimal($this->value($row, 'RECONCILIATION_AMOUNT', 'reconciliationAmount')),
            'status' => $this->value($row, 'status', 'STATUS'),
        ];
    }

    /**
     * @return array<int, array{number: int|null, product: array<string, mixed>|null}>
     */
    private function kitProductsForObject(string $objectId): array
    {
        $relations = Db::name('product_relation')
            ->where('OBJECT_ID', $objectId)
            ->where('CATEGORY', self::KIT_PRODUCT_DATA)
            ->order('ID', 'asc')
            ->select()
            ->toArray();
        $fallbackProducts = $this->fallbackProductsForRelations($relations);

        return array_values(array_map(function (array $relation) use ($fallbackProducts): array {
            $product = $this->productFromExtJson((string)($relation['EXT_JSON'] ?? ''));
            if ($product === null) {
                $product = $fallbackProducts[(string)($relation['TARGET_ID'] ?? '')] ?? null;
            }

            return [
                'number' => $this->numberFromExtJson((string)($relation['EXT_JSON'] ?? '')),
                'product' => $product,
            ];
        }, $relations));
    }

    /**
     * @param array<int, array<string, mixed>> $relations
     * @return array<string, array<string, mixed>>
     */
    private function fallbackProductsForRelations(array $relations): array
    {
        $targetIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['TARGET_ID'] ?? ''),
            $relations
        ))));
        if ($targetIds === []) {
            return [];
        }

        $rows = Db::name('biz_product')
            ->whereIn('ID', $targetIds)
            ->select()
            ->toArray();

        $products = [];
        foreach ($this->productRows($rows) as $product) {
            $products[(string)$product['id']] = $product;
        }

        return $products;
    }

    private function productFromExtJson(string $extJson): ?array
    {
        if (trim($extJson) === '') {
            return null;
        }

        $decoded = json_decode($extJson, true);
        if (!is_array($decoded) || !isset($decoded['product']) || !is_array($decoded['product'])) {
            return null;
        }

        return $this->productRow($decoded['product']);
    }

    private function numberFromExtJson(string $extJson): ?int
    {
        if (trim($extJson) === '') {
            return null;
        }

        $decoded = json_decode($extJson, true);
        if (!is_array($decoded) || !array_key_exists('number', $decoded)) {
            return null;
        }

        return (int)$decoded['number'];
    }

    /**
     * @param mixed $value
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

        if (isset($value['idList'])) {
            return $this->normalizeIdList($value['idList']);
        }

        if (isset($value['ids'])) {
            return $this->normalizeIdList($value['ids']);
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            if (is_array($item)) {
                return trim((string)($item['id'] ?? $item['ID'] ?? ''));
            }

            return trim((string)$item);
        }, $value))));
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function orgNames(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['ORG'] ?? $row['org'] ?? ''),
            $rows
        ))));
        if ($orgIds === []) {
            return [];
        }

        return Db::name('sys_org')->whereIn('ID', $orgIds)->column('NAME', 'ID');
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
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
