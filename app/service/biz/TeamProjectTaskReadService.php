<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Team-project task/category/comment queries compatible with the Java controllers.
 */
class TeamProjectTaskReadService
{
    private const NOT_DELETE = 'NOT_DELETE';

    private const DELETED = 'DELETED';

    private const TEAM_PROJECT_PERMISSION_CATEGORY = 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION';

    private const CATEGORY_FIELDS = <<<SQL
c.ID AS ID,
c.TEAM_PROJECT_ID AS TEAM_PROJECT_ID,
c.TITLE AS TITLE,
c.EXT_JSON AS EXT_JSON,
c.SORT_CODE AS SORT_CODE,
c.DELETE_FLAG AS DELETE_FLAG,
c.CREATE_TIME AS CREATE_TIME,
c.CREATE_USER AS CREATE_USER,
c.UPDATE_TIME AS UPDATE_TIME,
c.UPDATE_USER AS UPDATE_USER,
c.TENANT_ID AS TENANT_ID
SQL;

    private const TASK_FIELDS = <<<SQL
t.ID AS ID,
t.TEAM_PROJECT_ID AS TEAM_PROJECT_ID,
p.NAME AS TEAM_PROJECT_NAME,
t.TEAM_PROJECT_TASK_CATEGORY_ID AS TEAM_PROJECT_TASK_CATEGORY_ID,
c.TITLE AS CATEGORY_TITLE,
t.STATUS AS STATUS,
t.TITLE AS TITLE,
t.PROGRESS AS PROGRESS,
t.CONTENT_TEXT AS CONTENT_TEXT,
t.DELETE_FLAG AS DELETE_FLAG,
t.SORT_CODE AS SORT_CODE,
t.EXT_JSON AS EXT_JSON,
t.CREATE_TIME AS CREATE_TIME,
t.CREATE_USER AS CREATE_USER,
creator.NAME AS CREATE_USER_NAME,
creator.AVATAR AS CREATE_USER_AVATAR,
t.UPDATE_TIME AS UPDATE_TIME,
t.UPDATE_USER AS UPDATE_USER,
t.TENANT_ID AS TENANT_ID,
t.VERSION AS VERSION
SQL;

    private const TASK_USER_FIELDS = <<<SQL
tu.ID AS ID,
tu.USER_ID AS USER_ID,
tu.TEAM_PROJECT_ID AS TEAM_PROJECT_ID,
tu.TEAM_PROJECT_TASK_ID AS TEAM_PROJECT_TASK_ID,
tu.ROLE_TYPE AS ROLE_TYPE,
tu.DELETE_FLAG AS DELETE_FLAG,
tu.EXT_JSON AS EXT_JSON,
tu.CREATE_TIME AS CREATE_TIME,
tu.CREATE_USER AS CREATE_USER,
tu.UPDATE_TIME AS UPDATE_TIME,
tu.UPDATE_USER AS UPDATE_USER,
tu.TENANT_ID AS TENANT_ID,
u.NAME AS HEAD_NAME,
u.AVATAR AS AVATAR
SQL;

    private const PROJECT_COMMENT_FIELDS = <<<SQL
pc.ID AS ID,
pc.TEAM_PROJECT_ID AS TEAM_PROJECT_ID,
pc.STATUS AS STATUS,
pc.STATUS_COLOR AS STATUS_COLOR,
pc.CONTENT_TEXT AS CONTENT_TEXT,
pc.DELETE_FLAG AS DELETE_FLAG,
pc.EXT_JSON AS EXT_JSON,
pc.CREATE_TIME AS CREATE_TIME,
pc.CREATE_USER AS CREATE_USER,
creator.NAME AS CREATE_USER_NAME,
creator.AVATAR AS AVATAR,
pc.UPDATE_TIME AS UPDATE_TIME,
pc.UPDATE_USER AS UPDATE_USER,
pc.TENANT_ID AS TENANT_ID
SQL;

    private const PROJECT_REPLY_FIELDS = <<<SQL
r.ID AS ID,
r.TARGET_ID AS TARGET_ID,
r.CONTENT_TEXT AS CONTENT_TEXT,
r.DELETE_FLAG AS DELETE_FLAG,
r.EXT_JSON AS EXT_JSON,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
creator.NAME AS CREATE_USER_NAME,
creator.AVATAR AS AVATAR,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.TENANT_ID AS TENANT_ID
SQL;

    private const TASK_COMMENT_FIELDS = <<<SQL
tc.ID AS ID,
tc.TEAM_PROJECT_TASK_ID AS TEAM_PROJECT_TASK_ID,
tc.TEAM_PROJECT_ID AS TEAM_PROJECT_ID,
tc.CONTENT_TEXT AS CONTENT_TEXT,
tc.CATEGORY AS CATEGORY,
tc.DELETE_FLAG AS DELETE_FLAG,
tc.EXT_JSON AS EXT_JSON,
tc.CREATE_TIME AS CREATE_TIME,
tc.CREATE_USER AS CREATE_USER,
creator.NAME AS CREATE_USER_NAME,
creator.AVATAR AS AVATAR,
tc.UPDATE_TIME AS UPDATE_TIME,
tc.UPDATE_USER AS UPDATE_USER,
tc.TENANT_ID AS TENANT_ID
SQL;

    private const CATEGORY_SORT_MAP = [
        'id' => 'c.ID',
        'teamProjectId' => 'c.TEAM_PROJECT_ID',
        'title' => 'c.TITLE',
        'sortCode' => 'c.SORT_CODE',
        'createTime' => 'c.CREATE_TIME',
        'updateTime' => 'c.UPDATE_TIME',
        'tenantId' => 'c.TENANT_ID',
    ];

    private const TASK_SORT_MAP = [
        'id' => 't.ID',
        'teamProjectId' => 't.TEAM_PROJECT_ID',
        'teamProjectTaskCategoryId' => 't.TEAM_PROJECT_TASK_CATEGORY_ID',
        'status' => 't.STATUS',
        'progress' => 't.PROGRESS',
        'sortCode' => 't.SORT_CODE',
        'contentText' => 't.CONTENT_TEXT',
        'createTime' => 't.CREATE_TIME',
        'updateTime' => 't.UPDATE_TIME',
        'tenantId' => 't.TENANT_ID',
        'teamProjectName' => 'p.NAME',
        'createUserName' => 'creator.NAME',
    ];

    private const TASK_USER_SORT_MAP = [
        'id' => 'tu.ID',
        'userId' => 'tu.USER_ID',
        'teamProjectId' => 'tu.TEAM_PROJECT_ID',
        'teamProjectTaskId' => 'tu.TEAM_PROJECT_TASK_ID',
        'roleType' => 'tu.ROLE_TYPE',
        'createTime' => 'tu.CREATE_TIME',
        'updateTime' => 'tu.UPDATE_TIME',
        'tenantId' => 'tu.TENANT_ID',
        'headName' => 'u.NAME',
    ];

    private const PROJECT_COMMENT_SORT_MAP = [
        'id' => 'pc.ID',
        'teamProjectId' => 'pc.TEAM_PROJECT_ID',
        'status' => 'pc.STATUS',
        'createTime' => 'pc.CREATE_TIME',
        'updateTime' => 'pc.UPDATE_TIME',
        'tenantId' => 'pc.TENANT_ID',
        'createUserName' => 'creator.NAME',
    ];

    private const PROJECT_REPLY_SORT_MAP = [
        'id' => 'r.ID',
        'targetId' => 'r.TARGET_ID',
        'createTime' => 'r.CREATE_TIME',
        'updateTime' => 'r.UPDATE_TIME',
        'tenantId' => 'r.TENANT_ID',
        'createUserName' => 'creator.NAME',
    ];

    private const TASK_COMMENT_SORT_MAP = [
        'id' => 'tc.ID',
        'teamProjectTaskId' => 'tc.TEAM_PROJECT_TASK_ID',
        'teamProjectId' => 'tc.TEAM_PROJECT_ID',
        'category' => 'tc.CATEGORY',
        'createTime' => 'tc.CREATE_TIME',
        'updateTime' => 'tc.UPDATE_TIME',
        'tenantId' => 'tc.TENANT_ID',
        'createUserName' => 'creator.NAME',
    ];

    public function categoryPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->categoryQuery($filters, $payload)->count();
        $rows = $this->applySort($this->categoryQuery($filters, $payload), $filters, self::CATEGORY_SORT_MAP, 'c.SORT_CODE')
            ->field(self::CATEGORY_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResult($this->categoryRows($rows), $total, $page, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categoryList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->categoryQuery($filters, $payload), $filters, self::CATEGORY_SORT_MAP, 'c.SORT_CODE')
            ->field(self::CATEGORY_FIELDS)
            ->select()
            ->toArray();

        return $this->categoryRows($rows);
    }

    public function categoryDetail(string $id, array $payload = []): array
    {
        $row = $this->categoryQuery(['id' => $id], $payload)
            ->field(self::CATEGORY_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project task category not found', 404);
        }

        return $this->categoryRow($row);
    }

    public function categoryAdd(array $input, array $payload = []): array
    {
        $teamProjectId = $this->requiredInput($input, 'teamProjectId');
        $title = $this->requiredInput($input, 'title');

        return Db::transaction(function () use ($teamProjectId, $title, $input, $payload): array {
            $project = $this->assertTeamProjectMaintainer($teamProjectId, $payload, 'add task category');
            $id = $this->newId();
            $now = date('Y-m-d H:i:s');

            Db::name('biz_team_project_task_category')->insert([
                'ID' => $id,
                'TEAM_PROJECT_ID' => $teamProjectId,
                'TITLE' => $title,
                'EXT_JSON' => array_key_exists('extJson', $input) ? (string)$input['extJson'] : null,
                'SORT_CODE' => array_key_exists('sortCode', $input) ? (int)$input['sortCode'] : 99,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $this->currentUserId($payload),
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $project),
            ]);

            return $this->categoryDetail($id, $payload);
        });
    }

    public function categoryEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $title = $this->requiredInput($input, 'title');

        return Db::transaction(function () use ($id, $title, $input, $payload): array {
            $category = $this->activeCategoryForWrite($id, $payload, 'edit task category');
            if (!empty($input['teamProjectId']) && (string)$input['teamProjectId'] !== (string)$category['TEAM_PROJECT_ID']) {
                throw new RuntimeException('teamProjectId does not match category', 400);
            }
            $this->assertTeamProjectMaintainer((string)$category['TEAM_PROJECT_ID'], $payload, 'edit task category');

            $updates = [
                'TITLE' => $title,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->currentUserId($payload),
            ];
            if (array_key_exists('extJson', $input)) {
                $updates['EXT_JSON'] = (string)$input['extJson'];
            }
            if (array_key_exists('sortCode', $input)) {
                $updates['SORT_CODE'] = (int)$input['sortCode'];
            }

            $query = Db::name('biz_team_project_task_category')->where('ID', $id);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $query->update($updates);

            return ['id' => $id];
        });
    }

    public function categorySortEdit(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $rows = $this->activeCategoryRows($ids);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('team project task category not found', 404);
            }

            $teamProjectIds = array_values(array_unique(array_map(static fn (array $row): string => (string)$row['TEAM_PROJECT_ID'], $rows)));
            if (count($teamProjectIds) !== 1) {
                throw new RuntimeException('categories must belong to the same team project', 400);
            }
            $this->assertTeamProjectMaintainer($teamProjectIds[0], $payload, 'sort task categories');

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            foreach ($ids as $index => $id) {
                $query = Db::name('biz_team_project_task_category')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $query->update([
                    'SORT_CODE' => $index,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
            }

            return ['ids' => $ids, 'count' => count($ids)];
        });
    }

    public function categoryDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $affected = 0;

            foreach ($ids as $id) {
                $category = $this->activeCategoryForWrite($id, $payload, 'delete task category');
                $this->assertTeamProjectMaintainer((string)$category['TEAM_PROJECT_ID'], $payload, 'delete task category');
                $this->assertCategoryEmpty($id);

                $query = Db::name('biz_team_project_task_category')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
    }

    public function taskPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->taskQuery($filters, $payload)->count();
        $rows = $this->applySort($this->taskQuery($filters, $payload), $filters, self::TASK_SORT_MAP, 't.ID')
            ->field(self::TASK_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResult($this->taskRows($rows), $total, $page, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function taskList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->taskQuery($filters, $payload), $filters, self::TASK_SORT_MAP, 't.ID')
            ->field(self::TASK_FIELDS)
            ->select()
            ->toArray();

        return $this->taskRows($rows);
    }

    public function taskDetail(string $id, array $payload = []): array
    {
        $row = $this->taskQuery(['id' => $id], $payload)
            ->field(self::TASK_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project task not found', 404);
        }

        $task = $this->taskRow($row);
        $task['users'] = $this->taskUsers((string)$task['id']);

        return $task;
    }

    public function taskAdd(array $input, array $payload = []): array
    {
        $teamProjectId = $this->requiredInput($input, 'teamProjectId');
        $categoryId = $this->requiredInput($input, 'teamProjectTaskCategoryId');
        $userIds = $this->optionalUserIdList($input);

        return Db::transaction(function () use ($teamProjectId, $categoryId, $userIds, $input, $payload): array {
            $project = $this->assertTeamProjectMember($teamProjectId, $payload, 'add task');
            $category = $this->activeCategoryForWrite($categoryId, $payload, 'add task');
            if ((string)$category['TEAM_PROJECT_ID'] !== $teamProjectId) {
                throw new RuntimeException('teamProjectTaskCategoryId does not belong to teamProjectId', 400);
            }
            $this->assertProjectUsers($teamProjectId, $userIds);

            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            Db::name('biz_team_project_task')->insert([
                'ID' => $id,
                'TEAM_PROJECT_ID' => $teamProjectId,
                'TEAM_PROJECT_TASK_CATEGORY_ID' => $categoryId,
                'STATUS' => 'TODO',
                'TITLE' => array_key_exists('title', $input) ? (string)$input['title'] : null,
                'PROGRESS' => 0,
                'CONTENT_TEXT' => array_key_exists('contentText', $input) ? (string)$input['contentText'] : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'SORT_CODE' => array_key_exists('sortCode', $input) ? (int)$input['sortCode'] : null,
                'EXT_JSON' => array_key_exists('extJson', $input) ? (string)$input['extJson'] : null,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $project),
                'VERSION' => 0,
            ]);

            $taskUsers = [[
                'ID' => $this->newId(),
                'USER_ID' => $currentUserId,
                'TEAM_PROJECT_ID' => $teamProjectId,
                'TEAM_PROJECT_TASK_ID' => $id,
                'ROLE_TYPE' => 'MANAGE',
                'DELETE_FLAG' => self::NOT_DELETE,
                'EXT_JSON' => null,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $project),
            ]];

            foreach ($userIds as $userId) {
                if ($userId === $currentUserId) {
                    continue;
                }
                $taskUsers[] = [
                    'ID' => $this->newId(),
                    'USER_ID' => $userId,
                    'TEAM_PROJECT_ID' => $teamProjectId,
                    'TEAM_PROJECT_TASK_ID' => $id,
                    'ROLE_TYPE' => 'MEMBER',
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'EXT_JSON' => null,
                    'CREATE_TIME' => $now,
                    'CREATE_USER' => $currentUserId,
                    'UPDATE_TIME' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $this->tenantIdFromProject($payload, $project),
                ];
            }

            Db::name('biz_team_project_task_user')->insertAll($taskUsers);

            return $this->taskDetail($id, $payload);
        });
    }

    public function taskEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $task = $this->activeTaskForWrite($id, $payload, 'edit task');
            $this->assertTaskMaintainer($task, $payload, 'edit task');
            if (!empty($input['teamProjectId']) && (string)$input['teamProjectId'] !== (string)$task['TEAM_PROJECT_ID']) {
                throw new RuntimeException('teamProjectId does not match task', 400);
            }

            $updates = [];
            if (array_key_exists('title', $input)) {
                $updates['TITLE'] = (string)$input['title'];
            }
            if (array_key_exists('status', $input)) {
                $updates['STATUS'] = $this->normalizeTaskStatus((string)$input['status']);
            }
            if (array_key_exists('contentText', $input)) {
                $updates['CONTENT_TEXT'] = (string)$input['contentText'];
            }
            if (array_key_exists('progress', $input)) {
                $updates['PROGRESS'] = (int)$input['progress'];
            }
            if (array_key_exists('teamProjectTaskCategoryId', $input)) {
                $categoryId = trim((string)$input['teamProjectTaskCategoryId']);
                if ($categoryId === '') {
                    throw new RuntimeException('missing teamProjectTaskCategoryId', 400);
                }
                $category = $this->activeCategoryForWrite($categoryId, $payload, 'edit task category');
                if ((string)$category['TEAM_PROJECT_ID'] !== (string)$task['TEAM_PROJECT_ID']) {
                    throw new RuntimeException('teamProjectTaskCategoryId does not belong to task teamProjectId', 400);
                }
                $updates['TEAM_PROJECT_TASK_CATEGORY_ID'] = $categoryId;
            }
            if (array_key_exists('sortCode', $input)) {
                $updates['SORT_CODE'] = (int)$input['sortCode'];
            }
            if (array_key_exists('extJson', $input)) {
                $updates['EXT_JSON'] = (string)$input['extJson'];
            }

            if ($updates === []) {
                return ['id' => $id, 'count' => 0];
            }

            $updates['UPDATE_TIME'] = date('Y-m-d H:i:s');
            $updates['UPDATE_USER'] = $this->currentUserId($payload);
            $updates['VERSION'] = Db::raw('VERSION + 1');

            $query = Db::name('biz_team_project_task')->where('ID', $id);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $affected = $query->update($updates);

            return ['id' => $id, 'count' => $affected];
        });
    }

    public function taskDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $affected = 0;

            foreach ($ids as $id) {
                $task = $this->activeTaskForWrite($id, $payload, 'delete task');
                $this->assertTaskMaintainer($task, $payload, 'delete task');

                $query = Db::name('biz_team_project_task')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                    'VERSION' => Db::raw('VERSION + 1'),
                ]);

                $taskUserQuery = Db::name('biz_team_project_task_user')->where('TEAM_PROJECT_TASK_ID', $id);
                $this->whereNotDeleted($taskUserQuery, 'DELETE_FLAG');
                $taskUserQuery->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
    }

    public function taskUserEdit(array $input, array $payload = []): array
    {
        $taskId = $this->requiredInput($input, 'id');
        $userIds = $this->userIdList($input);

        return Db::transaction(function () use ($taskId, $userIds, $payload): array {
            $task = $this->activeTaskForWrite($taskId, $payload, 'edit task users');
            $teamProjectId = (string)$task['TEAM_PROJECT_ID'];
            $this->assertTaskAssignmentPermission($taskId, $teamProjectId, $payload);
            $this->assertProjectUsers($teamProjectId, $userIds);

            $currentRows = $this->activeTaskUserRows($taskId, $teamProjectId);
            $currentUserIds = [];
            foreach ($currentRows as $row) {
                $currentUserIds[] = (string)$row['USER_ID'];
            }

            $addUserIds = array_values(array_diff($userIds, $currentUserIds));
            $removeUserIds = array_values(array_diff($currentUserIds, $userIds));
            $removeRowIds = [];
            foreach ($currentRows as $row) {
                if (in_array((string)$row['USER_ID'], $removeUserIds, true)) {
                    $removeRowIds[] = (string)$row['ID'];
                }
            }
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);
            $tenantId = $this->tenantIdFromProject($payload, $task);

            if ($addUserIds !== []) {
                $rows = [];
                foreach ($addUserIds as $userId) {
                    $rows[] = [
                        'ID' => $this->newId(),
                        'USER_ID' => $userId,
                        'TEAM_PROJECT_ID' => $teamProjectId,
                        'TEAM_PROJECT_TASK_ID' => $taskId,
                        'ROLE_TYPE' => 'MEMBER',
                        'DELETE_FLAG' => self::NOT_DELETE,
                        'EXT_JSON' => null,
                        'CREATE_TIME' => $now,
                        'CREATE_USER' => $currentUserId,
                        'UPDATE_TIME' => null,
                        'UPDATE_USER' => null,
                        'TENANT_ID' => $tenantId,
                    ];
                }
                Db::name('biz_team_project_task_user')->insertAll($rows);
            }

            if ($removeRowIds !== []) {
                $query = Db::name('biz_team_project_task_user')->whereIn('ID', $removeRowIds);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                ]);
            }

            Db::name('biz_team_project_task')
                ->where('ID', $taskId)
                ->update([
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId,
                ]);

            return [
                'id' => $taskId,
                'teamProjectId' => $teamProjectId,
                'addedUserIds' => $addUserIds,
                'removedUserIds' => $removeUserIds,
                'users' => $this->taskUsers($taskId),
            ];
        });
    }

    public function taskUserPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->taskUserQuery($filters, $payload)->count();
        $rows = $this->applySort($this->taskUserQuery($filters, $payload), $filters, self::TASK_USER_SORT_MAP, 'tu.ID')
            ->field(self::TASK_USER_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResult(array_map(fn (array $row): array => $this->taskUserRow($row), $rows), $total, $page, $limit);
    }

    public function taskUserDetail(string $id, array $payload = []): array
    {
        $row = $this->taskUserQuery(['id' => $id], $payload)
            ->field(self::TASK_USER_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project task user not found', 404);
        }

        return $this->taskUserRow($row);
    }

    public function projectCommentPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->projectCommentQuery($filters, $payload)->count();
        $rows = $this->applySort(
            $this->projectCommentQuery($filters, $payload),
            $filters,
            self::PROJECT_COMMENT_SORT_MAP,
            'pc.ID'
        )
            ->field(self::PROJECT_COMMENT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResult($this->projectCommentRows($rows, $filters), $total, $page, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectCommentList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort(
            $this->projectCommentQuery($filters, $payload),
            $filters,
            self::PROJECT_COMMENT_SORT_MAP,
            'pc.ID'
        )
            ->field(self::PROJECT_COMMENT_FIELDS)
            ->select()
            ->toArray();

        return $this->projectCommentRows($rows, $filters);
    }

    public function projectCommentDetail(string $id, array $payload = []): array
    {
        $row = $this->projectCommentQuery(['id' => $id], $payload)
            ->field(self::PROJECT_COMMENT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project comment not found', 404);
        }

        return $this->projectCommentRows([$row], [])[0];
    }

    public function projectCommentAdd(array $input, array $payload = []): array
    {
        $teamProjectId = $this->requiredInput($input, 'teamProjectId');
        $status = $this->requiredInput($input, 'status');
        $statusColor = $this->requiredInput($input, 'statusColor');
        $contentText = $this->requiredInput($input, 'contentText');
        $mentionableUsers = $this->mentionableUsers($input);

        return Db::transaction(function () use ($teamProjectId, $status, $statusColor, $contentText, $mentionableUsers, $payload): array {
            $project = $this->assertTeamProjectMember($teamProjectId, $payload, 'add comment');
            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);

            Db::name('biz_team_project_comment')->insert([
                'ID' => $id,
                'TEAM_PROJECT_ID' => $teamProjectId,
                'STATUS' => $status,
                'STATUS_COLOR' => $statusColor,
                'CONTENT_TEXT' => $contentText,
                'DELETE_FLAG' => self::NOT_DELETE,
                'EXT_JSON' => json_encode(['mentionableUsers' => $mentionableUsers], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $project),
            ]);

            return ['id' => $id];
        });
    }

    public function projectCommentDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $affected = 0;

            foreach ($ids as $id) {
                $comment = $this->activeProjectCommentForWrite($id, $payload, 'delete comment');
                $this->assertTeamProjectPermission((string)$comment['TEAM_PROJECT_ID'], $payload, 'delComment', 'delete comment');

                $query = Db::name('biz_team_project_comment')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
    }

    public function projectReplyPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->projectReplyQuery($filters, $payload)->count();
        $rows = $this->applySort($this->projectReplyQuery($filters, $payload), $filters, self::PROJECT_REPLY_SORT_MAP, 'r.ID')
            ->field(self::PROJECT_REPLY_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResult(array_map(fn (array $row): array => $this->projectReplyRow($row), $rows), $total, $page, $limit);
    }

    public function projectReplyDetail(string $id, array $payload = []): array
    {
        $row = $this->projectReplyQuery(['id' => $id], $payload)
            ->field(self::PROJECT_REPLY_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project comment reply not found', 404);
        }

        return $this->projectReplyRow($row);
    }

    public function projectReplyAdd(array $input, array $payload = []): array
    {
        $targetId = $this->requiredInput($input, 'targetId');
        $contentText = $this->requiredInput($input, 'contentText');

        return Db::transaction(function () use ($targetId, $contentText, $payload): array {
            $comment = $this->activeProjectCommentForWrite($targetId, $payload);
            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);

            Db::name('biz_team_project_comment_reply')->insert([
                'ID' => $id,
                'TARGET_ID' => $targetId,
                'CONTENT_TEXT' => $contentText,
                'DELETE_FLAG' => self::NOT_DELETE,
                'EXT_JSON' => null,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $comment),
            ]);

            return ['id' => $id];
        });
    }

    public function projectReplyEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $targetId = $this->requiredInput($input, 'targetId');
        $contentText = $this->requiredInput($input, 'contentText');

        return Db::transaction(function () use ($id, $targetId, $contentText, $payload): array {
            $reply = $this->activeProjectReplyForWrite($id, $payload, 'edit reply');
            $this->assertReplyMaintainer($reply, $payload, 'edit reply');
            $comment = $this->activeProjectCommentForWrite($targetId, $payload, 'edit reply target');
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);

            $query = Db::name('biz_team_project_comment_reply')->where('ID', $id);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $query->update([
                'TARGET_ID' => $targetId,
                'CONTENT_TEXT' => $contentText,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $comment),
            ]);

            return ['id' => $id];
        });
    }

    public function projectReplyDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $affected = 0;

            foreach ($ids as $id) {
                $reply = $this->activeProjectReplyForWrite($id, $payload, 'delete reply');
                $this->assertReplyMaintainer($reply, $payload, 'delete reply');

                $query = Db::name('biz_team_project_comment_reply')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
    }

    public function taskCommentPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->taskCommentQuery($filters, $payload)->count();
        $rows = $this->applySort($this->taskCommentQuery($filters, $payload), $filters, self::TASK_COMMENT_SORT_MAP, 'tc.ID')
            ->field(self::TASK_COMMENT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResult($this->taskCommentRows($rows), $total, $page, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function taskCommentList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->taskCommentQuery($filters, $payload), $filters, self::TASK_COMMENT_SORT_MAP, 'tc.ID')
            ->field(self::TASK_COMMENT_FIELDS)
            ->select()
            ->toArray();

        return $this->taskCommentRows($rows);
    }

    public function taskCommentDetail(string $id, array $payload = []): array
    {
        $row = $this->taskCommentQuery(['id' => $id], $payload)
            ->field(self::TASK_COMMENT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('team project task comment not found', 404);
        }

        return $this->taskCommentRow($row);
    }

    public function taskCommentAdd(array $input, array $payload = []): array
    {
        $taskId = $this->requiredInput($input, 'teamProjectTaskId');
        $contentText = (string)($input['contentText'] ?? '');
        $files = $this->fileList($input);

        return Db::transaction(function () use ($taskId, $contentText, $files, $payload): array {
            $task = $this->activeTaskForWrite($taskId, $payload, 'add task comment');
            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);

            Db::name('biz_team_project_task_comment')->insert([
                'ID' => $id,
                'TEAM_PROJECT_TASK_ID' => $taskId,
                'TEAM_PROJECT_ID' => (string)$task['TEAM_PROJECT_ID'],
                'CONTENT_TEXT' => $contentText,
                'CATEGORY' => 'COMMENT',
                'DELETE_FLAG' => self::NOT_DELETE,
                'EXT_JSON' => json_encode(['file' => $files], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantIdFromProject($payload, $task),
            ]);

            return [
                'id' => $id,
                'teamProjectTaskId' => $taskId,
                'teamProjectId' => (string)$task['TEAM_PROJECT_ID'],
            ];
        });
    }

    public function taskCommentEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $comment = $this->activeTaskCommentForWrite($id, $payload, 'edit task comment');
            $this->assertTaskCommentMaintainer($comment, $payload, 'edit task comment');
            $now = date('Y-m-d H:i:s');

            $updates = [
                'CONTENT_TEXT' => (string)($input['contentText'] ?? $comment['CONTENT_TEXT'] ?? ''),
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $this->currentUserId($payload),
            ];

            if ($this->hasFileInput($input)) {
                $updates['EXT_JSON'] = json_encode(['file' => $this->fileList($input)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (array_key_exists('extJson', $input)) {
                $updates['EXT_JSON'] = (string)$input['extJson'];
            }

            $query = Db::name('biz_team_project_task_comment')->where('ID', $id);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $query->update($updates);

            return ['id' => $id];
        });
    }

    public function taskCommentDelete(array $input, array $payload = []): array
    {
        $ids = $this->idList($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $affected = 0;

            foreach ($ids as $id) {
                $comment = $this->activeTaskCommentForWrite($id, $payload, 'delete task comment');
                $this->assertTaskCommentMaintainer($comment, $payload, 'delete task comment');

                $query = Db::name('biz_team_project_task_comment')->where('ID', $id);
                $this->whereNotDeleted($query, 'DELETE_FLAG');
                $affected += $query->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
            }

            return ['ids' => $ids, 'count' => $affected];
        });
    }

    private function categoryQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_task_category')
            ->alias('c')
            ->leftJoin('biz_team_project p', 'p.ID = c.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = c.TEAM_PROJECT_ID')
            ->where('pm.USER_ID', $this->currentUserId($payload))
            ->where(function ($query): void {
                $query->whereNull('c.DELETE_FLAG')->whereOr('c.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pm.DELETE_FLAG')->whereOr('pm.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $this->applyTenant($query, $filters, $payload, 'c.TENANT_ID');
        $this->applyExactFilters($query, $filters, [
            'id' => 'c.ID',
            'teamProjectId' => 'c.TEAM_PROJECT_ID',
        ]);

        if (!empty($filters['title'])) {
            $query->whereLike('c.TITLE', '%' . trim((string)$filters['title']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('c.TITLE', '%' . trim((string)$filters['searchKey']) . '%');
        }

        $this->applyTimeRange($query, $filters, 'c.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function taskQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_task')
            ->alias('t')
            ->leftJoin('biz_team_project p', 'p.ID = t.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = t.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_task_category c', 'c.ID = t.TEAM_PROJECT_TASK_CATEGORY_ID')
            ->leftJoin('sys_user creator', 'creator.ID = t.CREATE_USER')
            ->where('pm.USER_ID', $this->currentUserId($payload))
            ->where(function ($query): void {
                $query->whereNull('t.DELETE_FLAG')->whereOr('t.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pm.DELETE_FLAG')->whereOr('pm.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $this->applyTenant($query, $filters, $payload, 't.TENANT_ID');
        $this->applyExactFilters($query, $filters, [
            'id' => 't.ID',
            'teamProjectId' => 't.TEAM_PROJECT_ID',
            'teamProjectTaskCategoryId' => 't.TEAM_PROJECT_TASK_CATEGORY_ID',
            'status' => 't.STATUS',
            'createUser' => 't.CREATE_USER',
        ]);

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('t.CONTENT_TEXT', $keyword)
                    ->whereOr('t.TITLE', 'like', $keyword)
                    ->whereOr('p.NAME', 'like', $keyword)
                    ->whereOr('creator.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 't.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function taskUserQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_task_user')
            ->alias('tu')
            ->leftJoin('biz_team_project_task t', 't.ID = tu.TEAM_PROJECT_TASK_ID')
            ->leftJoin('biz_team_project p', 'p.ID = tu.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = tu.TEAM_PROJECT_ID')
            ->leftJoin('sys_user u', 'u.ID = tu.USER_ID')
            ->where('pm.USER_ID', $this->currentUserId($payload))
            ->where(function ($query): void {
                $query->whereNull('tu.DELETE_FLAG')->whereOr('tu.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('t.DELETE_FLAG')->whereOr('t.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pm.DELETE_FLAG')->whereOr('pm.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $this->applyTenant($query, $filters, $payload, 'tu.TENANT_ID');
        $this->applyExactFilters($query, $filters, [
            'id' => 'tu.ID',
            'userId' => 'tu.USER_ID',
            'teamProjectId' => 'tu.TEAM_PROJECT_ID',
            'teamProjectTaskId' => 'tu.TEAM_PROJECT_TASK_ID',
            'roleType' => 'tu.ROLE_TYPE',
        ]);

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('tu.ROLE_TYPE', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'tu.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function projectCommentQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_comment')
            ->alias('pc')
            ->leftJoin('biz_team_project p', 'p.ID = pc.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = pc.TEAM_PROJECT_ID')
            ->leftJoin('sys_user creator', 'creator.ID = pc.CREATE_USER')
            ->where('pm.USER_ID', $this->currentUserId($payload))
            ->where(function ($query): void {
                $query->whereNull('pc.DELETE_FLAG')->whereOr('pc.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pm.DELETE_FLAG')->whereOr('pm.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $this->applyTenant($query, $filters, $payload, 'pc.TENANT_ID');
        $this->applyExactFilters($query, $filters, [
            'id' => 'pc.ID',
            'teamProjectId' => 'pc.TEAM_PROJECT_ID',
            'status' => 'pc.STATUS',
            'createUser' => 'pc.CREATE_USER',
        ]);

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('pc.CONTENT_TEXT', $keyword)
                    ->whereOr('pc.STATUS', 'like', $keyword)
                    ->whereOr('creator.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'pc.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function taskCommentQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_task_comment')
            ->alias('tc')
            ->leftJoin('biz_team_project_task t', 't.ID = tc.TEAM_PROJECT_TASK_ID')
            ->leftJoin('biz_team_project p', 'p.ID = tc.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = tc.TEAM_PROJECT_ID')
            ->leftJoin('sys_user creator', 'creator.ID = tc.CREATE_USER')
            ->where('pm.USER_ID', $this->currentUserId($payload))
            ->where(function ($query): void {
                $query->whereNull('tc.DELETE_FLAG')->whereOr('tc.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('t.DELETE_FLAG')->whereOr('t.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pm.DELETE_FLAG')->whereOr('pm.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $this->applyTenant($query, $filters, $payload, 'tc.TENANT_ID');
        $this->applyExactFilters($query, $filters, [
            'id' => 'tc.ID',
            'teamProjectTaskId' => 'tc.TEAM_PROJECT_TASK_ID',
            'teamProjectId' => 'tc.TEAM_PROJECT_ID',
            'category' => 'tc.CATEGORY',
            'createUser' => 'tc.CREATE_USER',
        ]);

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('tc.CONTENT_TEXT', $keyword)
                    ->whereOr('tc.CATEGORY', 'like', $keyword)
                    ->whereOr('creator.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'tc.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function projectReplyQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_team_project_comment_reply')
            ->alias('r')
            ->leftJoin('biz_team_project_comment pc', 'pc.ID = r.TARGET_ID')
            ->leftJoin('biz_team_project p', 'p.ID = pc.TEAM_PROJECT_ID')
            ->leftJoin('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = pc.TEAM_PROJECT_ID')
            ->leftJoin('sys_user creator', 'creator.ID = r.CREATE_USER')
            ->where('pm.USER_ID', $this->currentUserId($payload))
            ->where(function ($query): void {
                $query->whereNull('r.DELETE_FLAG')->whereOr('r.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pc.DELETE_FLAG')->whereOr('pc.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->where(function ($query): void {
                $query->whereNull('pm.DELETE_FLAG')->whereOr('pm.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $this->applyTenant($query, $filters, $payload, 'r.TENANT_ID');
        $this->applyExactFilters($query, $filters, [
            'id' => 'r.ID',
            'targetId' => 'r.TARGET_ID',
            'teamProjectId' => 'pc.TEAM_PROJECT_ID',
            'projectId' => 'pc.TEAM_PROJECT_ID',
            'createUser' => 'r.CREATE_USER',
        ]);

        if (!empty($filters['targetIds']) && is_array($filters['targetIds'])) {
            $targetIds = array_values(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $filters['targetIds'])));
            if ($targetIds !== []) {
                $query->whereIn('r.TARGET_ID', $targetIds);
            }
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('r.CONTENT_TEXT', $keyword)
                    ->whereOr('creator.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'r.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function activeProjectCommentForWrite(string $commentId, array $payload, string $action = 'write comment'): array
    {
        $query = Db::name('biz_team_project_comment')->where('ID', $commentId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $comment = $query->find();
        if (!is_array($comment) || $comment === []) {
            throw new RuntimeException('team project comment not found', 404);
        }

        $project = $this->assertTeamProjectMember((string)$comment['TEAM_PROJECT_ID'], $payload, $action);
        $comment['TENANT_ID'] = $comment['TENANT_ID'] ?? ($project['TENANT_ID'] ?? null);

        return $comment;
    }

    private function activeProjectReplyForWrite(string $replyId, array $payload, string $action): array
    {
        $query = Db::name('biz_team_project_comment_reply')
            ->alias('r')
            ->leftJoin('biz_team_project_comment pc', 'pc.ID = r.TARGET_ID')
            ->where('r.ID', $replyId);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'pc.DELETE_FLAG');

        $reply = $query
            ->field('r.*, pc.TEAM_PROJECT_ID AS TEAM_PROJECT_ID, pc.TENANT_ID AS COMMENT_TENANT_ID')
            ->find();
        if (!is_array($reply) || $reply === []) {
            throw new RuntimeException('team project comment reply not found', 404);
        }

        $project = $this->assertTeamProjectMember((string)$reply['TEAM_PROJECT_ID'], $payload, $action);
        $reply['TENANT_ID'] = $reply['TENANT_ID'] ?? ($reply['COMMENT_TENANT_ID'] ?? $project['TENANT_ID'] ?? null);

        return $reply;
    }

    private function activeTaskForWrite(string $taskId, array $payload, string $action): array
    {
        $query = Db::name('biz_team_project_task')
            ->alias('t')
            ->leftJoin('biz_team_project p', 'p.ID = t.TEAM_PROJECT_ID')
            ->where('t.ID', $taskId);
        $this->whereNotDeleted($query, 't.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $task = $query
            ->field('t.*, p.TENANT_ID AS PROJECT_TENANT_ID')
            ->find();
        if (!is_array($task) || $task === []) {
            throw new RuntimeException('team project task not found', 404);
        }

        $project = $this->assertTeamProjectMember((string)$task['TEAM_PROJECT_ID'], $payload, $action);
        $task['TENANT_ID'] = $task['TENANT_ID'] ?? ($task['PROJECT_TENANT_ID'] ?? $project['TENANT_ID'] ?? null);

        return $task;
    }

    private function activeCategoryForWrite(string $categoryId, array $payload, string $action): array
    {
        $query = Db::name('biz_team_project_task_category')
            ->alias('c')
            ->leftJoin('biz_team_project p', 'p.ID = c.TEAM_PROJECT_ID')
            ->where('c.ID', $categoryId);
        $this->whereNotDeleted($query, 'c.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $category = $query
            ->field('c.*, p.TENANT_ID AS PROJECT_TENANT_ID')
            ->find();
        if (!is_array($category) || $category === []) {
            throw new RuntimeException('team project task category not found', 404);
        }

        $project = $this->assertTeamProjectMember((string)$category['TEAM_PROJECT_ID'], $payload, $action);
        $category['TENANT_ID'] = $category['TENANT_ID'] ?? ($category['PROJECT_TENANT_ID'] ?? $project['TENANT_ID'] ?? null);

        return $category;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeCategoryRows(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_team_project_task_category')->whereIn('ID', $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        return $query->field('ID, TEAM_PROJECT_ID')->select()->toArray();
    }

    private function activeTaskCommentForWrite(string $commentId, array $payload, string $action): array
    {
        $query = Db::name('biz_team_project_task_comment')
            ->alias('tc')
            ->leftJoin('biz_team_project_task t', 't.ID = tc.TEAM_PROJECT_TASK_ID')
            ->leftJoin('biz_team_project p', 'p.ID = tc.TEAM_PROJECT_ID')
            ->where('tc.ID', $commentId);
        $this->whereNotDeleted($query, 'tc.DELETE_FLAG');
        $this->whereNotDeleted($query, 't.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $comment = $query
            ->field('tc.*, t.TEAM_PROJECT_ID AS TASK_TEAM_PROJECT_ID, p.TENANT_ID AS PROJECT_TENANT_ID')
            ->find();
        if (!is_array($comment) || $comment === []) {
            throw new RuntimeException('team project task comment not found', 404);
        }
        if ((string)($comment['CATEGORY'] ?? '') !== 'COMMENT') {
            throw new RuntimeException('task log maintenance is read-only', 403);
        }

        $teamProjectId = (string)($comment['TEAM_PROJECT_ID'] ?? $comment['TASK_TEAM_PROJECT_ID'] ?? '');
        $project = $this->assertTeamProjectMember($teamProjectId, $payload, $action);
        $comment['TEAM_PROJECT_ID'] = $teamProjectId;
        $comment['TENANT_ID'] = $comment['TENANT_ID'] ?? ($comment['PROJECT_TENANT_ID'] ?? $project['TENANT_ID'] ?? null);

        return $comment;
    }

    private function assertTeamProjectMember(string $teamProjectId, array $payload, string $action): array
    {
        $userId = $this->currentUserId($payload);
        $query = Db::name('biz_team_project')
            ->alias('p')
            ->join('biz_team_project_user pm', 'pm.TEAM_PROJECT_ID = p.ID', 'INNER')
            ->where('p.ID', $teamProjectId)
            ->where('pm.USER_ID', $userId);
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $this->whereNotDeleted($query, 'pm.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        $row = $query->field('p.ID AS ID, p.TENANT_ID AS TENANT_ID, pm.ROLE_TYPE AS ROLE_TYPE')->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException("no permission to {$action} on this team project", 403);
        }

        return $row;
    }

    private function assertTeamProjectMaintainer(string $teamProjectId, array $payload, string $action): array
    {
        $project = $this->assertTeamProjectMember($teamProjectId, $payload, $action);
        $roleType = strtoupper((string)($project['ROLE_TYPE'] ?? ''));
        if (in_array($roleType, ['LEADER', 'MANAGE'], true)) {
            return $project;
        }
        if ($this->hasTeamProjectPermission($teamProjectId, $payload, 'addUser')) {
            return $project;
        }

        throw new RuntimeException("no permission to {$action} on this team project", 403);
    }

    private function assertTeamProjectPermission(string $teamProjectId, array $payload, string $code, string $action): void
    {
        if ($this->hasTeamProjectPermission($teamProjectId, $payload, $code)) {
            return;
        }

        throw new RuntimeException("no permission to {$action} on this team project", 403);
    }

    private function hasTeamProjectPermission(string $teamProjectId, array $payload, string $code): bool
    {
        $userId = $this->currentUserId($payload);
        $relation = Db::name('biz_relation')
            ->where('OBJECT_ID', $teamProjectId)
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::TEAM_PROJECT_PERMISSION_CATEGORY)
            ->find();

        $permissions = [];
        if (is_array($relation) && !empty($relation['EXT_JSON'])) {
            $decoded = json_decode((string)$relation['EXT_JSON'], true);
            if (is_array($decoded)) {
                $permissions = array_values(array_map(static fn (mixed $item): string => (string)$item, $decoded));
            }
        }

        return in_array($code, $permissions, true);
    }

    private function assertTaskAssignmentPermission(string $taskId, string $teamProjectId, array $payload): void
    {
        if ($this->hasTeamProjectPermission($teamProjectId, $payload, 'addUser')) {
            return;
        }

        $query = Db::name('biz_team_project_task_user')
            ->where('TEAM_PROJECT_TASK_ID', $taskId)
            ->where('TEAM_PROJECT_ID', $teamProjectId)
            ->where('USER_ID', $this->currentUserId($payload))
            ->where('ROLE_TYPE', 'MANAGE');
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($query->count() > 0) {
            return;
        }

        throw new RuntimeException('no permission to edit task users on this team project', 403);
    }

    private function assertTaskMaintainer(array $task, array $payload, string $action): void
    {
        $currentUserId = $this->currentUserId($payload);
        if ((string)($task['CREATE_USER'] ?? '') === $currentUserId) {
            return;
        }

        $teamProjectId = (string)$task['TEAM_PROJECT_ID'];
        if ($this->hasTaskManageRole((string)$task['ID'], $teamProjectId, $payload)) {
            return;
        }

        $this->assertTeamProjectMaintainer($teamProjectId, $payload, $action);
    }

    private function assertCategoryEmpty(string $categoryId): void
    {
        $query = Db::name('biz_team_project_task')->where('TEAM_PROJECT_TASK_CATEGORY_ID', $categoryId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($query->count() > 0) {
            throw new RuntimeException('cannot delete a category that still has tasks', 400);
        }
    }

    private function assertTaskCommentMaintainer(array $comment, array $payload, string $action): void
    {
        $userId = $this->currentUserId($payload);
        if ((string)($comment['CREATE_USER'] ?? '') === $userId) {
            return;
        }

        $teamProjectId = (string)$comment['TEAM_PROJECT_ID'];
        if ($this->hasTeamProjectPermission($teamProjectId, $payload, 'delComment')) {
            return;
        }

        if ($this->hasTaskManageRole((string)$comment['TEAM_PROJECT_TASK_ID'], $teamProjectId, $payload)) {
            return;
        }

        throw new RuntimeException("no permission to {$action} on this team project", 403);
    }

    private function hasTaskManageRole(string $taskId, string $teamProjectId, array $payload): bool
    {
        $query = Db::name('biz_team_project_task_user')
            ->where('TEAM_PROJECT_TASK_ID', $taskId)
            ->where('TEAM_PROJECT_ID', $teamProjectId)
            ->where('USER_ID', $this->currentUserId($payload))
            ->where('ROLE_TYPE', 'MANAGE');
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        return $query->count() > 0;
    }

    /**
     * @param array<int, string> $userIds
     */
    private function assertProjectUsers(string $teamProjectId, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $query = Db::name('biz_team_project_user')
            ->where('TEAM_PROJECT_ID', $teamProjectId)
            ->whereIn('USER_ID', $userIds);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        if ((int)$query->count() !== count($userIds)) {
            throw new RuntimeException('selected user is not a member of this team project', 400);
        }
    }

    private function assertReplyMaintainer(array $reply, array $payload, string $action): void
    {
        $userId = $this->currentUserId($payload);
        if ((string)($reply['CREATE_USER'] ?? '') === $userId) {
            return;
        }

        $this->assertTeamProjectPermission((string)$reply['TEAM_PROJECT_ID'], $payload, 'delComment', $action);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskUsers(string $taskId): array
    {
        $rows = Db::name('biz_team_project_task_user')
            ->alias('tu')
            ->leftJoin('sys_user u', 'u.ID = tu.USER_ID')
            ->where('tu.TEAM_PROJECT_TASK_ID', $taskId)
            ->where(function ($query): void {
                $query->whereNull('tu.DELETE_FLAG')->whereOr('tu.DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(self::TASK_USER_FIELDS)
            ->order('tu.ID', 'asc')
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->taskUserRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeTaskUserRows(string $taskId, string $teamProjectId): array
    {
        $query = Db::name('biz_team_project_task_user')
            ->where('TEAM_PROJECT_TASK_ID', $taskId)
            ->where('TEAM_PROJECT_ID', $teamProjectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        return $query
            ->field('ID, USER_ID, ROLE_TYPE')
            ->select()
            ->toArray();
    }

    /**
     * @param array<int, string> $targetIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function projectReplies(array $targetIds, array $filters): array
    {
        if ($targetIds === []) {
            return [];
        }

        $query = Db::name('biz_team_project_comment_reply')
            ->alias('r')
            ->leftJoin('sys_user creator', 'creator.ID = r.CREATE_USER')
            ->whereIn('r.TARGET_ID', $targetIds)
            ->where(function ($query): void {
                $query->whereNull('r.DELETE_FLAG')->whereOr('r.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $rows = $this->applySort($query, $filters, self::PROJECT_REPLY_SORT_MAP, 'r.ID')
            ->field(self::PROJECT_REPLY_FIELDS)
            ->select()
            ->toArray();

        $grouped = [];
        foreach ($rows as $row) {
            $reply = $this->projectReplyRow($row);
            $grouped[(string)$reply['targetId']][] = $reply;
        }

        return $grouped;
    }

    private function applyTenant($query, array $filters, array $payload, string $column): void
    {
        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where($column, $tenantId);
        }
    }

    private function applyExactFilters($query, array $filters, array $map): void
    {
        foreach ($map as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
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
    private function categoryRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->categoryRow($row), $rows);
    }

    private function categoryRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'teamProjectId' => $this->value($row, 'TEAM_PROJECT_ID', 'teamProjectId'),
            'title' => $this->value($row, 'TITLE', 'title'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'sortCode' => $this->intValue($this->value($row, 'SORT_CODE', 'sortCode')),
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
     * @return array<int, array<string, mixed>>
     */
    private function taskRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->taskRow($row), $rows);
    }

    private function taskRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'teamProjectId' => $this->value($row, 'TEAM_PROJECT_ID', 'teamProjectId'),
            'teamProjectName' => $this->value($row, 'TEAM_PROJECT_NAME', 'teamProjectName'),
            'teamProjectTaskCategoryId' => $this->value($row, 'TEAM_PROJECT_TASK_CATEGORY_ID', 'teamProjectTaskCategoryId'),
            'categoryTitle' => $this->value($row, 'CATEGORY_TITLE', 'categoryTitle'),
            'status' => $this->value($row, 'STATUS', 'status'),
            'title' => $this->value($row, 'TITLE', 'title'),
            'progress' => $this->intValue($this->value($row, 'PROGRESS', 'progress')),
            'contentText' => $this->value($row, 'CONTENT_TEXT', 'contentText'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'sortCode' => $this->intValue($this->value($row, 'SORT_CODE', 'sortCode')),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'createUserAvatar' => $this->value($row, 'CREATE_USER_AVATAR', 'createUserAvatar'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => $this->intValue($this->value($row, 'VERSION', 'version')) ?? 0,
        ];
    }

    private function taskUserRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'userId' => $this->value($row, 'USER_ID', 'userId'),
            'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'teamProjectId' => $this->value($row, 'TEAM_PROJECT_ID', 'teamProjectId'),
            'teamProjectTaskId' => $this->value($row, 'TEAM_PROJECT_TASK_ID', 'teamProjectTaskId'),
            'roleType' => $this->value($row, 'ROLE_TYPE', 'roleType'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function projectCommentRows(array $rows, array $filters): array
    {
        $comments = array_map(fn (array $row): array => $this->projectCommentRow($row), $rows);
        $ids = array_values(array_filter(array_map(static fn (array $row): string => (string)$row['id'], $comments)));
        $replies = $this->projectReplies($ids, $filters);

        foreach ($comments as &$comment) {
            $comment['bizTeamProjectCommentReplies'] = $replies[(string)$comment['id']] ?? [];
        }
        unset($comment);

        return $comments;
    }

    private function projectCommentRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'teamProjectId' => $this->value($row, 'TEAM_PROJECT_ID', 'teamProjectId'),
            'status' => $this->value($row, 'STATUS', 'status'),
            'statusColor' => $this->value($row, 'STATUS_COLOR', 'statusColor'),
            'contentText' => $this->value($row, 'CONTENT_TEXT', 'contentText'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'bizTeamProjectCommentReplies' => [],
        ];
    }

    private function projectReplyRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'targetId' => $this->value($row, 'TARGET_ID', 'targetId'),
            'contentText' => $this->value($row, 'CONTENT_TEXT', 'contentText'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function taskCommentRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->taskCommentRow($row), $rows);
    }

    private function taskCommentRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'teamProjectTaskId' => $this->value($row, 'TEAM_PROJECT_TASK_ID', 'teamProjectTaskId'),
            'teamProjectId' => $this->value($row, 'TEAM_PROJECT_ID', 'teamProjectId'),
            'contentText' => $this->value($row, 'CONTENT_TEXT', 'contentText'),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
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
            $id = '';
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

    /**
     * @return array<int, string>
     */
    private function optionalUserIdList(array $input): array
    {
        if (!array_key_exists('user', $input) && !array_key_exists('users', $input) && !array_key_exists('userIds', $input)) {
            return [];
        }

        return $this->userIdList($input);
    }

    /**
     * @return array<int, string>
     */
    private function mentionableUsers(array $input): array
    {
        if (!array_key_exists('mentionableUsers', $input) || !is_array($input['mentionableUsers'])) {
            throw new RuntimeException('missing mentionableUsers', 400);
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $input['mentionableUsers']))));
    }

    /**
     * @return array<int, mixed>
     */
    private function fileList(array $input): array
    {
        $raw = $input['files'] ?? $input['file'] ?? $input['fileList'] ?? [];

        return is_array($raw) ? array_values($raw) : [];
    }

    private function hasFileInput(array $input): bool
    {
        return array_key_exists('files', $input) || array_key_exists('file', $input) || array_key_exists('fileList', $input);
    }

    private function normalizeTaskStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, ['TODO', 'CANCEL', 'COMPLETE'], true)) {
            throw new RuntimeException('invalid task status', 400);
        }

        return $status;
    }

    private function tenantIdFromProject(array $payload, array $project): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? $project['TENANT_ID'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function pageResult(array $records, int $total, int $page, int $limit): array
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

    private function intValue(mixed $value): ?int
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
