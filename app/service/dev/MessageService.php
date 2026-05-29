<?php

declare(strict_types=1);

namespace app\service\dev;

use think\facade\Db;

/**
 * Read-only station-message queries compatible with Java DevMessageController.
 */
class MessageService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const MESSAGE_TO_USER = 'MSG_TO_USER';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'category' => 'CATEGORY',
        'subject' => 'SUBJECT',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $messageIds = $this->messageIdsForReceiver($filters);
        $total = $this->messageQuery($filters, $tenantId, $messageIds)->count();
        $rows = $this->applySort($this->messageQuery($filters, $tenantId, $messageIds), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();
        $relations = $this->relationsByMessageId(array_map(static fn (array $row): string => (string)($row['ID'] ?? ''), $rows));

        return [
            'records' => array_map(fn (array $row): array => $this->messageRow($row, $relations[(string)($row['ID'] ?? '')] ?? []), $rows),
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
        $row = $this->messageQuery(['id' => $id], $tenantId, null)->find();
        if (!$row) {
            return null;
        }

        $message = is_array($row) ? $row : $row->toArray();
        $relations = $this->relationsForMessage($id);
        $detail = $this->messageRow($message, $relations);
        $detail['receiveInfoList'] = $this->receiveInfoList($relations);

        return $detail;
    }

    private function messageQuery(array $filters, ?string $tenantId, ?array $messageIds = null)
    {
        $query = Db::name('dev_message')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('SUBJECT', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if ($messageIds !== null) {
            $messageIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('ID', $messageIds);
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
     * @return array<int, string>|null
     */
    private function messageIdsForReceiver(array $filters): ?array
    {
        $receiverId = trim((string)($filters['receiveUserId'] ?? $filters['receiverId'] ?? ''));
        if ($receiverId === '') {
            return null;
        }

        return array_values(array_filter(Db::name('dev_relation')
            ->where('TARGET_ID', $receiverId)
            ->where('CATEGORY', self::MESSAGE_TO_USER)
            ->column('OBJECT_ID')));
    }

    /**
     * @param array<int, string> $messageIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function relationsByMessageId(array $messageIds): array
    {
        $messageIds = array_values(array_filter($messageIds));
        if ($messageIds === []) {
            return [];
        }

        $rows = Db::name('dev_relation')
            ->whereIn('OBJECT_ID', $messageIds)
            ->where('CATEGORY', self::MESSAGE_TO_USER)
            ->select()
            ->toArray();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string)($row['OBJECT_ID'] ?? '')][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relationsForMessage(string $id): array
    {
        return Db::name('dev_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', self::MESSAGE_TO_USER)
            ->select()
            ->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $relations
     * @return array<int, array<string, mixed>>
     */
    private function receiveInfoList(array $relations): array
    {
        $userIds = array_values(array_filter(array_map(static fn (array $relation): string => (string)($relation['TARGET_ID'] ?? ''), $relations)));
        $userNames = $userIds === [] ? [] : Db::name('sys_user')->whereIn('ID', $userIds)->column('NAME', 'ID');

        return array_map(function (array $relation) use ($userNames): array {
            $receiveUserId = (string)($relation['TARGET_ID'] ?? '');

            return [
                'receiveUserId' => $receiveUserId,
                'receiveUserName' => $userNames[$receiveUserId] ?? 'unknown user',
                'read' => $this->relationReadStatus($relation) ?? false,
            ];
        }, $relations);
    }

    /**
     * @param array<int, array<string, mixed>> $relations
     */
    private function messageRow(array $row, array $relations): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'subject' => $row['SUBJECT'] ?? null,
            'content' => $row['CONTENT'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'receiveCount' => count($relations),
            'readCount' => count(array_filter($relations, fn (array $relation): bool => $this->relationReadStatus($relation) === true)),
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function relationReadStatus(?array $relation): ?bool
    {
        if (!$relation) {
            return null;
        }

        $decoded = json_decode((string)($relation['EXT_JSON'] ?? '{}'), true);
        if (!is_array($decoded) || !array_key_exists('read', $decoded)) {
            return null;
        }

        return (bool)$decoded['read'];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
