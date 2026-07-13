<?php

declare(strict_types=1);

namespace app\service\dev;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only email send record queries compatible with Java DevEmailController.
 */
class EmailService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'engine' => 'ENGINE',
        'sendAccount' => 'SEND_ACCOUNT',
        'sendUser' => 'SEND_USER',
        'subject' => 'SUBJECT',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->emailQuery($filters, $tenantId)->count();
        $rows = $this->applySort($this->emailQuery($filters, $tenantId), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->emailRow($row), $rows),
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
        $row = $this->emailQuery(['id' => $id], $tenantId)->find();
        if (!$row) {
            return null;
        }

        return $this->emailRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<int, string> $ids
     */
    public function delete(array $ids, array $payload = []): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $tenantId = $this->requiredTenantId($payload);
        Db::name('dev_email')
            ->whereIn('ID', $ids)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->currentUserId($payload),
            ]);

        return null;
    }

    private function emailQuery(array $filters, ?string $tenantId)
    {
        $query = Db::name('dev_email')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['engine'])) {
            $query->where('ENGINE', (string)$filters['engine']);
        }

        if (!empty($filters['sendAccount'])) {
            $query->whereLike('SEND_ACCOUNT', '%' . trim((string)$filters['sendAccount']) . '%');
        }

        if (!empty($filters['receiveAccounts'])) {
            $query->whereLike('RECEIVE_ACCOUNTS', '%' . trim((string)$filters['receiveAccounts']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('SUBJECT', '%' . trim((string)$filters['searchKey']) . '%');
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

    private function emailRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'engine' => $row['ENGINE'] ?? null,
            'sendAccount' => $row['SEND_ACCOUNT'] ?? null,
            'sendUser' => $row['SEND_USER'] ?? null,
            'receiveAccounts' => $row['RECEIVE_ACCOUNTS'] ?? null,
            'subject' => $row['SUBJECT'] ?? null,
            'content' => $row['CONTENT'] ?? null,
            'tagName' => $row['TAG_NAME'] ?? null,
            'templateName' => $row['TEMPLATE_NAME'] ?? null,
            'templateParam' => $row['TEMPLATE_PARAM'] ?? null,
            'receiptInfo' => $row['RECEIPT_INFO'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function requiredTenantId(array $payload): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            return $tenantId;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== null) {
            $tenantId = trim((string)Db::name('sys_user')->where('ID', $userId)->value('TENANT_ID'));
            if ($tenantId !== '') {
                return $tenantId;
            }
        }

        throw new RuntimeException('missing tenantId', 400);
    }

    private function currentUserId(array $payload): ?string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));

        return $userId === '' ? null : $userId;
    }
}
