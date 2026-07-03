<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Product query and base-maintenance compatibility for Java BizProductController.
 */
class ProductService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const ENABLE = 'ENABLE';
    private const DISABLE = 'DISABLE';
    private const KIT_PRODUCT = 'KIT_PRODUCT';
    private const SINGLE_PRODUCT = 'SINGLE_PRODUCT';
    private const KIT_PRODUCT_DATA = 'KIT_PRODUCT_DATA';
    private const SORT_FIELD_MAP = [
        'id' => 'p.ID',
        'productName' => 'p.PRODUCT_NAME',
        'productCategory' => 'p.PRODUCT_CATEGORY',
        'safetyStock' => 'p.SAFETY_STOCK',
        'purchasePrice' => 'p.PURCHASE_PRICE',
        'salePrice' => 'p.SALE_PRICE',
        'minPrice' => 'p.MIN_PRICE',
        'category' => 'p.CATEGORY',
        'specs' => 'p.SPECS',
        'org' => 'p.ORG',
        'orgId' => 'p.ORG',
        'createTime' => 'p.CREATE_TIME',
        'createUserName' => 'creator.NAME',
        'updateTime' => 'p.UPDATE_TIME',
        'tenantId' => 'p.TENANT_ID',
        'reconciliationType' => 'p.RECONCILIATION_TYPE',
        'reconciliationAmount' => 'p.RECONCILIATION_AMOUNT',
        'status' => 'p.status',
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

    public function add(array $input, array $payload = []): array
    {
        $category = $this->requiredInput($input, 'category');
        $this->assertProductCategory($category);
        $this->validateRequiredProductInput($input);

        return Db::transaction(function () use ($input, $payload, $category): array {
            $userId = $this->currentUserId($payload);
            $tenantId = $this->tenantId($input, $payload);
            $id = $this->newId();
            $row = [
                'ID' => $id,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => date('Y-m-d H:i:s'),
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'ORG' => $this->defaultOrgId($payload),
                'status' => self::ENABLE,
            ];

            $this->applyProductInput($row, $input, true);
            $this->assertNewProductWritable($row, $payload);

            Db::name('biz_product')->insert($row);

            if ($category === self::KIT_PRODUCT) {
                $this->syncKitRelations($id, $input['productList'] ?? [], $tenantId);
            }

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $product = $this->assertProductWritable($id, $payload, 'edit');
            $row = $this->auditFields($payload);
            $this->applyProductInput($row, $input, false);

            $updated = Db::name('biz_product')
                ->where('ID', $id)
                ->update($row);

            if ((string)($product['CATEGORY'] ?? '') === self::KIT_PRODUCT && array_key_exists('productList', $input)) {
                $tenantId = trim((string)($product['TENANT_ID'] ?? ''));
                $this->syncKitRelations($id, $input['productList'], $tenantId !== '' ? $tenantId : '1');
            }

            return ['id' => $id, 'count' => $updated];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->normalizeIdList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            foreach ($idList as $id) {
                $this->assertProductWritable($id, $payload, 'delete');
            }

            $referencingKitNames = $this->referencingKitNames($idList);
            if ($referencingKitNames !== []) {
                throw new RuntimeException('product referenced by kit product: ' . implode(',', $referencingKitNames), 409);
            }

            $updated = Db::name('biz_product')
                ->whereIn('ID', $idList)
                ->update(array_merge(
                    ['DELETE_FLAG' => self::DELETED],
                    $this->auditFields($payload)
                ));

            return ['ids' => $idList, 'count' => $updated];
        });
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

    private function validateRequiredProductInput(array $input): void
    {
        foreach (['productName', 'productCategory'] as $key) {
            $this->requiredInput($input, $key);
        }

        foreach (['safetyStock', 'purchasePrice', 'salePrice', 'minPrice'] as $key) {
            $this->requiredDecimalInput($input, $key);
        }
    }

    private function applyProductInput(array &$row, array $input, bool $allowCategory): void
    {
        foreach ([
            'productName' => 'PRODUCT_NAME',
            'productCategory' => 'PRODUCT_CATEGORY',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $row[$column] = $this->requiredInput($input, $inputKey);
            }
        }

        foreach ([
            'safetyStock' => 'SAFETY_STOCK',
            'purchasePrice' => 'PURCHASE_PRICE',
            'salePrice' => 'SALE_PRICE',
            'minPrice' => 'MIN_PRICE',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $row[$column] = $this->requiredDecimalInput($input, $inputKey);
            }
        }

        foreach ([
            'specs' => 'SPECS',
            'coverImage' => 'COVER_IMAGE',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $row[$column] = $this->nullableString($input[$inputKey]);
            }
        }

        if ($allowCategory && array_key_exists('category', $input)) {
            $category = $this->requiredInput($input, 'category');
            $this->assertProductCategory($category);
            $row['CATEGORY'] = $category;
        }
    }

    private function assertNewProductWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $productOrg = trim((string)($row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($productOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to add this product', 403);
    }

    /**
     * @param mixed $productList
     * @return array<int, array{id: string, number: int}>
     */
    private function kitProductItems(mixed $productList): array
    {
        if (is_string($productList)) {
            $decoded = json_decode($productList, true);
            $productList = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($productList)) {
            return [];
        }

        $items = [];
        $seen = [];
        foreach ($productList as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string)($item['id'] ?? $item['ID'] ?? $item['productId'] ?? ''));
            $number = (int)($item['number'] ?? 0);
            if ($id === '' || $number < 1) {
                throw new RuntimeException('invalid kit product item', 400);
            }

            if (isset($seen[$id])) {
                throw new RuntimeException('duplicate kit product item', 400);
            }

            $seen[$id] = true;
            $items[] = ['id' => $id, 'number' => $number];
        }

        return $items;
    }

    private function syncKitRelations(string $productId, mixed $productList, string $tenantId): void
    {
        $items = $this->kitProductItems($productList);
        if ($items === []) {
            throw new RuntimeException('kit product items required', 400);
        }

        $targetIds = array_column($items, 'id');
        if (in_array($productId, $targetIds, true)) {
            throw new RuntimeException('kit product cannot contain itself', 400);
        }

        $rows = Db::name('biz_product')
            ->whereIn('ID', $targetIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();
        if (count($rows) !== count($targetIds)) {
            throw new RuntimeException('kit product item verification failed', 400);
        }

        $productsById = [];
        foreach ($this->productRows($rows) as $product) {
            $productsById[(string)$product['id']] = $product;
        }

        Db::name('product_relation')
            ->where('OBJECT_ID', $productId)
            ->where('CATEGORY', self::KIT_PRODUCT_DATA)
            ->delete();

        $relationRows = [];
        foreach ($items as $item) {
            $childProduct = $productsById[$item['id']] ?? null;
            if ($childProduct === null) {
                throw new RuntimeException('kit product item verification failed', 400);
            }

            $relationRows[] = [
                'ID' => $this->newId(),
                'OBJECT_ID' => $productId,
                'TARGET_ID' => $item['id'],
                'CATEGORY' => self::KIT_PRODUCT_DATA,
                'EXT_JSON' => json_encode([
                    'number' => $item['number'],
                    'product' => $childProduct,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'TENANT_ID' => $tenantId !== '' ? $tenantId : '1',
            ];
        }

        if ($relationRows !== []) {
            Db::name('product_relation')->insertAll($relationRows);
        }
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    private function referencingKitNames(array $ids): array
    {
        $relations = Db::name('product_relation')
            ->whereIn('TARGET_ID', $ids)
            ->where('CATEGORY', self::KIT_PRODUCT_DATA)
            ->select()
            ->toArray();
        if ($relations === []) {
            return [];
        }

        $objectIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['OBJECT_ID'] ?? '')),
            $relations
        ))));
        if ($objectIds === []) {
            return ['unknown kit product'];
        }

        $names = Db::name('biz_product')
            ->whereIn('ID', $objectIds)
            ->column('PRODUCT_NAME');

        return array_values(array_filter(array_map(
            static fn (mixed $name): string => trim((string)$name),
            $names
        )));
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

    private function requiredDecimalInput(array $input, string $key): string
    {
        if (!array_key_exists($key, $input)) {
            throw new RuntimeException("missing {$key}", 400);
        }

        $value = $this->nullableDecimal($input[$key], $key);
        if ($value === null) {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = implode(',', array_map(static fn (mixed $item): string => trim((string)$item), $value));
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function assertProductCategory(string $category): void
    {
        if (!in_array($category, [self::SINGLE_PRODUCT, self::KIT_PRODUCT], true)) {
            throw new RuntimeException('unsupported product category', 400);
        }
    }

    private function tenantId(array $input, array $payload): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function defaultOrgId(array $payload): ?string
    {
        $orgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($orgId !== '') {
            return $orgId;
        }

        $userId = $this->currentUserId($payload);
        if ($userId === '') {
            return null;
        }

        $row = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ORG_ID')
            ->find();
        if (!is_array($row) || $row === []) {
            return null;
        }

        $orgId = trim((string)($row['ORG_ID'] ?? ''));

        return $orgId !== '' ? $orgId : null;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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
            ->alias('p')
            ->field('p.*, creator.NAME AS CREATE_USER_NAME, updater.NAME AS UPDATE_USER_NAME')
            ->leftJoin('sys_user creator', 'creator.ID = p.CREATE_USER')
            ->leftJoin('sys_user updater', 'updater.ID = p.UPDATE_USER')
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('p.ID', (string)$filters['id']);
        }

        if (!empty($filters['productName'])) {
            $query->whereLike('p.PRODUCT_NAME', '%' . trim((string)$filters['productName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('p.PRODUCT_NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['productCategory'])) {
            $query->where('p.PRODUCT_CATEGORY', (string)$filters['productCategory']);
        }

        if (!empty($filters['category'])) {
            $query->where('p.CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['startCreateTime']) && !empty($filters['endCreateTime'])) {
            $query->whereBetweenTime('p.CREATE_TIME', (string)$filters['startCreateTime'], (string)$filters['endCreateTime']);
        }

        $ignoreIds = $this->normalizeIdList($filters['ignoreIdList'] ?? []);
        if ($ignoreIds !== []) {
            $query->whereNotIn('p.ID', $ignoreIds);
        }

        if (!empty($filters['reconciliationAmount'])) {
            $query->whereLike('p.RECONCILIATION_AMOUNT', '%' . trim((string)$filters['reconciliationAmount']) . '%');
        }

        if (!empty($filters['reconciliationType'])) {
            if ((string)$filters['reconciliationType'] === self::ENABLE) {
                $query->where('p.RECONCILIATION_TYPE', self::ENABLE);
            } else {
                $query->where(function ($query): void {
                    $query->whereNull('p.RECONCILIATION_TYPE')
                        ->whereOr('p.RECONCILIATION_TYPE', '<>', self::ENABLE);
                });
            }
        }

        if ($hideDisabledByDefault && !$this->truthy($filters['showDisabledProducts'] ?? false)) {
            $query->where('p.status', self::ENABLE);
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('p.ORG', $orgIds);
            }
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('p.ORG', $scopeOrgIds);
        }

        return $query;
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
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'updateUserName' => $this->value($row, 'UPDATE_USER_NAME', 'updateUserName'),
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
