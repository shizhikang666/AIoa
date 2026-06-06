<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Sale-project product item mark compatibility for Java BizSaleProjectProductItemController.
 */
class SaleProjectProductItemService
{
    private const NOT_DELETE = 'NOT_DELETE';

    public function editMark(array $input, array $payload = []): array
    {
        $id = $this->requiredString($input, 'id');
        $mark = $this->nullableMark($input['mark'] ?? null, 50);
        $row = $this->activeItemForWrite($id, $payload);

        $updated = Db::name('biz_sale_project_product_item')
            ->where('ID', $id)
            ->update([
                'MARK' => $mark,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->currentUserId($payload) ?: null,
            ]);

        return [
            'id' => $id,
            'projectId' => $row['PROJECT_ID'] ?? null,
            'productId' => $row['PRODUCT_ID'] ?? null,
            'mark' => $mark,
            'count' => $updated,
        ];
    }

    private function activeItemForWrite(string $id, array $payload): array
    {
        $query = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->join('biz_sale_project project', 'project.ID = i.PROJECT_ID', 'INNER')
            ->where('i.ID', $id)
            ->field('i.ID, i.PROJECT_ID, i.PRODUCT_ID, i.MARK, i.TENANT_ID, project.USER AS PROJECT_USER, project.ORG AS PROJECT_ORG');
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'project.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $this->applyDataScope($query, $payload);

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project product item not found', 404);
        }

        return $row;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
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
}
