<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only sale-project queries compatible with Java BizSaleProjectController.
 */
class SaleProjectService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const PROJECT_PLAY = 'PROJECT_PLAY';
    private const PUBLIC_VISIBILITY = 'PUBLIC';
    private const DISCARD_STATE = 'DISCARD';
    private const PURCHASE_ORDER_SETTLEMENT_COMPLETED = 'COMPLETED';

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
p.MIN_PRICE AS MIN_PRICE
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'p.ID',
        'customer' => 'p.CUSTOMER',
        'customerName' => 'c.NAME',
        'projectName' => 'p.PROJECT_NAME',
        'projectCode' => 'p.PROJECT_CODE',
        'projectState' => 'p.PROJECT_STATE',
        'playState' => 'p.PLAY_STATE',
        'visibility' => 'p.VISIBILITY',
        'initPrice' => 'p.INIT_PRICE',
        'totalPrice' => 'p.TOTAL_PRICE',
        'amountCollected' => 'p.AMOUNT_COLLECTED',
        'projectCategory' => 'p.PROJECT_CATEGORY',
        'user' => 'p.USER',
        'headName' => 'u.NAME',
        'org' => 'p.ORG',
        'orgName' => 'org.NAME',
        'completionDate' => 'p.COMPLETION_DATE',
        'createTime' => 'p.CREATE_TIME',
        'updateTime' => 'p.UPDATE_TIME',
        'tenantId' => 'p.TENANT_ID',
        'specialType' => 'p.special_type',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        return $this->pageResult($filters, $payload);
    }

    public function casePage(array $filters = [], array $payload = []): array
    {
        $filters['isCase'] = true;

        return $this->pageResult($filters, $payload);
    }

    public function operationPage(array $filters = [], array $payload = []): array
    {
        return $this->pageResult($filters, $payload);
    }

    public function publicPage(array $filters = [], array $payload = []): array
    {
        $filters['visibility'] = self::PUBLIC_VISIBILITY;

        return $this->pageResult($filters, $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDetail(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->projectQuery($filters, $payload), $filters)
            ->field(self::PROJECT_FIELDS)
            ->select()
            ->toArray();
        $projects = $this->projectRows($rows);

        return $this->detailsForProjects($projects, $payload);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field(self::PROJECT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $this->detailsForProjects($this->projectRows([$row]), $payload)[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function product(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field('p.ID AS ID')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        return $this->productItemsByProjectIds([$id], $payload)[$id] ?? [];
    }

    public function cost(string $id, array $payload = []): int|float
    {
        $total = 0.0;
        foreach ($this->costDetails($id, $payload)['items'] as $item) {
            $total += $this->number($item['amount'] ?? 0) * $this->number($item['avgUnitAmount'] ?? 0);
        }

        return $this->decimal($total) ?? 0;
    }

    public function costDetails(string $id, array $payload = []): array
    {
        $row = $this->projectQuery(['id' => $id], $payload)
            ->field('p.ID AS ID')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        $productItems = $this->productItemsByProjectIds([$id], $payload)[$id] ?? [];
        $returnOrders = $this->returnOrdersWithProductList($id, $payload);
        $productAmounts = [];
        $productNames = [];
        $projectProductItemsById = [];

        foreach ($productItems as $item) {
            $projectProductItemsById[(string)$item['id']] = $item;
            $this->addProductCostAmount($productAmounts, $productNames, $item, 1);
        }

        foreach ($returnOrders as $returnOrder) {
            foreach ($returnOrder['productList'] ?? [] as $returnItem) {
                $item = $projectProductItemsById[(string)($returnItem['projectProductItemId'] ?? '')] ?? null;
                if ($item === null) {
                    continue;
                }

                $this->addProductCostAmount($productAmounts, $productNames, $item, -1);
            }
        }

        $avgUnitAmounts = $this->averagePurchaseUnitAmounts(array_keys($productAmounts), $payload);
        $items = [];
        foreach ($productAmounts as $productId => $amount) {
            $items[] = [
                'transMap' => [],
                'productId' => $productId,
                'productName' => $productNames[$productId] ?? null,
                'amount' => $this->decimal($amount) ?? 0,
                'avgUnitAmount' => $this->decimal($avgUnitAmounts[$productId] ?? 0) ?? 0,
            ];
        }

        return [
            'items' => $items,
            'productItems' => $productItems,
            'returnOrders' => $returnOrders,
        ];
    }

    private function pageResult(array $filters, array $payload): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->projectQuery($filters, $payload)->count('DISTINCT p.ID');
        $rows = $this->applySort($this->projectQuery($filters, $payload), $filters)
            ->field(self::PROJECT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();
        $records = $this->projectRows($rows);
        $returnOrders = $this->relatedRowsByIds('return_order', 'PROJECT_ID', array_column($records, 'id'), $payload, 'CREATE_TIME');

        foreach ($records as &$record) {
            $record['returnOrders'] = $returnOrders[(string)$record['id']] ?? [];
        }
        unset($record);

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function projectQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_sale_project')
            ->alias('p')
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->leftJoin('sys_user u', 'u.ID = p.USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->leftJoin('settlement_account a', 'a.ID = p.ACCOUNT_ID');

        if ($this->truthy($filters['isCase'] ?? false)) {
            $query->join('sale_project_rate rate', 'rate.PROJECT_ID = p.ID', 'INNER')->distinct(true);
        }

        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        if (array_key_exists('showDiscard', $filters) && !$this->truthy($filters['showDiscard'])) {
            $query->where('p.PROJECT_STATE', '<>', self::DISCARD_STATE);
        }

        foreach ([
            'id' => 'p.ID',
            'customer' => 'p.CUSTOMER',
            'visibility' => 'p.VISIBILITY',
            'projectCategory' => 'p.PROJECT_CATEGORY',
            'accountId' => 'p.ACCOUNT_ID',
            'payerCategory' => 'p.PAYER_CATEGORY',
            'freightCategory' => 'p.FREIGHT_CATEGORY',
            'processId' => 'p.PROCESS_ID',
            'specialType' => 'p.special_type',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'projectName' => 'p.PROJECT_NAME',
            'projectCode' => 'p.PROJECT_CODE',
            'remark' => 'p.REMARK',
            'customerName' => 'c.NAME',
            'consignee' => 'p.CONSIGNEE',
            'phone' => 'p.PHONE',
            'area' => 'p.AREA',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        $this->whereInIfPresent($query, 'p.PROJECT_STATE', $this->stringList($filters['projectState'] ?? []));
        $this->whereInIfPresent($query, 'p.PLAY_STATE', $this->stringList($filters['playState'] ?? []));
        $this->whereInIfPresent($query, 'c.SOURCE_TYPE', $this->stringList($filters['customerSourceType'] ?? []));

        if (array_key_exists('kickback', $filters) && trim((string)$filters['kickback']) !== '') {
            $this->truthy($filters['kickback'])
                ? $query->where('p.REBATE_AMOUNT', '>=', 1)
                : $query->where('p.REBATE_AMOUNT', '<=', 0);
        }

        if (!empty($filters['user'])) {
            $this->applyUserFilter($query, (string)$filters['user']);
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                '(p.PROJECT_NAME LIKE ? OR p.PROJECT_CODE LIKE ? OR p.REMARK LIKE ? OR c.NAME LIKE ? OR u.NAME LIKE ? OR p.PHONE LIKE ?)',
                [$keyword, $keyword, $keyword, $keyword, $keyword, $keyword]
            );
        }

        if (array_key_exists('totalPrice', $filters) && trim((string)$filters['totalPrice']) !== '') {
            $query->whereLike('p.TOTAL_PRICE', '%' . trim((string)$filters['totalPrice']) . '%');
        }

        if (array_key_exists('amountCollected', $filters) && trim((string)$filters['amountCollected']) !== '') {
            $query->whereLike('p.AMOUNT_COLLECTED', '%' . trim((string)$filters['amountCollected']) . '%');
        }

        $this->applyCreateTimeRange($query, $filters);
        $this->applyCompletionTimeRange($query, $filters);
        $this->applyDataScope($query, $filters, $payload);

        return $query;
    }

    private function applyUserFilter($query, string $user): void
    {
        $user = trim($user);
        if ($user === '') {
            return;
        }

        $userIds = Db::name('sys_user')
            ->whereLike('NAME', '%' . $user . '%')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID');

        if ($userIds !== []) {
            $query->whereIn('p.USER', array_map('strval', $userIds));

            return;
        }

        $query->where('p.USER', $user);
    }

    private function applyDataScope($query, array $filters, array $payload): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('p.ORG', $orgIds);
            }

            return;
        }

        if ($this->canSeeAll($payload)) {
            return;
        }

        $scope = $payload['data_scope_org_ids'] ?? [];
        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }
        if (is_array($scope)) {
            $scope = array_values(array_filter(array_map(static fn ($id): string => trim((string)$id), $scope)));
            if ($scope !== []) {
                $query->whereIn('p.ORG', $scope);

                return;
            }
        }

        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId !== '') {
            $query->where('p.USER', $userId);
        }
    }

    private function applyCreateTimeRange($query, array $filters): void
    {
        $this->applyTimeRange($query, 'p.CREATE_TIME', $filters['startCreateTime'] ?? '', $filters['endCreateTime'] ?? '');
    }

    private function applyCompletionTimeRange($query, array $filters): void
    {
        $this->applyTimeRange($query, 'p.COMPLETION_DATE', $filters['startCompletionTime'] ?? '', $filters['endCompletionTime'] ?? '');
    }

    private function applyTimeRange($query, string $column, mixed $startValue, mixed $endValue): void
    {
        $start = trim((string)$startValue);
        $end = trim((string)$endValue);
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        } elseif ($start !== '') {
            $query->whereTime($column, '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime($column, '<=', $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('p.ID', 'asc');
        }

        return $query->order('p.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @return array<int, array<string, mixed>>
     */
    private function detailsForProjects(array $projects, array $payload): array
    {
        $projectIds = array_values(array_filter(array_map(static fn (array $row): string => (string)($row['id'] ?? ''), $projects)));
        if ($projectIds === []) {
            return [];
        }

        $productItems = $this->productItemsByProjectIds($projectIds, $payload);
        $invoiceList = $this->invoiceRowsByProjectIds($projectIds, $payload);
        $invoicingList = $this->relatedRowsByIds('biz_sale_project_invoicing', 'PROJECT_ID', $projectIds, $payload, 'CREATE_TIME');
        $paymentRecords = $this->paymentRowsByProjectIds($projectIds, $payload);
        $followUps = $this->relatedRowsByIds('sale_project_follow_up', 'PROJECT_ID', $projectIds, $payload, 'FOLLOW_UP_TIME');
        $changeLogs = $this->relatedRowsByIds('sales_project_field_change_log', 'OBJECT_ID', $projectIds, $payload, 'CREATE_TIME');
        $returnOrders = $this->relatedRowsByIds('return_order', 'PROJECT_ID', $projectIds, $payload, 'CREATE_TIME');

        return array_map(function (array $project) use (
            $productItems,
            $invoiceList,
            $invoicingList,
            $paymentRecords,
            $followUps,
            $changeLogs,
            $returnOrders
        ): array {
            $projectId = (string)$project['id'];

            return [
                'bizSaleProject' => $project,
                'productItems' => $productItems[$projectId] ?? [],
                'invoicingList' => $invoicingList[$projectId] ?? [],
                'invoiceList' => $invoiceList[$projectId] ?? [],
                'paymentRecords' => $paymentRecords[$projectId] ?? [],
                'saleProjectFollowUps' => $followUps[$projectId] ?? [],
                'changeLogs' => $changeLogs[$projectId] ?? [],
                'returnOrders' => $returnOrders[$projectId] ?? [],
            ];
        }, $projects);
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function productItemsByProjectIds(array $projectIds, array $payload): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->field(self::PRODUCT_ITEM_FIELDS)
            ->whereIn('i.PROJECT_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $rows = $query->order('i.ID', 'asc')->select()->toArray();
        $items = $this->productItemRows($rows);
        $children = $this->childrenByItemIds(array_column($items, 'id'), $payload);
        $result = [];

        foreach ($items as $item) {
            $item['children'] = $children[(string)$item['id']] ?? [];
            $result[(string)$item['projectId']][] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, string> $itemIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function childrenByItemIds(array $itemIds, array $payload): array
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->leftJoin('biz_product p', 'p.ID = r.TARGET_ID')
            ->field('r.*, p.PRODUCT_NAME AS PRODUCT_NAME, p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, p.CATEGORY AS PRODUCT_SYS_CATEGORY, p.SPECS AS SPECS')
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
                    ],
                ], JSON_UNESCAPED_UNICODE);
            }
            $result[(string)($child['objectId'] ?? '')][] = $child;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function returnOrdersWithProductList(string $projectId, array $payload): array
    {
        $orders = $this->relatedRowsByIds('return_order', 'PROJECT_ID', [$projectId], $payload, 'CREATE_TIME')[$projectId] ?? [];
        $itemsByOrderId = $this->returnOrderItemsByOrderIds(array_column($orders, 'id'), $payload);

        foreach ($orders as &$order) {
            $order['productList'] = $itemsByOrderId[(string)($order['id'] ?? '')] ?? [];
        }
        unset($order);

        return $orders;
    }

    /**
     * @param array<int, string|null> $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function returnOrderItemsByOrderIds(array $orderIds, array $payload): array
    {
        $ids = $this->stringList($orderIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('return_order_item')
            ->alias('i')
            ->leftJoin('biz_sale_project_product_item pi', 'pi.ID = i.PROJECT_PRODUCT_ITEM_ID')
            ->leftJoin('biz_product bp', 'bp.ID = pi.PRODUCT_ID')
            ->field(<<<SQL
i.ID AS ID,
i.RETURN_ORDER_ID AS RETURN_ORDER_ID,
i.PROJECT_PRODUCT_ITEM_ID AS PROJECT_PRODUCT_ITEM_ID,
i.AMOUNT AS AMOUNT,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
pi.PROJECT_ID AS PROJECT_ID,
pi.PRODUCT_ID AS PRODUCT_ID,
bp.PRODUCT_NAME AS PRODUCT_NAME,
bp.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
bp.CATEGORY AS PRODUCT_SYS_CATEGORY,
bp.SPECS AS SPECS,
bp.PURCHASE_PRICE AS PURCHASE_PRICE,
bp.SALE_PRICE AS SALE_PRICE,
bp.MIN_PRICE AS MIN_PRICE
SQL)
            ->whereIn('i.RETURN_ORDER_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('i.ID', 'asc')->select()->toArray() as $row) {
            $item = $this->normalizeRow($row);
            foreach (['amount', 'purchasePrice', 'salePrice', 'minPrice'] as $decimalField) {
                $item[$decimalField] = $this->decimal($item[$decimalField] ?? null);
            }
            $result[(string)($item['returnOrderId'] ?? '')][] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, float> $productAmounts
     * @param array<string, string|null> $productNames
     */
    private function addProductCostAmount(array &$productAmounts, array &$productNames, array $item, int $direction): void
    {
        $itemNumber = $this->number($item['number'] ?? 0);
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];

        if ($children === []) {
            $productId = trim((string)($item['productId'] ?? ''));
            if ($productId === '') {
                return;
            }

            $productAmounts[$productId] = ($productAmounts[$productId] ?? 0.0) + ($direction * $itemNumber);
            $productNames[$productId] ??= $item['productName'] ?? null;

            return;
        }

        foreach ($children as $child) {
            $productId = trim((string)($child['targetId'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $amount = $this->number($child['number'] ?? 0) * $itemNumber;
            $productAmounts[$productId] = ($productAmounts[$productId] ?? 0.0) + ($direction * $amount);
            $productNames[$productId] ??= $child['productName'] ?? null;
        }
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, float>
     */
    private function averagePurchaseUnitAmounts(array $productIds, array $payload): array
    {
        $ids = $this->stringList($productIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('biz_purchase_order_item')
            ->alias('i')
            ->leftJoin('biz_purchase_order o', 'o.ID = i.PURCHASE_ORDER_ID')
            ->field('i.PRODUCT_ID AS PRODUCT_ID, i.UNIT_AMOUNT AS UNIT_AMOUNT')
            ->whereIn('i.PRODUCT_ID', $ids)
            ->where('o.SETTLEMENT_STATUS', self::PURCHASE_ORDER_SETTLEMENT_COMPLETED);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'o.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $groups = [];
        foreach ($query->select()->toArray() as $row) {
            $productId = trim((string)($row['PRODUCT_ID'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $groups[$productId][] = $this->number($row['UNIT_AMOUNT'] ?? 0);
        }

        $result = [];
        foreach ($groups as $productId => $amounts) {
            $count = count($amounts);
            $result[$productId] = $count === 0 ? 0.0 : round(array_sum($amounts) / $count, 2);
        }

        return $result;
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function invoiceRowsByProjectIds(array $projectIds, array $payload): array
    {
        $invoices = $this->relatedRowsByIds('biz_sale_project_invoice', 'PROJECT_ID', $projectIds, $payload, 'CREATE_TIME');
        $flat = [];
        foreach ($invoices as $rows) {
            array_push($flat, ...$rows);
        }

        $itemsByInvoiceId = $this->relatedRowsByIds('biz_sale_project_invoice_item', 'INVOICE_ID', array_column($flat, 'id'), $payload, 'CREATE_TIME');
        foreach ($invoices as &$rows) {
            foreach ($rows as &$invoice) {
                $invoice['invoiceItems'] = $itemsByInvoiceId[(string)$invoice['id']] ?? [];
            }
            unset($invoice);
        }
        unset($rows);

        return $invoices;
    }

    /**
     * @param array<int, string> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function paymentRowsByProjectIds(array $projectIds, array $payload): array
    {
        $ids = $this->stringList($projectIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name('biz_payment_record')
            ->alias('r')
            ->leftJoin('settlement_account a', 'a.ID = r.TARGET_ID')
            ->field('r.*, a.ACCOUNT_NAME AS ACCOUNT_NAME')
            ->whereIn('r.OBJECT_ID', $ids)
            ->where('r.SETTLEMENT_CATEGORY', self::PROJECT_PLAY);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($query->order('r.CREATE_TIME', 'asc')->select()->toArray() as $row) {
            $record = $this->normalizeRow($row);
            $result[(string)($record['objectId'] ?? '')][] = $record;
        }

        return $result;
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function relatedRowsByIds(string $table, string $foreignKey, array $ids, array $payload, string $orderField): array
    {
        $ids = $this->stringList($ids);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = Db::name($table)->whereIn($foreignKey, $ids);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $result = [];
        $groupKey = $this->camelKey($foreignKey);
        foreach ($query->order($orderField, 'asc')->select()->toArray() as $row) {
            $normalized = $this->normalizeRow($row);
            $result[(string)($normalized[$groupKey] ?? '')][] = $normalized;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function projectRows(array $rows): array
    {
        return array_map(function (array $row): array {
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
            ];
        }, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function productItemRows(array $rows): array
    {
        return array_map(function (array $row): array {
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
                'children' => [],
            ];
        }, $rows);
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    /**
     * @param array<int, string> $values
     */
    private function whereInIfPresent($query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, $values);
        }
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

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? [];
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (in_array(strtolower((string)$roleCode), ['superadmin', 'tenantadmin', 'bizadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($item): string => trim((string)$item), $value)));
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function normalizeRow(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $result[$this->camelKey((string)$key)] = $this->normalizeValue($value);
        }

        return $result;
    }

    private function camelKey(string $key): string
    {
        $key = strtolower($key);

        return preg_replace_callback('/_([a-z0-9])/', static fn (array $matches): string => strtoupper($matches[1]), $key) ?? $key;
    }

    private function normalizeValue(mixed $value): mixed
    {
        return $value;
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0.0;
        }

        return (float)$value;
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
