<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

class SalesProjectFieldChangeLogService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';

    private const FIELDS = <<<SQL
l.ID AS ID,
l.OBJECT_ID AS OBJECT_ID,
l.FIELD_NAME AS FIELD_NAME,
l.FIELD_LABEL AS FIELD_LABEL,
l.BEFORE_VALUE AS BEFORE_VALUE,
l.AFTER_VALUE AS AFTER_VALUE,
l.CHANGE_REASON AS CHANGE_REASON,
l.DELETE_FLAG AS DELETE_FLAG,
l.CREATE_TIME AS CREATE_TIME,
l.CREATE_USER AS CREATE_USER,
l.UPDATE_TIME AS UPDATE_TIME,
l.UPDATE_USER AS UPDATE_USER,
l.TENANT_ID AS TENANT_ID,
p.PROJECT_NAME AS PROJECT_NAME,
u.NAME AS CREATE_USER_NAME
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'l.ID',
        'objectId' => 'l.OBJECT_ID',
        'fieldName' => 'l.FIELD_NAME',
        'fieldLabel' => 'l.FIELD_LABEL',
        'createTime' => 'l.CREATE_TIME',
        'updateTime' => 'l.UPDATE_TIME',
        'tenantId' => 'l.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->query($filters, $payload)->count('DISTINCT l.ID');
        $rows = $this->applySort($this->query($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResponse($this->rows($rows), $total, $page, $limit);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->query(['id' => $id], $payload)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sales project field change log not found', 404);
        }

        return $this->row($row);
    }

    public function add(array $input, array $payload = []): array
    {
        $objectId = $this->requiredInput($input, 'objectId');
        $fieldName = $this->requiredInput($input, 'fieldName');
        $fieldLabel = $this->requiredInput($input, 'fieldLabel');
        $beforeValue = $this->requiredInput($input, 'beforeValue');
        $afterValue = $this->requiredInput($input, 'afterValue');
        $changeReason = $this->requiredInput($input, 'changeReason');

        return Db::transaction(function () use ($objectId, $fieldName, $fieldLabel, $beforeValue, $afterValue, $changeReason, $input, $payload): array {
            $project = $this->assertProjectWritable($objectId, $payload, 'add');
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $id = $this->newId();

            Db::name('sales_project_field_change_log')->insert([
                'ID' => $id,
                'OBJECT_ID' => $objectId,
                'FIELD_NAME' => $fieldName,
                'FIELD_LABEL' => $fieldLabel,
                'BEFORE_VALUE' => $beforeValue,
                'AFTER_VALUE' => $afterValue,
                'CHANGE_REASON' => $changeReason,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload, $project),
            ]);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $objectId = $this->requiredInput($input, 'objectId');
        $fieldName = $this->requiredInput($input, 'fieldName');
        $fieldLabel = $this->requiredInput($input, 'fieldLabel');
        $beforeValue = $this->requiredInput($input, 'beforeValue');
        $afterValue = $this->requiredInput($input, 'afterValue');
        $changeReason = $this->requiredInput($input, 'changeReason');

        return Db::transaction(function () use ($id, $objectId, $fieldName, $fieldLabel, $beforeValue, $afterValue, $changeReason, $payload): array {
            $log = $this->activeLog($id);
            $this->assertProjectWritable((string)$log['OBJECT_ID'], $payload, 'edit');
            if ((string)$log['OBJECT_ID'] !== $objectId) {
                $this->assertProjectWritable($objectId, $payload, 'edit');
            }

            Db::name('sales_project_field_change_log')
                ->where('ID', $id)
                ->update([
                    'OBJECT_ID' => $objectId,
                    'FIELD_NAME' => $fieldName,
                    'FIELD_LABEL' => $fieldLabel,
                    'BEFORE_VALUE' => $beforeValue,
                    'AFTER_VALUE' => $afterValue,
                    'CHANGE_REASON' => $changeReason,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                ]);

            return ['id' => $id];
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
            $query = Db::name('sales_project_field_change_log')->whereIn('ID', $idList);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $rows = $query->select()->toArray();
            if (count($rows) !== count($idList)) {
                throw new RuntimeException('sales project field change log not found', 404);
            }

            foreach ($rows as $row) {
                $this->assertProjectWritable((string)$row['OBJECT_ID'], $payload, 'delete');
            }

            $updated = Db::name('sales_project_field_change_log')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function query(array $filters, array $payload)
    {
        $query = Db::name('sales_project_field_change_log')
            ->alias('l')
            ->leftJoin('biz_sale_project p', 'p.ID = l.OBJECT_ID COLLATE utf8mb4_general_ci')
            ->leftJoin('sys_user u', 'u.ID = l.CREATE_USER');
        $this->whereNotDeleted($query, 'l.DELETE_FLAG');
        $this->applyTenant($query, 'l', $filters, $payload);

        foreach ([
            'id' => 'l.ID',
            'objectId' => 'l.OBJECT_ID',
            'fieldName' => 'l.FIELD_NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'fieldLabel' => 'l.FIELD_LABEL',
            'changeReason' => 'l.CHANGE_REASON',
            'projectName' => 'p.PROJECT_NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->row($row), $rows);
    }

    private function row(array $row): array
    {
        return array_merge($this->normalizeRow($row), [
            'id' => $row['ID'] ?? null,
            'objectId' => $row['OBJECT_ID'] ?? null,
            'fieldName' => $row['FIELD_NAME'] ?? null,
            'fieldLabel' => $row['FIELD_LABEL'] ?? null,
            'beforeValue' => $row['BEFORE_VALUE'] ?? null,
            'afterValue' => $row['AFTER_VALUE'] ?? null,
            'changeReason' => $row['CHANGE_REASON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'createUserName' => $row['CREATE_USER_NAME'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'projectName' => $row['PROJECT_NAME'] ?? null,
        ]);
    }

    private function pageResponse(array $records, int $total, int $page, int $limit): array
    {
        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function applyTenant($query, string $alias, array $filters, array $payload): void
    {
        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where("{$alias}.TENANT_ID", $tenantId);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('l.ID', 'asc');
        }

        return $query->order('l.ID', 'asc');
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function activeLog(string $id): array
    {
        $query = Db::name('sales_project_field_change_log')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sales project field change log not found', 404);
        }

        return $row;
    }

    private function assertProjectWritable(string $projectId, array $payload, string $action): array
    {
        $query = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $project = $query->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        if ($this->canSeeAll($payload)) {
            return $project;
        }

        $projectOrg = trim((string)($project['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            if (!in_array($projectOrg, $scopeOrgIds, true)) {
                throw new RuntimeException("no permission to {$action} this sales project field change log", 403);
            }

            return $project;
        }

        $projectUser = trim((string)($project['USER'] ?? ''));
        $userId = $this->currentUserId($payload);
        if ($userId === '' || $projectUser !== $userId) {
            throw new RuntimeException("no permission to {$action} this sales project field change log", 403);
        }

        return $project;
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function tenantId(array $input, array $payload, array $project): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? $project['TENANT_ID'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
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
}
