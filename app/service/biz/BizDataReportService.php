<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only report queries compatible with selected Java BizDataReportController endpoints.
 */
class BizDataReportService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DEAL_STATES = ['WAIT_DELIVER', 'SHIPPED', 'PARTIALLY_SHIPPED', 'COMPLETED'];
    private const UNPAID_PLAY_STATES = ['PARTIALLY_PAID', 'UNPAID'];
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
    private const PAYMENT_RECORD_FIELDS = <<<SQL
r.ID AS ID,
r.OBJECT_ID AS OBJECT_ID,
r.TARGET_ID AS TARGET_ID,
r.SERIAL_ID AS SERIAL_ID,
r.PROCESS_ID AS PROCESS_ID,
r.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
r.PAYER AS PAYER,
r.BANK_NAME AS BANK_NAME,
r.BANK_ACCOUNT AS BANK_ACCOUNT,
r.REMARK AS REMARK,
r.PAYER_TIME AS PAYER_TIME,
r.AMOUNT AS AMOUNT,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.TENANT_ID AS TENANT_ID,
r.`USER` AS USER_ID,
r.ORG AS ORG,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
org.NAME AS ORG_NAME
SQL;
    private const PURCHASE_ORDER_FIELDS = <<<SQL
o.ID AS ID,
o.TITLE AS TITLE,
o.SETTLEMENT_STATUS AS SETTLEMENT_STATUS,
o.STORAGE_STATUS AS STORAGE_STATUS,
o.SUPPLIER_ID AS SUPPLIER_ID,
o.INSTANCE_ID AS INSTANCE_ID,
o.DESIRE_PURCHASE_DATE AS DESIRE_PURCHASE_DATE,
o.AMOUNT AS AMOUNT,
o.REMARK AS REMARK,
o.EXT_JSON AS EXT_JSON,
o.DELETE_FLAG AS DELETE_FLAG,
o.CREATE_TIME AS CREATE_TIME,
o.CREATE_USER AS CREATE_USER,
o.UPDATE_TIME AS UPDATE_TIME,
o.UPDATE_USER AS UPDATE_USER,
o.TENANT_ID AS TENANT_ID,
o.VERSION AS VERSION,
o.ORG AS ORG,
org.NAME AS ORG_NAME
SQL;
    private const PURCHASE_ORDER_ITEM_FIELDS = <<<SQL
i.ID AS ID,
i.PURCHASE_ORDER_ID AS PURCHASE_ORDER_ID,
i.STORAGE_STATUS AS STORAGE_STATUS,
i.PRODUCT_ID AS PRODUCT_ID,
i.AMOUNT AS AMOUNT,
i.NUMBER AS NUMBER,
i.UNIT_AMOUNT AS UNIT_AMOUNT,
i.DISCOUNT_RATE AS DISCOUNT_RATE,
i.REMARK AS REMARK,
i.EXT_JSON AS EXT_JSON,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
i.VERSION AS VERSION,
i.FREIGHT_SHARE_AMOUNT AS FREIGHT_SHARE_AMOUNT,
i.UNIT_COST_WITH_FREIGHT AS UNIT_COST_WITH_FREIGHT,
p.PRODUCT_NAME AS PRODUCT_NAME
SQL;
    private const PRODUCT_FIELDS = <<<SQL
p.ID AS ID,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.SAFETY_STOCK AS SAFETY_STOCK,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.MIN_PRICE AS MIN_PRICE,
p.CATEGORY AS CATEGORY,
p.DELETE_FLAG AS DELETE_FLAG,
p.CREATE_TIME AS CREATE_TIME,
p.CREATE_USER AS CREATE_USER,
p.UPDATE_TIME AS UPDATE_TIME,
p.UPDATE_USER AS UPDATE_USER,
p.TENANT_ID AS TENANT_ID,
p.SPECS AS SPECS,
p.ORG AS ORG,
p.COVER_IMAGE AS COVER_IMAGE,
p.RECONCILIATION_TYPE AS RECONCILIATION_TYPE,
p.RECONCILIATION_AMOUNT AS RECONCILIATION_AMOUNT,
p.status AS STATUS
SQL;
    private const RETURN_ORDER_ITEM_FIELDS = <<<SQL
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
bp.PRODUCT_NAME AS PRODUCT_NAME
SQL;
    private const EXPENDITURE_RECORD_FIELDS = <<<SQL
e.ID AS ID,
e.OBJECT_ID AS OBJECT_ID,
e.TARGET_ID AS TARGET_ID,
e.SERIAL_ID AS SERIAL_ID,
e.PROCESS_ID AS PROCESS_ID,
e.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
e.PAYER AS PAYER,
e.BANK_NAME AS BANK_NAME,
e.BANK_ACCOUNT AS BANK_ACCOUNT,
e.REMARK AS REMARK,
e.PAYER_TIME AS PAYER_TIME,
e.AMOUNT AS AMOUNT,
e.DELETE_FLAG AS DELETE_FLAG,
e.CREATE_TIME AS CREATE_TIME,
e.CREATE_USER AS CREATE_USER,
e.UPDATE_TIME AS UPDATE_TIME,
e.UPDATE_USER AS UPDATE_USER,
e.TENANT_ID AS TENANT_ID,
e.`USER` AS USER_ID,
e.ORG AS ORG,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
org.NAME AS ORG_NAME
SQL;
    private const ORG_FIELDS = <<<SQL
o.ID AS ID,
o.PARENT_ID AS PARENT_ID,
o.DIRECTOR_ID AS DIRECTOR_ID,
o.NAME AS NAME,
o.CODE AS CODE,
o.CATEGORY AS CATEGORY,
o.SORT_CODE AS SORT_CODE,
o.EXT_JSON AS EXT_JSON,
o.DELETE_FLAG AS DELETE_FLAG,
o.CREATE_TIME AS CREATE_TIME,
o.CREATE_USER AS CREATE_USER,
o.UPDATE_TIME AS UPDATE_TIME,
o.UPDATE_USER AS UPDATE_USER,
o.TENANT_ID AS TENANT_ID
SQL;
    private const SETTLEMENT_ACCOUNT_FIELDS = <<<SQL
a.ID AS ID,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
a.INITIAL_AMOUNT AS INITIAL_AMOUNT,
a.CURRENT_AMOUNT AS CURRENT_AMOUNT,
a.ACCOUNT_STATUS AS ACCOUNT_STATUS,
a.SORT_CODE AS SORT_CODE,
a.DELETE_FLAG AS DELETE_FLAG,
a.CREATE_TIME AS CREATE_TIME,
a.CREATE_USER AS CREATE_USER,
a.UPDATE_TIME AS UPDATE_TIME,
a.UPDATE_USER AS UPDATE_USER,
a.EXT_JSON AS EXT_JSON,
a.TENANT_ID AS TENANT_ID,
a.VERSION AS VERSION,
a.org AS ORG,
a.ARCHIVE_AMOUNT AS ARCHIVE_AMOUNT,
a.ARCHIVE_TIME AS ARCHIVE_TIME,
org.NAME AS ORG_NAME
SQL;
    private const DEBIT_NOTE_FIELDS = <<<SQL
d.ID AS ID,
d.EXPENDITURE_RECORD_ID AS EXPENDITURE_RECORD_ID,
d.REMARK AS REMARK,
d.PLAY_STATUS AS PLAY_STATUS,
d.AMOUNT AS AMOUNT,
d.SETTLEMENT_AMOUNT AS SETTLEMENT_AMOUNT,
d.DELETE_FLAG AS DELETE_FLAG,
d.CREATE_TIME AS CREATE_TIME,
d.CREATE_USER AS CREATE_USER,
d.UPDATE_TIME AS UPDATE_TIME,
d.UPDATE_USER AS UPDATE_USER,
d.TENANT_ID AS TENANT_ID,
d.VERSION AS VERSION,
d.ORG AS ORG,
d.HISTORY_AMOUNT AS HISTORY_AMOUNT,
e.PAYER_TIME AS PAYER_TIME,
e.TARGET_ID AS ACCOUNT_ID,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
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

    public function saleProjectAmount(array $filters = [], array $payload = []): array
    {
        return [
            'amount' => $this->decimal($this->saleProjectQuery($filters, $payload)->sum('p.TOTAL_PRICE') ?? 0) ?? 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function saleProjectList(array $filters = [], array $payload = []): array
    {
        return $this->projectRows(
            $this->saleProjectQuery($filters, $payload)
                ->order('p.COMPLETION_DATE', 'asc')
                ->order('p.ID', 'asc')
                ->select()
                ->toArray()
        );
    }

    public function saleProjectReport(array $filters = [], array $payload = []): array
    {
        $rows = $this->saleProjectReportQuery($filters, $payload)
            ->order('p.CREATE_TIME', 'asc')
            ->order('p.ID', 'asc')
            ->select()
            ->toArray();

        return [
            'list' => array_values(array_map(fn (array $row): array => [
                'playState' => $this->value($row, 'PLAY_STATE', 'playState'),
                'projectState' => $this->value($row, 'PROJECT_STATE', 'projectState'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'completionDate' => $this->value($row, 'COMPLETION_DATE', 'completionDate'),
            ], $rows)),
        ];
    }

    public function saleProjectUnpaidPayment(array $filters = [], array $payload = []): array
    {
        $rows = $this->saleProjectQuery($filters, $payload)
            ->whereIn('p.PLAY_STATE', self::UNPAID_PLAY_STATES)
            ->field('p.TOTAL_PRICE AS TOTAL_PRICE, p.AMOUNT_COLLECTED AS AMOUNT_COLLECTED, p.TOTAL_RETURN_AMOUNT AS TOTAL_RETURN_AMOUNT')
            ->select()
            ->toArray();

        $amount = array_reduce($rows, static function (float $sum, array $row): float {
            return $sum
                + (float)($row['TOTAL_PRICE'] ?? 0)
                - (float)($row['AMOUNT_COLLECTED'] ?? 0)
                + (float)($row['TOTAL_RETURN_AMOUNT'] ?? 0);
        }, 0.0);

        return [
            'amount' => $this->decimal($amount) ?? 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settlementIncome(array $filters = [], array $payload = []): array
    {
        $rows = $this->settlementIncomeQuery($filters, $payload)
            ->field(self::PAYMENT_RECORD_FIELDS)
            ->order('r.PAYER_TIME', 'asc')
            ->order('r.ID', 'asc')
            ->select()
            ->toArray();

        return $this->paymentRecordRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settlementExpenses(array $filters = [], array $payload = []): array
    {
        $rows = $this->settlementExpensesQuery($filters, $payload)
            ->field(self::EXPENDITURE_RECORD_FIELDS)
            ->order('e.PAYER_TIME', 'asc')
            ->order('e.ID', 'asc')
            ->select()
            ->toArray();

        return $this->expenditureRecordRows($rows);
    }

    public function saleProfit(array $filters = [], array $payload = []): array
    {
        $projects = $this->projectRows(
            $this->saleProjectQuery($filters, $payload)
                ->order('p.COMPLETION_DATE', 'asc')
                ->order('p.ID', 'asc')
                ->select()
                ->toArray()
        );
        $projectIds = array_column($projects, 'id');
        $productItems = $this->saleProfitProductItemsByProjectIds($projectIds, $payload);
        $returnOrders = $this->returnOrdersWithItemsByProjectIds($projectIds, $payload);

        foreach ($projects as &$project) {
            $projectId = (string)($project['id'] ?? '');
            $project['productList'] = $productItems[$projectId] ?? [];
            $project['returnOrders'] = $returnOrders[$projectId] ?? [];
        }
        unset($project);

        return [
            'projectlist' => $projects,
            'orderList' => $this->completedPurchaseOrders($payload),
            'bizProducts' => $this->saleProfitProducts($payload),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaryStatistics(array $filters = [], array $payload = []): array
    {
        $endDate = $this->summaryEndOfYear($filters['year'] ?? null);
        $companyScopes = $this->summaryCompanyScopes($payload);

        if ($companyScopes === []) {
            throw new RuntimeException('no permission to view summary statistics', 403);
        }

        return array_values(array_map(function (array $scope) use ($endDate, $payload): array {
            $orgIds = $scope['orgIds'] ?? [];
            if (!is_array($orgIds) || $orgIds === []) {
                return [
                    'org' => $scope['org'] ?? [],
                    'settlementAccounts' => [],
                    'paymentRecords' => [],
                    'bizExpenditureRecords' => [],
                    'bizSaleProjects' => [],
                    'bizDebitNotes' => [],
                ];
            }

            $settlementAccounts = $this->summarySettlementAccounts($orgIds, $payload);
            $settlementAccountIds = array_column($settlementAccounts, 'id');

            return [
                'org' => $scope['org'] ?? [],
                'settlementAccounts' => $settlementAccounts,
                'paymentRecords' => $this->summaryPaymentRecords($settlementAccountIds, $endDate, $payload),
                'bizExpenditureRecords' => $this->summaryExpenditureRecords($settlementAccountIds, $endDate, $payload),
                'bizSaleProjects' => $this->summarySaleProjects($orgIds, $endDate, $payload),
                'bizDebitNotes' => $this->summaryDebitNotes($orgIds, $endDate, $payload),
            ];
        }, $companyScopes));
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

    private function settlementIncomeQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_payment_record')
            ->alias('r')
            ->leftJoin('settlement_account a', 'a.ID = r.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = r.ORG');
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');

        $this->applyTenant($query, $filters, $payload, 'r.TENANT_ID');

        $category = trim((string)($filters['category'] ?? $filters['settlementCategory'] ?? ''));
        if ($category !== '') {
            $query->where('r.SETTLEMENT_CATEGORY', $category);
        }

        $this->applyOrgAndDataScope($query, $filters, $payload, 'r.ORG', 'r.USER');
        $this->applyReportPayerTimeRange($query, $filters, 'r.PAYER_TIME');

        return $query;
    }

    private function settlementExpensesQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_expenditure_record')
            ->alias('e')
            ->leftJoin('settlement_account a', 'a.ID = e.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = e.ORG');
        $this->whereNotDeleted($query, 'e.DELETE_FLAG');

        $this->applyTenant($query, $filters, $payload, 'e.TENANT_ID');

        $category = trim((string)($filters['category'] ?? $filters['settlementCategory'] ?? ''));
        if ($category !== '') {
            $query->where('e.SETTLEMENT_CATEGORY', $category);
        }

        $this->applyOrgAndDataScope($query, $filters, $payload, 'e.ORG', 'e.USER');
        $this->applyReportPayerTimeRange($query, $filters, 'e.PAYER_TIME');

        return $query;
    }

    /**
     * @param array<int, string> $orgIds
     * @return array<int, array<string, mixed>>
     */
    private function summarySaleProjects(array $orgIds, string $endDate, array $payload): array
    {
        $ids = $this->stringList($orgIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_sale_project')
            ->alias('p')
            ->field(self::PROJECT_FIELDS)
            ->leftJoin('customer c', 'c.ID = p.CUSTOMER')
            ->leftJoin('sys_user u', 'u.ID = p.USER')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->leftJoin('settlement_account a', 'a.ID = p.ACCOUNT_ID')
            ->whereIn('p.PROJECT_STATE', self::DEAL_STATES)
            ->whereIn('p.ORG', $ids)
            ->where('p.COMPLETION_DATE', '<=', $endDate);
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');
        $this->applyTenant($query, [], $payload, 'p.TENANT_ID');

        return $this->projectRows(
            $query->order('p.COMPLETION_DATE', 'asc')
                ->order('p.ID', 'asc')
                ->select()
                ->toArray()
        );
    }

    /**
     * @param array<int, string> $orgIds
     * @return array<int, array<string, mixed>>
     */
    private function summarySettlementAccounts(array $orgIds, array $payload): array
    {
        $ids = $this->stringList($orgIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('settlement_account')
            ->alias('a')
            ->field(self::SETTLEMENT_ACCOUNT_FIELDS)
            ->leftJoin('sys_org org', 'org.ID = a.org')
            ->whereIn('a.org', $ids);
        $this->whereNotDeleted($query, 'a.DELETE_FLAG');
        $this->applyTenant($query, [], $payload, 'a.TENANT_ID');

        return $this->settlementAccountRows(
            $query->order('a.SORT_CODE', 'asc')
                ->order('a.ID', 'asc')
                ->select()
                ->toArray()
        );
    }

    /**
     * @param array<int, string|null> $settlementAccountIds
     * @return array<int, array<string, mixed>>
     */
    private function summaryPaymentRecords(array $settlementAccountIds, string $endDate, array $payload): array
    {
        $ids = $this->stringList($settlementAccountIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_payment_record')
            ->alias('r')
            ->field(self::PAYMENT_RECORD_FIELDS)
            ->leftJoin('settlement_account a', 'a.ID = r.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = r.ORG')
            ->whereIn('r.TARGET_ID', $ids)
            ->where('r.PAYER_TIME', '<=', $endDate);
        $this->whereNotDeleted($query, 'r.DELETE_FLAG');
        $this->applyTenant($query, [], $payload, 'r.TENANT_ID');

        return $this->paymentRecordRows(
            $query->order('r.PAYER_TIME', 'asc')
                ->order('r.ID', 'asc')
                ->select()
                ->toArray()
        );
    }

    /**
     * @param array<int, string|null> $settlementAccountIds
     * @return array<int, array<string, mixed>>
     */
    private function summaryExpenditureRecords(array $settlementAccountIds, string $endDate, array $payload): array
    {
        $ids = $this->stringList($settlementAccountIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_expenditure_record')
            ->alias('e')
            ->field(self::EXPENDITURE_RECORD_FIELDS)
            ->leftJoin('settlement_account a', 'a.ID = e.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = e.ORG')
            ->whereIn('e.TARGET_ID', $ids)
            ->where('e.PAYER_TIME', '<=', $endDate);
        $this->whereNotDeleted($query, 'e.DELETE_FLAG');
        $this->applyTenant($query, [], $payload, 'e.TENANT_ID');

        return $this->expenditureRecordRows(
            $query->order('e.PAYER_TIME', 'asc')
                ->order('e.ID', 'asc')
                ->select()
                ->toArray()
        );
    }

    /**
     * @param array<int, string> $orgIds
     * @return array<int, array<string, mixed>>
     */
    private function summaryDebitNotes(array $orgIds, string $endDate, array $payload): array
    {
        $ids = $this->stringList($orgIds);
        if ($ids === []) {
            return [];
        }

        $query = Db::name('biz_debit_note')
            ->alias('d')
            ->field(self::DEBIT_NOTE_FIELDS)
            ->leftJoin('biz_expenditure_record e', 'e.ID = d.EXPENDITURE_RECORD_ID')
            ->leftJoin('settlement_account a', 'a.ID = e.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = d.ORG')
            ->whereIn('d.ORG', $ids)
            ->where('d.CREATE_TIME', '<=', $endDate);
        $this->whereNotDeleted($query, 'd.DELETE_FLAG');
        $this->applyTenant($query, [], $payload, 'd.TENANT_ID');

        return $this->debitNoteRows(
            $query->order('d.CREATE_TIME', 'asc')
                ->order('d.ID', 'asc')
                ->select()
                ->toArray()
        );
    }

    private function saleProjectReportQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_sale_project')
            ->alias('p')
            ->field('p.PLAY_STATE AS PLAY_STATE, p.PROJECT_STATE AS PROJECT_STATE, p.CREATE_TIME AS CREATE_TIME, p.COMPLETION_DATE AS COMPLETION_DATE')
            ->leftJoin('sys_user u', 'u.ID = p.USER');
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
            $start = (string)$filters['startCreateTime'];
            $end = (string)$filters['endCreateTime'];
            $query->where(function ($query) use ($start, $end): void {
                $query->whereBetweenTime('p.CREATE_TIME', $start, $end)
                    ->whereOr(function ($query) use ($start, $end): void {
                        $query->whereBetweenTime('p.COMPLETION_DATE', $start, $end);
                    });
            });
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
     * @param array<int, string|null> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function saleProfitProductItemsByProjectIds(array $projectIds, array $payload): array
    {
        $itemsByProjectId = $this->productItemsByProjectIds($projectIds, $payload);

        foreach ($itemsByProjectId as &$items) {
            foreach ($items as &$item) {
                if (($item['children'] ?? []) === []) {
                    unset($item['children']);
                }
            }
            unset($item);
        }
        unset($items);

        return $itemsByProjectId;
    }

    /**
     * @param array<int, string|null> $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function returnOrdersWithItemsByProjectIds(array $projectIds, array $payload): array
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

        $orders = $this->returnOrderRows($query->order('r.CREATE_TIME', 'asc')->select()->toArray());
        $itemsByOrderId = $this->returnOrderItemsByOrderIds(array_column($orders, 'id'), $payload);
        $result = [];

        foreach ($orders as $order) {
            $order['productList'] = $itemsByOrderId[(string)($order['id'] ?? '')] ?? [];
            $result[(string)($order['projectId'] ?? '')][] = $order;
        }

        return $result;
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

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $query = Db::name('return_order_item')
            ->alias('i')
            ->leftJoin('biz_sale_project_product_item pi', 'pi.ID = i.PROJECT_PRODUCT_ITEM_ID')
            ->leftJoin('biz_product bp', 'bp.ID = pi.PRODUCT_ID')
            ->field(self::RETURN_ORDER_ITEM_FIELDS)
            ->whereIn('i.RETURN_ORDER_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($this->returnOrderItemRows($query->order('i.ID', 'asc')->select()->toArray()) as $row) {
            $result[(string)($row['returnOrderId'] ?? '')][] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function completedPurchaseOrders(array $payload): array
    {
        $query = Db::name('biz_purchase_order')
            ->alias('o')
            ->leftJoin('sys_org org', 'org.ID = o.ORG')
            ->field(self::PURCHASE_ORDER_FIELDS)
            ->where('o.SETTLEMENT_STATUS', self::PURCHASE_ORDER_SETTLEMENT_COMPLETED);
        $this->whereNotDeleted($query, 'o.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('o.TENANT_ID', $tenantId);
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('o.ORG', $scopeOrgIds);
        } else {
            $userId = $this->currentUserId($payload);
            if ($userId !== '') {
                $query->where('o.CREATE_USER', $userId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $orders = $this->purchaseOrderRows($query->order('o.ID', 'asc')->select()->toArray());
        $itemsByOrderId = $this->purchaseOrderItemsByOrderIds(array_column($orders, 'id'), $payload);

        foreach ($orders as &$order) {
            $order['orderItems'] = $itemsByOrderId[(string)($order['id'] ?? '')] ?? [];
        }
        unset($order);

        return $orders;
    }

    /**
     * @param array<int, string|null> $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function purchaseOrderItemsByOrderIds(array $orderIds, array $payload): array
    {
        $ids = $this->stringList($orderIds);
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $query = Db::name('biz_purchase_order_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->field(self::PURCHASE_ORDER_ITEM_FIELDS)
            ->whereIn('i.PURCHASE_ORDER_ID', $ids);
        $this->whereNotDeleted($query, 'i.DELETE_FLAG');

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        $result = [];
        foreach ($this->purchaseOrderItemRows($query->order('i.ID', 'asc')->select()->toArray()) as $row) {
            $result[(string)($row['purchaseOrderId'] ?? '')][] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function saleProfitProducts(array $payload): array
    {
        $query = Db::name('biz_product')
            ->alias('p')
            ->field(self::PRODUCT_FIELDS);
        $this->whereNotDeleted($query, 'p.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        return $this->productRows($query->order('p.ID', 'asc')->select()->toArray());
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
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'title' => $this->value($row, 'TITLE', 'title'),
                'settlementStatus' => $this->value($row, 'SETTLEMENT_STATUS', 'settlementStatus'),
                'storageStatus' => $this->value($row, 'STORAGE_STATUS', 'storageStatus'),
                'supplierId' => $this->value($row, 'SUPPLIER_ID', 'supplierId'),
                'instanceId' => $this->value($row, 'INSTANCE_ID', 'instanceId'),
                'desirePurchaseDate' => $this->value($row, 'DESIRE_PURCHASE_DATE', 'desirePurchaseDate'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'orderItems' => [],
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderItemRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'purchaseOrderId' => $this->value($row, 'PURCHASE_ORDER_ID', 'purchaseOrderId'),
                'storageStatus' => $this->value($row, 'STORAGE_STATUS', 'storageStatus'),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'number' => $this->decimal($this->value($row, 'NUMBER', 'number')),
                'unitAmount' => $this->decimal($this->value($row, 'UNIT_AMOUNT', 'unitAmount')),
                'discountRate' => $this->decimal($this->value($row, 'DISCOUNT_RATE', 'discountRate')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'freightShareAmount' => $this->decimal($this->value($row, 'FREIGHT_SHARE_AMOUNT', 'freightShareAmount')),
                'unitCostWithFreight' => $this->decimal($this->value($row, 'UNIT_COST_WITH_FREIGHT', 'unitCostWithFreight')),
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function productRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
                'safetyStock' => $this->decimal($this->value($row, 'SAFETY_STOCK', 'safetyStock')),
                'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
                'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
                'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
                'category' => $this->value($row, 'CATEGORY', 'category'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'specs' => $this->value($row, 'SPECS', 'specs'),
                'org' => $this->value($row, 'ORG', 'org'),
                'coverImage' => $this->value($row, 'COVER_IMAGE', 'coverImage'),
                'reconciliationType' => $this->value($row, 'RECONCILIATION_TYPE', 'reconciliationType'),
                'reconciliationAmount' => $this->decimal($this->value($row, 'RECONCILIATION_AMOUNT', 'reconciliationAmount')),
                'status' => $this->value($row, 'STATUS', 'status'),
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function returnOrderItemRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'returnOrderId' => $this->value($row, 'RETURN_ORDER_ID', 'returnOrderId'),
                'projectProductItemId' => $this->value($row, 'PROJECT_PRODUCT_ITEM_ID', 'projectProductItemId'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'projectId' => $this->value($row, 'PROJECT_ID', 'projectId'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function settlementAccountRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
                'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
                'initialAmount' => $this->decimal($this->value($row, 'INITIAL_AMOUNT', 'initialAmount')),
                'currentAmount' => $this->decimal($this->value($row, 'CURRENT_AMOUNT', 'currentAmount')),
                'accountStatus' => $this->value($row, 'ACCOUNT_STATUS', 'accountStatus'),
                'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'archiveAmount' => $this->decimal($this->value($row, 'ARCHIVE_AMOUNT', 'archiveAmount')),
                'archiveTime' => $this->value($row, 'ARCHIVE_TIME', 'archiveTime'),
            ];
        }, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function debitNoteRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'expenditureRecordId' => $this->value($row, 'EXPENDITURE_RECORD_ID', 'expenditureRecordId'),
                'accountId' => $this->value($row, 'ACCOUNT_ID', 'accountId'),
                'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
                'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'playStatus' => $this->value($row, 'PLAY_STATUS', 'playStatus'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'settlementAmount' => $this->decimal($this->value($row, 'SETTLEMENT_AMOUNT', 'settlementAmount')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'org' => $this->value($row, 'ORG', 'org'),
                'historyAmount' => $this->decimal($this->value($row, 'HISTORY_AMOUNT', 'historyAmount')),
            ];
        }, $rows));
    }

    private function orgRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'parentId' => $this->value($row, 'PARENT_ID', 'parentId'),
            'directorId' => $this->value($row, 'DIRECTOR_ID', 'directorId'),
            'name' => $this->value($row, 'NAME', 'name'),
            'code' => $this->value($row, 'CODE', 'code'),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function paymentRecordRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->settlementRecordRow($row), $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function expenditureRecordRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->settlementRecordRow($row), $rows);
    }

    private function settlementRecordRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
            'targetId' => $this->value($row, 'TARGET_ID', 'targetId'),
            'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
            'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
            'serialId' => $this->value($row, 'SERIAL_ID', 'serialId'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
            'payer' => $this->value($row, 'PAYER', 'payer'),
            'bankName' => $this->value($row, 'BANK_NAME', 'bankName'),
            'bankAccount' => $this->value($row, 'BANK_ACCOUNT', 'bankAccount'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'user' => $this->value($row, 'USER_ID', 'user'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
        ];
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

    private function applyTenant($query, array $filters, array $payload, string $column): void
    {
        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where($column, $tenantId);
        }
    }

    private function applyOrgAndDataScope($query, array $filters, array $payload, string $orgColumn, string $userColumn): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn($orgColumn, $orgIds);
            }
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn($orgColumn, $scopeOrgIds);
            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where($userColumn, $userId);
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyReportPayerTimeRange($query, array $filters, string $column): void
    {
        foreach ([
            ['startCreateTime', 'endCreateTime'],
            ['startPayerTime', 'endPayerTime'],
            ['payerStartTime', 'payerEndTime'],
        ] as [$startKey, $endKey]) {
            $start = trim((string)($filters[$startKey] ?? ''));
            $end = trim((string)($filters[$endKey] ?? ''));
            if ($start !== '' && $end !== '') {
                $query->whereBetweenTime($column, $start, $end);
            }
        }
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['userId'] ?? $payload['user_id'] ?? $payload['id'] ?? ''));
    }

    private function summaryEndOfYear(mixed $year): string
    {
        $value = trim((string)$year);
        if ($value === '') {
            throw new RuntimeException('missing year', 400);
        }

        if (preg_match('/^\d{4}$/', $value) === 1) {
            $value .= '-01-01 00:00:00';
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new RuntimeException('invalid year', 400);
        }

        return $date->setDate((int)$date->format('Y'), 12, 31)
            ->setTime(23, 59, 59)
            ->format('Y-m-d H:i:s');
    }

    /**
     * @return array<int, array{org: array<string, mixed>, orgIds: array<int, string>}>
     */
    private function summaryCompanyScopes(array $payload): array
    {
        $rows = $this->activeOrgRows($payload);
        $rowsById = $this->orgRowsById($rows);
        $scopeOrgIds = $this->scopeOrgIds($payload);

        if ($scopeOrgIds === []) {
            $currentOrgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
            if ($currentOrgId !== '') {
                $scopeOrgIds = [$currentOrgId];
            }
        }

        $scopes = [];
        foreach ($scopeOrgIds as $scopeOrgId) {
            if (!isset($rowsById[$scopeOrgId])) {
                continue;
            }

            $companyId = $this->companyIdForOrg($scopeOrgId, $rowsById);
            if ($companyId === null || !isset($rowsById[$companyId])) {
                continue;
            }

            $isCompanyScope = strtoupper((string)($rowsById[$scopeOrgId]['CATEGORY'] ?? '')) === 'COMPANY';
            $orgIds = $this->orgAndChildrenFromRows($rows, $isCompanyScope ? $companyId : $scopeOrgId);
            if ($orgIds === []) {
                continue;
            }

            if (!isset($scopes[$companyId])) {
                $scopes[$companyId] = [
                    'org' => $this->orgRow($rowsById[$companyId]),
                    'orgIds' => [],
                ];
            }

            $scopes[$companyId]['orgIds'] = $this->stringList(array_merge($scopes[$companyId]['orgIds'], $orgIds));
        }

        $result = array_values($scopes);
        usort($result, static function (array $left, array $right): int {
            return ((int)($left['org']['sortCode'] ?? 0) <=> (int)($right['org']['sortCode'] ?? 0))
                ?: strcmp((string)($left['org']['id'] ?? ''), (string)($right['org']['id'] ?? ''));
        });

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeOrgRows(array $payload = []): array
    {
        $query = Db::name('sys_org')
            ->alias('o')
            ->field(self::ORG_FIELDS);
        $this->whereNotDeleted($query, 'o.DELETE_FLAG');
        $this->applyTenant($query, [], $payload, 'o.TENANT_ID');

        return $query->order('o.SORT_CODE', 'asc')
            ->order('o.ID', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function orgRowsById(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['ID'] ?? ''));
            if ($id !== '') {
                $result[$id] = $row;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $rowsById
     */
    private function companyIdForOrg(string $orgId, array $rowsById): ?string
    {
        $current = trim($orgId);
        $visited = [];

        while ($current !== '' && $current !== '0' && isset($rowsById[$current])) {
            if (isset($visited[$current])) {
                return null;
            }
            $visited[$current] = true;

            if (strtoupper((string)($rowsById[$current]['CATEGORY'] ?? '')) === 'COMPANY') {
                return $current;
            }

            $current = trim((string)($rowsById[$current]['PARENT_ID'] ?? ''));
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function orgAndChildrenFromRows(array $rows, string $orgId): array
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return [];
        }

        $childrenByParent = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['ID'] ?? ''));
            if ($id === '') {
                continue;
            }

            $childrenByParent[trim((string)($row['PARENT_ID'] ?? ''))][] = $id;
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
