<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only history Excel queries compatible with Java BizHistoryExcelController.
 */
class BizHistoryExcelService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const FIELDS = <<<SQL
h.ID AS ID,
h.NAME AS NAME,
h.REMARK AS REMARK,
h.DELETE_FLAG AS DELETE_FLAG,
h.EXT_JSON AS EXT_JSON,
h.CREATE_TIME AS CREATE_TIME,
h.CREATE_USER AS CREATE_USER,
h.UPDATE_TIME AS UPDATE_TIME,
h.UPDATE_USER AS UPDATE_USER,
h.TENANT_ID AS TENANT_ID
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'h.ID',
        'name' => 'h.NAME',
        'createTime' => 'h.CREATE_TIME',
        'createUser' => 'h.CREATE_USER',
        'updateTime' => 'h.UPDATE_TIME',
        'updateUser' => 'h.UPDATE_USER',
        'deleteFlag' => 'h.DELETE_FLAG',
        'tenantId' => 'h.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->historyQuery($filters, $payload)->count();
        $rows = $this->applySort($this->historyQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->historyRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->historyQuery(['id' => $id], $payload)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('history excel not found', 404);
        }

        return $this->historyRow($row);
    }

    public function add(array $input, array $payload = []): array
    {
        $name = $this->requiredInput($input, 'name');
        $now = date('Y-m-d H:i:s');
        $userId = $this->currentUserId($payload);
        $id = $this->newId();

        return Db::transaction(function () use ($id, $name, $input, $payload, $now, $userId): array {
            Db::name('biz_history_excel')->insert([
                'ID' => $id,
                'NAME' => $name,
                'REMARK' => null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'EXT_JSON' => $this->extJson($input['extJson'] ?? null),
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload),
            ]);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $this->activeHistory($id);
            $row = [
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
            ];
            if (array_key_exists('extJson', $input)) {
                $row['EXT_JSON'] = $this->extJson($input['extJson']);
            }

            Db::name('biz_history_excel')->where('ID', $id)->update($row);

            return ['id' => $id];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->stringList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $query = Db::name('biz_history_excel')->whereIn('ID', $idList);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $rows = $query->select()->toArray();
            if (count($rows) !== count($idList)) {
                throw new RuntimeException('history excel not found', 404);
            }

            $updated = Db::name('biz_history_excel')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function historyQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_history_excel')
            ->alias('h')
            ->where(function ($query): void {
                $query->whereNull('h.DELETE_FLAG')->whereOr('h.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $id = trim((string)($filters['id'] ?? ''));
        if ($id !== '') {
            $query->where('h.ID', $id);
        }

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('h.TENANT_ID', $tenantId);
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('h.ID', 'asc');
        }

        return $query->order('h.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function historyRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->historyRow($row), $rows);
    }

    private function historyRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'remark' => $row['REMARK'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(100, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function activeHistory(string $id): array
    {
        $query = Db::name('biz_history_excel')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('history excel not found', 404);
        }

        return $row;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
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

    private function extJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)$value;
    }

    private function tenantId(array $input, array $payload): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            if (is_array($item)) {
                return trim((string)($item['id'] ?? $item['ID'] ?? ''));
            }

            return trim((string)$item);
        }, $value))));
    }
}
