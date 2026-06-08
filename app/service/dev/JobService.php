<?php

declare(strict_types=1);

namespace app\service\dev;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only scheduled job queries compatible with Java DevJobController.
 */
class JobService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'name' => 'NAME',
        'code' => 'CODE',
        'category' => 'CATEGORY',
        'actionClass' => 'ACTION_CLASS',
        'cronExpression' => 'CRON_EXPRESSION',
        'jobStatus' => 'JOB_STATUS',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->jobQuery($filters)->count();
        $rows = $this->applySort($this->jobQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->jobRow($row), $rows),
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
    public function list(array $filters = []): array
    {
        $rows = $this->applySort($this->jobQuery($filters), $filters)
            ->limit(500)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->jobRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->jobQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->jobRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<int, string> $ids
     * @param array<string, mixed> $payload
     */
    public function delete(array $ids, array $payload = []): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $rows = Db::name('dev_job')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('job not found', 404);
        }

        Db::name('dev_job')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->payloadUserId($payload),
            ]);

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function actionClasses(): array
    {
        $classes = Db::name('dev_job')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->whereNotNull('ACTION_CLASS')
            ->where('ACTION_CLASS', '<>', '')
            ->order('ACTION_CLASS', 'asc')
            ->column('ACTION_CLASS');

        return array_values(array_unique(array_map('strval', $classes)));
    }

    private function jobQuery(array $filters)
    {
        $query = Db::name('dev_job')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['jobStatus'])) {
            $query->whereLike('JOB_STATUS', '%' . trim((string)$filters['jobStatus']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
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

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    private function jobRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'actionClass' => $row['ACTION_CLASS'] ?? null,
            'cronExpression' => $row['CRON_EXPRESSION'] ?? null,
            'jobStatus' => $row['JOB_STATUS'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function payloadUserId(array $payload): ?string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));

        return $userId === '' ? null : $userId;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
