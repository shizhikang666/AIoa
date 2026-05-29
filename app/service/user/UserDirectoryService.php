<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysPosition;
use app\model\SysUser;

/**
 * Read-only user directory queries compatible with Java SysUserService selectors.
 */
class UserDirectoryService
{
    private const NOT_DELETE = 'NOT_DELETE';

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
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();

        return $row ? $row->toArray() : null;
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

        return SysUser::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
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

        return SysPosition::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
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
}
