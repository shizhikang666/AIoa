<?php

declare(strict_types=1);

namespace app\service\sys;

use app\model\SysResource;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only resource queries compatible with Java SysModule/SysMenu/SysButton controllers.
 */
class ResourceService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const CATEGORY_MODULE = 'MODULE';
    private const CATEGORY_MENU = 'MENU';
    private const CATEGORY_BUTTON = 'BUTTON';
    private const CATEGORY_FIELD = 'FIELD';
    private const RELATION_ROLE_HAS_RESOURCE = 'SYS_ROLE_HAS_RESOURCE';
    private const BUILT_IN_MODULE_CODES = ['system', 'tenant'];
    private const ROOT_PARENT_ID = '0';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'title' => 'TITLE',
        'name' => 'NAME',
        'code' => 'CODE',
        'category' => 'CATEGORY',
        'module' => 'MODULE',
        'parentId' => 'PARENT_ID',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function modulePage(array $filters = []): array
    {
        return $this->page(self::CATEGORY_MODULE, $filters);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function moduleAdd(array $input, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->moduleWriteData($input);
        $this->assertDuplicateModuleTitle($data['TITLE'], null);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $row = array_merge($data, [
            'ID' => $this->newId(),
            'CODE' => $this->newResourceCode(self::CATEGORY_MODULE),
            'CATEGORY' => self::CATEGORY_MODULE,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')->insert($row);

        return $this->resourceRow($row);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function moduleEdit(array $input, mixed $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $payload = is_array($payload) ? $payload : [];
        $existing = $this->activeResourceRow($id, self::CATEGORY_MODULE);
        $data = $this->moduleWriteData($input, $existing);
        $this->assertDuplicateModuleTitle($data['TITLE'], $id);

        $operatorId = $this->payloadUserId($payload);
        $update = array_merge($data, [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')
            ->where('ID', $id)
            ->where('CATEGORY', self::CATEGORY_MODULE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        return $this->resourceRow($this->activeResourceRow($id, self::CATEGORY_MODULE));
    }

    /**
     * @param array<int, mixed> $ids
     * @param array<string, mixed>|mixed $payload
     */
    public function moduleDelete(array $ids, mixed $payload = []): array
    {
        $idList = $this->idInputList($ids, 'moduleId');
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $rows = $this->activeResourceRows($idList, self::CATEGORY_MODULE);
        foreach ($rows as $row) {
            $code = strtolower(trim((string)($row['CODE'] ?? '')));
            if (in_array($code, self::BUILT_IN_MODULE_CODES, true)) {
                throw new RuntimeException('built-in module cannot be deleted', 400);
            }
        }

        $toDeleteIds = $this->moduleResourceIdsForDelete($idList);
        $operatorId = $this->payloadUserId($payload);

        $updated = Db::transaction(function () use ($toDeleteIds, $operatorId): int {
            Db::name('sys_relation')
                ->where('CATEGORY', self::RELATION_ROLE_HAS_RESOURCE)
                ->whereIn('TARGET_ID', $toDeleteIds)
                ->delete();

            return Db::name('sys_resource')
                ->whereIn('ID', $toDeleteIds)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                ]);
        });

        return [
            'ids' => $toDeleteIds,
            'count' => $updated,
        ];
    }

    public function menuPage(array $filters = []): array
    {
        return $this->page(self::CATEGORY_MENU, $filters);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function menuAdd(array $input, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->menuWriteData($input);
        $this->assertDuplicateMenuTitle($data['PARENT_ID'], $data['TITLE'], null);
        $this->assertMenuModuleAndParent($data['MODULE'], $data['PARENT_ID']);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $row = array_merge($data, [
            'ID' => $this->newId(),
            'CATEGORY' => self::CATEGORY_MENU,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')->insert($row);

        return $this->resourceRow($row);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function menuEdit(array $input, mixed $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $payload = is_array($payload) ? $payload : [];
        $existing = $this->activeResourceRow($id, self::CATEGORY_MENU);
        $data = $this->menuWriteData($input, $existing);
        $this->assertDuplicateMenuTitle($data['PARENT_ID'], $data['TITLE'], $id);
        $this->assertMenuParentIsNotChild($id, $data['PARENT_ID']);
        $this->assertMenuModuleAndParent($data['MODULE'], $data['PARENT_ID']);

        $operatorId = $this->payloadUserId($payload);
        $update = array_merge($data, [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')
            ->where('ID', $id)
            ->where('CATEGORY', self::CATEGORY_MENU)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        return $this->resourceRow($this->activeResourceRow($id, self::CATEGORY_MENU));
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function menuChangeModule(array $input, mixed $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $module = $this->requiredInput($input, ['module', 'MODULE'], 'module');
        $payload = is_array($payload) ? $payload : [];
        $row = $this->activeResourceRow($id, self::CATEGORY_MENU);
        if ((string)($row['PARENT_ID'] ?? self::ROOT_PARENT_ID) !== self::ROOT_PARENT_ID) {
            throw new RuntimeException('non-root menu cannot change module', 400);
        }

        $toUpdateIds = $this->resourceTreeIdsForDelete([$id], [self::CATEGORY_MENU]);
        if ($toUpdateIds === []) {
            throw new RuntimeException('menu not found', 404);
        }

        $operatorId = $this->payloadUserId($payload);
        $updated = Db::name('sys_resource')
            ->whereIn('ID', $toUpdateIds)
            ->where('CATEGORY', self::CATEGORY_MENU)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'MODULE' => $module,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
            ]);

        return [
            'ids' => $toUpdateIds,
            'module' => $module,
            'count' => $updated,
        ];
    }

    /**
     * @param array<int, mixed> $ids
     * @param array<string, mixed>|mixed $payload
     */
    public function menuDelete(array $ids, mixed $payload = []): array
    {
        $idList = $this->idInputList($ids, 'menuId');
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $toDeleteIds = $this->resourceTreeIdsForDelete($idList, [self::CATEGORY_MENU, self::CATEGORY_BUTTON]);
        if ($toDeleteIds === []) {
            return [
                'ids' => $idList,
                'count' => 0,
            ];
        }

        $operatorId = $this->payloadUserId($payload);
        $updated = Db::transaction(function () use ($toDeleteIds, $operatorId): int {
            Db::name('sys_relation')
                ->where('CATEGORY', self::RELATION_ROLE_HAS_RESOURCE)
                ->whereIn('TARGET_ID', $toDeleteIds)
                ->delete();

            return Db::name('sys_resource')
                ->whereIn('ID', $toDeleteIds)
                ->whereIn('CATEGORY', [self::CATEGORY_MENU, self::CATEGORY_BUTTON])
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                ]);
        });

        return [
            'ids' => $idList,
            'deletedIds' => $toDeleteIds,
            'count' => $updated,
        ];
    }

    public function buttonPage(array $filters = []): array
    {
        return $this->page(self::CATEGORY_BUTTON, $filters);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function buttonAdd(array $input, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->buttonWriteData($input);
        $this->assertDuplicateButtonCode($data['CODE'], null);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $row = array_merge($data, [
            'ID' => $this->newId(),
            'CATEGORY' => self::CATEGORY_BUTTON,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')->insert($row);

        return $this->resourceRow($row);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function buttonEdit(array $input, mixed $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $payload = is_array($payload) ? $payload : [];
        $existing = $this->activeButtonRow($id);
        $data = $this->buttonWriteData($input, $existing);
        $this->assertDuplicateButtonCode($data['CODE'], $id);

        $operatorId = $this->payloadUserId($payload);
        $update = array_merge($data, [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')
            ->where('ID', $id)
            ->where('CATEGORY', self::CATEGORY_BUTTON)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        return $this->resourceRow($this->activeButtonRow($id));
    }

    /**
     * @param array<int, mixed> $ids
     * @param array<string, mixed>|mixed $payload
     */
    public function buttonDelete(array $ids, mixed $payload = []): array
    {
        $idList = $this->idInputList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $rows = $this->activeButtonRows($idList);
        $parentMenuIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string)($row['PARENT_ID'] ?? '')),
            $rows
        ))));
        $operatorId = $this->payloadUserId($payload);

        $updated = Db::transaction(function () use ($idList, $parentMenuIds, $operatorId): int {
            $this->removeDeletedButtonsFromRoleRelations($idList, $parentMenuIds);

            return Db::name('sys_resource')
                ->whereIn('ID', $idList)
                ->where('CATEGORY', self::CATEGORY_BUTTON)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                ]);
        });

        return [
            'ids' => $idList,
            'count' => $updated,
        ];
    }

    public function fieldPage(array $filters = []): array
    {
        return $this->page(self::CATEGORY_FIELD, $filters);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function fieldAdd(array $input, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->fieldWriteData($input);
        $this->assertFieldParentMenu($data['PARENT_ID']);
        $this->assertDuplicateFieldCode($data['PARENT_ID'], $data['CODE'], null);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $row = array_merge($data, [
            'ID' => $this->newId(),
            'CATEGORY' => self::CATEGORY_FIELD,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')->insert($row);

        return $this->resourceRow($row);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function fieldEdit(array $input, mixed $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $payload = is_array($payload) ? $payload : [];
        $existing = $this->activeResourceRow($id, self::CATEGORY_FIELD);
        $data = $this->fieldWriteData($input, $existing);
        $this->assertFieldParentMenu($data['PARENT_ID']);
        $this->assertDuplicateFieldCode($data['PARENT_ID'], $data['CODE'], $id);

        $operatorId = $this->payloadUserId($payload);
        $update = array_merge($data, [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_resource')
            ->where('ID', $id)
            ->where('CATEGORY', self::CATEGORY_FIELD)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        return $this->resourceRow($this->activeResourceRow($id, self::CATEGORY_FIELD));
    }

    /**
     * @param array<int, mixed> $ids
     * @param array<string, mixed>|mixed $payload
     */
    public function fieldDelete(array $ids, mixed $payload = []): array
    {
        $idList = $this->idInputList($ids, 'fieldId');
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $activeIds = array_values(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            Db::name('sys_resource')
                ->whereIn('ID', $idList)
                ->where('CATEGORY', self::CATEGORY_FIELD)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->column('ID')
        )));
        if ($activeIds === []) {
            return [
                'ids' => $idList,
                'count' => 0,
            ];
        }

        $operatorId = $this->payloadUserId($payload);
        $updated = Db::transaction(function () use ($activeIds, $operatorId): int {
            Db::name('sys_relation')
                ->where('CATEGORY', self::RELATION_ROLE_HAS_RESOURCE)
                ->whereIn('TARGET_ID', $activeIds)
                ->delete();

            return Db::name('sys_resource')
                ->whereIn('ID', $activeIds)
                ->where('CATEGORY', self::CATEGORY_FIELD)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                ]);
        });

        return [
            'ids' => $idList,
            'deletedIds' => $activeIds,
            'count' => $updated,
        ];
    }

    public function detail(string $id, string $category): ?array
    {
        $row = $this->resourceQuery($category, ['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->resourceRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function menuTree(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_MENU, $filters, false)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        if (!empty($filters['searchKey'])) {
            $rows = $this->withParentMenus($rows);
        }

        return $this->buildTree($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function moduleSelector(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_MODULE, $filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->selectorRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function menuTreeSelector(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_MENU, $filters, false)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fieldTree(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_FIELD, $filters, false)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows);
    }

    private function page(string $category, array $filters): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->resourceQuery($category, $filters)->count();
        $rows = $this->applySort($this->resourceQuery($category, $filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->resourceRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function resourceQuery(string $category, array $filters = [], bool $moduleLike = true)
    {
        $query = SysResource::where('CATEGORY', $category)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $query->where('PARENT_ID', (string)$filters['parentId']);
        }

        if (!empty($filters['module'])) {
            $module = trim((string)$filters['module']);
            $moduleLike ? $query->whereLike('MODULE', '%' . $module . '%') : $query->where('MODULE', $module);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('TITLE', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['title'])) {
            $query->whereLike('TITLE', '%' . trim((string)$filters['title']) . '%');
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

        return $query->order(['SORT_CODE' => 'asc', 'ID' => 'asc']);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function withParentMenus(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = (string)($row['ID'] ?? '');
            if ($id !== '') {
                $byId[$id] = $row;
            }
        }

        while (true) {
            $parentIds = [];
            foreach ($byId as $row) {
                $parentId = (string)($row['PARENT_ID'] ?? self::ROOT_PARENT_ID);
                if ($parentId !== '' && $parentId !== self::ROOT_PARENT_ID && !isset($byId[$parentId])) {
                    $parentIds[$parentId] = $parentId;
                }
            }

            if ($parentIds === []) {
                break;
            }

            $parents = SysResource::where('CATEGORY', self::CATEGORY_MENU)
                ->whereIn('ID', array_values($parentIds))
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->select()
                ->toArray();

            if ($parents === []) {
                break;
            }

            foreach ($parents as $parent) {
                $byId[(string)$parent['ID']] = $parent;
            }
        }

        return $this->sortRows(array_values($byId));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $rows, bool $selector = false): array
    {
        $rows = $this->sortRows($rows);
        $nodes = [];
        $parentById = [];

        foreach ($rows as $row) {
            $id = (string)($row['ID'] ?? '');
            if ($id === '') {
                continue;
            }

            $node = $selector ? $this->selectorRow($row) : $this->treeRow($row);
            $node['children'] = [];
            $nodes[$id] = $node;
            $parentById[$id] = (string)($row['PARENT_ID'] ?? self::ROOT_PARENT_ID);
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $parentById[$id] ?? self::ROOT_PARENT_ID;
            if ($parentId !== self::ROOT_PARENT_ID && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
                continue;
            }

            $tree[] = &$node;
        }
        unset($node);

        return $tree;
    }

    private function treeRow(array $row): array
    {
        $resource = $this->resourceRow($row);
        $resource['label'] = $resource['title'];
        $resource['value'] = $resource['id'];
        $resource['weight'] = $resource['sortCode'];

        return $resource;
    }

    private function selectorRow(array $row): array
    {
        $resource = $this->resourceRow($row);
        $resource['label'] = $resource['title'];
        $resource['value'] = $resource['id'];
        $resource['weight'] = $resource['sortCode'];

        return $resource;
    }

    private function resourceRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'title' => $row['TITLE'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'module' => $row['MODULE'] ?? null,
            'menuType' => $row['MENU_TYPE'] ?? null,
            'path' => $row['PATH'] ?? null,
            'component' => $row['COMPONENT'] ?? null,
            'icon' => $row['ICON'] ?? null,
            'color' => $row['COLOR'] ?? null,
            'visible' => $row['VISIBLE'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function moduleWriteData(array $input, ?array $existing = null): array
    {
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE'], 'sortCode');
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $extJson = array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)
            ? $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null)
            : ($existing['EXT_JSON'] ?? null);

        return [
            'TITLE' => $this->requiredInput($input, ['title', 'TITLE'], 'title'),
            'ICON' => $this->requiredInput($input, ['icon', 'ICON'], 'icon'),
            'COLOR' => $this->requiredInput($input, ['color', 'COLOR'], 'color'),
            'SORT_CODE' => (int)$sortCode,
            'EXT_JSON' => $extJson,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function buttonWriteData(array $input, ?array $existing = null): array
    {
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE'], 'sortCode');
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $extJson = array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)
            ? $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null)
            : ($existing['EXT_JSON'] ?? null);

        return [
            'PARENT_ID' => $this->requiredInput($input, ['parentId', 'PARENT_ID'], 'parentId'),
            'TITLE' => $this->requiredInput($input, ['title', 'TITLE'], 'title'),
            'CODE' => $this->requiredInput($input, ['code', 'CODE'], 'code'),
            'SORT_CODE' => (int)$sortCode,
            'EXT_JSON' => $extJson,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function fieldWriteData(array $input, ?array $existing = null): array
    {
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE'], 'sortCode');
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $extJson = array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)
            ? $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null)
            : ($existing['EXT_JSON'] ?? null);

        return [
            'PARENT_ID' => $this->requiredInput($input, ['parentId', 'PARENT_ID'], 'parentId'),
            'TITLE' => $this->requiredInput($input, ['title', 'TITLE'], 'title'),
            'CODE' => $this->requiredInput($input, ['code', 'CODE'], 'code'),
            'SORT_CODE' => (int)$sortCode,
            'EXT_JSON' => $extJson,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function menuWriteData(array $input, ?array $existing = null): array
    {
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE'], 'sortCode');
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $menuType = $this->requiredInput($input, ['menuType', 'MENU_TYPE'], 'menuType');
        if (!in_array($menuType, ['CATALOG', 'MENU', 'IFRAME', 'LINK'], true)) {
            throw new RuntimeException('invalid menuType', 400);
        }

        $name = $this->optionalInput($input, ['name', 'NAME'], (string)($existing['NAME'] ?? ''));
        $component = $this->optionalInput($input, ['component', 'COMPONENT'], (string)($existing['COMPONENT'] ?? ''));
        if ($menuType === 'MENU') {
            if ($name === '') {
                throw new RuntimeException('missing name', 400);
            }
            if ($component === '') {
                throw new RuntimeException('missing component', 400);
            }
        } elseif (in_array($menuType, ['IFRAME', 'LINK'], true)) {
            if ($name === '') {
                $name = (string)random_int(1000000000, 9999999999);
            }
            $component = '';
        } else {
            $name = '';
            $component = '';
        }

        $extJson = array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)
            ? $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null)
            : ($existing['EXT_JSON'] ?? null);

        return [
            'PARENT_ID' => $this->requiredInput($input, ['parentId', 'PARENT_ID'], 'parentId'),
            'TITLE' => $this->requiredInput($input, ['title', 'TITLE'], 'title'),
            'NAME' => $name !== '' ? $name : null,
            'MODULE' => $this->requiredInput($input, ['module', 'MODULE'], 'module'),
            'MENU_TYPE' => $menuType,
            'PATH' => $this->requiredInput($input, ['path', 'PATH'], 'path'),
            'COMPONENT' => $component !== '' ? $component : null,
            'ICON' => $this->optionalInput($input, ['icon', 'ICON'], (string)($existing['ICON'] ?? '')),
            'VISIBLE' => $this->optionalInput($input, ['visible', 'VISIBLE'], (string)($existing['VISIBLE'] ?? '')),
            'SORT_CODE' => (int)$sortCode,
            'EXT_JSON' => $extJson,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredInput(array $input, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<int, string> $keys
     */
    private function optionalInput(array $input, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return trim((string)$input[$key]);
            }
        }

        return $default;
    }

    private function normalizeJsonInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function assertDuplicateButtonCode(string $code, ?string $ignoreId): void
    {
        $query = Db::name('sys_resource')
            ->where('CATEGORY', self::CATEGORY_BUTTON)
            ->where('CODE', $code)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException("duplicate button code: {$code}", 400);
        }
    }

    private function assertDuplicateFieldCode(string $parentId, string $code, ?string $ignoreId): void
    {
        $query = Db::name('sys_resource')
            ->where('CATEGORY', self::CATEGORY_FIELD)
            ->where('PARENT_ID', $parentId)
            ->where('CODE', $code)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException("duplicate field code: {$code}", 400);
        }
    }

    private function assertDuplicateModuleTitle(string $title, ?string $ignoreId): void
    {
        $query = Db::name('sys_resource')
            ->where('CATEGORY', self::CATEGORY_MODULE)
            ->where('TITLE', $title)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException("duplicate module title: {$title}", 400);
        }
    }

    private function assertDuplicateMenuTitle(string $parentId, string $title, ?string $ignoreId): void
    {
        $query = Db::name('sys_resource')
            ->where('CATEGORY', self::CATEGORY_MENU)
            ->where('PARENT_ID', $parentId)
            ->where('TITLE', $title)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException("duplicate menu title: {$title}", 400);
        }
    }

    private function assertMenuModuleAndParent(string $module, string $parentId): void
    {
        if ($parentId === self::ROOT_PARENT_ID) {
            return;
        }

        $parent = $this->activeResourceRow($parentId, self::CATEGORY_MENU);
        if ((string)($parent['MODULE'] ?? '') !== $module) {
            throw new RuntimeException('module does not match parent menu', 400);
        }
    }

    private function assertFieldParentMenu(string $parentId): void
    {
        $this->activeResourceRow($parentId, self::CATEGORY_MENU);
    }

    private function assertMenuParentIsNotChild(string $id, string $parentId): void
    {
        if ($parentId === self::ROOT_PARENT_ID) {
            return;
        }
        if ($parentId === $id) {
            throw new RuntimeException('menu parent cannot be self', 400);
        }

        if (in_array($parentId, $this->resourceTreeIdsForDelete([$id], [self::CATEGORY_MENU]), true)) {
            throw new RuntimeException('menu parent cannot be child', 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function activeResourceRow(string $id, string $category): array
    {
        $row = Db::name('sys_resource')
            ->where('ID', $id)
            ->where('CATEGORY', $category)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!$row) {
            throw new RuntimeException(strtolower($category) . ' not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeResourceRows(array $ids, string $category): array
    {
        $rows = Db::name('sys_resource')
            ->whereIn('ID', $ids)
            ->where('CATEGORY', $category)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $found = array_values(array_filter(array_map(static fn (array $row): string => (string)($row['ID'] ?? ''), $rows)));
        $missing = array_values(array_diff($ids, $found));
        if ($missing !== []) {
            throw new RuntimeException(strtolower($category) . ' not found', 404);
        }

        return $rows;
    }

    /**
     * @param array<int, string> $moduleIds
     * @return array<int, string>
     */
    private function moduleResourceIdsForDelete(array $moduleIds): array
    {
        $deleteMap = array_fill_keys($moduleIds, true);
        $rows = Db::name('sys_resource')
            ->whereIn('CATEGORY', [self::CATEGORY_MENU, self::CATEGORY_BUTTON])
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID', 'MODULE'])
            ->select()
            ->toArray();

        $childrenByParent = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['ID'] ?? ''));
            $parentId = trim((string)($row['PARENT_ID'] ?? ''));
            if ($id === '') {
                continue;
            }
            if (in_array((string)($row['MODULE'] ?? ''), $moduleIds, true)) {
                $deleteMap[$id] = true;
            }
            if ($parentId !== '') {
                $childrenByParent[$parentId][] = $id;
            }
        }

        $queue = array_keys($deleteMap);
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (!isset($deleteMap[$childId])) {
                    $deleteMap[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_map(static fn($id): string => (string)$id, array_keys($deleteMap));
    }

    /**
     * @param array<int, string> $rootIds
     * @param array<int, string> $categories
     * @return array<int, string>
     */
    private function resourceTreeIdsForDelete(array $rootIds, array $categories): array
    {
        $rows = Db::name('sys_resource')
            ->whereIn('CATEGORY', $categories)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID'])
            ->select()
            ->toArray();

        $idMap = [];
        $childrenByParent = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['ID'] ?? ''));
            $parentId = trim((string)($row['PARENT_ID'] ?? ''));
            if ($id === '') {
                continue;
            }
            $idMap[$id] = true;
            if ($parentId !== '') {
                $childrenByParent[$parentId][] = $id;
            }
        }

        $deleteMap = [];
        foreach ($rootIds as $rootId) {
            if (isset($idMap[$rootId])) {
                $deleteMap[$rootId] = true;
            }
        }

        $queue = array_keys($deleteMap);
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (!isset($deleteMap[$childId])) {
                    $deleteMap[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_map(static fn($id): string => (string)$id, array_keys($deleteMap));
    }

    /**
     * @return array<string, mixed>
     */
    private function activeButtonRow(string $id): array
    {
        return $this->activeResourceRow($id, self::CATEGORY_BUTTON);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeButtonRows(array $ids): array
    {
        return $this->activeResourceRows($ids, self::CATEGORY_BUTTON);
    }

    /**
     * @param array<int, string> $buttonIds
     * @param array<int, string> $parentMenuIds
     */
    private function removeDeletedButtonsFromRoleRelations(array $buttonIds, array $parentMenuIds): void
    {
        if ($parentMenuIds === []) {
            return;
        }

        $buttonMap = array_fill_keys($buttonIds, true);
        $relations = Db::name('sys_relation')
            ->where('CATEGORY', self::RELATION_ROLE_HAS_RESOURCE)
            ->whereIn('TARGET_ID', $parentMenuIds)
            ->whereNotNull('EXT_JSON')
            ->select()
            ->toArray();

        foreach ($relations as $relation) {
            $decoded = json_decode((string)($relation['EXT_JSON'] ?? ''), true);
            if (!is_array($decoded) || empty($decoded['buttonInfo']) || !is_array($decoded['buttonInfo'])) {
                continue;
            }

            $buttonInfo = array_values(array_filter(
                array_map(static fn (mixed $id): string => trim((string)$id), $decoded['buttonInfo']),
                static fn (string $id): bool => $id !== '' && !isset($buttonMap[$id])
            ));
            if ($buttonInfo === $decoded['buttonInfo']) {
                continue;
            }

            $decoded['buttonInfo'] = $buttonInfo;
            Db::name('sys_relation')
                ->where('ID', (string)$relation['ID'])
                ->update(['EXT_JSON' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    /**
     * @param array<int, mixed> $value
     * @return array<int, string>
     */
    private function idInputList(array $value, string $arrayKey = 'buttonId'): array
    {
        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $id = trim((string)($item['id'] ?? $item[$arrayKey] ?? $item['buttonId'] ?? $item['value'] ?? $item['key'] ?? ''));
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
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function newResourceCode(string $category): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Db::name('sys_resource')
                ->where('CATEGORY', $category)
                ->where('CODE', $code)
                ->count();
            if ($exists === 0) {
                return $code;
            }
        }

        return substr($this->newId(), -10);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            $leftSort = (int)($left['SORT_CODE'] ?? 0);
            $rightSort = (int)($right['SORT_CODE'] ?? 0);
            if ($leftSort !== $rightSort) {
                return $leftSort <=> $rightSort;
            }

            return strcmp((string)($left['ID'] ?? ''), (string)($right['ID'] ?? ''));
        });

        return $rows;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
