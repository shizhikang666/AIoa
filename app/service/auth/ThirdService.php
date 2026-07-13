<?php

declare(strict_types=1);

namespace app\service\auth;

use think\facade\Db;

/**
 * Read-only third-party user binding queries compatible with Java AuthThirdController.
 */
class ThirdService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'thirdId' => 'THIRD_ID',
        'userId' => 'USER_ID',
        'name' => 'NAME',
        'nickname' => 'NICKNAME',
        'gender' => 'GENDER',
        'category' => 'CATEGORY',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->thirdQuery($filters)->count();
        $rows = $this->applySort($this->thirdQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->thirdRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function thirdQuery(array $filters)
    {
        $query = Db::name('auth_third_user')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', trim((string)$filters['category']));
        }

        if (!empty($filters['searchKey'])) {
            $searchKey = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($searchKey): void {
                $query->whereLike('NAME', $searchKey)
                    ->whereOr('NICKNAME', 'like', $searchKey);
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

        return $query->order('CREATE_TIME', 'desc')->order('ID', 'asc');
    }

    private function thirdRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'thirdId' => $row['THIRD_ID'] ?? null,
            'userId' => $row['USER_ID'] ?? null,
            'avatar' => $row['AVATAR'] ?? null,
            'name' => $row['NAME'] ?? null,
            'nickname' => $row['NICKNAME'] ?? null,
            'gender' => $row['GENDER'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
