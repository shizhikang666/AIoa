<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysOrg;

/**
 * Read-only organization queries compatible with Java SysOrgService.
 */
class OrgService
{
    private const NOT_DELETE = 'NOT_DELETE';

    public function __construct(private readonly TreeBuilder $treeBuilder = new TreeBuilder())
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        return $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(array $filters = []): array
    {
        return $this->treeBuilder->build($this->all($filters));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function selector(array $filters = []): array
    {
        return $this->treeBuilder->toSelector($this->tree($filters));
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();

        return $row ? $row->toArray() : null;
    }

    private function baseQuery(array $filters)
    {
        $query = SysOrg::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $query->where('PARENT_ID', (string)$filters['parentId']);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }
}
