<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Sale-project product item relation compatibility for Java SaleProjectProductItemRelationController.
 */
class SaleProjectProductItemRelationService
{
    private const NOT_DELETE = 'NOT_DELETE';

    private const RELATION_FIELDS = <<<SQL
r.ID AS ID,
r.OBJECT_ID AS OBJECT_ID,
r.TARGET_ID AS TARGET_ID,
r.MARK AS MARK,
r.NUMBER AS NUMBER,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.EXT_JSON AS EXT_JSON,
r.TENANT_ID AS TENANT_ID,
r.REMARK AS REMARK,
i.PROJECT_ID AS PROJECT_ID,
project.PROJECT_NAME AS PROJECT_NAME,
project.USER AS PROJECT_USER,
project.ORG AS PROJECT_ORG,
product.PRODUCT_NAME AS PRODUCT_NAME,
product.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
product.CATEGORY AS PRODUCT_SYS_CATEGORY,
product.SPECS AS SPECS,
product.PURCHASE_PRICE AS PURCHASE_PRICE,
product.SALE_PRICE AS SALE_PRICE,
product.MIN_PRICE AS MIN_PRICE
SQL;

    /**
     * @param array<int, string> $objectIds
     * @return array<int, array<string, mixed>>
     */
    public function listByObjectIds(array $objectIds, array $payload = []): array
    {
        $ids = $this->stringList($objectIds);
        if ($ids === []) {
            return [];
        }

        $query = $this->relationQuery($ids, $payload)
            ->field(self::RELATION_FIELDS)
            ->order('r.ID', 'asc');

        return $this->relationRows($query->select()->toArray());
    }

    /**
     * Return the minimum product identity required by a project workflow form.
     *
     * This path is intentionally separate from listByObjectIds(): workflow
     * participants may read the exact process form without receiving purchase,
     * minimum or sale prices from the full project-product relation endpoint.
     *
     * @param array<int, string> $objectIds
     * @return array<int, array<string, mixed>>
     */
    public function listForWorkflowProject(
        array $objectIds,
        string $projectId,
        array $payload = []
    ): array {
        $ids = array_values(array_unique($this->stringList($objectIds)));
        $projectId = trim($projectId);
        if ($ids === []) {
            return [];
        }
        if ($projectId === '') {
            throw new RuntimeException('permission denied', 403);
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId === '' && !$this->isPlatformSuperAdmin($payload)) {
            throw new RuntimeException('permission denied', 403);
        }

        $itemQuery = Db::name('biz_sale_project_product_item')
            ->whereIn('ID', $ids)
            ->where('PROJECT_ID', $projectId);
        $this->whereNotDeleted($itemQuery, 'DELETE_FLAG');
        if ($tenantId !== '') {
            $itemQuery->where('TENANT_ID', $tenantId);
        }
        if ((int)$itemQuery->count() !== count($ids)) {
            throw new RuntimeException('permission denied', 403);
        }

        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->join('biz_sale_project_product_item i', 'i.ID = r.OBJECT_ID', 'INNER')
            ->join('biz_sale_project project', 'project.ID = i.PROJECT_ID', 'INNER')
            ->leftJoin('biz_product product', 'product.ID = r.TARGET_ID AND product.TENANT_ID = r.TENANT_ID')
            ->whereIn('r.OBJECT_ID', $ids)
            ->where('i.PROJECT_ID', $projectId)
            ->field(
                'r.ID,r.OBJECT_ID,r.TARGET_ID,r.NUMBER,r.REMARK,'
                . 'product.PRODUCT_NAME,product.PRODUCT_CATEGORY,'
                . 'product.CATEGORY AS PRODUCT_SYS_CATEGORY,product.SPECS'
            )
            ->order('r.ID', 'asc');
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'project.DELETE_FLAG');

        if ($tenantId !== '') {
            $query
                ->where('r.TENANT_ID', $tenantId)
                ->where('i.TENANT_ID', $tenantId)
                ->where('project.TENANT_ID', $tenantId);
        }

        return array_map(function (array $row): array {
            $product = [
                'id' => $row['TARGET_ID'] ?? null,
                'productName' => $row['PRODUCT_NAME'] ?? null,
                'productCategory' => $row['PRODUCT_CATEGORY'] ?? null,
                'category' => $row['PRODUCT_SYS_CATEGORY'] ?? null,
                'specs' => $row['SPECS'] ?? null,
            ];

            return [
                'id' => $row['ID'] ?? null,
                'objectId' => $row['OBJECT_ID'] ?? null,
                'targetId' => $row['TARGET_ID'] ?? null,
                'productId' => $row['TARGET_ID'] ?? null,
                'number' => $this->decimal($row['NUMBER'] ?? null),
                'remark' => $row['REMARK'] ?? null,
                'productName' => $product['productName'],
                'productCategory' => $product['productCategory'],
                'productSysCategory' => $product['category'],
                'specs' => $product['specs'],
                'extJson' => json_encode(['product' => $product], JSON_UNESCAPED_UNICODE),
            ];
        }, $query->select()->toArray());
    }

    public function editMark(array $input, array $payload = []): array
    {
        $id = $this->requiredString($input, 'id');
        $mark = $this->nullableMark($input['mark'] ?? null, 255);
        $row = $this->activeRelationForWrite($id, $payload);

        $updated = Db::name('sale_project_product_item_relation')
            ->where('ID', $id)
            ->update([
                'MARK' => $mark,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->currentUserId($payload) ?: null,
            ]);

        return [
            'id' => $id,
            'objectId' => $row['OBJECT_ID'] ?? null,
            'targetId' => $row['TARGET_ID'] ?? null,
            'mark' => $mark,
            'count' => $updated,
        ];
    }

    /**
     * @param array<int, string> $objectIds
     */
    private function relationQuery(array $objectIds, array $payload)
    {
        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->join('biz_sale_project_product_item i', 'i.ID = r.OBJECT_ID', 'INNER')
            ->join('biz_sale_project project', 'project.ID = i.PROJECT_ID', 'INNER')
            ->leftJoin('biz_product product', 'product.ID = r.TARGET_ID')
            ->whereIn('r.OBJECT_ID', $objectIds);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'project.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $this->applyDataScope($query, $payload);

        return $query;
    }

    private function applyDataScope($query, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('project.ORG', $scopeOrgIds);

            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where('project.USER', $userId);
        }
    }

    private function activeRelationForWrite(string $id, array $payload): array
    {
        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->join('biz_sale_project_product_item i', 'i.ID = r.OBJECT_ID', 'INNER')
            ->join('biz_sale_project project', 'project.ID = i.PROJECT_ID', 'INNER')
            ->where('r.ID', $id)
            ->field('r.ID, r.OBJECT_ID, r.TARGET_ID, r.MARK, r.TENANT_ID, project.ID AS PROJECT_ID, project.USER AS PROJECT_USER, project.ORG AS PROJECT_ORG');
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'project.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $this->applyDataScope($query, $payload);

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project product item relation not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function relationRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $relation = $this->normalizeRow($row);
            $relation['productId'] = $relation['targetId'] ?? null;
            foreach (['number', 'purchasePrice', 'salePrice', 'minPrice'] as $decimalField) {
                $relation[$decimalField] = $this->decimal($relation[$decimalField] ?? null);
            }

            if (empty($relation['extJson'])) {
                $relation['extJson'] = json_encode([
                    'product' => [
                        'id' => $relation['targetId'] ?? null,
                        'productName' => $relation['productName'] ?? null,
                        'productCategory' => $relation['productCategory'] ?? null,
                        'category' => $relation['productSysCategory'] ?? null,
                        'specs' => $relation['specs'] ?? null,
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }

            return $relation;
        }, $rows);
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
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

    private function isPlatformSuperAdmin(array $payload): bool
    {
        if (strtolower(trim((string)($payload['account'] ?? ''))) === 'superadmin') {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? [];
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (strtolower(trim((string)$roleCode)) === 'superadmin') {
                return true;
            }
        }

        return false;
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function requiredString(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function nullableMark(mixed $value, int $maxLength): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string)$value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $maxLength) {
            throw new RuntimeException('mark too long', 400);
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $value)));
    }

    private function normalizeRow(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $result[$this->camelKey((string)$key)] = $value;
        }

        return $result;
    }

    private function camelKey(string $key): string
    {
        $key = strtolower($key);

        return preg_replace_callback('/_([a-z0-9])/', static fn (array $matches): string => strtoupper($matches[1]), $key) ?? $key;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }
}
