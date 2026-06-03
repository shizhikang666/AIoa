<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only software package/version info queries compatible with Java BizSaleProjectProductInfoController.
 */
class SaleProjectProductInfoService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'id' => 'i.ID',
        'productId' => 'i.PRODUCT_ID',
        'targetId' => 'i.TARGET_ID',
        'contentText' => 'i.CONTENT_TEXT',
        'remark' => 'i.REMARK',
        'alias' => 'i.ALIAS',
        'versionType' => 'i.VERSION_TYPE',
        'versionRemark' => 'i.VERSION_REMARK',
        'abbreviation' => 'i.ABBREVIATION',
        'hardware' => 'i.HARDWARE',
        'oldCode' => 'i.OLD_CODE',
        'createTime' => 'i.CREATE_TIME',
        'createUser' => 'i.CREATE_USER',
        'updateTime' => 'i.UPDATE_TIME',
        'updateUser' => 'i.UPDATE_USER',
        'tenantId' => 'i.TENANT_ID',
    ];
    private const FIELDS = [
        'i.ID',
        'i.PRODUCT_ID',
        'i.TARGET_ID',
        'i.CONTENT_TEXT',
        'i.REMARK',
        'i.ALIAS',
        'i.VERSION_TYPE',
        'i.VERSION_REMARK',
        'i.ABBREVIATION',
        'i.HARDWARE',
        'i.OLD_CODE',
        'i.DELETE_FLAG',
        'i.EXT_JSON',
        'i.CREATE_TIME',
        'i.CREATE_USER',
        'i.UPDATE_TIME',
        'i.UPDATE_USER',
        'i.TENANT_ID',
        'creator.NAME AS CREATE_USER_NAME',
        'updater.NAME AS UPDATE_USER_NAME',
        'product.PRODUCT_NAME AS PRODUCT_NAME',
        'target.PRODUCT_NAME AS TARGET_PRODUCT_NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->productInfoQuery($filters, $payload)->count();
        $rows = $this->applySort($this->productInfoQuery($filters, $payload), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->rows($rows),
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
        $rows = $this->applySort($this->productInfoQuery($filters, $payload), $filters)
            ->select()
            ->toArray();

        return $this->rows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->productInfoQuery(['id' => $id], $payload)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project product info not found', 404);
        }

        return $this->row($row);
    }

    private function productInfoQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_sale_project_product_info')
            ->alias('i')
            ->field(self::FIELDS)
            ->leftJoin('sys_user creator', 'creator.ID = i.CREATE_USER')
            ->leftJoin('sys_user updater', 'updater.ID = i.UPDATE_USER')
            ->leftJoin('biz_product product', 'product.ID = i.PRODUCT_ID')
            ->leftJoin('biz_product target', 'target.ID = i.TARGET_ID')
            ->where(function ($query): void {
                $query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('i.ID', (string)$filters['id']);
        }

        if (!empty($filters['productId'])) {
            $query->where('i.PRODUCT_ID', (string)$filters['productId']);
        }

        if (!empty($filters['targetId'])) {
            $query->where('i.TARGET_ID', (string)$filters['targetId']);
        }

        $targetIds = $this->normalizeIdList($filters['targetIds'] ?? []);
        if ($targetIds !== []) {
            $query->whereIn('i.TARGET_ID', $targetIds);
        }

        $searchKey = trim((string)($filters['searchKey'] ?? ''));
        if ($searchKey !== '') {
            $query->where(function ($query) use ($searchKey): void {
                $query->whereLike('i.CONTENT_TEXT', '%' . $searchKey . '%')
                    ->whereOr('i.REMARK', 'like', '%' . $searchKey . '%')
                    ->whereOr('i.ALIAS', 'like', '%' . $searchKey . '%')
                    ->whereOr('i.VERSION_TYPE', 'like', '%' . $searchKey . '%')
                    ->whereOr('i.VERSION_REMARK', 'like', '%' . $searchKey . '%')
                    ->whereOr('i.ABBREVIATION', 'like', '%' . $searchKey . '%')
                    ->whereOr('i.HARDWARE', 'like', '%' . $searchKey . '%')
                    ->whereOr('i.OLD_CODE', 'like', '%' . $searchKey . '%');
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('i.ID', 'asc');
        }

        return $query->order('i.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => $this->row($row), $rows));
    }

    private function row(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'productId' => $row['PRODUCT_ID'] ?? null,
            'targetId' => $row['TARGET_ID'] ?? null,
            'contentText' => $row['CONTENT_TEXT'] ?? null,
            'remark' => $row['REMARK'] ?? null,
            'alias' => $row['ALIAS'] ?? null,
            'versionType' => $row['VERSION_TYPE'] ?? null,
            'versionRemark' => $row['VERSION_REMARK'] ?? null,
            'abbreviation' => $row['ABBREVIATION'] ?? null,
            'hardware' => $row['HARDWARE'] ?? null,
            'oldCode' => $row['OLD_CODE'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'createUserName' => $row['CREATE_USER_NAME'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'updateUserName' => $row['UPDATE_USER_NAME'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'productName' => $row['PRODUCT_NAME'] ?? null,
            'targetProductName' => $row['TARGET_PRODUCT_NAME'] ?? null,
        ];
    }

    /**
     * @param mixed $value
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
                return trim((string)($item['id'] ?? $item['targetId'] ?? $item['ID'] ?? ''));
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
}
