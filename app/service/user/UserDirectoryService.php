<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysOrg;
use app\model\SysPosition;
use app\model\SysRelation;
use app\model\SysRole;
use app\model\SysUser;
use app\model\SysUserProcessConfig;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only user directory queries compatible with Java SysUserService selectors.
 */
class UserDirectoryService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const WORKBENCH_CATEGORY = 'SYS_USER_WORKBENCH_DATA';
    private const DEFAULT_WORKBENCH_KEY = 'SNOWY_SYS_DEFAULT_WORKBENCH_DATA';
    private const MESSAGE_TO_USER_CATEGORY = 'MSG_TO_USER';
    private const DEFAULT_PROCESS_NAMES = [
        'Process_reimbursement',
        'Process_make_payment',
        'Process_project_reissue_product',
        'Process_sale_project_play',
        'Process_payment',
        'Process_sale_project_init',
        'Process_sale_project_delivery',
        'Process_procure',
        'Process_ask_leave',
    ];

    public function __construct(
        private readonly OrgService $orgService = new OrgService(),
        private readonly PositionService $positionService = new PositionService()
    ) {
    }

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $records = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->sanitizeUserRows($records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        $rows = $this->sanitizeUserRows([$row->toArray()]);

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDetail(array $filters = []): array
    {
        $queryFilters = $filters;
        unset($queryFilters['orgId']);
        $query = $this->baseQuery($queryFilters);
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('ORG_ID', $orgIds);
        }

        $rows = $query
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->sanitizeUserRows($rows);
    }

    /**
     * @return array<int, string>
     */
    public function ownRole(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            return [];
        }

        return array_values(array_map('strval', Db::name('sys_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', 'SYS_USER_HAS_ROLE')
            ->column('TARGET_ID')));
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getUserListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = SysUser::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->sanitizeUserRows($rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getOrgListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = SysOrg::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => array_merge($row, [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
        ]), $rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getPositionListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = SysPosition::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => array_merge($row, [
            'id' => $row['ID'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
        ]), $rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getRoleListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        return SysRole::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function userSelector(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $rows = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $rows = $this->sanitizeUserRows($rows);

        return array_map(static function (array $row): array {
            return [
                'id' => $row['id'] ?? null,
                'value' => $row['id'] ?? null,
                'label' => $row['name'] ?? $row['account'] ?? null,
                'title' => $row['name'] ?? $row['account'] ?? null,
                'name' => $row['name'] ?? null,
                'account' => $row['account'] ?? null,
                'orgId' => $row['orgId'] ?? null,
                'orgName' => $row['orgName'] ?? null,
                'positionId' => $row['positionId'] ?? null,
                'positionName' => $row['positionName'] ?? null,
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roleSelector(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $rows = $this->roleQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            return [
                'id' => $row['ID'] ?? null,
                'value' => $row['ID'] ?? null,
                'label' => $row['NAME'] ?? $row['CODE'] ?? null,
                'title' => $row['NAME'] ?? $row['CODE'] ?? null,
                'name' => $row['NAME'] ?? null,
                'code' => $row['CODE'] ?? null,
                'category' => $row['CATEGORY'] ?? null,
                'orgId' => $row['ORG_ID'] ?? null,
            ];
        }, $rows);
    }

    public function getAvatarById(string $id): array
    {
        $user = $this->detail($id);

        return [
            'id' => $id,
            'avatar' => $user['AVATAR'] ?? null,
        ];
    }

    public function loginWorkbench(string $userId): string
    {
        if (!$this->detail($userId)) {
            throw new RuntimeException('user not found', 404);
        }

        $relation = SysRelation::where('OBJECT_ID', $userId)
            ->where('CATEGORY', self::WORKBENCH_CATEGORY)
            ->find();

        $workbench = $relation ? trim((string)$relation->getAttr('EXT_JSON')) : '';
        if ($workbench !== '') {
            return $workbench;
        }

        return $this->defaultWorkbenchData();
    }

    public function processConfig(string $userId): array
    {
        $row = SysUserProcessConfig::where('CREATE_USER', $userId)
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->order(['UPDATE_TIME' => 'desc', 'CREATE_TIME' => 'desc', 'ID' => 'desc'])
            ->find();

        if (!$row) {
            $config = $this->defaultProcessConfig();
            $configJson = json_encode(['config' => $config], JSON_UNESCAPED_UNICODE);

            return [
                'id' => null,
                'configJson' => $configJson,
                'deleteFlag' => self::NOT_DELETE,
                'createUser' => $userId,
                'config' => $config,
            ];
        }

        $data = $row->toArray();
        return [
            'id' => $data['ID'] ?? null,
            'configJson' => $data['CONFIG_JSON'] ?? null,
            'deleteFlag' => $data['DELETE_FLAG'] ?? null,
            'createTime' => $data['CREATE_TIME'] ?? null,
            'createUser' => $data['CREATE_USER'] ?? null,
            'updateTime' => $data['UPDATE_TIME'] ?? null,
            'updateUser' => $data['UPDATE_USER'] ?? null,
            'tenantId' => $data['TENANT_ID'] ?? null,
            'version' => $data['VERSION'] ?? null,
            'config' => $this->decodeProcessConfig((string)($data['CONFIG_JSON'] ?? '')),
        ];
    }

    public function loginUnreadMessagePage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $relations = $this->messageRelationsForUser($userId);
        $messageIds = array_keys($relations);

        if ($messageIds === []) {
            return [
                'records' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
            ];
        }

        $total = $this->messageQuery($messageIds, $filters)->count();
        $rows = $this->messageQuery($messageIds, $filters)
            ->order('CREATE_TIME', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $records = array_map(fn (array $row): array => $this->messageRow(
            $row,
            $relations[(string)($row['ID'] ?? '')] ?? null
        ), $rows);

        usort($records, static function (array $left, array $right): int {
            return (int)($left['read'] ?? false) <=> (int)($right['read'] ?? false);
        });

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loginUnreadMessageList(string $userId, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $relations = array_filter(
            $this->messageRelationsForUser($userId),
            fn (array $relation): bool => $this->relationReadStatus($relation) === false
        );
        $messageIds = array_keys($relations);

        if ($messageIds === []) {
            return [];
        }

        $rows = $this->messageQuery($messageIds, [])
            ->order('CREATE_TIME', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->messageRow(
            $row,
            $relations[(string)($row['ID'] ?? '')] ?? null
        ), $rows);
    }

    public function loginUnreadMessageDetail(string $userId, string $id): ?array
    {
        $ownRelation = Db::name('dev_relation')
            ->where('OBJECT_ID', $id)
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->find();

        if (!$ownRelation) {
            return null;
        }

        $message = Db::name('dev_message')
            ->where('ID', $id)
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->find();

        if (!$message) {
            return null;
        }

        $receiveRelations = Db::name('dev_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->select()
            ->toArray();
        $receiveUserIds = array_values(array_filter(array_map(static fn (array $relation): string => (string)($relation['TARGET_ID'] ?? ''), $receiveRelations)));
        $userNames = $receiveUserIds === []
            ? []
            : SysUser::whereIn('ID', $receiveUserIds)->column('NAME', 'ID');

        $detail = $this->messageRow($message, $ownRelation);
        $detail['receiveInfoList'] = array_map(function (array $relation) use ($userNames): array {
            $receiveUserId = (string)($relation['TARGET_ID'] ?? '');

            return [
                'receiveUserId' => $receiveUserId,
                'receiveUserName' => $userNames[$receiveUserId] ?? 'unknown user',
                'read' => $this->relationReadStatus($relation) ?? false,
            ];
        }, $receiveRelations);

        return $detail;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loginOrgTree(string $userId): array
    {
        $user = $this->detail($userId);
        if (!$user || empty($user['ORG_ID'])) {
            return [];
        }

        return $this->orgService->tree(['tenantId' => $user['TENANT_ID'] ?? null]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loginPositionInfo(string $userId): array
    {
        $user = $this->detail($userId);
        if (!$user) {
            return [];
        }

        $positionIds = [];
        if (!empty($user['POSITION_ID'])) {
            $positionIds[] = (string)$user['POSITION_ID'];
        }

        $extraPositions = json_decode((string)($user['POSITION_JSON'] ?? ''), true);
        if (is_array($extraPositions)) {
            foreach ($extraPositions as $position) {
                if (is_array($position) && !empty($position['id'])) {
                    $positionIds[] = (string)$position['id'];
                }
            }
        }

        return $this->getPositionListByIdList($positionIds);
    }

    private function baseQuery(array $filters)
    {
        $query = SysUser::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['account'])) {
            $query->whereLike('ACCOUNT', '%' . trim((string)$filters['account']) . '%');
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['phone'])) {
            $query->whereLike('PHONE', '%' . trim((string)$filters['phone']) . '%');
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
        }

        if (!empty($filters['positionId'])) {
            $query->where('POSITION_ID', (string)$filters['positionId']);
        }

        if (!empty($filters['userStatus'])) {
            $query->where('USER_STATUS', (string)$filters['userStatus']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    private function roleQuery(array $filters)
    {
        $query = SysRole::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['code'])) {
            $query->whereLike('CODE', '%' . trim((string)$filters['code']) . '%');
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeUserRows(array $rows): array
    {
        $orgNames = $this->orgNames($rows);
        $positionNames = $this->positionNames($rows);
        $genderLabels = $this->dictLabels('GENDER');

        return array_map(
            fn (array $row): array => $this->sanitizeUserRow($row, $orgNames, $positionNames, $genderLabels),
            $rows
        );
    }

    /**
     * @param array<string, string> $orgNames
     * @param array<string, string> $positionNames
     * @param array<string, string> $genderLabels
     */
    private function sanitizeUserRow(array $row, array $orgNames = [], array $positionNames = [], array $genderLabels = []): array
    {
        unset($row['PASSWORD']);

        $orgId = $this->rowValue($row, 'ORG_ID', 'orgId');
        $positionId = $this->rowValue($row, 'POSITION_ID', 'positionId');
        $gender = $this->rowValue($row, 'GENDER', 'gender');

        return array_merge($row, [
            'id' => $this->rowValue($row, 'ID', 'id'),
            'avatar' => $this->rowValue($row, 'AVATAR', 'avatar'),
            'signature' => $this->rowValue($row, 'SIGNATURE', 'signature'),
            'account' => $this->rowValue($row, 'ACCOUNT', 'account'),
            'name' => $this->rowValue($row, 'NAME', 'name'),
            'nickname' => $this->rowValue($row, 'NICKNAME', 'nickname'),
            'gender' => $gender,
            'genderName' => $gender !== null ? ($genderLabels[(string)$gender] ?? $gender) : null,
            'age' => $this->rowValue($row, 'AGE', 'age'),
            'birthday' => $this->rowValue($row, 'BIRTHDAY', 'birthday'),
            'phone' => $this->rowValue($row, 'PHONE', 'phone'),
            'email' => $this->rowValue($row, 'EMAIL', 'email'),
            'empNo' => $this->rowValue($row, 'EMP_NO', 'empNo'),
            'entryDate' => $this->rowValue($row, 'ENTRY_DATE', 'entryDate'),
            'orgId' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? $this->rowValue($row, 'orgName', 'ORG_NAME')) : null,
            'positionId' => $positionId,
            'positionName' => $positionId !== null ? ($positionNames[(string)$positionId] ?? $this->rowValue($row, 'positionName', 'POSITION_NAME')) : null,
            'positionLevel' => $this->rowValue($row, 'POSITION_LEVEL', 'positionLevel'),
            'directorId' => $this->rowValue($row, 'DIRECTOR_ID', 'directorId'),
            'positionJson' => $this->rowValue($row, 'POSITION_JSON', 'positionJson'),
            'userStatus' => $this->rowValue($row, 'USER_STATUS', 'userStatus'),
            'sortCode' => $this->rowValue($row, 'SORT_CODE', 'sortCode'),
            'extJson' => $this->rowValue($row, 'EXT_JSON', 'extJson'),
            'deleteFlag' => $this->rowValue($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->rowValue($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->rowValue($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->rowValue($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->rowValue($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->rowValue($row, 'TENANT_ID', 'tenantId'),
        ]);
    }

    private function rowValue(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function orgNames(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            fn (array $row): string => (string)$this->rowValue($row, 'ORG_ID', 'orgId'),
            $rows
        ))));

        return $orgIds === []
            ? []
            : SysOrg::whereIn('ID', $orgIds)->column('NAME', 'ID');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function positionNames(array $rows): array
    {
        $positionIds = array_values(array_unique(array_filter(array_map(
            fn (array $row): string => (string)$this->rowValue($row, 'POSITION_ID', 'positionId'),
            $rows
        ))));

        return $positionIds === []
            ? []
            : SysPosition::whereIn('ID', $positionIds)->column('NAME', 'ID');
    }

    /**
     * @return array<string, string>
     */
    private function dictLabels(string $parentDictValue): array
    {
        $parentId = Db::name('dev_dict')
            ->where('DICT_VALUE', $parentDictValue)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', self::NOT_DELETE);
            })
            ->value('ID');

        if ($parentId === null || $parentId === '') {
            return [];
        }

        return Db::name('dev_dict')
            ->where('PARENT_ID', (string)$parentId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', self::NOT_DELETE);
            })
            ->column('DICT_LABEL', 'DICT_VALUE');
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string)$id);
        }, $ids))));
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
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

    private function defaultWorkbenchData(): string
    {
        $value = Db::name('dev_config')
            ->where('CONFIG_KEY', self::DEFAULT_WORKBENCH_KEY)
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->value('CONFIG_VALUE');

        $workbench = is_string($value) ? trim($value) : '';

        return $workbench !== '' ? $workbench : '{"shortcut":[]}';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultProcessConfig(): array
    {
        return array_map(static fn (string $processName): array => [
            'processName' => $processName,
            'approveUserIdList' => [],
            'copyUserIdList' => [],
        ], self::DEFAULT_PROCESS_NAMES);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeProcessConfig(string $configJson): array
    {
        $decoded = json_decode($configJson, true);
        if (!is_array($decoded) || !isset($decoded['config']) || !is_array($decoded['config'])) {
            return $this->defaultProcessConfig();
        }

        return $decoded['config'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function messageRelationsForUser(string $userId): array
    {
        $rows = Db::name('dev_relation')
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->select()
            ->toArray();

        $relations = [];
        foreach ($rows as $row) {
            $messageId = (string)($row['OBJECT_ID'] ?? '');
            if ($messageId !== '') {
                $relations[$messageId] = $row;
            }
        }

        return $relations;
    }

    /**
     * @param array<int, string> $messageIds
     */
    private function messageQuery(array $messageIds, array $filters)
    {
        $query = Db::name('dev_message')
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $messageIds);

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('SUBJECT', '%' . trim((string)$filters['searchKey']) . '%');
        }

        return $query;
    }

    private function messageRow(array $row, ?array $relation): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'subject' => $row['SUBJECT'] ?? null,
            'content' => $row['CONTENT'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'read' => $this->relationReadStatus($relation) ?? false,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function relationReadStatus(?array $relation): ?bool
    {
        if (!$relation) {
            return null;
        }

        $decoded = json_decode((string)($relation['EXT_JSON'] ?? '{}'), true);
        if (!is_array($decoded) || !array_key_exists('read', $decoded)) {
            return null;
        }

        return (bool)$decoded['read'];
    }
}
