<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only sale-project follow-up queries compatible with Java SaleProjectFollowUpController.
 */
class SaleProjectFollowUpService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';

    private const FOLLOW_UP_FIELDS = <<<SQL
f.ID AS ID,
f.PROJECT_ID AS PROJECT_ID,
f.FOLLOW_UP_TIME AS FOLLOW_UP_TIME,
f.CATEGORY AS CATEGORY,
f.CONTENT AS CONTENT,
f.DELETE_FLAG AS DELETE_FLAG,
f.CREATE_TIME AS CREATE_TIME,
f.CREATE_USER AS CREATE_USER,
f.UPDATE_TIME AS UPDATE_TIME,
f.UPDATE_USER AS UPDATE_USER,
f.TENANT_ID AS TENANT_ID,
f.EXT_JSON AS EXT_JSON,
p.PROJECT_NAME AS PROJECT_NAME,
p.USER AS PROJECT_USER,
p.ORG AS PROJECT_ORG,
creator.NAME AS CREATE_USER_NAME,
creator.AVATAR AS AVATAR,
creator.ORG_ID AS CREATE_USER_ORG_ID,
creatorOrg.NAME AS CREATE_USER_ORG_NAME
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'f.ID',
        'projectId' => 'f.PROJECT_ID',
        'projectName' => 'p.PROJECT_NAME',
        'followUpTime' => 'f.FOLLOW_UP_TIME',
        'category' => 'f.CATEGORY',
        'content' => 'f.CONTENT',
        'createTime' => 'f.CREATE_TIME',
        'createUserName' => 'creator.NAME',
        'tenantId' => 'f.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->followUpQuery($filters, $payload, true)->count('DISTINCT f.ID');
        $rows = $this->applySort($this->followUpQuery($filters, $payload, true), $filters)
            ->field(self::FOLLOW_UP_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->followUpRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->followUpQuery(['id' => $id], $payload, true)
            ->field(self::FOLLOW_UP_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project follow-up not found', 404);
        }

        return $this->followUpRows([$row])[0];
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function listByProjectIds(array $projectIds, array $payload = []): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $query = $this->baseQuery($payload)
            ->whereIn('f.PROJECT_ID', $ids)
            ->field(self::FOLLOW_UP_FIELDS)
            ->order('f.FOLLOW_UP_TIME', 'asc')
            ->order('f.ID', 'asc');

        $result = [];
        foreach ($this->followUpRows($query->select()->toArray()) as $row) {
            $result[(string)$row['projectId']][] = $row;
        }

        return $result;
    }

    public function add(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInput($input, 'projectId');
        $followUpTime = $this->requiredInput($input, 'followUpTime');
        $category = $this->requiredInput($input, 'category');
        $content = $this->requiredInput($input, 'content');

        return Db::transaction(function () use ($projectId, $followUpTime, $category, $content, $input, $payload): array {
            $project = $this->assertProjectWritable($projectId, $payload, 'add');
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $id = $this->newId();
            $row = [
                'ID' => $id,
                'PROJECT_ID' => $projectId,
                'FOLLOW_UP_TIME' => $followUpTime,
                'CATEGORY' => $category,
                'CONTENT' => $content,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload, $project),
                'EXT_JSON' => $this->extJsonForAdd($input),
            ];

            Db::name('sale_project_follow_up')->insert($row);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $projectId = $this->requiredInput($input, 'projectId');
        $followUpTime = $this->requiredInput($input, 'followUpTime');
        $category = $this->requiredInput($input, 'category');
        $content = $this->requiredInput($input, 'content');

        return Db::transaction(function () use ($id, $projectId, $followUpTime, $category, $content, $payload): array {
            $followUp = $this->activeFollowUp($id);
            $this->assertProjectWritable((string)$followUp['PROJECT_ID'], $payload, 'edit');
            if ((string)$followUp['PROJECT_ID'] !== $projectId) {
                $this->assertProjectWritable($projectId, $payload, 'edit');
            }

            Db::name('sale_project_follow_up')
                ->where('ID', $id)
                ->update([
                    'PROJECT_ID' => $projectId,
                    'FOLLOW_UP_TIME' => $followUpTime,
                    'CATEGORY' => $category,
                    'CONTENT' => $content,
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
            $query = Db::name('sale_project_follow_up')->whereIn('ID', $idList);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $rows = $query->select()->toArray();
            if (count($rows) !== count($idList)) {
                throw new RuntimeException('sale project follow-up not found', 404);
            }

            foreach ($rows as $row) {
                $this->assertProjectWritable((string)$row['PROJECT_ID'], $payload, 'delete');
            }

            $updated = Db::name('sale_project_follow_up')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function followUpQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = $this->baseQuery($payload);

        if (!empty($filters['id'])) {
            $query->where('f.ID', (string)$filters['id']);
        }

        if (!empty($filters['projectId'])) {
            $query->where('f.PROJECT_ID', (string)$filters['projectId']);
        }

        if (!empty($filters['category'])) {
            $query->where('f.CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['content'])) {
            $query->whereLike('f.CONTENT', '%' . trim((string)$filters['content']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw('(f.CONTENT LIKE ? OR p.PROJECT_NAME LIKE ?)', [$keyword, $keyword]);
        }

        $this->applyTimeRange($query, 'f.FOLLOW_UP_TIME', $filters['startFollowUpTime'] ?? '', $filters['endFollowUpTime'] ?? '');

        if ($applyDataScope) {
            $this->applyDataScope($query, $filters, $payload);
        }

        return $query;
    }

    private function baseQuery(array $payload)
    {
        $query = Db::name('sale_project_follow_up')
            ->alias('f')
            ->join('biz_sale_project p', 'p.ID = f.PROJECT_ID', 'INNER')
            ->leftJoin('sys_user creator', 'creator.ID = f.CREATE_USER')
            ->leftJoin('sys_org creatorOrg', 'creatorOrg.ID = creator.ORG_ID');
        $this->whereNotDeleted($query, 'f.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('f.TENANT_ID', $tenantId);
        }

        return $query;
    }

    private function applyDataScope($query, array $filters, array $payload): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('p.ORG', $orgIds);

            return;
        }

        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('p.ORG', $scopeOrgIds);

            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where('p.USER', $userId);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('f.ID', 'asc');
        }

        return $query->order('f.ID', 'asc');
    }

    private function applyTimeRange($query, string $column, mixed $startValue, mixed $endValue): void
    {
        $start = trim((string)$startValue);
        $end = trim((string)$endValue);
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        } elseif ($start !== '') {
            $query->whereTime($column, '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime($column, '<=', $end);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function followUpRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'id' => $this->value($row, 'ID', 'id'),
            'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
            'projectName' => $this->value($row, 'PROJECT_NAME', 'projectName'),
            'projectUser' => $this->value($row, 'PROJECT_USER', 'projectUser'),
            'projectOrg' => $this->value($row, 'PROJECT_ORG', 'projectOrg'),
            'followUpTime' => $this->value($row, 'FOLLOW_UP_TIME', 'followUpTime'),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'content' => $this->value($row, 'CONTENT', 'content'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'createUserOrgId' => $this->value($row, 'CREATE_USER_ORG_ID', 'createUserOrgId'),
            'createUserOrgName' => $this->value($row, 'CREATE_USER_ORG_NAME', 'createUserOrgName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
        ], $rows);
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function activeFollowUp(string $id): array
    {
        $query = Db::name('sale_project_follow_up')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project follow-up not found', 404);
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
                throw new RuntimeException("no permission to {$action} this sale project follow-up", 403);
            }

            return $project;
        }

        $projectUser = trim((string)($project['USER'] ?? ''));
        $userId = $this->currentUserId($payload);
        if ($userId === '' || $projectUser !== $userId) {
            throw new RuntimeException("no permission to {$action} this sale project follow-up", 403);
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

    private function extJsonForAdd(array $input): ?string
    {
        if (!empty($input['fileList']) && is_array($input['fileList'])) {
            return json_encode(['fileList' => $input['fileList']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (array_key_exists('extJson', $input)) {
            if (is_array($input['extJson'])) {
                return json_encode($input['extJson'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $input['extJson'] !== null ? (string)$input['extJson'] : null;
        }

        return null;
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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
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
