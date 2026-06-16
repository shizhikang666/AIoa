<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only CC record queries compatible with Java BizCcRecordsController.
 */
class CcRecordsService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SORT_FIELD_MAP = [
        'id' => 'c.ID',
        'title' => 'c.TITLE',
        'processId' => 'c.PROCESS_ID',
        'promoterId' => 'c.PROMOTER_ID',
        'instanceId' => 'c.INSTANCE_ID',
        'category' => 'c.CATEGORY',
        'user' => 'c.USER',
        'createTime' => 'c.CREATE_TIME',
        'updateTime' => 'c.UPDATE_TIME',
        'tenantId' => 'c.TENANT_ID',
    ];
    private const FIELDS = <<<SQL
c.ID AS ID,
c.TITLE AS TITLE,
c.PROCESS_ID AS PROCESS_ID,
c.PROMOTER_ID AS PROMOTER_ID,
c.INSTANCE_ID AS INSTANCE_ID,
c.CATEGORY AS CATEGORY,
c.EXT_JSON AS EXT_JSON,
c.USER AS USER_ID,
c.DELETE_FLAG AS DELETE_FLAG,
c.CREATE_TIME AS CREATE_TIME,
c.CREATE_USER AS CREATE_USER,
c.UPDATE_TIME AS UPDATE_TIME,
c.UPDATE_USER AS UPDATE_USER,
c.TENANT_ID AS TENANT_ID,
promoter.NAME AS PROMOTER_NAME,
receiver.NAME AS USER_NAME
SQL;

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters, $payload)->count();
        $rows = $this->applySort($this->baseQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->recordRow($row), $rows),
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
        $row = $this->baseQuery(['id' => $id], $payload)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('cc record not found', 404);
        }

        return $this->recordRow($row);
    }

    public function add(array $input, array $payload = []): ?array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $tenantId = $this->writeTenantId($input, $payload);
        $title = $this->requiredInput($input, ['title', 'TITLE'], 'title', 255);
        $processId = $this->requiredInput($input, ['processId', 'PROCESS_ID'], 'processId', 255);
        $instanceId = $this->requiredInput($input, ['instanceId', 'INSTANCE_ID'], 'instanceId', 255);
        $category = $this->requiredInput($input, ['category', 'CATEGORY'], 'category', 100);
        $promoterId = $this->optionalInput($input, ['promoterId', 'PROMOTER_ID'], 20);
        if ($promoterId === null || $promoterId === '') {
            $promoterId = $currentUserId;
        }
        $extJson = $this->optionalInput($input, ['extJson', 'EXT_JSON'], 65535);

        $this->assertActiveUser($currentUserId, $tenantId);
        $this->assertActiveUser($promoterId, $tenantId);

        Db::transaction(function () use ($currentUserId, $tenantId, $title, $processId, $instanceId, $category, $promoterId, $extJson): void {
            $now = date('Y-m-d H:i:s');
            Db::name('biz_cc_records')->insert([
                'ID' => $this->newId(),
                'TITLE' => $title,
                'PROCESS_ID' => $processId,
                'PROMOTER_ID' => $promoterId,
                'INSTANCE_ID' => $instanceId,
                'CATEGORY' => $category,
                'EXT_JSON' => $extJson,
                'USER' => $currentUserId,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
            ]);
        });

        return null;
    }

    public function edit(array $input, array $payload = []): ?array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $tenantId = $this->writeTenantId($input, $payload);
        $id = $this->requiredInput($input, ['id', 'ID'], 'id', 20);
        $title = $this->requiredInput($input, ['title', 'TITLE'], 'title', 255);
        $processId = $this->requiredInput($input, ['processId', 'PROCESS_ID'], 'processId', 255);
        $instanceId = $this->requiredInput($input, ['instanceId', 'INSTANCE_ID'], 'instanceId', 255);
        $category = $this->requiredInput($input, ['category', 'CATEGORY'], 'category', 100);
        $promoterId = $this->optionalInput($input, ['promoterId', 'PROMOTER_ID'], 20);
        if ($promoterId === null || $promoterId === '') {
            $promoterId = $currentUserId;
        }
        $extJson = $this->optionalInput($input, ['extJson', 'EXT_JSON'], 65535);

        $this->activeRecord($id, $payload);
        $this->assertActiveUser($promoterId, $tenantId);

        Db::transaction(function () use ($id, $currentUserId, $tenantId, $title, $processId, $instanceId, $category, $promoterId, $extJson): void {
            $update = Db::name('biz_cc_records')
                ->where('ID', $id)
                ->where('USER', $currentUserId)
                ->where('DELETE_FLAG', self::NOT_DELETE);
            if ($tenantId !== '') {
                $update->where('TENANT_ID', $tenantId);
            }

            $updated = $update->update([
                'TITLE' => $title,
                'PROCESS_ID' => $processId,
                'PROMOTER_ID' => $promoterId,
                'INSTANCE_ID' => $instanceId,
                'CATEGORY' => $category,
                'EXT_JSON' => $extJson,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $currentUserId,
            ]);

            if ($updated < 1) {
                throw new RuntimeException('cc record not found', 404);
            }
        });

        return null;
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->normalizeIdList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId === '') {
            throw new RuntimeException('missing current user', 400);
        }

        return Db::transaction(function () use ($idList, $payload, $currentUserId): array {
            $query = Db::name('biz_cc_records')
                ->whereIn('ID', $idList)
                ->where('USER', $currentUserId)
                ->where('DELETE_FLAG', self::NOT_DELETE);

            $tenantId = $this->tenantId($payload);
            if ($tenantId !== '') {
                $query->where('TENANT_ID', $tenantId);
            }

            $rows = $query->select()->toArray();
            if (count($rows) !== count($idList)) {
                throw new RuntimeException('cc record not found', 404);
            }

            $update = Db::name('biz_cc_records')
                ->whereIn('ID', $idList)
                ->where('USER', $currentUserId)
                ->where('DELETE_FLAG', self::NOT_DELETE);
            if ($tenantId !== '') {
                $update->where('TENANT_ID', $tenantId);
            }

            $updated = $update->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $currentUserId,
            ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function baseQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_cc_records')
            ->alias('c')
            ->leftJoin('sys_user promoter', 'promoter.ID = c.PROMOTER_ID')
            ->leftJoin('sys_user receiver', 'receiver.ID = c.USER')
            ->where('c.DELETE_FLAG', self::NOT_DELETE);

        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId !== '') {
            $query->where('c.USER', $currentUserId);
        }

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('c.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('c.ID', (string)$filters['id']);
        }

        if (!empty($filters['title'])) {
            $query->whereLike('c.TITLE', '%' . trim((string)$filters['title']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('c.TITLE', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['processId'])) {
            $query->where('c.PROCESS_ID', (string)$filters['processId']);
        }

        if (!empty($filters['promoterId'])) {
            $query->where('c.PROMOTER_ID', (string)$filters['promoterId']);
        }

        if (!empty($filters['instanceId'])) {
            $query->where('c.INSTANCE_ID', (string)$filters['instanceId']);
        }

        if (!empty($filters['category'])) {
            $query->where('c.CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['startCreateTime']) && !empty($filters['endCreateTime'])) {
            $query->whereBetweenTime(
                'c.CREATE_TIME',
                (string)$filters['startCreateTime'],
                (string)$filters['endCreateTime']
            );
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['asc', 'ascend', 'ascending'], true) ? 'asc' : 'desc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('c.ID', 'asc');
        }

        return $query->order('c.ID', 'asc');
    }

    private function recordRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'title' => $row['TITLE'] ?? null,
            'processId' => $row['PROCESS_ID'] ?? null,
            'promoterId' => $row['PROMOTER_ID'] ?? null,
            'promoterName' => $row['PROMOTER_NAME'] ?? null,
            'instanceId' => $row['INSTANCE_ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'user' => $row['USER_ID'] ?? null,
            'userName' => $row['USER_NAME'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    private function currentUserId(array $payload): string
    {
        return (string)($payload['userId'] ?? $payload['user_id'] ?? $payload['id'] ?? '');
    }

    private function requiredCurrentUserId(array $payload): string
    {
        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId === '') {
            throw new RuntimeException('missing current user', 400);
        }

        return $currentUserId;
    }

    private function tenantId(array $payload): string
    {
        return (string)($payload['tenantId'] ?? $payload['tenant_id'] ?? '');
    }

    private function writeTenantId(array $input, array $payload): string
    {
        $tenantId = $this->tenantId($payload);
        $inputTenantId = trim((string)($input['tenantId'] ?? $input['TENANT_ID'] ?? ''));
        if ($tenantId !== '' && $inputTenantId !== '' && $tenantId !== $inputTenantId) {
            throw new RuntimeException('tenant mismatch', 400);
        }
        if ($tenantId === '') {
            $tenantId = $inputTenantId;
        }
        if ($tenantId === '') {
            throw new RuntimeException('missing tenantId', 400);
        }

        return $tenantId;
    }

    private function activeRecord(string $id, array $payload): array
    {
        $currentUserId = $this->requiredCurrentUserId($payload);
        $query = Db::name('biz_cc_records')
            ->where('ID', $id)
            ->where('USER', $currentUserId)
            ->where('DELETE_FLAG', self::NOT_DELETE);

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('cc record not found', 404);
        }

        return $row;
    }

    private function assertActiveUser(string $userId, string $tenantId): void
    {
        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if ($query->count() < 1) {
            throw new RuntimeException('user not found', 400);
        }
    }

    private function requiredInput(array $input, array $keys, string $label, int $maxLength): string
    {
        $value = $this->optionalInput($input, $keys, $maxLength);
        if ($value === null || trim($value) === '') {
            throw new RuntimeException('missing ' . $label, 400);
        }

        return trim($value);
    }

    private function optionalInput(array $input, array $keys, int $maxLength): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                if (is_array($value)) {
                    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $value = $encoded === false ? '' : $encoded;
                }
                $value = trim((string)$value);
                if (strlen($value) > $maxLength) {
                    throw new RuntimeException($keys[0] . ' is too long', 400);
                }

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeIdList(mixed $value): array
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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
