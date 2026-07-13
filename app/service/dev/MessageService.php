<?php

declare(strict_types=1);

namespace app\service\dev;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only station-message queries compatible with Java DevMessageController.
 */
class MessageService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const MESSAGE_TO_USER = 'MSG_TO_USER';
    private const DEFAULT_CATEGORY = 'SYS';
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

    /**
     * @param array<string, mixed>|mixed $payload
     */
    public function detail(string $id, ?string $tenantId = null, mixed $payload = []): ?array
    {
        $row = $this->messageQuery(['id' => $id], $tenantId, null)->find();
        if (!$row) {
            return null;
        }

        $message = is_array($row) ? $row : $row->toArray();
        $relations = $this->relationsForMessage($id);
        $payload = is_array($payload) ? $payload : [];
        $relations = $this->markReadForCurrentUser($id, $relations, $this->payloadUserId($payload));
        $detail = $this->messageRow($message, $relations);
        $detail['receiveInfoList'] = $this->receiveInfoList($relations);

        return $detail;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function send(array $input, ?string $tenantId, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        if (!$this->canManageMessages($payload)) {
            throw new RuntimeException('permission denied', 403);
        }

        $subject = trim((string)($input['subject'] ?? ''));
        if ($subject === '') {
            throw new RuntimeException('missing subject', 400);
        }

        $receiverIds = $this->receiverIdList($input['receiverIdList'] ?? $input['receiverIds'] ?? $input['receivers'] ?? []);
        if ($receiverIds === []) {
            throw new RuntimeException('missing receiverIdList', 400);
        }

        $tenantId = $this->tenantIdForWrite($tenantId, $payload);
        $receiverIds = $this->activeReceiverIds($receiverIds, $tenantId);
        if ($receiverIds === []) {
            throw new RuntimeException('receiver user not found', 404);
        }

        $content = (string)($input['content'] ?? '');
        if (trim($content) === '') {
            $content = $subject;
        }

        $category = trim((string)($input['category'] ?? ''));
        if ($category === '') {
            $category = self::DEFAULT_CATEGORY;
        }

        $href = array_key_exists('href', $input) ? (string)$input['href'] : null;
        $userId = $this->payloadUserId($payload);
        $now = date('Y-m-d H:i:s');

        return Db::transaction(function () use ($subject, $content, $category, $href, $receiverIds, $tenantId, $userId, $now): array {
            $id = $this->newId();
            $extJson = json_encode(['href' => $href], JSON_UNESCAPED_UNICODE);

            Db::name('dev_message')->insert([
                'ID' => $id,
                'CATEGORY' => $category,
                'SUBJECT' => $subject,
                'CONTENT' => $content,
                'EXT_JSON' => $extJson === false ? '{}' : $extJson,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $tenantId,
            ]);

            $relations = [];
            foreach ($receiverIds as $receiverId) {
                $readJson = json_encode(['read' => false], JSON_UNESCAPED_UNICODE);
                $relations[] = [
                    'ID' => $this->newId(),
                    'OBJECT_ID' => $id,
                    'TARGET_ID' => $receiverId,
                    'CATEGORY' => self::MESSAGE_TO_USER,
                    'EXT_JSON' => $readJson === false ? '{"read":false}' : $readJson,
                ];
            }

            if ($relations !== []) {
                Db::name('dev_relation')->insertAll($relations);
            }

            return [
                'id' => $id,
                'receiveCount' => count($receiverIds),
            ];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>|mixed $payload
     */
    public function delete(array $items, ?string $tenantId, mixed $payload = []): array
    {
        $ids = $this->idList($items);
        if ($ids === []) {
            throw new RuntimeException('empty message list', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $allowedIds = $this->allowedDeleteIds($ids, $tenantId, $payload);
        if ($allowedIds === []) {
            throw new RuntimeException('message not found or permission denied', 404);
        }

        return Db::transaction(function () use ($allowedIds): array {
            Db::name('dev_relation')
                ->where('CATEGORY', self::MESSAGE_TO_USER)
                ->whereIn('OBJECT_ID', $allowedIds)
                ->delete();

            $count = Db::name('dev_message')
                ->whereIn('ID', $allowedIds)
                ->delete();

            return ['count' => $count];
        });
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

    /**
     * @param array<int, array<string, mixed>> $relations
     * @return array<int, array<string, mixed>>
     */
    private function markReadForCurrentUser(string $messageId, array $relations, string $userId): array
    {
        if ($userId === '') {
            return $relations;
        }

        foreach ($relations as $index => $relation) {
            if ((string)($relation['TARGET_ID'] ?? '') !== $userId) {
                continue;
            }

            if ($this->relationReadStatus($relation) === true) {
                return $relations;
            }

            $extJson = $this->relationExtWithRead($relation);
            Db::name('dev_relation')
                ->where('OBJECT_ID', $messageId)
                ->where('TARGET_ID', $userId)
                ->where('CATEGORY', self::MESSAGE_TO_USER)
                ->update(['EXT_JSON' => $extJson]);

            $relations[$index]['EXT_JSON'] = $extJson;

            return $relations;
        }

        return $relations;
    }

    private function relationExtWithRead(array $relation): string
    {
        $decoded = json_decode((string)($relation['EXT_JSON'] ?? '{}'), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded['read'] = true;
        $extJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);

        return $extJson === false ? '{"read":true}' : $extJson;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function idList(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = trim((string)($item['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, string>
     */
    private function receiverIdList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $id = trim((string)($item['id'] ?? $item['userId'] ?? $item['value'] ?? $item['key'] ?? ''));
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
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    private function activeReceiverIds(array $ids, string $tenantId): array
    {
        $query = Db::name('sys_user')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return array_values(array_filter($query->column('ID')));
    }

    /**
     * @param array<int, string> $ids
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function allowedDeleteIds(array $ids, ?string $tenantId, array $payload): array
    {
        $query = Db::name('dev_message')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if (!$this->canManageMessages($payload)) {
            $userId = $this->payloadUserId($payload);
            $query->where('CREATE_USER', $userId);
        }

        return array_values(array_filter($query->column('ID')));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function tenantIdForWrite(?string $tenantId, array $payload): string
    {
        $tenantId = trim((string)($tenantId ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            return $tenantId;
        }

        $userId = $this->payloadUserId($payload);
        if ($userId !== '') {
            $userTenantId = Db::name('sys_user')->where('ID', $userId)->value('TENANT_ID');
            if ($userTenantId !== null && (string)$userTenantId !== '') {
                return (string)$userTenantId;
            }
        }

        return '1';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function canManageMessages(array $payload): bool
    {
        $account = strtolower(trim((string)($payload['account'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            return true;
        }

        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );

        foreach ($codes as $code) {
            $code = strtolower($code);
            if (
                in_array($code, ['superadmin', 'bizadmin', 'tenantadmin', 'devmessage'], true)
                || str_contains($code, 'dev:message')
                || str_contains($code, 'devmessage')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
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

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
