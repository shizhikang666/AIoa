<?php

declare(strict_types=1);

namespace app\service\auth;

use app\service\user\OrgService;
use app\service\user\UserDirectoryService;
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

    public function ownMobileMenu(string $id): array
    {
        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::ROLE_HAS_MOBILE_MENU, 'menuId'),
        ];
    }

    public function ownPermission(string $id): array
    {
        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::ROLE_HAS_PERMISSION, 'apiUrl'),
        ];
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
}
