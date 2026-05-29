<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only supplier queries compatible with Java SupplierController.
 */
class SupplierService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ENABLE = 'ENABLE';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'name' => 'NAME',
        'contacts' => 'CONTACTS',
        'phone' => 'PHONE',
        'bankName' => 'BANK_NAME',
        'bankAccount' => 'BANK_ACCOUNT',
        'status' => 'STATUS',
        'enterpriseNature' => 'ENTERPRISE_NATURE',
        'taxRegistrationNumber' => 'TAX_REGISTRATION_NUMBER',
        'paymentMethod' => 'PAYMENT_METHOD',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
        'tenantId' => 'TENANT_ID',
        'aliasName' => 'ALIAS_NAME',
        'org' => 'org',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->supplierQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->supplierQuery($filters, $payload, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->supplierRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->supplierQuery($filters, $payload, false), $filters)
            ->select()
            ->toArray();

        return $this->supplierRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function queryByName(string $name, array $payload = []): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('name is required', 400);
        }

        $keyword = '%' . $name . '%';
        $rows = $this->supplierQuery(['status' => self::ENABLE], $payload, false)
            ->where(function ($query) use ($keyword): void {
                $query->whereLike('NAME', $keyword)->whereOr('ALIAS_NAME', 'like', $keyword);
            })
            ->order('SORT_CODE', 'asc')
            ->order('ID', 'asc')
            ->select()
            ->toArray();

        return $this->supplierRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->supplierQuery(['id' => $id], $payload, false)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('supplier not found', 404);
        }

        return $this->supplierRows([$row])[0];
    }

    private function supplierQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = Db::name('supplier')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        foreach ([
            'name' => 'NAME',
            'contacts' => 'CONTACTS',
            'phone' => 'PHONE',
            'bankName' => 'BANK_NAME',
            'bankAccount' => 'BANK_ACCOUNT',
            'enterpriseNature' => 'ENTERPRISE_NATURE',
            'taxRegistrationNumber' => 'TAX_REGISTRATION_NUMBER',
            'paymentMethod' => 'PAYMENT_METHOD',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('NAME', $keyword)
                    ->whereOr('ALIAS_NAME', 'like', $keyword)
                    ->whereOr('CONTACTS', 'like', $keyword);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('STATUS', (string)$filters['status']);
        }

        if (!empty($filters['orgId'])) {
            $query->where('org', (string)$filters['orgId']);
        }

        $scopeOrgIds = $applyDataScope ? $this->scopeOrgIds($payload) : [];
        if ($scopeOrgIds !== []) {
            $query->whereIn('org', $scopeOrgIds);
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

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function supplierRows(array $rows): array
    {
        $orgNames = $this->orgNames($rows);

        return array_map(fn (array $row): array => $this->supplierRow($row, $orgNames), $rows);
    }

    /**
     * @param array<string, string> $orgNames
     */
    private function supplierRow(array $row, array $orgNames = []): array
    {
        $orgId = $row['org'] ?? $row['ORG'] ?? null;

        return [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'contacts' => $row['CONTACTS'] ?? null,
            'phone' => $row['PHONE'] ?? null,
            'bankName' => $row['BANK_NAME'] ?? null,
            'bankAccount' => $row['BANK_ACCOUNT'] ?? null,
            'status' => $row['STATUS'] ?? null,
            'enterpriseNature' => $row['ENTERPRISE_NATURE'] ?? null,
            'taxRegistrationNumber' => $row['TAX_REGISTRATION_NUMBER'] ?? null,
            'paymentMethod' => $row['PAYMENT_METHOD'] ?? null,
            'sortCode' => isset($row['SORT_CODE']) ? (int)$row['SORT_CODE'] : null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'aliasName' => $row['ALIAS_NAME'] ?? null,
            'org' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? null) : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function orgNames(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['org'] ?? $row['ORG'] ?? ''),
            $rows
        ))));
        if ($orgIds === []) {
            return [];
        }

        return Db::name('sys_org')->whereIn('ID', $orgIds)->column('NAME', 'ID');
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $scope): string {
            if (!is_array($scope)) {
                return '';
            }

            return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
        }, $scopes))));
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
