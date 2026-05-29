<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only team-project queries compatible with Java BizTeamProjectController.
 */
class TeamProjectService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const PROJECT_FIELDS = <<<SQL
p.ID AS ID,
p.NAME AS NAME,
p.DESCRIPTION AS DESCRIPTION,
p.PROJECT_STATUS AS PROJECT_STATUS,
p.COMPLETION_TIME AS COMPLETION_TIME,
p.`USER` AS USER_ID,
p.ORG AS ORG,
p.VERSION AS VERSION,
p.DELETE_FLAG AS DELETE_FLAG,
p.CREATE_TIME AS CREATE_TIME,
p.CREATE_USER AS CREATE_USER,
p.UPDATE_TIME AS UPDATE_TIME,
p.UPDATE_USER AS UPDATE_USER,
p.TENANT_ID AS TENANT_ID,
owner.NAME AS HEAD_NAME,
owner.AVATAR AS AVATAR,
creator.NAME AS CREATE_USER_NAME,
org.NAME AS ORG_NAME,
member.ID AS CURRENT_MEMBER_ID,
member.ROLE_TYPE AS CURRENT_ROLE_TYPE
SQL;
    private const MEMBER_FIELDS = <<<SQL
m.ID AS ID,
m.TEAM_PROJECT_ID AS TEAM_PROJECT_ID,
m.USER_ID AS USER_ID,
m.ROLE_TYPE AS ROLE_TYPE,
m.DELETE_FLAG AS DELETE_FLAG,
m.CREATE_TIME AS CREATE_TIME,
m.CREATE_USER AS CREATE_USER,
m.UPDATE_TIME AS UPDATE_TIME,
m.UPDATE_USER AS UPDATE_USER,
m.TENANT_ID AS TENANT_ID,
u.NAME AS HEAD_NAME,
u.AVATAR AS AVATAR,
p.NAME AS PROJECT_NAME
SQL;
    private const PROJECT_SORT_MAP = [
        'id' => 'p.ID',
        'name' => 'p.NAME',
        'projectStatus' => 'p.PROJECT_STATUS',
        'completionTime' => 'p.COMPLETION_TIME',
        'user' => 'p.`USER`',
        'org' => 'p.ORG',
        'version' => 'p.VERSION',
        'createTime' => 'p.CREATE_TIME',
        'updateTime' => 'p.UPDATE_TIME',
        'tenantId' => 'p.TENANT_ID',
        'headName' => 'owner.NAME',
        'orgName' => 'org.NAME',
    ];
    private const MEMBER_SORT_MAP = [
        'id' => 'm.ID',
        'teamProjectId' => 'm.TEAM_PROJECT_ID',
        'userId' => 'm.USER_ID',
        'roleType' => 'm.ROLE_TYPE',
        'createTime' => 'm.CREATE_TIME',
        'updateTime' => 'm.UPDATE_TIME',
        'tenantId' => 'm.TENANT_ID',
        'headName' => 'u.NAME',
        'projectName' => 'p.NAME',
    ];
    private const ROLE_META = [
        'LEADER' => [
            'roleName' => 'LEADER',
            'permissionCode' => ['delComment', 'delProject', 'addUser', 'addManage', 'addComment', 'delComment'],
        ],
        'MANAGE' => [
            'roleName' => 'MANAGE',
            'permissionCode' => ['delComment', 'addUser', 'addComment', 'delComment'],
        ],
        'MEMBER' => [
            'roleName' => 'MEMBER',
            'permissionCode' => ['addComment'],
        ],
    ];

    public function projectPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->projectQuery($filters, $payload)->count();
        $rows = $this->applySort($this->projectQuery($filters, $payload), $filters, self::PROJECT_SORT_MAP, 'p.ID')
            ->field(self::PROJECT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->projectRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function projectDetail(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field(self::PROJECT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project not found', 404);
        }

        $project = $this->projectRow($row);

        return [
            'project' => $project,
            'user' => $this->currentMemberForProject($id, $payload),
        ];
    }

    public function memberPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->memberQuery($filters, $payload)->count();
        $rows = $this->applySort($this->memberQuery($filters, $payload), $filters, self::MEMBER_SORT_MAP, 'm.ID')
            ->field(self::MEMBER_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->memberRows($rows),
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
    public function memberList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->memberQuery($filters, $payload), $filters, self::MEMBER_SORT_MAP, 'm.ID')
            ->field(self::MEMBER_FIELDS)
            ->select()
            ->toArray();

        return $this->memberRows($rows);
    }

    public function memberDetail(string $id, array $payload = []): array
    {
        $row = $this->memberQuery(['memberId' => $id], $payload)
            ->field(self::MEMBER_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project user not found', 404);
        }

        return $this->memberRow($row);
    }

    private function projectQuery(array $filters, array $payload)
    {
        $currentUserId = $this->currentUserId($payload);
        $query = Db::name('biz_team_project')
            ->alias('p')
            ->leftJoin('biz_team_project_user member', 'member.TEAM_PROJECT_ID = p.ID')
            ->leftJoin('sys_user owner', 'owner.ID = p.`USER`')
            ->leftJoin('sys_user creator', 'creator.ID = p.CREATE_USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->where('member.USER_ID', $currentUserId)
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('member.DELETE_FLAG')->whereOr('member.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'p.ID',
            'projectStatus' => 'p.PROJECT_STATUS',
            'user' => 'p.`USER`',
            'userId' => 'p.`USER`',
            'org' => 'p.ORG',
            'orgId' => 'p.ORG',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['name'])) {
            $query->whereLike('p.NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('p.NAME', $keyword)
                    ->whereOr('p.DESCRIPTION', 'like', $keyword)
                    ->whereOr('owner.NAME', 'like', $keyword)
                    ->whereOr('creator.NAME', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'p.COMPLETION_TIME', 'startCompletionTime', 'endCompletionTime');
        $this->applyTimeRange($query, $filters, 'p.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function memberQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_user')
            ->alias('m')
            ->leftJoin('sys_user u', 'u.ID = m.USER_ID')
            ->leftJoin('biz_team_project p', 'p.ID = m.TEAM_PROJECT_ID')
            ->where(function ($query): void {
                $query->whereNull('m.DELETE_FLAG')->whereOr('m.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('m.TENANT_ID', $tenantId);
        }

        foreach ([
            'memberId' => 'm.ID',
            'teamProjectId' => 'm.TEAM_PROJECT_ID',
            'projectId' => 'm.TEAM_PROJECT_ID',
            'userId' => 'm.USER_ID',
            'roleType' => 'm.ROLE_TYPE',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['id']) && empty($filters['teamProjectId']) && empty($filters['memberId'])) {
            $query->where('m.TEAM_PROJECT_ID', (string)$filters['id']);
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('m.ROLE_TYPE', 'like', $keyword)
                    ->whereOr('p.NAME', 'like', $keyword);
            });
        }

        return $query;
    }

    private function currentMemberForProject(string $projectId, array $payload): array
    {
        return $this->memberList([
            'teamProjectId' => $projectId,
            'userId' => $this->currentUserId($payload),
        ], $payload)[0] ?? throw new RuntimeException('team project user not found', 404);
    }

    private function currentUserId(array $payload): string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('unauthenticated', 401);
        }

        return $userId;
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    private function applySort($query, array $filters, array $map, string $defaultColumn)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset($map[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order($map[$sortField], $direction)->order($defaultColumn, 'asc');
        }

        return $query->order($defaultColumn, 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function projectRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->projectRow($row), $rows);
    }

    private function projectRow(array $row): array
    {
        $ownerId = $this->value($row, 'USER_ID', 'userId');
        $headName = $this->value($row, 'HEAD_NAME', 'headName');
        $createUserName = $this->value($row, 'CREATE_USER_NAME', 'createUserName');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'name' => $this->value($row, 'NAME', 'name'),
            'description' => $this->value($row, 'DESCRIPTION', 'description'),
            'projectStatus' => $this->value($row, 'PROJECT_STATUS', 'projectStatus'),
            'completionTime' => $this->value($row, 'COMPLETION_TIME', 'completionTime'),
            'user' => $ownerId,
            'userId' => $ownerId,
            'headName' => $headName,
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'version' => (int)$this->value($row, 'VERSION', 'version'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $createUserName ?: $headName,
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'currentMemberId' => $this->value($row, 'CURRENT_MEMBER_ID', 'currentMemberId'),
            'currentRoleType' => $this->value($row, 'CURRENT_ROLE_TYPE', 'currentRoleType'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function memberRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->memberRow($row), $rows);
    }

    private function memberRow(array $row): array
    {
        $roleType = (string)$this->value($row, 'ROLE_TYPE', 'roleType');
        $roleMeta = self::ROLE_META[$roleType] ?? [
            'roleName' => $roleType,
            'permissionCode' => [],
        ];

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'teamProjectId' => $this->value($row, 'TEAM_PROJECT_ID', 'teamProjectId'),
            'projectName' => $this->value($row, 'PROJECT_NAME', 'projectName'),
            'userId' => $this->value($row, 'USER_ID', 'userId'),
            'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'roleType' => $roleType,
            'roleName' => $roleMeta['roleName'],
            'permissionCode' => $roleMeta['permissionCode'],
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
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
