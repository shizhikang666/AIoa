<?php

declare(strict_types=1);

namespace app\service\dev;

use RuntimeException;
use think\facade\Db;

/**
 * Dictionary queries and business dictionary edit compatible with Java DevDictController.
 */
class DictService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ROOT_PARENT_ID = '0';
    private const SYSTEM_CATEGORY = 'FRM';
    private const BIZ_CATEGORY = 'BIZ';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'parentId' => 'PARENT_ID',
        'dictLabel' => 'DICT_LABEL',
        'dictValue' => 'DICT_VALUE',
        'category' => 'CATEGORY',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->dictQuery($filters, $tenantId, true)->count();
        $rows = $this->applySort($this->dictQuery($filters, $tenantId, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->dictRow($row), $rows),
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
    public function list(array $filters = [], ?string $tenantId = null): array
    {
        $rows = $this->dictQuery($filters, $tenantId, true)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->dictRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(array $filters = [], ?string $tenantId = null): array
    {
        $rows = $this->dictQuery($filters, $tenantId, true)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function treeAll(array $filters = []): array
    {
        $rows = $this->dictQuery($filters, null, false)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows);
    }

    public function detail(string $id, ?string $tenantId = null): ?array
    {
        $row = $this->dictQuery(['id' => $id], $tenantId, true)->find();
        if (!$row) {
            return null;
        }

        return $this->dictRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function editBizDict(array $input, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $existing = $this->activeBizDictRow($id);
        $data = $this->bizDictWriteData($input, $existing);

        $this->ensureTenantCompatible($payload, $existing);
        $this->assertBizParent($data['PARENT_ID'], $id, trim((string)($existing['TENANT_ID'] ?? '')));
        $this->assertDuplicateBizLabel($data['PARENT_ID'], $data['DICT_LABEL'], $id);

        $operatorId = $this->payloadUserId($payload);
        $update = [
            'PARENT_ID' => $data['PARENT_ID'],
            'DICT_LABEL' => $data['DICT_LABEL'],
            'SORT_CODE' => $data['SORT_CODE'],
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ];
        if (array_key_exists('EXT_JSON', $data)) {
            $update['EXT_JSON'] = $data['EXT_JSON'];
        }

        Db::name('dev_dict')
            ->where('ID', $id)
            ->where('CATEGORY', self::BIZ_CATEGORY)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        return $this->dictRow($this->activeBizDictRow($id));
    }

    private function dictQuery(array $filters, ?string $tenantId, bool $tenantScoped)
    {
        $query = Db::name('dev_dict')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $parentId = (string)$filters['parentId'];
            $query->where(function ($query) use ($parentId): void {
                $query->where('PARENT_ID', $parentId)->whereOr('ID', '=', $parentId);
            });
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('DICT_LABEL', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if ($tenantScoped) {
            $query->where(function ($query) use ($tenantId): void {
                $query->where('CATEGORY', self::SYSTEM_CATEGORY);
                if ($tenantId !== null && $tenantId !== '') {
                    $query->whereOr('TENANT_ID', '=', $tenantId);
                }
            });
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
    private function buildTree(array $rows): array
    {
        $nodes = [];
        $parentById = [];

        foreach ($rows as $row) {
            $id = (string)($row['ID'] ?? '');
            if ($id === '') {
                continue;
            }

            $node = $this->treeRow($row);
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
        $dict = $this->dictRow($row);
        $dict['name'] = $dict['dictLabel'];
        $dict['label'] = $dict['dictLabel'];
        $dict['value'] = $dict['dictValue'];
        $dict['weight'] = $dict['sortCode'];

        return $dict;
    }

    private function dictRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'dictLabel' => $row['DICT_LABEL'] ?? null,
            'dictValue' => $row['DICT_VALUE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'viewState' => $row['VIEW_STATE'] ?? null,
            'editState' => $row['EDIT_STATE'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(500, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @param array<string, mixed> $input
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
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function bizDictWriteData(array $input, array $existing): array
    {
        $dictLabel = $this->requiredInput($input, ['dictLabel', 'DICT_LABEL', 'label', 'name'], 'dictLabel');
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE', 'weight'], 'sortCode');
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $parentId = array_key_exists('parentId', $input) || array_key_exists('PARENT_ID', $input)
            ? trim((string)($input['parentId'] ?? $input['PARENT_ID'] ?? ''))
            : trim((string)($existing['PARENT_ID'] ?? self::ROOT_PARENT_ID));
        if ($parentId === '') {
            $parentId = self::ROOT_PARENT_ID;
        }

        $data = [
            'PARENT_ID' => $parentId,
            'DICT_LABEL' => $dictLabel,
            'SORT_CODE' => (int)$sortCode,
        ];
        if (array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)) {
            $data['EXT_JSON'] = $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null);
        }

        return $data;
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

    /**
     * @return array<string, mixed>
     */
    private function activeBizDictRow(string $id): array
    {
        $row = Db::name('dev_dict')
            ->where('ID', $id)
            ->where('CATEGORY', self::BIZ_CATEGORY)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('biz dict not found', 404);
        }

        return $row;
    }

    private function assertBizParent(string $parentId, string $id, string $tenantId): void
    {
        if ($parentId === self::ROOT_PARENT_ID) {
            return;
        }
        if ($parentId === $id) {
            throw new RuntimeException('parent cannot be self', 400);
        }

        $parent = Db::name('dev_dict')
            ->where('ID', $parentId)
            ->where('CATEGORY', self::BIZ_CATEGORY)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();
        if (!is_array($parent) || $parent === []) {
            throw new RuntimeException('parent dict not found', 404);
        }

        $parentTenantId = trim((string)($parent['TENANT_ID'] ?? ''));
        if ($tenantId !== '' && $parentTenantId !== '' && $tenantId !== $parentTenantId) {
            throw new RuntimeException('parent tenant mismatch', 403);
        }
    }

    private function assertDuplicateBizLabel(string $parentId, string $dictLabel, string $excludeId): void
    {
        $count = Db::name('dev_dict')
            ->where('CATEGORY', self::BIZ_CATEGORY)
            ->where('PARENT_ID', $parentId)
            ->where('DICT_LABEL', $dictLabel)
            ->where('ID', '<>', $excludeId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->count();
        if ($count > 0) {
            throw new RuntimeException('same-parent biz dict label exists', 400);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $row
     */
    private function ensureTenantCompatible(array $payload, array $row): void
    {
        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $rowTenantId = trim((string)($row['TENANT_ID'] ?? ''));
        if ($payloadTenantId !== '' && $rowTenantId !== '' && $payloadTenantId !== $rowTenantId && !$this->isAdminCompatible($payload)) {
            throw new RuntimeException('permission denied', 403);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isAdminCompatible(array $payload): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? [])
        );
        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            if (in_array($normalized, ['superadmin', 'admin', 'sysadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
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

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $value)));
    }
}
