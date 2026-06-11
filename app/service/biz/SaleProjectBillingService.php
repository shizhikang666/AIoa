<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only sales-project billing queries compatible with Java invoicing,
 * delivery invoice, reissue order, and project rating controllers.
 */
class SaleProjectBillingService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';

    /**
     * Java BizSaleProjectStateEnum.getInvoiceableStates().
     */
    private const INVOICEABLE_PROJECT_STATES = [
        'PARTIALLY_SHIPPED',
        'SHIPPED',
        'COMPLETED',
    ];
    private const INVOICING_STATE_COMPLETE = 'INVOICING_STATE_COMPLETE';

    private const INVOICING_SORT_FIELDS = [
        'id' => 'i.ID',
        'projectId' => 'i.PROJECT_ID',
        'projectName' => 'p.PROJECT_NAME',
        'amount' => 'i.AMOUNT',
        'invoicingState' => 'i.INVOICING_STATE',
        'invoicingCategory' => 'i.INVOICING_CATEGORY',
        'companyName' => 'i.COMPANY_NAME',
        'customerCompany' => 'i.CUSTOMER_COMPANY',
        'unit' => 'i.UNIT',
        'phone' => 'i.PHONE',
        'createTime' => 'i.CREATE_TIME',
        'updateTime' => 'i.UPDATE_TIME',
        'tenantId' => 'i.TENANT_ID',
    ];

    private const INVOICE_SORT_FIELDS = [
        'id' => 'v.ID',
        'projectId' => 'v.PROJECT_ID',
        'projectName' => 'p.PROJECT_NAME',
        'logisticsCategory' => 'v.LOGISTICS_CATEGORY',
        'phone' => 'v.PHONE',
        'logisticsId' => 'v.LOGISTICS_ID',
        'freight' => 'v.FREIGHT',
        'freightTime' => 'v.FREIGHT_TIME',
        'freightCategory' => 'v.FREIGHT_CATEGORY',
        'unit' => 'v.UNIT',
        'address' => 'v.ADDRESS',
        'createTime' => 'v.CREATE_TIME',
        'updateTime' => 'v.UPDATE_TIME',
        'tenantId' => 'v.TENANT_ID',
    ];

    private const INVOICE_ITEM_SORT_FIELDS = [
        'id' => 'item.ID',
        'invoiceId' => 'item.INVOICE_ID',
        'projectProductItemId' => 'item.PROJECT_PRODUCT_ITEM_ID',
        'warehousesId' => 'item.WAREHOUSES_ID',
        'amount' => 'item.AMOUNT',
        'createTime' => 'item.CREATE_TIME',
        'updateTime' => 'item.UPDATE_TIME',
        'tenantId' => 'item.TENANT_ID',
    ];

    private const RATE_SORT_FIELDS = [
        'id' => 'r.ID',
        'projectId' => 'r.PROJECT_ID',
        'projectName' => 'p.PROJECT_NAME',
        'rateAmount' => 'r.RATE_AMOUNT',
        'subject' => 'r.SUBJECT',
        'createTime' => 'r.CREATE_TIME',
        'updateTime' => 'r.UPDATE_TIME',
        'tenantId' => 'r.TENANT_ID',
    ];

    public function invoicingPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->invoicingQuery($filters, $payload, true)->count('DISTINCT i.ID');
        $rows = $this->applySort($this->invoicingQuery($filters, $payload, true), $filters, self::INVOICING_SORT_FIELDS, 'i.ID')
            ->field($this->invoicingFields())
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResponse($this->rows($rows), $total, $page, $limit);
    }

    public function invoicingCustomer(string $customerId, array $payload = []): ?array
    {
        $row = $this->invoicingQuery(['customer' => $customerId], $payload, false)
            ->field($this->invoicingFields())
            ->order('i.CREATE_TIME', 'desc')
            ->order('i.ID', 'desc')
            ->find();

        return is_array($row) && $row !== [] ? $this->rows([$row])[0] : null;
    }

    public function invoicingDetail(string $id, array $payload = []): array
    {
        $row = $this->invoicingQuery(['id' => $id], $payload, false)
            ->field($this->invoicingFields())
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project invoicing not found', 404);
        }

        return $this->rows([$row])[0];
    }

    public function invoicingComplete(string $id, array $payload = []): ?array
    {
        return Db::transaction(function () use ($id, $payload): ?array {
            $row = $this->invoicingQuery(['id' => $id], $payload, false)
                ->field('i.ID, i.INVOICING_STATE')
                ->find();
            if (!is_array($row) || $row === []) {
                throw new RuntimeException('sale project invoicing not found', 404);
            }

            Db::name('biz_sale_project_invoicing')
                ->where('ID', $id)
                ->update([
                    'INVOICING_STATE' => self::INVOICING_STATE_COMPLETE,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                ]);

            return null;
        });
    }

    public function invoicePage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->invoiceQuery($filters, $payload)->count('DISTINCT v.ID');
        $rows = $this->applySort($this->invoiceQuery($filters, $payload), $filters, self::INVOICE_SORT_FIELDS, 'v.ID')
            ->field($this->invoiceFields())
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResponse($this->rows($rows), $total, $page, $limit);
    }

    /**
     * @return array<int, array{bizSaleProjectInvoice: array<string, mixed>, invoiceItems: array<int, array<string, mixed>>}>
     */
    public function invoiceList(string $projectId, array $payload = []): array
    {
        $rows = $this->invoiceQuery(['projectId' => $projectId], $payload)
            ->field($this->invoiceFields())
            ->order('v.ID', 'asc')
            ->select()
            ->toArray();
        $invoices = $this->rows($rows);
        if ($invoices === []) {
            return [];
        }

        $items = $this->invoiceItemsByInvoiceIds(array_column($invoices, 'id'), $payload);
        $result = [];
        foreach ($invoices as $invoice) {
            $invoiceId = (string)($invoice['id'] ?? '');
            $result[] = [
                'bizSaleProjectInvoice' => $invoice,
                'invoiceItems' => $items[$invoiceId] ?? [],
            ];
        }

        return $result;
    }

    public function invoiceItemPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->invoiceItemQuery($filters, $payload)->count('DISTINCT item.ID');
        $rows = $this->applySort($this->invoiceItemQuery($filters, $payload), $filters, self::INVOICE_ITEM_SORT_FIELDS, 'item.PROJECT_PRODUCT_ITEM_ID')
            ->field($this->invoiceItemFields())
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResponse($this->rows($rows), $total, $page, $limit);
    }

    /**
     * @return array<int, array{order: array<string, mixed>, productItemList: array<int, array<string, mixed>>}>
     */
    public function reissueOrderListQuery(string $projectId, array $payload = []): array
    {
        $rows = $this->reissueOrderQuery($projectId, $payload)
            ->field('o.*, p.PROJECT_NAME AS PROJECT_NAME, p.CUSTOMER AS CUSTOMER, c.NAME AS CUSTOMER_NAME')
            ->order('o.ID', 'asc')
            ->select()
            ->toArray();
        $orders = $this->rows($rows);
        if ($orders === []) {
            return [];
        }

        $items = $this->reissueProductItemsByOrderIds(array_column($orders, 'id'), $projectId, $payload);

        return array_map(static fn (array $order): array => [
            'order' => $order,
            'productItemList' => $items[(string)($order['id'] ?? '')] ?? [],
        ], $orders);
    }

    public function ratePage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->rateQuery($filters, $payload)->count('DISTINCT r.ID');
        $rows = $this->applySort($this->rateQuery($filters, $payload), $filters, self::RATE_SORT_FIELDS, 'r.ID')
            ->field('r.*, p.PROJECT_NAME AS PROJECT_NAME, c.NAME AS CUSTOMER_NAME')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResponse($this->rows($rows), $total, $page, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rateList(string $projectId, array $payload = []): array
    {
        $rows = $this->rateQuery(['projectId' => $projectId], $payload)
            ->field('r.*, p.PROJECT_NAME AS PROJECT_NAME, c.NAME AS CUSTOMER_NAME')
            ->order('r.ID', 'asc')
            ->select()
            ->toArray();

        return $this->rows($rows);
    }

    public function rateDetail(string $id, array $payload = []): array
    {
        $row = $this->rateQuery(['id' => $id], $payload)
            ->field('r.*, p.PROJECT_NAME AS PROJECT_NAME, c.NAME AS CUSTOMER_NAME')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sale project rate not found', 404);
        }

        return $this->rows([$row])[0];
    }

    public function rateAdd(array $input, array $payload = []): array
    {
        $projectId = $this->requiredInput($input, 'projectId');
        $subject = $this->requiredInput($input, 'subject');
        $content = array_key_exists('content', $input) ? (string)($input['content'] ?? '') : '';
        $rateAmount = $this->rateAmount($input['rateAmount'] ?? 0);

        return Db::transaction(function () use ($projectId, $subject, $content, $rateAmount, $input, $payload): array {
            $project = $this->assertRateProjectWritable($projectId, $payload, 'add');
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $id = $this->newId();

            Db::name('sale_project_rate')->insert([
                'ID' => $id,
                'PROJECT_ID' => $projectId,
                'RATE_AMOUNT' => $rateAmount,
                'CONTENT' => $content,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload, $project),
                'EXT_JSON' => $this->rateExtJson($input),
                'SUBJECT' => $subject,
            ]);

            return ['id' => $id];
        });
    }

    public function rateEdit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $row = $this->rateQuery(['id' => $id], $payload)
                ->field('r.*')
                ->find();
            if (!is_array($row) || $row === []) {
                throw new RuntimeException('sale project rate not found', 404);
            }

            $currentProjectId = (string)$row['PROJECT_ID'];
            $project = $this->assertRateProjectWritable($currentProjectId, $payload, 'edit');
            $projectId = trim((string)($input['projectId'] ?? $input['project_id'] ?? $currentProjectId));
            if ($projectId === '') {
                throw new RuntimeException('missing projectId', 400);
            }
            if ($projectId !== $currentProjectId) {
                $project = $this->assertRateProjectWritable($projectId, $payload, 'edit');
            }

            $updates = [
                'PROJECT_ID' => $projectId,
                'TENANT_ID' => $this->tenantId($input, $payload, $project),
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
            ];

            if (array_key_exists('rateAmount', $input) || array_key_exists('rate_amount', $input)) {
                $updates['RATE_AMOUNT'] = $this->rateAmount($input['rateAmount'] ?? $input['rate_amount']);
            }
            if (array_key_exists('content', $input)) {
                $updates['CONTENT'] = (string)($input['content'] ?? '');
            }
            if (array_key_exists('subject', $input)) {
                $updates['SUBJECT'] = trim((string)($input['subject'] ?? ''));
            }
            if (array_key_exists('imgList', $input) || array_key_exists('extJson', $input)) {
                $updates['EXT_JSON'] = $this->rateExtJson($input);
            }

            $updated = Db::name('sale_project_rate')
                ->where('ID', $id)
                ->update($updates);

            return ['id' => $id, 'count' => $updated];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function rateDelete(array $ids, array $payload = []): array
    {
        $idList = $this->normalizeIdList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $query = Db::name('sale_project_rate')->whereIn('ID', $idList);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $rows = $query->select()->toArray();
            if (count($rows) !== count($idList)) {
                throw new RuntimeException('sale project rate not found', 404);
            }

            foreach ($rows as $row) {
                $this->assertRateProjectWritable((string)$row['PROJECT_ID'], $payload, 'delete');
            }

            $updated = Db::name('sale_project_rate')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => ($this->currentUserId($payload) !== '' ? $this->currentUserId($payload) : null),
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function invoicingQuery(array $filters, array $payload, bool $onlyInvoiceable)
    {
        $query = Db::name('biz_sale_project_invoicing')
            ->alias('i')
            ->leftJoin('biz_sale_project p', 'p.ID = i.PROJECT_ID')
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->leftJoin('sys_user u', 'u.ID = p.USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG');
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $this->applyTenant($query, 'i', $filters, $payload);

        foreach ([
            'id' => 'i.ID',
            'projectId' => 'i.PROJECT_ID',
            'invoicingState' => 'i.INVOICING_STATE',
            'invoicingCategory' => 'i.INVOICING_CATEGORY',
            'customer' => 'p.CUSTOMER',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'companyName' => 'i.COMPANY_NAME',
            'customerCompany' => 'i.CUSTOMER_COMPANY',
            'unit' => 'i.UNIT',
            'phone' => 'i.PHONE',
            'projectName' => 'p.PROJECT_NAME',
            'customerName' => 'c.NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if ($onlyInvoiceable) {
            $query->whereIn('p.PROJECT_STATE', self::INVOICEABLE_PROJECT_STATES);
        }

        $this->applyTimeRange($query, 'i.CREATE_TIME', $filters['startCreateTime'] ?? '', $filters['endCreateTime'] ?? '');
        $this->applyProjectScope($query, $filters, $payload, 'p');

        return $query;
    }

    private function invoiceQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_sale_project_invoice')
            ->alias('v')
            ->leftJoin('biz_sale_project p', 'p.ID = v.PROJECT_ID')
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->leftJoin('sys_user u', 'u.ID = p.USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG');
        $this->whereNotDeleted($query, 'v.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $this->applyTenant($query, 'v', $filters, $payload);

        foreach ([
            'id' => 'v.ID',
            'projectId' => 'v.PROJECT_ID',
            'logisticsCategory' => 'v.LOGISTICS_CATEGORY',
            'phone' => 'v.PHONE',
            'freightCategory' => 'v.FREIGHT_CATEGORY',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'logisticsId' => 'v.LOGISTICS_ID',
            'unit' => 'v.UNIT',
            'address' => 'v.ADDRESS',
            'projectName' => 'p.PROJECT_NAME',
            'customerName' => 'c.NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        $this->applyTimeRange($query, 'v.FREIGHT_TIME', $filters['startFreightTime'] ?? '', $filters['endFreightTime'] ?? '');
        $this->applyProjectScope($query, $filters, $payload, 'p');

        return $query;
    }

    private function reissueOrderQuery(string $projectId, array $payload)
    {
        $query = Db::name('biz_sale_project_reissue_order')
            ->alias('o')
            ->leftJoin('biz_sale_project p', 'p.ID = o.PROJECT_ID')
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->where('o.PROJECT_ID', $projectId);
        $this->whereNotDeleted($query, 'o.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $this->applyTenant($query, 'o', [], $payload);
        $this->applyProjectScope($query, ['projectId' => $projectId], $payload, 'p');

        return $query;
    }

    private function rateQuery(array $filters, array $payload)
    {
        $query = Db::name('sale_project_rate')
            ->alias('r')
            ->leftJoin('biz_sale_project p', 'p.ID = r.PROJECT_ID')
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER');
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $this->applyTenant($query, 'r', $filters, $payload);

        if (!empty($filters['id'])) {
            $query->where('r.ID', (string)$filters['id']);
        }

        if (!empty($filters['projectId'])) {
            $query->where('r.PROJECT_ID', (string)$filters['projectId']);
        }

        $this->applyProjectScope($query, $filters, $payload, 'p');

        return $query;
    }

    private function assertRateProjectWritable(string $projectId, array $payload, string $action): array
    {
        $query = Db::name('biz_sale_project')->where('ID', $projectId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $project = $query->find();
        if (!is_array($project) || $project === []) {
            throw new RuntimeException('sale project not found', 404);
        }

        if ($this->canSeeAll($payload)) {
            return $project;
        }

        $projectOrg = trim((string)($project['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            if (!in_array($projectOrg, $scopeOrgIds, true)) {
                throw new RuntimeException("no permission to {$action} this sale project rate", 403);
            }

            return $project;
        }

        $userId = $this->currentUserId($payload);
        if ($userId === '' || trim((string)($project['USER'] ?? '')) !== $userId) {
            throw new RuntimeException("no permission to {$action} this sale project rate", 403);
        }

        return $project;
    }

    private function invoiceItemQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_sale_project_invoice_item')
            ->alias('item')
            ->leftJoin('biz_sale_project_product_item pi', 'pi.ID = item.PROJECT_PRODUCT_ITEM_ID')
            ->leftJoin('biz_product product', 'product.ID = pi.PRODUCT_ID')
            ->leftJoin('warehouses w', 'w.ID = item.WAREHOUSES_ID');
        $this->whereNotDeleted($query, 'item.DELETE_FLAG');
        $this->applyTenant($query, 'item', $filters, $payload);

        if (!empty($filters['invoiceId'])) {
            $query->where('item.INVOICE_ID', (string)$filters['invoiceId']);
        }

        if (!empty($filters['warehousesId'])) {
            $query->where('item.WAREHOUSES_ID', (string)$filters['warehousesId']);
        }

        return $query;
    }

    /**
     * @param array<int, string> $invoiceIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function invoiceItemsByInvoiceIds(array $invoiceIds, array $payload): array
    {
        $ids = $this->stringList($invoiceIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_sale_project_invoice_item')
            ->alias('item')
            ->leftJoin('biz_sale_project_product_item pi', 'pi.ID = item.PROJECT_PRODUCT_ITEM_ID')
            ->leftJoin('biz_product product', 'product.ID = pi.PRODUCT_ID')
            ->leftJoin('warehouses w', 'w.ID = item.WAREHOUSES_ID')
            ->field('item.*, pi.PROJECT_ID AS PROJECT_ID, pi.PRODUCT_ID AS PRODUCT_ID, product.PRODUCT_NAME AS PRODUCT_NAME, product.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, product.CATEGORY AS PRODUCT_SYS_CATEGORY, product.SPECS AS SPECS, w.NAME AS WAREHOUSES_NAME')
            ->whereIn('item.INVOICE_ID', $ids);
        $this->whereNotDeleted($query, 'item.DELETE_FLAG');
        $this->applyTenant($query, 'item', [], $payload);

        $result = [];
        foreach ($query->order('item.ID', 'asc')->select()->toArray() as $row) {
            $item = $this->normalizeRow($row);
            $result[(string)($item['invoiceId'] ?? '')][] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, string> $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function reissueProductItemsByOrderIds(array $orderIds, string $projectId, array $payload): array
    {
        $ids = $this->stringList($orderIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->field('i.*, p.PRODUCT_NAME AS PRODUCT_NAME, p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, p.CATEGORY AS PRODUCT_SYS_CATEGORY, p.SPECS AS SPECS, p.PURCHASE_PRICE AS PURCHASE_PRICE, p.SALE_PRICE AS SALE_PRICE, p.MIN_PRICE AS MIN_PRICE')
            ->whereIn('i.PROJECT_REISSUE_ORDER_ID', $ids)
            ->where('i.PROJECT_ID', $projectId);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');
        $this->applyTenant($query, 'i', [], $payload);

        $items = $this->rows($query->order('i.ID', 'asc')->select()->toArray());
        $children = $this->childrenByProductItemIds(array_column($items, 'id'), $payload);
        $result = [];

        foreach ($items as $item) {
            $item['children'] = $children[(string)($item['id'] ?? '')] ?? [];
            $result[(string)($item['projectReissueOrderId'] ?? '')][] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, string> $itemIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function childrenByProductItemIds(array $itemIds, array $payload): array
    {
        $ids = $this->stringList($itemIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('sale_project_product_item_relation')
            ->alias('r')
            ->leftJoin('biz_product p', 'p.ID = r.TARGET_ID')
            ->field('r.*, p.PRODUCT_NAME AS PRODUCT_NAME, p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, p.CATEGORY AS PRODUCT_SYS_CATEGORY, p.SPECS AS SPECS')
            ->whereIn('r.OBJECT_ID', $ids);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->applyTenant($query, 'r', [], $payload);

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

    private function invoicingFields(): string
    {
        return 'i.*, p.PROJECT_NAME AS PROJECT_NAME, p.PROJECT_STATE AS PROJECT_STATE, p.CUSTOMER AS CUSTOMER, c.NAME AS CUSTOMER_NAME, p.ORG AS ORG, org.NAME AS ORG_NAME, p.USER AS USER, u.NAME AS HEAD_NAME';
    }

    private function invoiceFields(): string
    {
        return 'v.*, p.PROJECT_NAME AS PROJECT_NAME, p.PROJECT_STATE AS PROJECT_STATE, p.CUSTOMER AS CUSTOMER, c.NAME AS CUSTOMER_NAME, p.ORG AS ORG, org.NAME AS ORG_NAME, p.USER AS USER, u.NAME AS HEAD_NAME';
    }

    private function invoiceItemFields(): string
    {
        return 'item.*, pi.PROJECT_ID AS PROJECT_ID, pi.PRODUCT_ID AS PRODUCT_ID, product.PRODUCT_NAME AS PRODUCT_NAME, product.PRODUCT_CATEGORY AS PRODUCT_CATEGORY, product.CATEGORY AS PRODUCT_SYS_CATEGORY, product.SPECS AS SPECS, w.NAME AS WAREHOUSES_NAME';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    private function applyProjectScope($query, array $filters, array $payload, string $projectAlias): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn("{$projectAlias}.ORG", $orgIds);
        }

        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn("{$projectAlias}.ORG", $scopeOrgIds);

            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where("{$projectAlias}.USER", $userId);
        }
    }

    private function applyTenant($query, string $alias, array $filters, array $payload): void
    {
        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where("{$alias}.TENANT_ID", $tenantId);
        }
    }

    private function applySort($query, array $filters, array $fieldMap, string $defaultColumn)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset($fieldMap[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order($fieldMap[$sortField], $direction)->order($defaultColumn, 'asc');
        }

        return $query->order($defaultColumn, 'asc');
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
        $ids = [];
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct)) {
            $ids = array_merge($ids, $direct);
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (is_array($scope)) {
                    $ids[] = $scope['orgId'] ?? $scope['org_id'] ?? '';
                }
            }
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
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

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function pageResponse(array $records, int $total, int $page, int $limit): array
    {
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

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $value)));
    }

    /**
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
                return trim((string)($item['id'] ?? $item['ID'] ?? ''));
            }

            return trim((string)$item);
        }, $value))));
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function rateAmount(mixed $value): string
    {
        $amount = trim((string)$value);
        if ($amount === '') {
            return '0.00';
        }

        if (!is_numeric($amount)) {
            throw new RuntimeException('invalid rateAmount', 400);
        }

        return number_format((float)$amount, 2, '.', '');
    }

    private function rateExtJson(array $input): ?string
    {
        if (!empty($input['imgList']) && is_array($input['imgList'])) {
            $imgList = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $input['imgList'])));
            if ($imgList !== []) {
                return json_encode(['imgList' => $imgList], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if (array_key_exists('extJson', $input)) {
            if (is_array($input['extJson'])) {
                return json_encode($input['extJson'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $input['extJson'] !== null ? (string)$input['extJson'] : null;
        }

        return null;
    }

    private function tenantId(array $input, array $payload, array $project): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? $project['TENANT_ID'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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
}
