<?php

declare(strict_types=1);

namespace app\service\auth;

use app\service\user\OrgService;
use app\service\user\UserDirectoryService;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only RBAC role queries compatible with Java SysRoleController.
 */
class RoleService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const USER_HAS_ROLE = 'SYS_USER_HAS_ROLE';
    private const ROLE_HAS_RESOURCE = 'SYS_ROLE_HAS_RESOURCE';
    private const ROLE_HAS_MOBILE_MENU = 'SYS_ROLE_HAS_MOBILE_MENU';
    private const ROLE_HAS_PERMISSION = 'SYS_ROLE_HAS_PERMISSION';
    private const USER_HAS_PERMISSION = 'SYS_USER_HAS_PERMISSION';
    private const CATEGORY_MODULE = 'MODULE';
    private const CATEGORY_MENU = 'MENU';
    private const CATEGORY_BUTTON = 'BUTTON';
    private const MENU_TYPE_CATALOG = 'CATALOG';
    private const BUILD_IN_ROLE_CODES = ['superadmin', 'tenantadmin'];
    private const SCOPE_CATEGORIES = [
        'SCOPE_ALL',
        'SCOPE_SELF',
        'SCOPE_ORG',
        'SCOPE_ORG_CHILD',
        'SCOPE_ORG_DEFINE',
    ];

    public function __construct(
        private readonly OrgService $orgService = new OrgService(),
        private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService()
    ) {
    }

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->roleQuery($filters)->count();
        $records = $this->roleQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function detail(string $id): ?array
    {
        $role = $this->roleQuery(['id' => $id])->find();

        return is_array($role) && $role !== [] ? $role : null;
    }

    public function ownResource(string $id): array
    {
        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::ROLE_HAS_RESOURCE, 'menuId'),
        ];
    }

    public function grantResource(array $input): array
    {
        return $this->saveGrant(
            $input,
            self::ROLE_HAS_RESOURCE,
            $this->resourceGrantInfoList($this->requiredGrantInfoList($input)),
            'menuId',
            function (array $role, array $grantInfoList): void {
                $this->assertActiveResourceGrantInfo($grantInfoList, 'sys_resource');
                $this->assertSystemModuleResourceGrant($role, $grantInfoList);
            }
        );
    }

    public function ownMobileMenu(string $id): array
    {
        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::ROLE_HAS_MOBILE_MENU, 'menuId'),
        ];
    }

    public function grantMobileMenu(array $input): array
    {
        return $this->saveGrant(
            $input,
            self::ROLE_HAS_MOBILE_MENU,
            $this->resourceGrantInfoList($this->requiredGrantInfoList($input)),
            'menuId',
            function (array $role, array $grantInfoList): void {
                $this->assertActiveResourceGrantInfo($grantInfoList, 'mobile_resource');
            }
        );
    }

    public function ownPermission(string $id): array
    {
        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::ROLE_HAS_PERMISSION, 'apiUrl'),
        ];
    }

    public function grantPermission(array $input): array
    {
        return $this->saveGrant(
            $input,
            self::ROLE_HAS_PERMISSION,
            $this->permissionGrantInfoList($this->requiredGrantInfoList($input)),
            'apiUrl',
            function (array $role, array $grantInfoList): void {
                $this->assertActivePermissionScopeOrgs($grantInfoList);
            }
        );
    }

    /**
     * @return array<int, string>
     */
    public function ownUser(string $id): array
    {
        return array_values(array_filter(Db::name('sys_relation')
            ->where('TARGET_ID', $id)
            ->where('CATEGORY', self::USER_HAS_ROLE)
            ->column('OBJECT_ID')));
    }

    public function grantUser(array $input): array
    {
        $id = $this->requiredInput($input, 'id');
        if (!array_key_exists('grantInfoList', $input)) {
            throw new RuntimeException('missing grantInfoList', 400);
        }

        $role = $this->activeRoleRow($id);
        $userIds = $this->stringList($input['grantInfoList']);
        if ($userIds === [] && $this->isBuiltInRole($role)) {
            throw new RuntimeException('built-in role must keep at least one user', 400);
        }

        $this->assertActiveUsers($userIds);

        return Db::transaction(function () use ($id, $userIds): array {
            Db::name('sys_relation')
                ->where('TARGET_ID', $id)
                ->where('CATEGORY', self::USER_HAS_ROLE)
                ->delete();

            $rows = [];
            foreach ($userIds as $userId) {
                $rows[] = [
                    'ID' => $this->newId(),
                    'OBJECT_ID' => $userId,
                    'TARGET_ID' => $id,
                    'CATEGORY' => self::USER_HAS_ROLE,
                    'EXT_JSON' => null,
                ];
            }

            if ($rows !== []) {
                Db::name('sys_relation')->insertAll($rows);
            }

            return [
                'id' => $id,
                'grantInfoList' => $userIds,
                'count' => count($userIds),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orgTreeSelector(array $filters = []): array
    {
        return $this->orgService->selector($filters);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resourceTreeSelector(): array
    {
        return $this->grantTree('sys_resource');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mobileMenuTreeSelector(): array
    {
        return $this->grantTree('mobile_resource');
    }

    /**
     * @return array<int, string>
     */
    public function permissionTreeSelector(): array
    {
        return array_values(array_filter(Db::name('sys_relation')
            ->whereIn('CATEGORY', [self::ROLE_HAS_PERMISSION, self::USER_HAS_PERMISSION])
            ->distinct(true)
            ->order('TARGET_ID', 'asc')
            ->column('TARGET_ID')));
    }

    public function roleSelector(array $filters = []): array
    {
        $page = $this->page($filters);
        $page['records'] = array_map(static function (array $row): array {
            return [
                'id' => $row['ID'] ?? null,
                'orgId' => $row['ORG_ID'] ?? null,
                'name' => $row['NAME'] ?? null,
                'code' => $row['CODE'] ?? null,
                'category' => $row['CATEGORY'] ?? null,
                'sortCode' => $row['SORT_CODE'] ?? null,
            ];
        }, $page['records']);

        return $page;
    }

    public function userSelector(array $filters = []): array
    {
        return $this->userDirectoryService->page($filters);
    }

    private function roleQuery(array $filters)
    {
        $query = Db::name('sys_role')->where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grantInfoList(string $id, string $category, string $targetKey): array
    {
        $relations = Db::name('sys_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', $category)
            ->select()
            ->toArray();

        return array_map(function (array $relation) use ($targetKey): array {
            $decoded = $this->decodeExtJson((string)($relation['EXT_JSON'] ?? ''));
            if ($decoded === []) {
                $decoded[$targetKey] = $relation['TARGET_ID'] ?? null;
            }

            return $decoded;
        }, $relations);
    }

    /**
     * @param array<int, array<string, mixed>> $grantInfoList
     */
    private function saveGrant(array $input, string $category, array $grantInfoList, string $targetKey, callable $validator): array
    {
        $id = $this->requiredInput($input, 'id');
        $role = $this->activeRoleRow($id);
        $validator($role, $grantInfoList);

        return Db::transaction(function () use ($id, $category, $grantInfoList, $targetKey): array {
            Db::name('sys_relation')
                ->where('OBJECT_ID', $id)
                ->where('CATEGORY', $category)
                ->delete();

            $rows = [];
            foreach ($grantInfoList as $grantInfo) {
                $targetId = trim((string)($grantInfo[$targetKey] ?? ''));
                if ($targetId === '') {
                    continue;
                }

                $extJson = json_encode($grantInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $rows[] = [
                    'ID' => $this->newId(),
                    'OBJECT_ID' => $id,
                    'TARGET_ID' => $targetId,
                    'CATEGORY' => $category,
                    'EXT_JSON' => $extJson === false ? '{}' : $extJson,
                ];
            }

            if ($rows !== []) {
                Db::name('sys_relation')->insertAll($rows);
            }

            return [
                'id' => $id,
                'grantInfoList' => $grantInfoList,
                'count' => count($grantInfoList),
            ];
        });
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function requiredGrantInfoList(array $input): array
    {
        if (!array_key_exists('grantInfoList', $input)) {
            throw new RuntimeException('missing grantInfoList', 400);
        }
        if (!is_array($input['grantInfoList'])) {
            throw new RuntimeException('invalid grantInfoList', 400);
        }

        return $input['grantInfoList'];
    }

    private function activeRoleRow(string $id): array
    {
        $role = $this->roleQuery(['id' => $id])->find();
        if (!is_array($role) || $role === []) {
            throw new RuntimeException('role not found', 404);
        }

        return $role;
    }

    private function isBuiltInRole(array $role): bool
    {
        return in_array(strtolower((string)($role['CODE'] ?? '')), self::BUILD_IN_ROLE_CODES, true);
    }

    /**
     * @param array<int, mixed> $value
     * @return array<int, array{menuId: string, buttonInfo: array<int, string>}>
     */
    private function resourceGrantInfoList(array $value): array
    {
        $byMenuId = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid grantInfoList', 400);
            }

            $menuId = trim((string)($item['menuId'] ?? $item['menu_id'] ?? $item['id'] ?? ''));
            if ($menuId === '') {
                throw new RuntimeException('missing menuId', 400);
            }
            if (!array_key_exists('buttonInfo', $item) && !array_key_exists('button_info', $item)) {
                throw new RuntimeException('missing buttonInfo', 400);
            }

            $buttonInfo = $this->stringList($item['buttonInfo'] ?? $item['button_info'] ?? []);
            $byMenuId[$menuId] = [
                'menuId' => $menuId,
                'buttonInfo' => array_values(array_unique(array_merge($byMenuId[$menuId]['buttonInfo'] ?? [], $buttonInfo))),
            ];
        }

        return array_values($byMenuId);
    }

    /**
     * @param array<int, mixed> $value
     * @return array<int, array{apiUrl: string, scopeCategory: string, scopeDefineOrgIdList: array<int, string>}>
     */
    private function permissionGrantInfoList(array $value): array
    {
        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid grantInfoList', 400);
            }

            $apiUrl = trim((string)($item['apiUrl'] ?? $item['api_url'] ?? ''));
            if ($apiUrl === '') {
                throw new RuntimeException('missing apiUrl', 400);
            }

            $scopeCategory = trim((string)($item['scopeCategory'] ?? $item['scope_category'] ?? ''));
            if (!in_array($scopeCategory, self::SCOPE_CATEGORIES, true)) {
                throw new RuntimeException('invalid scopeCategory', 400);
            }

            $items[$apiUrl] = [
                'apiUrl' => $apiUrl,
                'scopeCategory' => $scopeCategory,
                'scopeDefineOrgIdList' => $this->stringList($item['scopeDefineOrgIdList'] ?? $item['scope_define_org_id_list'] ?? []),
            ];
        }

        return array_values($items);
    }

    /**
     * @param array<int, array{menuId: string, buttonInfo: array<int, string>}> $grantInfoList
     */
    private function assertActiveResourceGrantInfo(array $grantInfoList, string $table): void
    {
        $menuIds = array_values(array_unique(array_map(static fn (array $grantInfo): string => $grantInfo['menuId'], $grantInfoList)));
        $buttonIds = [];
        foreach ($grantInfoList as $grantInfo) {
            $buttonIds = array_merge($buttonIds, $grantInfo['buttonInfo']);
        }

        $this->assertActiveIds($table, $menuIds, 'menu resource not found');
        $this->assertActiveIds($table, array_values(array_unique($buttonIds)), 'button resource not found');
    }

    /**
     * @param array<int, array{apiUrl: string, scopeCategory: string, scopeDefineOrgIdList: array<int, string>}> $grantInfoList
     */
    private function assertActivePermissionScopeOrgs(array $grantInfoList): void
    {
        $orgIds = [];
        foreach ($grantInfoList as $grantInfo) {
            if ($grantInfo['scopeCategory'] === 'SCOPE_ORG_DEFINE') {
                $orgIds = array_merge($orgIds, $grantInfo['scopeDefineOrgIdList']);
            }
        }

        $this->assertActiveIds('sys_org', array_values(array_unique($orgIds)), 'scope organization not found');
    }

    /**
     * @param array<int, string> $ids
     */
    private function assertActiveIds(string $table, array $ids, string $message): void
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return;
        }

        $validIds = array_values(array_filter(array_map('strval', Db::name($table)
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID'))));
        $missing = array_values(array_diff($ids, $validIds));
        if ($missing !== []) {
            throw new RuntimeException($message, 404);
        }
    }

    /**
     * @param array<int, array{menuId: string, buttonInfo: array<int, string>}> $grantInfoList
     */
    private function assertSystemModuleResourceGrant(array $role, array $grantInfoList): void
    {
        if ($this->isBuiltInRole($role)) {
            return;
        }

        $menuIds = array_values(array_unique(array_map(static fn (array $grantInfo): string => $grantInfo['menuId'], $grantInfoList)));
        if ($menuIds === []) {
            return;
        }

        $rows = Db::name('sys_resource')
            ->whereIn('ID', $menuIds)
            ->field(['ID', 'MODULE', 'CODE', 'CATEGORY'])
            ->select()
            ->toArray();
        $moduleIds = [];
        foreach ($rows as $row) {
            if ((string)($row['CATEGORY'] ?? '') === self::CATEGORY_MODULE) {
                $moduleIds[] = (string)($row['ID'] ?? '');
            }
            $moduleIds[] = (string)($row['MODULE'] ?? '');
        }
        $moduleIds = array_values(array_filter(array_unique($moduleIds)));
        if ($moduleIds === []) {
            return;
        }

        $moduleCodes = array_map('strtolower', array_filter(array_map('strval', Db::name('sys_resource')
            ->whereIn('ID', $moduleIds)
            ->column('CODE'))));
        if (in_array('system', $moduleCodes, true)) {
            throw new RuntimeException('non-super roles cannot be granted system module resources', 400);
        }
    }

    /**
     * @param array<int, string> $userIds
     */
    private function assertActiveUsers(array $userIds): void
    {
        $this->assertActiveIds('sys_user', $userIds, 'user not found');
    }

    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $id = trim((string)($item['id'] ?? $item['userId'] ?? $item['roleId'] ?? $item['value'] ?? $item['key'] ?? ''));
            } else {
                $id = trim((string)$item);
            }
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grantTree(string $table): array
    {
        $rows = Db::name($table)
            ->whereIn('CATEGORY', [self::CATEGORY_MODULE, self::CATEGORY_MENU, self::CATEGORY_BUTTON])
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        $modules = [];
        $menus = [];
        $buttonsByMenu = [];

        foreach ($rows as $row) {
            $category = (string)($row['CATEGORY'] ?? '');
            if ($category === self::CATEGORY_MODULE) {
                $modules[] = $row;
                continue;
            }

            if ($category === self::CATEGORY_MENU) {
                $menus[] = $row;
                continue;
            }

            if ($category === self::CATEGORY_BUTTON) {
                $buttonsByMenu[(string)($row['PARENT_ID'] ?? '')][] = $this->resourceNode($row);
            }
        }

        $menusByModule = [];
        foreach ($menus as $menu) {
            if (($menu['MENU_TYPE'] ?? '') === self::MENU_TYPE_CATALOG) {
                continue;
            }

            $menuNode = $this->resourceNode($menu);
            $menuNode['button'] = $buttonsByMenu[(string)($menu['ID'] ?? '')] ?? [];
            $menusByModule[(string)($menu['MODULE'] ?? '')][] = $menuNode;
        }

        return array_map(function (array $module) use ($menusByModule): array {
            $node = $this->resourceNode($module);
            $node['menu'] = $menusByModule[(string)($module['ID'] ?? '')] ?? [];

            return $node;
        }, $modules);
    }

    private function resourceNode(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'title' => $row['TITLE'] ?? $row['NAME'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'module' => $row['MODULE'] ?? null,
            'menuType' => $row['MENU_TYPE'] ?? null,
            'path' => $row['PATH'] ?? null,
            'icon' => $row['ICON'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
        ];
    }

    private function decodeExtJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
