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
    private const DELETED = 'DELETED';
    private const TEAM_PROJECT_PERMISSION_CATEGORY = 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION';
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

    public function projectAdd(array $input, array $payload = []): array
    {
        $name = $this->requiredInput($input, 'name');
        $description = array_key_exists('description', $input) ? (string)$input['description'] : null;

        return Db::transaction(function () use ($name, $description, $payload): array {
            $projectId = $this->newId();
            $memberId = $this->newId();
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $tenantId = $this->tenantIdFromProject($payload, []);

            Db::name('biz_team_project')->insert([
                'ID' => $projectId,
                'NAME' => $name,
                'DESCRIPTION' => $description,
                'PROJECT_STATUS' => null,
                'COMPLETION_TIME' => null,
                'USER' => $currentUserId,
                'ORG' => $this->currentOrgId($payload, $currentUserId),
                'VERSION' => 0,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ]);

            Db::name('biz_team_project_user')->insert([
                'ID' => $memberId,
                'TEAM_PROJECT_ID' => $projectId,
                'USER_ID' => $currentUserId,
                'ROLE_TYPE' => 'LEADER',
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ]);
            $this->syncMemberRelation($projectId, $currentUserId, 'LEADER', $tenantId);

            return ['id' => $projectId, 'memberId' => $memberId];
        });
    }

    public function projectEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $this->assertProjectPermission($id, $payload, 'delProject', 'edit team project');

            $updates = [];
            if (array_key_exists('name', $input)) {
                $name = trim((string)$input['name']);
                if ($name === '') {
                    throw new RuntimeException('missing name', 400);
                }
                $updates['NAME'] = $name;
            }
            if (array_key_exists('description', $input)) {
                $updates['DESCRIPTION'] = (string)$input['description'];
            }
            if (array_key_exists('projectStatus', $input)) {
                $updates['PROJECT_STATUS'] = trim((string)$input['projectStatus']) !== '' ? trim((string)$input['projectStatus']) : null;
            }
            if (array_key_exists('completionTime', $input)) {
                $completionTime = trim((string)$input['completionTime']);
                $updates['COMPLETION_TIME'] = $completionTime !== '' ? $completionTime : null;
            }

            if ($updates === []) {
                return ['id' => $id, 'count' => 0];
            }

            $updates['UPDATE_TIME'] = date('Y-m-d H:i:s');
            $updates['UPDATE_USER'] = $this->currentUserId($payload);
            $updates['VERSION'] = Db::raw('VERSION + 1');

            $query = Db::name('biz_team_project')->where('ID', $id);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $affected = $query->update($updates);

            return ['id' => $id, 'count' => $affected];
        });
    }

    public function projectDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $affected = 0;

            foreach ($ids as $id) {
                $this->assertProjectPermission($id, $payload, 'delProject', 'delete team project');

                $query = Db::name('biz_team_project')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                    'VERSION' => Db::raw('VERSION + 1'),
                ]);

                $memberQuery = Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', $id);
                $this->whereNotDeleted($memberQuery, 'DELETE_FLAG');
                $memberQuery->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
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

    public function memberAdd(array $input, array $payload = [], string $roleType = 'MEMBER'): array
    {
        $teamProjectId = $this->requiredInput($input, 'teamProjectId');
        $roleType = $this->normalizeMemberRole($roleType);
        $userIds = $this->userIdList($input);
        if ($userIds === []) {
            throw new RuntimeException('missing user', 400);
        }

        return Db::transaction(function () use ($teamProjectId, $roleType, $userIds, $payload): array {
            $requiredPermission = $roleType === 'MANAGE' ? 'addManage' : 'addUser';
            $project = $this->assertProjectPermission($teamProjectId, $payload, $requiredPermission, 'add team project users');
            $this->assertUsersExist($userIds);
            $this->assertNoActiveProjectMembers($teamProjectId, $userIds);

            $tenantId = $this->tenantIdFromProject($payload, $project);
            $currentUserId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $deletedRows = $this->deletedMemberRowsByUser($teamProjectId, $userIds);
            $ids = [];

            foreach ($userIds as $userId) {
                $deletedRow = $deletedRows[$userId] ?? null;
                if (is_array($deletedRow)) {
                    $id = (string)$deletedRow['ID'];
                    Db::name('biz_team_project_user')->where('ID', $id)->update([
                        'ROLE_TYPE' => $roleType,
                        'DELETE_FLAG' => self::NOT_DELETE,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $currentUserId,
                        'TENANT_ID' => $tenantId,
                    ]);
                } else {
                    $id = $this->newId();
                    Db::name('biz_team_project_user')->insert([
                        'ID' => $id,
                        'TEAM_PROJECT_ID' => $teamProjectId,
                        'USER_ID' => $userId,
                        'ROLE_TYPE' => $roleType,
                        'DELETE_FLAG' => self::NOT_DELETE,
                        'CREATE_TIME' => $now,
                        'CREATE_USER' => $currentUserId,
                        'UPDATE_TIME' => null,
                        'UPDATE_USER' => null,
                        'TENANT_ID' => $tenantId,
                    ]);
                }

                $this->syncMemberRelation($teamProjectId, $userId, $roleType, $tenantId);
                $ids[] = $id;
            }

            return [
                'teamProjectId' => $teamProjectId,
                'roleType' => $roleType,
                'ids' => $ids,
                'count' => count($ids),
            ];
        });
    }

    public function memberDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $rows = $this->activeMemberRowsByIds($ids, $payload);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('team project user not found', 404);
            }

            $currentUserId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $affected = 0;

            foreach ($rows as $row) {
                $targetUserId = (string)$row['USER_ID'];
                $teamProjectId = (string)$row['TEAM_PROJECT_ID'];
                $targetRoleType = strtoupper((string)$row['ROLE_TYPE']);
                if ($targetUserId === $currentUserId) {
                    throw new RuntimeException('cannot remove yourself from team project', 400);
                }
                if ($targetRoleType === 'LEADER') {
                    throw new RuntimeException('cannot remove team project leader', 400);
                }

                $requiredPermission = $targetRoleType === 'MANAGE' ? 'addManage' : 'addUser';
                $this->assertProjectPermission($teamProjectId, $payload, $requiredPermission, 'delete team project users');

                $query = Db::name('biz_team_project_user')->where('ID', (string)$row['ID']);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
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

    private function assertProjectPermission(string $teamProjectId, array $payload, string $code, string $action): array
    {
        $project = $this->activeProjectForWrite($teamProjectId, $payload);
        $member = $this->activeCurrentMemberForWrite($teamProjectId, $payload, $action);
        $permissions = $this->memberPermissionCodes($teamProjectId, $this->currentUserId($payload), (string)$member['ROLE_TYPE']);
        if (!in_array($code, $permissions, true)) {
            throw new RuntimeException("no permission to {$action} on this team project", 403);
        }

        return $project;
    }

    private function activeProjectForWrite(string $teamProjectId, array $payload): array
    {
        $query = Db::name('biz_team_project')->where('ID', $teamProjectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project not found', 404);
        }

        return $row;
    }

    private function activeCurrentMemberForWrite(string $teamProjectId, array $payload, string $action): array
    {
        $query = Db::name('biz_team_project_user')
            ->where('TEAM_PROJECT_ID', $teamProjectId)
            ->where('USER_ID', $this->currentUserId($payload));
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException("no permission to {$action} on this team project", 403);
        }

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function memberPermissionCodes(string $teamProjectId, string $userId, string $roleType): array
    {
        $relation = Db::name('biz_relation')
            ->where('OBJECT_ID', $teamProjectId)
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::TEAM_PROJECT_PERMISSION_CATEGORY)
            ->find();

        if (is_array($relation) && !empty($relation['EXT_JSON'])) {
            $decoded = json_decode((string)$relation['EXT_JSON'], true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_map(static fn (mixed $item): string => (string)$item, $decoded)));
            }
        }

        return self::ROLE_META[$roleType]['permissionCode'] ?? [];
    }

    /**
     * @param array<int, string> $userIds
     */
    private function assertUsersExist(array $userIds): void
    {
        $query = Db::name('sys_user')->whereIn('ID', $userIds);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ((int)$query->count() !== count($userIds)) {
            throw new RuntimeException('selected user not found', 400);
        }
    }

    /**
     * @param array<int, string> $userIds
     */
    private function assertNoActiveProjectMembers(string $teamProjectId, array $userIds): void
    {
        $query = Db::name('biz_team_project_user')
            ->where('TEAM_PROJECT_ID', $teamProjectId)
            ->whereIn('USER_ID', $userIds);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ((int)$query->count() > 0) {
            throw new RuntimeException('selected user is already in this team project', 400);
        }
    }

    /**
     * @param array<int, string> $userIds
     * @return array<string, array<string, mixed>>
     */
    private function deletedMemberRowsByUser(string $teamProjectId, array $userIds): array
    {
        $rows = Db::name('biz_team_project_user')
            ->where('TEAM_PROJECT_ID', $teamProjectId)
            ->whereIn('USER_ID', $userIds)
            ->where('DELETE_FLAG', self::DELETED)
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $userId = (string)($row['USER_ID'] ?? '');
            if ($userId !== '' && !isset($map[$userId])) {
                $map[$userId] = $row;
            }
        }

        return $map;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeMemberRowsByIds(array $ids, array $payload): array
    {
        $query = Db::name('biz_team_project_user')->whereIn('ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query->select()->toArray();
    }

    private function syncMemberRelation(string $teamProjectId, string $userId, string $roleType, string $tenantId): void
    {
        $permissions = self::ROLE_META[$roleType]['permissionCode'] ?? [];
        $extJson = json_encode(array_values(array_unique($permissions)), JSON_UNESCAPED_UNICODE);
        $relation = Db::name('biz_relation')
            ->where('OBJECT_ID', $teamProjectId)
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::TEAM_PROJECT_PERMISSION_CATEGORY)
            ->find();

        if (is_array($relation) && !empty($relation['ID'])) {
            Db::name('biz_relation')->where('ID', (string)$relation['ID'])->update([
                'EXT_JSON' => $extJson,
                'TENANT_ID' => $tenantId,
            ]);

            return;
        }

        Db::name('biz_relation')->insert([
            'ID' => $this->newId(),
            'OBJECT_ID' => $teamProjectId,
            'TARGET_ID' => $userId,
            'CATEGORY' => self::TEAM_PROJECT_PERMISSION_CATEGORY,
            'EXT_JSON' => $extJson,
            'TENANT_ID' => $tenantId,
        ]);
    }

    private function currentUserId(array $payload): string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('unauthenticated', 401);
        }

        return $userId;
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
    private function idList(array $input): array
    {
        $raw = [];
        if (array_is_list($input)) {
            $raw = $input;
        } elseif (isset($input['idList']) && is_array($input['idList'])) {
            $raw = $input['idList'];
        } elseif (isset($input['ids']) && is_array($input['ids'])) {
            $raw = $input['ids'];
        } elseif (isset($input['ids']) && is_string($input['ids'])) {
            $raw = explode(',', $input['ids']);
        } elseif (isset($input['id'])) {
            $raw = [$input['id']];
        }

        $ids = [];
        foreach ($raw as $item) {
            $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
            $id = trim($id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    private function userIdList(array $input): array
    {
        if (!array_key_exists('user', $input) && !array_key_exists('users', $input) && !array_key_exists('userIds', $input)) {
            throw new RuntimeException('missing user', 400);
        }

        $raw = $input['user'] ?? $input['users'] ?? $input['userIds'] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $id = (string)($item['id'] ?? $item['userId'] ?? $item['value'] ?? '');
            } else {
                $id = (string)$item;
            }

            $id = trim($id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeMemberRole(string $roleType): string
    {
        $roleType = strtoupper(trim($roleType));
        if (!in_array($roleType, ['MEMBER', 'MANAGE'], true)) {
            throw new RuntimeException('invalid team project member role', 400);
        }

        return $roleType;
    }

    private function tenantIdFromProject(array $payload, array $project): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? $project['TENANT_ID'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function currentOrgId(array $payload, string $userId): ?string
    {
        $orgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($orgId !== '') {
            return $orgId;
        }

        $orgId = trim((string)Db::name('sys_user')->where('ID', $userId)->value('ORG_ID'));

        return $orgId !== '' ? $orgId : null;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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
