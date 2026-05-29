<?php

declare(strict_types=1);

namespace app\service\dev;

use DateTimeImmutable;
use think\facade\Db;

/**
 * Read-only log queries compatible with Java DevLogController.
 */
class LogService
{
    private const LOGIN = 'LOGIN';
    private const LOGOUT = 'LOGOUT';
    private const OPERATE = 'OPERATE';
    private const EXCEPTION = 'EXCEPTION';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'category' => 'CATEGORY',
        'name' => 'NAME',
        'exeStatus' => 'EXE_STATUS',
        'opIp' => 'OP_IP',
        'opAddress' => 'OP_ADDRESS',
        'opBrowser' => 'OP_BROWSER',
        'opOs' => 'OP_OS',
        'className' => 'CLASS_NAME',
        'methodName' => 'METHOD_NAME',
        'reqMethod' => 'REQ_METHOD',
        'opTime' => 'OP_TIME',
        'opUser' => 'OP_USER',
        'createTime' => 'CREATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->logQuery($filters, $tenantId)->count();
        $rows = $this->applySort($this->logQuery($filters, $tenantId), $filters)
            ->field([
                'ID',
                'CATEGORY',
                'NAME',
                'EXE_STATUS',
                'OP_IP',
                'OP_ADDRESS',
                'OP_BROWSER',
                'OP_OS',
                'CLASS_NAME',
                'METHOD_NAME',
                'REQ_METHOD',
                'REQ_URL',
                'OP_TIME',
                'OP_USER',
                'CREATE_TIME',
                'CREATE_USER',
                'UPDATE_TIME',
                'UPDATE_USER',
                'TENANT_ID',
            ])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->logRow($row, false), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id, ?string $tenantId = null): ?array
    {
        $row = $this->logQuery(['id' => $id], $tenantId)->find();
        if (!$row) {
            return null;
        }

        return $this->logRow(is_array($row) ? $row : $row->toArray(), true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function visLineChartData(?string $tenantId = null): array
    {
        $start = new DateTimeImmutable('-7 days');
        $end = new DateTimeImmutable('now');
        $rows = $this->chartRows([self::LOGIN, self::LOGOUT], $start, $end, $tenantId);
        $grouped = $this->groupCountsByDate($rows);
        $result = [];

        for ($i = 1; $i <= 7; $i++) {
            $date = $start->modify("+{$i} days")->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'loginCount' => $grouped[$date][self::LOGIN] ?? 0,
                'logoutCount' => $grouped[$date][self::LOGOUT] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function visPieChartData(?string $tenantId = null): array
    {
        return [
            ['type' => '登录', 'value' => $this->countByCategory(self::LOGIN, $tenantId)],
            ['type' => '登出', 'value' => $this->countByCategory(self::LOGOUT, $tenantId)],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function opBarChartData(?string $tenantId = null): array
    {
        $start = new DateTimeImmutable('-7 days');
        $end = new DateTimeImmutable('now');
        $rows = $this->chartRows([self::OPERATE, self::EXCEPTION], $start, $end, $tenantId);
        $grouped = $this->groupCountsByDate($rows);
        $result = [];

        for ($i = 1; $i <= 7; $i++) {
            $date = $start->modify("+{$i} days")->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'name' => '操作日志',
                'count' => $grouped[$date][self::OPERATE] ?? 0,
            ];
            $result[] = [
                'date' => $date,
                'name' => '异常日志',
                'count' => $grouped[$date][self::EXCEPTION] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function opPieChartData(?string $tenantId = null): array
    {
        return [
            ['type' => '操作日志', 'value' => $this->countByCategory(self::OPERATE, $tenantId)],
            ['type' => '异常日志', 'value' => $this->countByCategory(self::EXCEPTION, $tenantId)],
        ];
    }

    private function logQuery(array $filters = [], ?string $tenantId = null)
    {
        $query = Db::name('dev_log');

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
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

        return $query->order('CREATE_TIME', 'desc')->order('ID', 'desc');
    }

    /**
     * @param array<int, string> $categories
     * @return array<int, array<string, mixed>>
     */
    private function chartRows(array $categories, DateTimeImmutable $start, DateTimeImmutable $end, ?string $tenantId): array
    {
        $query = Db::name('dev_log')
            ->field(['CATEGORY', 'OP_TIME'])
            ->whereIn('CATEGORY', $categories)
            ->whereBetweenTime('OP_TIME', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'))
            ->order('OP_TIME', 'asc');

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query->select()->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, int>>
     */
    private function groupCountsByDate(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $time = (string)($row['OP_TIME'] ?? '');
            if ($time === '') {
                continue;
            }

            $date = substr($time, 0, 10);
            $category = (string)($row['CATEGORY'] ?? '');
            if ($date === '' || $category === '') {
                continue;
            }

            $grouped[$date][$category] = ($grouped[$date][$category] ?? 0) + 1;
        }

        return $grouped;
    }

    private function countByCategory(string $category, ?string $tenantId): int
    {
        $query = Db::name('dev_log')->where('CATEGORY', $category);
        if ($tenantId !== null && $tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count();
    }

    private function logRow(array $row, bool $includeLargeFields): array
    {
        $data = [
            'id' => $row['ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'name' => $row['NAME'] ?? null,
            'exeStatus' => $row['EXE_STATUS'] ?? null,
            'opIp' => $row['OP_IP'] ?? null,
            'opAddress' => $row['OP_ADDRESS'] ?? null,
            'opBrowser' => $row['OP_BROWSER'] ?? null,
            'opOs' => $row['OP_OS'] ?? null,
            'className' => $row['CLASS_NAME'] ?? null,
            'methodName' => $row['METHOD_NAME'] ?? null,
            'reqMethod' => $row['REQ_METHOD'] ?? null,
            'reqUrl' => $row['REQ_URL'] ?? null,
            'opTime' => $row['OP_TIME'] ?? null,
            'opUser' => $row['OP_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];

        if ($includeLargeFields) {
            $data['exeMessage'] = $row['EXE_MESSAGE'] ?? null;
            $data['paramJson'] = $row['PARAM_JSON'] ?? null;
            $data['resultJson'] = $row['RESULT_JSON'] ?? null;
            $data['signData'] = $row['SIGN_DATA'] ?? null;
        }

        return $data;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
