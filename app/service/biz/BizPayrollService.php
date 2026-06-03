<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only payroll queries compatible with Java BizPayrollController.
 */
class BizPayrollService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const FIELDS = <<<SQL
p.ID AS ID,
p.SENIORITY_SALARY AS SENIORITY_SALARY,
p.PERFORMANCE_SALARY AS PERFORMANCE_SALARY,
p.WORK_SALARY AS WORK_SALARY,
p.BASIC_SALARY AS BASIC_SALARY,
p.RENT_SUBSIDIES AS RENT_SUBSIDIES,
p.MEAL_ALLOWANCE AS MEAL_ALLOWANCE,
p.DORMITORY_RENT AS DORMITORY_RENT,
p.BASE_AMOUNT AS BASE_AMOUNT,
p.TRANSACTION_VOLUME AS TRANSACTION_VOLUME,
p.RECEIVED_AMOUNT AS RECEIVED_AMOUNT,
p.TAX_FREIGHT AS TAX_FREIGHT,
p.MONTHLY_COMMISSION AS MONTHLY_COMMISSION,
p.BEFORE_RECEIVED_AMOUNT AS BEFORE_RECEIVED_AMOUNT,
p.BEFORE_COMMISSION AS BEFORE_COMMISSION,
p.RATE_COMMISSION AS RATE_COMMISSION,
p.TOTAL_COMMISSION AS TOTAL_COMMISSION,
p.MERIT_BONUSES AS MERIT_BONUSES,
p.VACATION AS VACATION,
p.VACATION_SUB_AMOUNT AS VACATION_SUB_AMOUNT,
p.PAYABLE_AMOUNT AS PAYABLE_AMOUNT,
p.PERSONAL_INCOME_TAX AS PERSONAL_INCOME_TAX,
p.SOCIAL_SECURITY AS SOCIAL_SECURITY,
p.ACTUAL_AMOUNT AS ACTUAL_AMOUNT,
p.SALARY_TIME AS SALARY_TIME,
p.`USER` AS USER_ID,
p.ORG AS ORG,
p.DELETE_FLAG AS DELETE_FLAG,
p.CREATE_TIME AS CREATE_TIME,
p.CREATE_USER AS CREATE_USER,
p.UPDATE_TIME AS UPDATE_TIME,
p.UPDATE_USER AS UPDATE_USER,
p.TENANT_ID AS TENANT_ID,
p.YEAR_END_BONUS AS YEAR_END_BONUS,
p.POST_WAGE AS POST_WAGE,
p.PRIVATE_ACCOUNT AS PRIVATE_ACCOUNT,
p.PUBLIC_ACCOUNT AS PUBLIC_ACCOUNT,
p.REMARK AS REMARK,
u.NAME AS HEAD_NAME,
u.ACCOUNT AS USER_ACCOUNT,
org.NAME AS ORG_NAME,
org.SORT_CODE AS ORG_SORT_CODE,
creator.NAME AS CREATE_USER_NAME,
updater.NAME AS UPDATE_USER_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'p.ID',
        'senioritySalary' => 'p.SENIORITY_SALARY',
        'performanceSalary' => 'p.PERFORMANCE_SALARY',
        'workSalary' => 'p.WORK_SALARY',
        'basicSalary' => 'p.BASIC_SALARY',
        'postWage' => 'p.POST_WAGE',
        'baseAmount' => 'p.BASE_AMOUNT',
        'transactionVolume' => 'p.TRANSACTION_VOLUME',
        'receivedAmount' => 'p.RECEIVED_AMOUNT',
        'taxFreight' => 'p.TAX_FREIGHT',
        'monthlyCommission' => 'p.MONTHLY_COMMISSION',
        'beforeReceivedAmount' => 'p.BEFORE_RECEIVED_AMOUNT',
        'beforeCommission' => 'p.BEFORE_COMMISSION',
        'rateCommission' => 'p.RATE_COMMISSION',
        'totalCommission' => 'p.TOTAL_COMMISSION',
        'meritBonuses' => 'p.MERIT_BONUSES',
        'payableAmount' => 'p.PAYABLE_AMOUNT',
        'actualAmount' => 'p.ACTUAL_AMOUNT',
        'salaryTime' => 'p.SALARY_TIME',
        'user' => 'p.USER',
        'headName' => 'u.NAME',
        'org' => 'p.ORG',
        'orgName' => 'org.NAME',
        'createTime' => 'p.CREATE_TIME',
        'updateTime' => 'p.UPDATE_TIME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        return $this->pagedResult($filters, $payload, false);
    }

    public function myPage(array $filters = [], array $payload = []): array
    {
        return $this->pagedResult($filters, $payload, true);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->payrollQuery(['id' => $id], $payload, false)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('payroll not found', 404);
        }

        return $this->payrollRows([$row])[0];
    }

    private function pagedResult(array $filters, array $payload, bool $onlyCurrentUser): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->payrollQuery($filters, $payload, $onlyCurrentUser)->count();
        $rows = $this->applySort($this->payrollQuery($filters, $payload, $onlyCurrentUser), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->payrollRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function payrollQuery(array $filters, array $payload, bool $onlyCurrentUser)
    {
        $query = Db::name('biz_payroll')
            ->alias('p')
            ->leftJoin('sys_user u', 'u.ID = p.`USER`')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->leftJoin('sys_user creator', 'creator.ID = p.CREATE_USER')
            ->leftJoin('sys_user updater', 'updater.ID = p.UPDATE_USER')
            ->where(function ($query): void {
                $query->whereNull('p.DELETE_FLAG')->whereOr('p.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('p.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('p.ID', (string)$filters['id']);
        }

        if ($onlyCurrentUser) {
            $userId = $this->currentUserId($payload);
            $userId === '' ? $query->whereRaw('1 = 0') : $query->where('p.USER', $userId);
        } else {
            $scopeOrgIds = $this->scopeOrgIds($payload);
            if ($scopeOrgIds !== []) {
                $query->whereIn('p.ORG', $scopeOrgIds);
            } else {
                $userId = $this->currentUserId($payload);
                $userId === '' ? $query->whereRaw('1 = 0') : $query->where('p.USER', $userId);
            }
        }

        if (!empty($filters['user'])) {
            $query->where('p.USER', (string)$filters['user']);
        }

        if (!empty($filters['userId'])) {
            $query->where('p.USER', (string)$filters['userId']);
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('p.ORG', $orgIds);
        }

        $this->applySalaryTimeFilter($query, $filters);

        foreach ([
            'basicSalary' => 'p.BASIC_SALARY',
            'postWage' => 'p.POST_WAGE',
            'actualAmount' => 'p.ACTUAL_AMOUNT',
            'payableAmount' => 'p.PAYABLE_AMOUNT',
        ] as $filter => $column) {
            if ($filters[$filter] ?? null) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('u.ACCOUNT', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword)
                    ->whereOr('p.REMARK', 'like', $keyword);
            });
        }

        return $query;
    }

    private function applySalaryTimeFilter($query, array $filters): void
    {
        $start = trim((string)($filters['startSalaryTime'] ?? ''));
        $end = trim((string)($filters['endSalaryTime'] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime('p.SALARY_TIME', $start, $end);

            return;
        }

        $salaryTime = trim((string)($filters['salaryTime'] ?? ''));
        if ($salaryTime === '') {
            return;
        }

        $timestamp = strtotime($salaryTime);
        if ($timestamp === false) {
            return;
        }

        $query->whereBetweenTime(
            'p.SALARY_TIME',
            date('Y-m-01 00:00:00', $timestamp),
            date('Y-m-t 23:59:59', $timestamp)
        );
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

        if ($sortField === 'org') {
            return $query->order('org.SORT_CODE', $direction)->order('p.ID', 'asc');
        }

        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('p.ID', 'asc');
        }

        return $query->order('p.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function payrollRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->payrollRow($row), $rows);
    }

    private function payrollRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'senioritySalary' => $this->decimal($this->value($row, 'SENIORITY_SALARY', 'senioritySalary')),
            'performanceSalary' => $this->decimal($this->value($row, 'PERFORMANCE_SALARY', 'performanceSalary')),
            'workSalary' => $this->decimal($this->value($row, 'WORK_SALARY', 'workSalary')),
            'basicSalary' => $this->decimal($this->value($row, 'BASIC_SALARY', 'basicSalary')),
            'postWage' => $this->decimal($this->value($row, 'POST_WAGE', 'postWage')),
            'rentSubsidies' => $this->decimal($this->value($row, 'RENT_SUBSIDIES', 'rentSubsidies')),
            'mealAllowance' => $this->decimal($this->value($row, 'MEAL_ALLOWANCE', 'mealAllowance')),
            'dormitoryRent' => $this->decimal($this->value($row, 'DORMITORY_RENT', 'dormitoryRent')),
            'baseAmount' => $this->decimal($this->value($row, 'BASE_AMOUNT', 'baseAmount')),
            'transactionVolume' => $this->decimal($this->value($row, 'TRANSACTION_VOLUME', 'transactionVolume')),
            'receivedAmount' => $this->decimal($this->value($row, 'RECEIVED_AMOUNT', 'receivedAmount')),
            'taxFreight' => $this->decimal($this->value($row, 'TAX_FREIGHT', 'taxFreight')),
            'monthlyCommission' => $this->decimal($this->value($row, 'MONTHLY_COMMISSION', 'monthlyCommission')),
            'beforeReceivedAmount' => $this->decimal($this->value($row, 'BEFORE_RECEIVED_AMOUNT', 'beforeReceivedAmount')),
            'beforeCommission' => $this->decimal($this->value($row, 'BEFORE_COMMISSION', 'beforeCommission')),
            'rateCommission' => $this->decimal($this->value($row, 'RATE_COMMISSION', 'rateCommission')),
            'totalCommission' => $this->decimal($this->value($row, 'TOTAL_COMMISSION', 'totalCommission')),
            'meritBonuses' => $this->decimal($this->value($row, 'MERIT_BONUSES', 'meritBonuses')),
            'vacation' => $this->decimal($this->value($row, 'VACATION', 'vacation')),
            'vacationSubAmount' => $this->decimal($this->value($row, 'VACATION_SUB_AMOUNT', 'vacationSubAmount')),
            'payableAmount' => $this->decimal($this->value($row, 'PAYABLE_AMOUNT', 'payableAmount')),
            'personalIncomeTax' => $this->decimal($this->value($row, 'PERSONAL_INCOME_TAX', 'personalIncomeTax')),
            'socialSecurity' => $this->decimal($this->value($row, 'SOCIAL_SECURITY', 'socialSecurity')),
            'actualAmount' => $this->decimal($this->value($row, 'ACTUAL_AMOUNT', 'actualAmount')),
            'yearEndBonus' => $this->decimal($this->value($row, 'YEAR_END_BONUS', 'yearEndBonus')),
            'privateAccount' => $this->decimal($this->value($row, 'PRIVATE_ACCOUNT', 'privateAccount')),
            'publicAccount' => $this->decimal($this->value($row, 'PUBLIC_ACCOUNT', 'publicAccount')),
            'salaryTime' => $this->value($row, 'SALARY_TIME', 'salaryTime'),
            'user' => $this->value($row, 'USER_ID', 'user'),
            'userId' => $this->value($row, 'USER_ID', 'userId'),
            'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
            'name' => $this->value($row, 'HEAD_NAME', 'name'),
            'userAccount' => $this->value($row, 'USER_ACCOUNT', 'userAccount'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgId' => $this->value($row, 'ORG', 'orgId'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'orgSortCode' => $this->value($row, 'ORG_SORT_CODE', 'orgSortCode'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'updateUserName' => $this->value($row, 'UPDATE_USER_NAME', 'updateUserName'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
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

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
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
