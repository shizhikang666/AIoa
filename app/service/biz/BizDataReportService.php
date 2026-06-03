<?php

declare(strict_types=1);

namespace app\service\biz;

use think\facade\Db;

/**
 * Read-only report queries compatible with selected Java BizDataReportController endpoints.
 */
class BizDataReportService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DEAL_STATES = ['WAIT_DELIVER', 'SHIPPED', 'PARTIALLY_SHIPPED', 'COMPLETED'];
    private const PROJECT_FIELDS = <<<SQL
p.ID AS ID,
p.CUSTOMER AS CUSTOMER,
p.PROJECT_NAME AS PROJECT_NAME,
p.PROJECT_STATE AS PROJECT_STATE,
p.PLAY_STATE AS PLAY_STATE,
p.VISIBILITY AS VISIBILITY,
p.INIT_PRICE AS INIT_PRICE,
p.TOTAL_PRICE AS TOTAL_PRICE,
p.AMOUNT_COLLECTED AS AMOUNT_COLLECTED,
p.PROJECT_CATEGORY AS PROJECT_CATEGORY,
p.USER AS PROJECT_USER,
p.ORG AS ORG,
p.REMARK AS REMARK,
p.DELETE_FLAG AS DELETE_FLAG,
p.CREATE_TIME AS CREATE_TIME,
p.CREATE_USER AS CREATE_USER,
p.UPDATE_TIME AS UPDATE_TIME,
p.UPDATE_USER AS UPDATE_USER,
p.TENANT_ID AS TENANT_ID,
p.VERSION AS VERSION,
p.CONSIGNEE AS CONSIGNEE,
p.PHONE AS PHONE,
p.UNIT AS UNIT,
p.ADDRESS AS ADDRESS,
p.LOGISTICS_CATEGORY AS LOGISTICS_CATEGORY,
p.DELIVERY_NOTE AS DELIVERY_NOTE,
p.PROCESS_ID AS PROCESS_ID,
p.SPECIMEN_CATEGORY AS SPECIMEN_CATEGORY,
p.SPECIMEN_NAME AS SPECIMEN_NAME,
p.AREA AS AREA,
p.DETAILS_ADDRESS AS DETAILS_ADDRESS,
p.PROJECT_CODE AS PROJECT_CODE,
p.ACCOUNT_ID AS ACCOUNT_ID,
p.PAYER_CATEGORY AS PAYER_CATEGORY,
p.FREIGHT AS FREIGHT,
p.FREIGHT_CATEGORY AS FREIGHT_CATEGORY,
p.COMPLETION_DATE AS COMPLETION_DATE,
p.REBATE_AMOUNT AS REBATE_AMOUNT,
p.DEAL_AMOUNT AS DEAL_AMOUNT,
p.HISTORY_AMOUNT AS HISTORY_AMOUNT,
p.TOTAL_RETURN_AMOUNT AS TOTAL_RETURN_AMOUNT,
p.TOTAL_REFUND_AMOUNT AS TOTAL_REFUND_AMOUNT,
p.REPEAL_CONTENT AS REPEAL_CONTENT,
p.special_type AS SPECIAL_TYPE,
c.NAME AS CUSTOMER_NAME,
c.ADDRESS AS CUSTOMER_ADDRESS,
c.SOURCE_TYPE AS CUSTOMER_SOURCE_TYPE,
c.CUSTOM_TYPE AS CUSTOM_TYPE,
u.NAME AS HEAD_NAME,
u.PHONE AS HEAD_PHONE,
org.NAME AS ORG_NAME,
a.ACCOUNT_NAME AS ACCOUNT_NAME
SQL;
    private const PRODUCT_ITEM_FIELDS = <<<SQL
i.ID AS ID,
i.PROJECT_ID AS PROJECT_ID,
i.PRODUCT_ID AS PRODUCT_ID,
i.CATEGORY AS CATEGORY,
i.STATE AS STATE,
i.NUMBER AS NUMBER,
i.DELIVERY AS DELIVERY,
i.UNIT_PRICE AS UNIT_PRICE,
i.DISCOUNT_RATE AS DISCOUNT_RATE,
i.PRICE AS PRICE,
i.REMARK AS REMARK,
i.EXT_JSON AS EXT_JSON,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
i.VERSION AS VERSION,
i.PROJECT_REISSUE_ORDER_ID AS PROJECT_REISSUE_ORDER_ID,
i.MARK AS MARK,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.CATEGORY AS PRODUCT_SYS_CATEGORY,
p.SPECS AS SPECS,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.MIN_PRICE AS MIN_PRICE,
p.RECONCILIATION_TYPE AS RECONCILIATION_TYPE,
p.RECONCILIATION_AMOUNT AS RECONCILIATION_AMOUNT
SQL;
    private const RETURN_ORDER_FIELDS = <<<SQL
r.ID AS ID,
r.PROJECT_ID AS PROJECT_ID,
r.AMOUNT AS AMOUNT,
r.STATE AS STATE,
r.PROCESS_ID AS PROCESS_ID,
r.REMARK AS REMARK,
r.WAREHOUSES_ID AS WAREHOUSES_ID,
r.LOGISTICS_CATEGORY AS LOGISTICS_CATEGORY,
r.LOGISTICS_ID AS LOGISTICS_ID,
r.USER AS USER,
r.ORG AS ORG,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.EXT_JSON AS EXT_JSON,
r.TENANT_ID AS TENANT_ID,
w.NAME AS WAREHOUSE_NAME,
u.NAME AS HEAD_NAME,
org.NAME AS ORG_NAME
SQL;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function saleProjectListDetails(array $filters = [], array $payload = []): array
    {
        $projects = $this->projectRows(
            $this->saleProjectQuery($filters, $payload)
                ->order('p.COMPLETION_DATE', 'asc')
                ->order('p.ID', 'asc')
                ->select()
                ->toArray()
        );
        $projectIds = array_column($projects, 'id');
        $productItems = $this->productItemsByProjectIds($projectIds, $payload);
        $returnOrders = $this->returnOrdersByProjectIds($projectIds, $payload);

        return array_values(array_map(function (array $project) use ($productItems, $returnOrders): array {
            $projectId = (string)($project['id'] ?? '');
            $project['productList'] = $productItems[$projectId] ?? [];
            $project['returnOrders'] = $returnOrders[$projectId] ?? [];

            return $project;
        }, $projects));
    }

    private function saleProjectQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_sale_project')
            ->alias('p')
            ->field(self::PROJECT_FIELDS)
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->leftJoin('sys_user u', 'u.ID = p.USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->leftJoin('settlement_account a', 'a.ID = p.ACCOUNT_ID')
            ->whereIn('p.PROJECT_STATE', self::DEAL_STATES);
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('p.ORG', $orgIds);
            }
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('p.ORG', $scopeOrgIds);
        } else {
            $userId = trim((string)($payload['userId'] ?? $payload['user_id'] ?? $payload['id'] ?? ''));
            if ($userId !== '') {
                $query->where('p.USER', $userId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (!empty($filters['startCreateTime']) && !empty($filters['endCreateTime'])) {
            $query->whereBetweenTime('p.COMPLETION_DATE', (string)$filters['startCreateTime'], (string)$filters['endCreateTime']);
        }

        if (!empty($filters['headName'])) {
            $query->whereLike('u.NAME', '%' . trim((string)$filters['headName']) . '%');
        }

        return $query;
    }

    /**
     * @param array<int, string|null> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function productItemsByProjectIds(array $projectIds, array $payload): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $query = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->field(self::PRODUCT_ITEM_FIELDS)
            ->whereIn('i.PROJECT_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $items = $this->productItemRows($query->order('i.ID', 'asc')->select()->toArray());
        $children = $this->childrenByItemIds(array_column($items, 'id'), $payload);
        $result = [];

        foreach ($items as $item) {
            $item['children'] = $children[(string)($item['id'] ?? '')] ?? [];
            $result[(string)($item['projectId'] ?? '')][] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, string|null> $itemIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function childrenByItemIds(array $itemIds, array $payload): array
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->leftJoin('biz_product p', 'p.ID = r.TARGET_ID')
            ->field('r.*, p.PRODUCT_NAME AS PRODUCT_NAME, p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, p.CATEGORY AS PRODUCT_SYS_CATEGORY, p.SPECS AS SPECS, p.PURCHASE_PRICE AS PURCHASE_PRICE, p.SALE_PRICE AS SALE_PRICE, p.MIN_PRICE AS MIN_PRICE, p.RECONCILIATION_TYPE AS RECONCILIATION_TYPE, p.RECONCILIATION_AMOUNT AS RECONCILIATION_AMOUNT')
            ->whereIn('r.OBJECT_ID', $ids);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('r.ID', 'asc')->select()->toArray() as $row) {
            $child = $this->normalizeRow($row);
            if (empty($child['extJson'])) {
                $child['extJson'] = json_encode([
                    'product' => [
                        'id' => $child['targetId'] ?? null,
                        'productName' => $child['productName'] ?? null,
                        'productCategory' => $child['productCategory'] ?? null,
                        'category' => $child['productSysCategory'] ?? null,
                        'specs' => $child['specs'] ?? null,
                        'purchasePrice' => $child['purchasePrice'] ?? null,
                        'salePrice' => $child['salePrice'] ?? null,
                        'minPrice' => $child['minPrice'] ?? null,
                        'reconciliationType' => $child['reconciliationType'] ?? null,
                        'reconciliationAmount' => $child['reconciliationAmount'] ?? null,
                    ],
                    'number' => $child['number'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
            }
            $result[(string)($child['objectId'] ?? '')][] = $child;
        }

        return $result;
    }

    /**
     * @param array<int, string|null> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function returnOrdersByProjectIds(array $projectIds, array $payload): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $query = Db::name('return_order')
            ->alias('r')
            ->leftJoin('warehouses w', 'w.ID = r.WAREHOUSES_ID')
            ->leftJoin('sys_user u', 'u.ID = r.USER')
            ->leftJoin('sys_org org', 'org.ID = r.ORG')
            ->field(self::RETURN_ORDER_FIELDS)
            ->whereIn('r.PROJECT_ID', $ids);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($this->returnOrderRows($query->order('r.CREATE_TIME', 'asc')->select()->toArray()) as $row) {
            $result[(string)($row['projectId'] ?? '')][] = $row;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function projectRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'customer' => $this->value($row, 'CUSTOMER', 'customer'),
                'customerName' => $this->value($row, 'CUSTOMER_NAME', 'customerName'),
                'customerAddress' => $this->value($row, 'CUSTOMER_ADDRESS', 'customerAddress'),
                'customerSourceType' => $this->value($row, 'CUSTOMER_SOURCE_TYPE', 'customerSourceType'),
                'customType' => $this->value($row, 'CUSTOM_TYPE', 'customType'),
                'projectName' => $this->value($row, 'PROJECT_NAME', 'projectName'),
                'projectState' => $this->value($row, 'PROJECT_STATE', 'projectState'),
                'playState' => $this->value($row, 'PLAY_STATE', 'playState'),
                'visibility' => $this->value($row, 'VISIBILITY', 'visibility'),
                'initPrice' => $this->decimal($this->value($row, 'INIT_PRICE', 'initPrice')),
                'totalPrice' => $this->decimal($this->value($row, 'TOTAL_PRICE', 'totalPrice')),
                'amountCollected' => $this->decimal($this->value($row, 'AMOUNT_COLLECTED', 'amountCollected')),
                'projectCategory' => $this->value($row, 'PROJECT_CATEGORY', 'projectCategory'),
                'user' => $this->value($row, 'PROJECT_USER', 'user'),
                'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
                'headPhone' => $this->value($row, 'HEAD_PHONE', 'headPhone'),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'consignee' => $this->value($row, 'CONSIGNEE', 'consignee'),
                'phone' => $this->value($row, 'PHONE', 'phone'),
                'unit' => $this->value($row, 'UNIT', 'unit'),
                'address' => $this->value($row, 'ADDRESS', 'address'),
                'logisticsCategory' => $this->value($row, 'LOGISTICS_CATEGORY', 'logisticsCategory'),
                'deliveryNote' => $this->value($row, 'DELIVERY_NOTE', 'deliveryNote'),
                'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
                'specimenCategory' => $this->value($row, 'SPECIMEN_CATEGORY', 'specimenCategory'),
                'specimenName' => $this->value($row, 'SPECIMEN_NAME', 'specimenName'),
                'area' => $this->value($row, 'AREA', 'area'),
                'detailsAddress' => $this->value($row, 'DETAILS_ADDRESS', 'detailsAddress'),
                'projectCode' => $this->value($row, 'PROJECT_CODE', 'projectCode'),
                'accountId' => $this->value($row, 'ACCOUNT_ID', 'accountId'),
                'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
                'payerCategory' => $this->value($row, 'PAYER_CATEGORY', 'payerCategory'),
                'freight' => $this->decimal($this->value($row, 'FREIGHT', 'freight')),
                'freightCategory' => $this->value($row, 'FREIGHT_CATEGORY', 'freightCategory'),
                'completionDate' => $this->value($row, 'COMPLETION_DATE', 'completionDate'),
                'rebateAmount' => $this->decimal($this->value($row, 'REBATE_AMOUNT', 'rebateAmount')),
                'dealAmount' => $this->decimal($this->value($row, 'DEAL_AMOUNT', 'dealAmount')),
                'historyAmount' => $this->decimal($this->value($row, 'HISTORY_AMOUNT', 'historyAmount')),
                'totalReturnAmount' => $this->decimal($this->value($row, 'TOTAL_RETURN_AMOUNT', 'totalReturnAmount')),
                'totalRefundAmount' => $this->decimal($this->value($row, 'TOTAL_REFUND_AMOUNT', 'totalRefundAmount')),
                'repealContent' => $this->value($row, 'REPEAL_CONTENT', 'repealContent'),
                'specialType' => $this->value($row, 'SPECIAL_TYPE', 'specialType'),
                'productList' => [],
                'returnOrders' => [],
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function productItemRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'category' => $this->value($row, 'CATEGORY', 'category'),
                'state' => $this->value($row, 'STATE', 'state'),
                'number' => $this->decimal($this->value($row, 'NUMBER', 'number')),
                'delivery' => $this->decimal($this->value($row, 'DELIVERY', 'delivery')),
                'unitPrice' => $this->decimal($this->value($row, 'UNIT_PRICE', 'unitPrice')),
                'discountRate' => $this->decimal($this->value($row, 'DISCOUNT_RATE', 'discountRate')),
                'price' => $this->decimal($this->value($row, 'PRICE', 'price')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'projectReissueOrderId' => $this->value($row, 'PROJECT_REISSUE_ORDER_ID', 'projectReissueOrderId'),
                'mark' => $this->value($row, 'MARK', 'mark'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
                'productSysCategory' => $this->value($row, 'PRODUCT_SYS_CATEGORY', 'productSysCategory'),
                'specs' => $this->value($row, 'SPECS', 'specs'),
                'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
                'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
                'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
                'reconciliationType' => $this->value($row, 'RECONCILIATION_TYPE', 'reconciliationType'),
                'reconciliationAmount' => $this->decimal($this->value($row, 'RECONCILIATION_AMOUNT', 'reconciliationAmount')),
                'children' => [],
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function returnOrderRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'state' => $this->value($row, 'STATE', 'state'),
                'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'warehousesId' => $this->value($row, 'WAREHOUSES_ID', 'warehousesId'),
                'warehouseName' => $this->value($row, 'WAREHOUSE_NAME', 'warehouseName'),
                'logisticsCategory' => $this->value($row, 'LOGISTICS_CATEGORY', 'logisticsCategory'),
                'logisticsId' => $this->value($row, 'LOGISTICS_ID', 'logisticsId'),
                'user' => $this->value($row, 'USER', 'user'),
                'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            ];
        }, $rows));
    }

    /**
     * @param array<int, string|null> $values
     * @return array<int, string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(static fn ($value): string => trim((string)$value), $values))));
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    /**
     * @return array<int, string>
     */
    private function orgAndChildren(string $orgId): array
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return [];
        }

        $rows = Db::name('sys_org')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID'])
            ->select()
            ->toArray();

        $childrenByParent = [];
        foreach ($rows as $row) {
            $childrenByParent[(string)($row['PARENT_ID'] ?? '')][] = (string)$row['ID'];
        }

        $result = [];
        $queue = [$orgId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct) && $direct !== []) {
            return array_values(array_unique(array_filter(array_map('strval', $direct))));
        }

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

    private function normalizeRow(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $camelKey = $this->camelKey($key);
            $result[$camelKey] = in_array($camelKey, [
                'number',
                'purchasePrice',
                'salePrice',
                'minPrice',
                'reconciliationAmount',
            ], true)
                ? $this->decimal($value)
                : $value;
        }

        return $result;
    }

    private function camelKey(string $key): string
    {
        $key = strtolower($key);

        return preg_replace_callback('/_([a-z0-9])/', static fn (array $matches): string => strtoupper($matches[1]), $key) ?? $key;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function value(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }
}
