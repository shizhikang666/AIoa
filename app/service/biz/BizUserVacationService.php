<?php

declare(strict_types=1);

namespace app\service\biz;

use think\facade\Db;

/**
 * Read-only annual-leave balance queries compatible with Java BizUserVacationController.
 */
class BizUserVacationService
{
    private const NOT_DELETE = 'NOT_DELETE';
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

        if (!is_array($row) || $row === []) {
            return $this->emptyAnnualLeaveRow($userId, $category);
        }

        return $this->vacationRow($row);
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

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}
