<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only warehouse queries compatible with Java WarehousesController.
 */
class WarehousesService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'name' => 'NAME',
        'code' => 'CODE',
        'address' => 'ADDRESS',
        'sortCode' => 'SORT_CODE',
        'user' => 'USER',
        'org' => 'ORG',
        'orgId' => 'ORG',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
        'tenantId' => 'TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->warehouseQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->warehouseQuery($filters, $payload, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->warehouseRows($rows),
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
        $rows = $this->applySort($this->warehouseQuery($filters, $payload, false), $filters)
            ->select()
            ->toArray();

        return $this->warehouseRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->warehouseQuery(['id' => $id], $payload, false)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('warehouse not found', 404);
        }

        return $this->warehouseRows([$row])[0];
    }

    private function warehouseQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = Db::name('warehouses')
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

        foreach ([
            'name' => 'NAME',
            'code' => 'CODE',
            'address' => 'ADDRESS',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('NAME', $keyword)
                    ->whereOr('CODE', 'like', $keyword)
                    ->whereOr('ADDRESS', 'like', $keyword);
            });
        }

        if (!empty($filters['user'])) {
            $query->where('USER', (string)$filters['user']);
        }

        if (!empty($filters['userId'])) {
            $query->where('USER', (string)$filters['userId']);
        }

        if (!empty($filters['orgId'])) {
            $query->whereIn('ORG', $this->orgAndChildren((string)$filters['orgId']));
        }

        if (!empty($filters['org'])) {
            $query->where('ORG', (string)$filters['org']);
        }

        $scopeOrgIds = $applyDataScope ? $this->scopeOrgIds($payload) : [];
        if ($scopeOrgIds !== []) {
            $ownerUserIds = $this->ownerUserIdsByOrg($scopeOrgIds);
            $query->where(function ($query) use ($scopeOrgIds, $ownerUserIds): void {
                $query->whereIn('ORG', $scopeOrgIds);
                if ($ownerUserIds !== []) {
                    $query->whereOr('USER', 'in', $ownerUserIds);
                }
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function warehouseRows(array $rows): array
    {
        $ownerNames = $this->ownerNames($rows);
        $orgNames = $this->orgNames($rows);

        return array_map(fn (array $row): array => $this->warehouseRow($row, $ownerNames, $orgNames), $rows);
    }

    /**
     * @param array<string, string> $ownerNames
     * @param array<string, string> $orgNames
     */
    private function warehouseRow(array $row, array $ownerNames = [], array $orgNames = []): array
    {
        $userId = $this->value($row, 'USER', 'user');
        $orgId = $this->value($row, 'ORG', 'org');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'name' => $this->value($row, 'NAME', 'name'),
            'code' => $this->value($row, 'CODE', 'code'),
            'address' => $this->value($row, 'ADDRESS', 'address'),
            'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
            'user' => $userId,
            'headName' => $userId !== null ? ($ownerNames[(string)$userId] ?? $this->value($row, 'headName')) : $this->value($row, 'headName'),
            'org' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? $this->value($row, 'orgName')) : $this->value($row, 'orgName'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function ownerNames(array $rows): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['USER'] ?? $row['user'] ?? ''),
            $rows
        ))));
        if ($userIds === []) {
            return [];
        }

        return Db::name('sys_user')->whereIn('ID', $userIds)->column('NAME', 'ID');
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

    /**
     * @return array<int, string>
     */
    private function ownerUserIdsByOrg(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            Db::name('sys_user')->whereIn('ORG_ID', $orgIds)->column('ID')
        ))));
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
        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $scope): string {
            if (!is_array($scope)) {
                return '';
            }

            return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
        }, $scopes))));
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
