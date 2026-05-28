<?php

namespace app\service\auth;

use think\facade\Db;

class MenuService
{
    private const USER_HAS_RESOURCE = 'SYS_USER_HAS_RESOURCE';
    private const ROLE_HAS_RESOURCE = 'SYS_ROLE_HAS_RESOURCE';
    private const CATEGORY_MODULE = 'MODULE';
    private const CATEGORY_MENU = 'MENU';
    private const MENU_TYPE_CATALOG = 'CATALOG';

    public function loginMenu(array $payload): array
    {
        $userId = (string)($payload['user_id'] ?? '');
        if ($userId === '') {
            return [];
        }

        $roleIds = array_values(array_filter(array_map('strval', $payload['role_ids'] ?? [])));
        $objectIds = array_values(array_unique(array_merge([$userId], $roleIds)));
        $ownedMenuIds = $this->ownedMenuIds($objectIds);
        $resources = $this->allModuleAndMenuResources();
        $modules = array_values(array_filter($resources, static fn (array $row): bool => ($row['CATEGORY'] ?? '') === self::CATEGORY_MODULE));
        $menus = array_values(array_filter($resources, static fn (array $row): bool => ($row['CATEGORY'] ?? '') === self::CATEGORY_MENU));

        if ($resources === []) {
            return [];
        }

        $resultById = [];
        foreach ($menus as $menu) {
            $menuId = (string)($menu['ID'] ?? '');
            if ($menuId !== '' && in_array($menuId, $ownedMenuIds, true)) {
                $this->addMenuWithParents($menuId, $menus, $resultById);
            }
        }

        $moduleIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): ?string => isset($row['MODULE']) ? (string)$row['MODULE'] : null,
            $resultById
        ))));
        $moduleList = array_values(array_filter(
            $modules,
            static fn (array $module): bool => in_array((string)($module['ID'] ?? ''), $moduleIds, true)
        ));

        if ($moduleList === [] && $modules !== []) {
            $moduleList[] = $modules[0];
        }

        foreach ($moduleList as $module) {
            $module['PARENT_ID'] = '0';
            $module['PATH'] = $module['PATH'] ?: '/' . strtolower((string)($module['CODE'] ?? $module['ID']));
            $resultById[(string)$module['ID']] = $module;
        }

        $firstMenuId = $this->firstMenuId($resultById, (string)($moduleList[0]['ID'] ?? ''));
        $nodes = array_map(
            fn (array $resource): array => $this->normalizeResource($resource, $firstMenuId),
            array_values($resultById)
        );

        usort($nodes, static fn (array $a, array $b): int => ($a['sortCode'] ?? 0) <=> ($b['sortCode'] ?? 0));

        return $this->buildTree($nodes);
    }

    private function ownedMenuIds(array $objectIds): array
    {
        if ($objectIds === []) {
            return [];
        }

        return array_values(array_unique(array_filter(Db::name('sys_relation')
            ->whereIn('OBJECT_ID', $objectIds)
            ->whereIn('CATEGORY', [self::USER_HAS_RESOURCE, self::ROLE_HAS_RESOURCE])
            ->column('TARGET_ID'))));
    }

    private function allModuleAndMenuResources(): array
    {
        return Db::name('sys_resource')
            ->whereIn('CATEGORY', [self::CATEGORY_MODULE, self::CATEGORY_MENU])
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
            })
            ->order('CATEGORY', 'asc')
            ->order('SORT_CODE', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @param array<string, array<string, mixed>> $resultById
     */
    private function addMenuWithParents(string $menuId, array $menus, array &$resultById): void
    {
        $menuById = [];
        foreach ($menus as $menu) {
            $menuById[(string)($menu['ID'] ?? '')] = $menu;
        }

        $currentId = $menuId;
        while ($currentId !== '' && isset($menuById[$currentId])) {
            $menu = $menuById[$currentId];
            $resultById[$currentId] = $menu;
            $parentId = (string)($menu['PARENT_ID'] ?? '');
            if ($parentId === '' || $parentId === '0') {
                break;
            }

            $currentId = $parentId;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $resourcesById
     */
    private function firstMenuId(array $resourcesById, string $moduleId): ?string
    {
        foreach ($resourcesById as $resource) {
            if (($resource['CATEGORY'] ?? '') !== self::CATEGORY_MENU) {
                continue;
            }

            if ((string)($resource['MODULE'] ?? '') !== $moduleId) {
                continue;
            }

            if (($resource['MENU_TYPE'] ?? '') === self::MENU_TYPE_CATALOG) {
                continue;
            }

            return (string)($resource['ID'] ?? '');
        }

        return null;
    }

    private function normalizeResource(array $resource, ?string $firstMenuId): array
    {
        $category = (string)($resource['CATEGORY'] ?? '');
        $menuType = (string)($resource['MENU_TYPE'] ?? '');
        $parentId = (string)($resource['PARENT_ID'] ?? '');

        if ($category === self::CATEGORY_MODULE) {
            $parentId = '0';
        } elseif ($category === self::CATEGORY_MENU && $parentId === '0') {
            $parentId = (string)($resource['MODULE'] ?? '0');
        }

        $meta = [
            'icon' => $resource['ICON'] ?? null,
            'title' => $resource['TITLE'] ?? null,
            'type' => strtolower($menuType !== '' ? $menuType : $category),
        ];

        if ($category === self::CATEGORY_MENU && $menuType !== self::MENU_TYPE_CATALOG) {
            if (($resource['VISIBLE'] ?? null) === 'FALSE') {
                $meta['hidden'] = true;
            }
            if ($firstMenuId !== null && (string)($resource['ID'] ?? '') === $firstMenuId) {
                $meta['affix'] = true;
            }
        }

        return [
            'id' => (string)($resource['ID'] ?? ''),
            'parentId' => $parentId,
            'title' => $resource['TITLE'] ?? null,
            'name' => $resource['NAME'] ?? null,
            'code' => $resource['CODE'] ?? null,
            'category' => $category,
            'module' => $resource['MODULE'] ?? null,
            'menuType' => $menuType,
            'path' => $resource['PATH'] ?? null,
            'component' => $resource['COMPONENT'] ?? null,
            'icon' => $resource['ICON'] ?? null,
            'color' => $resource['COLOR'] ?? null,
            'visible' => $resource['VISIBLE'] ?? null,
            'sortCode' => (int)($resource['SORT_CODE'] ?? 0),
            'extJson' => $resource['EXT_JSON'] ?? null,
            'meta' => $meta,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $nodes): array
    {
        $byId = [];
        foreach ($nodes as $node) {
            $node['children'] = [];
            $byId[(string)$node['id']] = $node;
        }

        $tree = [];
        foreach (array_keys($byId) as $id) {
            $parentId = (string)($byId[$id]['parentId'] ?? '0');
            if ($parentId !== '0' && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$byId[$id];
                continue;
            }

            $tree[] = &$byId[$id];
        }

        unset($node);

        return $this->sortTree($tree);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function sortTree(array $nodes): array
    {
        usort($nodes, static fn (array $a, array $b): int => ($a['sortCode'] ?? 0) <=> ($b['sortCode'] ?? 0));

        foreach ($nodes as &$node) {
            if (($node['children'] ?? []) !== []) {
                $node['children'] = $this->sortTree($node['children']);
            }
        }

        unset($node);

        return $nodes;
    }
}
