<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Draft queries and save compatibility for Java BizDraftController.
 */
class BizDraftService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SALE_PROJECT_INIT = 'SALE_PROJECT_INIT';
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

    public function __construct(private readonly SaleProjectService $saleProjectService = new SaleProjectService())
    {
    }

    public function addOrEditSaleProjectDraft(array $input, array $payload = []): null
    {
        $targetId = $this->requiredInput($input, 'targetId');
        $this->saleProjectService->assertDraftWritable($targetId, $payload);
        $extJson = $this->requiredExtJson($input['extJson'] ?? null);
        $tenantId = $this->tenantId($payload);
        if ($tenantId === '') {
            throw new RuntimeException('missing tenantId', 400);
        }

        Db::transaction(function () use ($targetId, $extJson, $tenantId, $payload): void {
            $existing = Db::name('biz_draft')
                ->where('TARGET_ID', $targetId)
                ->where('CATEGORY', self::SALE_PROJECT_INIT)
                ->where('TENANT_ID', $tenantId)
                ->where('DELETE_FLAG', self::NOT_DELETE)
                ->order('UPDATE_TIME', 'desc')
                ->order('CREATE_TIME', 'desc')
                ->order('ID', 'desc')
                ->find();

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            if (is_array($existing) && $existing !== []) {
                Db::name('biz_draft')
                    ->where('ID', (string)$existing['ID'])
                    ->update([
                        'EXT_JSON' => $extJson,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $userId !== '' ? $userId : null,
                    ]);

                return;
            }

            Db::name('biz_draft')->insert([
                'ID' => $this->newId(),
                'TARGET_ID' => $targetId,
                'CATEGORY' => self::SALE_PROJECT_INIT,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'EXT_JSON' => $extJson,
                'TENANT_ID' => $tenantId,
            ]);
        });

        return null;
    }

    public function detail(string $targetId, array $payload = []): ?array
    {
        $this->saleProjectService->assertDraftReadable($targetId, $payload);
        $query = Db::name('biz_draft')
            ->alias('d')
            ->field(self::FIELDS)
            ->where('d.TARGET_ID', $targetId)
            ->where('d.CATEGORY', self::SALE_PROJECT_INIT)
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

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function requiredExtJson(mixed $value): string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = trim((string)$value);
        if ($value === '') {
            throw new RuntimeException('missing extJson', 400);
        }

        return $value;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
