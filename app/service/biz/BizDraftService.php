<?php

declare(strict_types=1);

namespace app\service\biz;

use think\facade\Db;

/**
 * Read-only draft queries compatible with Java BizDraftController.
 */
class BizDraftService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const FIELDS = <<<SQL
d.ID AS ID,
d.TARGET_ID AS TARGET_ID,
d.CATEGORY AS CATEGORY,
d.DELETE_FLAG AS DELETE_FLAG,
d.CREATE_TIME AS CREATE_TIME,
d.CREATE_USER AS CREATE_USER,
d.UPDATE_TIME AS UPDATE_TIME,
d.UPDATE_USER AS UPDATE_USER,
d.EXT_JSON AS EXT_JSON,
d.TENANT_ID AS TENANT_ID
SQL;

    public function detail(string $targetId, array $payload = []): ?array
    {
        $query = Db::name('biz_draft')
            ->alias('d')
            ->field(self::FIELDS)
            ->where('d.TARGET_ID', $targetId)
            ->where('d.DELETE_FLAG', self::NOT_DELETE);

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('d.TENANT_ID', $tenantId);
        }

        $row = $query->order('d.UPDATE_TIME', 'desc')
            ->order('d.CREATE_TIME', 'desc')
            ->order('d.ID', 'desc')
            ->find();

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->draftRow($row);
    }

    private function draftRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'targetId' => $row['TARGET_ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    private function tenantId(array $payload): string
    {
        return (string)($payload['tenantId'] ?? $payload['tenant_id'] ?? '');
    }
}
