<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Annual-leave balance queries and narrow manual maintenance.
 */
class BizUserVacationService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const DEFAULT_CATEGORY = 'annualLeave';
    private const FIELDS = <<<SQL
v.ID AS ID,
v.USER_ID AS USER_ID,
v.AMOUNT AS AMOUNT,
v.USED_AMOUNT AS USED_AMOUNT,
v.CATEGORY AS CATEGORY,
v.DELETE_FLAG AS DELETE_FLAG,
v.CREATE_TIME AS CREATE_TIME,
v.CREATE_USER AS CREATE_USER,
v.UPDATE_TIME AS UPDATE_TIME,
v.UPDATE_USER AS UPDATE_USER,
v.TENANT_ID AS TENANT_ID,
v.VERSION AS VERSION,
u.NAME AS USER_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'v.ID',
        'userId' => 'v.USER_ID',
        'userName' => 'u.NAME',
        'amount' => 'v.AMOUNT',
        'usedAmount' => 'v.USED_AMOUNT',
        'category' => 'v.CATEGORY',
        'createTime' => 'v.CREATE_TIME',
        'updateTime' => 'v.UPDATE_TIME',
        'tenantId' => 'v.TENANT_ID',
        'version' => 'v.VERSION',
    ];

    public function __construct(
        private readonly AnnualLeaveEntitlementService $annualLeaveEntitlementService = new AnnualLeaveEntitlementService()
    ) {
    }

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->vacationQuery($filters, $payload)->count();
        $rows = $this->applySort($this->vacationQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->vacationRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(array $filters = [], array $payload = []): array
    {
        $userId = trim((string)($filters['userId'] ?? $filters['user_id'] ?? ''));
        if ($userId === '') {
            $userId = $this->currentUserId($payload);
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category === '') {
            $category = self::DEFAULT_CATEGORY;
        }

        $query = Db::name('biz_user_vacation')
            ->alias('v')
            ->leftJoin('sys_user u', 'u.ID = v.USER_ID')
            ->field(self::FIELDS)
            ->where('v.USER_ID', $userId)
            ->where('v.CATEGORY', $category)
            ->where('v.DELETE_FLAG', self::NOT_DELETE)
            ->whereBetweenTime('v.CREATE_TIME', date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59'));

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('v.TENANT_ID', $tenantId);
        }

        $row = $query->order('v.CREATE_TIME', 'desc')
            ->order('v.ID', 'desc')
            ->find();

        if ($category === self::DEFAULT_CATEGORY) {
            $preview = $this->annualLeaveEntitlementService->previewCurrentYearBalance($userId, $tenantId);
            if (!is_array($row) || $row === []) {
                return array_merge($this->emptyAnnualLeaveRow($userId, $category), [
                    'userName' => $preview['userName'],
                    'amount' => $preview['amount'],
                    'usedAmount' => $preview['usedAmount'],
                    'tenantId' => $preview['tenantId'],
                ]);
            }

            $row['AMOUNT'] = $preview['amount'];
            $row['USED_AMOUNT'] = $preview['usedAmount'];
            if (trim((string)($row['USER_NAME'] ?? '')) === '') {
                $row['USER_NAME'] = $preview['userName'];
            }
        } elseif (!is_array($row) || $row === []) {
            return $this->emptyAnnualLeaveRow($userId, $category);
        }

        return $this->vacationRow($row);
    }

    public function add(array $input, array $payload = []): array
    {
        $userId = $this->requiredInput($input, ['userId', 'USER_ID'], 'userId');
        $category = $this->requiredInput($input, ['category', 'CATEGORY'], 'category');
        $this->assertMaxLength($userId, 'userId', 20);
        $this->assertMaxLength($category, 'category', 20);
        $amount = $this->decimalInput($input, ['amount', 'AMOUNT'], 'amount');
        $usedAmount = $this->decimalInput($input, ['usedAmount', 'USED_AMOUNT'], 'usedAmount');
        $this->assertAmounts($amount, $usedAmount);

        return Db::transaction(function () use ($input, $payload, $userId, $category, $amount, $usedAmount): array {
            $user = $this->activeUser($userId, $payload, true);
            $tenantId = $this->writeTenantId($input, $payload, $user);
            $this->assertNoDuplicate($userId, $category, $tenantId);

            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $currentUserId = $this->currentUserId($payload);

            Db::name('biz_user_vacation')->insert([
                'ID' => $id,
                'USER_ID' => $userId,
                'AMOUNT' => $amount,
                'USED_AMOUNT' => $usedAmount,
                'CATEGORY' => $category,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ]);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $userId = $this->requiredInput($input, ['userId', 'USER_ID'], 'userId');
        $category = $this->requiredInput($input, ['category', 'CATEGORY'], 'category');
        $this->assertMaxLength($id, 'id', 20);
        $this->assertMaxLength($userId, 'userId', 20);
        $this->assertMaxLength($category, 'category', 20);
        $amount = $this->decimalInput($input, ['amount', 'AMOUNT'], 'amount');
        $usedAmount = $this->decimalInput($input, ['usedAmount', 'USED_AMOUNT'], 'usedAmount');
        $this->assertAmounts($amount, $usedAmount);

        return Db::transaction(function () use ($id, $payload, $userId, $category, $amount, $usedAmount): array {
            $existing = $this->activeVacationRow($id, $payload);
            $user = $this->activeUser($userId, $payload);
            $tenantId = trim((string)($existing['TENANT_ID'] ?? ''));
            $this->assertUserTenant($tenantId, $user);
            $this->assertNoDuplicate($userId, $category, $tenantId, $id);

            $updated = Db::name('biz_user_vacation')
                ->where('ID', $id)
                ->update([
                    'USER_ID' => $userId,
                    'AMOUNT' => $amount,
                    'USED_AMOUNT' => $usedAmount,
                    'CATEGORY' => $category,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null,
                    'VERSION' => Db::raw('VERSION + 1'),
                ]);

            return ['id' => $id, 'count' => $updated];
        });
    }

    public function delete(array $input, array $payload = []): array
    {
        $ids = $this->deleteIds($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $rows = $this->activeVacationRows($ids, $payload);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('user vacation batch contains missing rows', 400);
            }

            $updated = Db::name('biz_user_vacation')
                ->whereIn('ID', $ids)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null,
                    'VERSION' => Db::raw('VERSION + 1'),
                ]);

            return ['count' => $updated];
        });
    }

    private function vacationQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_user_vacation')
            ->alias('v')
            ->leftJoin('sys_user u', 'u.ID = v.USER_ID')
            ->where('v.DELETE_FLAG', self::NOT_DELETE);

        $tenantId = trim((string)($filters['tenantId'] ?? $filters['tenant_id'] ?? $this->tenantId($payload)));
        if ($tenantId !== '') {
            $query->where('v.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'v.ID',
            'userId' => 'v.USER_ID',
            'category' => 'v.CATEGORY',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['userName'])) {
            $query->whereLike('u.NAME', '%' . trim((string)$filters['userName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('v.USER_ID', 'like', $keyword)
                    ->whereOr('v.CATEGORY', 'like', $keyword);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('v.ID', 'asc');
        }

        return $query->order('v.ID', 'asc');
    }

    private function vacationRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'userId' => $row['USER_ID'] ?? null,
            'userName' => $row['USER_NAME'] ?? null,
            'amount' => $this->decimal($row['AMOUNT'] ?? 0),
            'usedAmount' => $this->decimal($row['USED_AMOUNT'] ?? 0),
            'category' => $row['CATEGORY'] ?? self::DEFAULT_CATEGORY,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'version' => (int)($row['VERSION'] ?? 0),
        ]);
    }

    private function emptyAnnualLeaveRow(string $userId, string $category): array
    {
        return [
            'id' => null,
            'userId' => $userId !== '' ? $userId : null,
            'userName' => null,
            'amount' => '0',
            'usedAmount' => '0',
            'category' => $category !== '' ? $category : self::DEFAULT_CATEGORY,
            'deleteFlag' => self::NOT_DELETE,
            'createTime' => null,
            'createUser' => null,
            'updateTime' => null,
            'updateUser' => null,
            'tenantId' => null,
            'version' => 0,
        ];
    }

    private function currentUserId(array $payload): string
    {
        return (string)($payload['userId'] ?? $payload['user_id'] ?? $payload['id'] ?? '');
    }

    private function tenantId(array $payload): string
    {
        return (string)($payload['tenantId'] ?? $payload['tenant_id'] ?? '');
    }

    /**
     * @param array<int, string>|string $keys
     */
    private function requiredInput(array $input, array|string $keys, string $label): string
    {
        foreach ((array)$keys as $key) {
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
     * @param array<int, string> $keys
     */
    private function decimalInput(array $input, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '' && is_numeric($value)) {
                    $number = (float)$value;
                    if ($number < 0) {
                        throw new RuntimeException("invalid {$label}", 400);
                    }

                    return number_format($number, 2, '.', '');
                }
            }
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    private function assertAmounts(string $amount, string $usedAmount): void
    {
        if ((float)$usedAmount > (float)$amount) {
            throw new RuntimeException('usedAmount cannot be greater than amount', 400);
        }
    }

    private function assertMaxLength(string $value, string $label, int $maxLength): void
    {
        if (strlen($value) > $maxLength) {
            throw new RuntimeException("{$label} is too long", 400);
        }
    }

    private function activeUser(string $userId, array $payload, bool $lock = false): array
    {
        $query = Db::name('sys_user')
            ->where('ID', $userId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field('ID,TENANT_ID');

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        if ($lock) {
            $query->lock(true);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('target user not found', 404);
        }

        return $row;
    }

    private function writeTenantId(array $input, array $payload, array $user): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['TENANT_ID'] ?? ''));
        if ($tenantId === '') {
            $tenantId = trim((string)($user['TENANT_ID'] ?? ''));
        }
        if ($tenantId === '') {
            $tenantId = $this->tenantId($payload);
        }
        if ($tenantId === '') {
            $tenantId = '1';
        }

        $this->assertPayloadTenant($tenantId, $payload);
        $this->assertUserTenant($tenantId, $user);

        return $tenantId;
    }

    private function assertPayloadTenant(string $tenantId, array $payload): void
    {
        $payloadTenant = $this->tenantId($payload);
        if ($payloadTenant !== '' && $payloadTenant !== $tenantId) {
            throw new RuntimeException('tenant mismatch', 403);
        }
    }

    private function assertUserTenant(string $tenantId, array $user): void
    {
        $userTenant = trim((string)($user['TENANT_ID'] ?? ''));
        if ($tenantId !== '' && $userTenant !== '' && $userTenant !== $tenantId) {
            throw new RuntimeException('target user tenant mismatch', 403);
        }
    }

    private function assertNoDuplicate(string $userId, string $category, string $tenantId, ?string $excludeId = null): void
    {
        $query = Db::name('biz_user_vacation')
            ->where('USER_ID', $userId)
            ->where('CATEGORY', $category)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->whereBetweenTime('CREATE_TIME', date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59'));

        if ($excludeId !== null && $excludeId !== '') {
            $query->where('ID', '<>', $excludeId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('user vacation already exists for current year', 400);
        }
    }

    private function activeVacationRow(string $id, array $payload): array
    {
        $rows = $this->activeVacationRows([$id], $payload);
        if ($rows === []) {
            throw new RuntimeException('user vacation not found', 404);
        }

        return reset($rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function activeVacationRows(array $ids, array $payload): array
    {
        $query = Db::name('biz_user_vacation')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query->field('ID,USER_ID,CATEGORY,TENANT_ID')->select()->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['ID']] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $value = $input['idList'] ?? $input['ids'] ?? $input['id'] ?? $input;
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (is_array($value) && array_is_list($value)) {
            $ids = [];
            foreach ($value as $item) {
                $ids[] = is_array($item) ? ($item['id'] ?? $item['ID'] ?? '') : $item;
            }
        } else {
            $ids = [$value];
        }

        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        return $ids;
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

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}
