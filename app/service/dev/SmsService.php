<?php

declare(strict_types=1);

namespace app\service\dev;

use think\facade\Db;

/**
 * Read-only SMS send record queries compatible with Java DevSmsController.
 */
class SmsService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'engine' => 'ENGINE',
        'phoneNumbers' => 'PHONE_NUMBERS',
        'signName' => 'SIGN_NAME',
        'templateCode' => 'TEMPLATE_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->smsQuery($filters, $tenantId)->count();
        $rows = $this->applySort($this->smsQuery($filters, $tenantId), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->smsRow($row), $rows),
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
        $row = $this->smsQuery(['id' => $id], $tenantId)->find();
        if (!$row) {
            return null;
        }

        return $this->smsRow(is_array($row) ? $row : $row->toArray());
    }

    private function smsQuery(array $filters, ?string $tenantId)
    {
        $query = Db::name('dev_sms')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['engine'])) {
            $query->where('ENGINE', (string)$filters['engine']);
        }

        if (!empty($filters['signName'])) {
            $query->whereLike('SIGN_NAME', '%' . trim((string)$filters['signName']) . '%');
        }

        if (!empty($filters['templateCode'])) {
            $query->whereLike('TEMPLATE_CODE', '%' . trim((string)$filters['templateCode']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('PHONE_NUMBERS', '%' . trim((string)$filters['searchKey']) . '%');
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

    private function smsRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'engine' => $row['ENGINE'] ?? null,
            'phoneNumbers' => $row['PHONE_NUMBERS'] ?? null,
            'signName' => $row['SIGN_NAME'] ?? null,
            'templateCode' => $row['TEMPLATE_CODE'] ?? null,
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
}
