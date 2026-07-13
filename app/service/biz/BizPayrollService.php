<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;
use think\file\UploadedFile;
use ZipArchive;

/**
 * Read-only payroll queries compatible with Java BizPayrollController.
 */
class BizPayrollService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const PROJECT_PLAY = 'PROJECT_PLAY';
    private const PAID = 'PAID';
    private const LEAVE_OF_ABSENCE = 'leaveOfAbsence';
    private const DEAL_PROJECT_STATES = [
        'WAIT_DELIVER',
        'SHIPPED',
        'PARTIALLY_SHIPPED',
        'COMPLETED',
    ];
    private const MAX_IMPORT_BYTES = 5 * 1024 * 1024;
    private const EDITABLE_FIELDS = [
        'senioritySalary' => 'SENIORITY_SALARY',
        'performanceSalary' => 'PERFORMANCE_SALARY',
        'workSalary' => 'WORK_SALARY',
        'basicSalary' => 'BASIC_SALARY',
        'rentSubsidies' => 'RENT_SUBSIDIES',
        'mealAllowance' => 'MEAL_ALLOWANCE',
        'dormitoryRent' => 'DORMITORY_RENT',
        'baseAmount' => 'BASE_AMOUNT',
        'transactionVolume' => 'TRANSACTION_VOLUME',
        'receivedAmount' => 'RECEIVED_AMOUNT',
        'taxFreight' => 'TAX_FREIGHT',
        'monthlyCommission' => 'MONTHLY_COMMISSION',
        'beforeReceivedAmount' => 'BEFORE_RECEIVED_AMOUNT',
        'beforeCommission' => 'BEFORE_COMMISSION',
        'totalCommission' => 'TOTAL_COMMISSION',
        'meritBonuses' => 'MERIT_BONUSES',
        'vacation' => 'VACATION',
        'vacationSubAmount' => 'VACATION_SUB_AMOUNT',
        'payableAmount' => 'PAYABLE_AMOUNT',
        'personalIncomeTax' => 'PERSONAL_INCOME_TAX',
        'socialSecurity' => 'SOCIAL_SECURITY',
        'actualAmount' => 'ACTUAL_AMOUNT',
        'rateCommission' => 'RATE_COMMISSION',
    ];
    private const IMPORT_COLUMNS = [
        0 => 'orgName',
        1 => 'order',
        2 => 'name',
        3 => 'basicSalary',
        4 => 'postWage',
        5 => 'workSalary',
        6 => 'senioritySalary',
        7 => 'performanceSalary',
        8 => 'rentSubsidies',
        9 => 'mealAllowance',
        10 => 'dormitoryRent',
        11 => 'baseAmount',
        12 => 'transactionVolume',
        13 => 'receivedAmount',
        14 => 'taxFreight',
        15 => 'monthlyCommission',
        16 => 'beforeReceivedAmount',
        17 => 'beforeCommission',
        18 => 'totalCommission',
        19 => 'meritBonuses',
        20 => 'vacationSubAmount',
        21 => 'yearEndBonus',
        22 => 'payableAmount',
        23 => 'personalIncomeTax',
        24 => 'socialSecurity',
        25 => 'actualAmount',
        26 => 'publicAccount',
        27 => 'privateAccount',
        28 => 'remark',
    ];
    private const IMPORT_NUMERIC_FIELDS = [
        'senioritySalary' => 'SENIORITY_SALARY',
        'performanceSalary' => 'PERFORMANCE_SALARY',
        'workSalary' => 'WORK_SALARY',
        'basicSalary' => 'BASIC_SALARY',
        'postWage' => 'POST_WAGE',
        'rentSubsidies' => 'RENT_SUBSIDIES',
        'mealAllowance' => 'MEAL_ALLOWANCE',
        'dormitoryRent' => 'DORMITORY_RENT',
        'baseAmount' => 'BASE_AMOUNT',
        'transactionVolume' => 'TRANSACTION_VOLUME',
        'receivedAmount' => 'RECEIVED_AMOUNT',
        'taxFreight' => 'TAX_FREIGHT',
        'monthlyCommission' => 'MONTHLY_COMMISSION',
        'beforeReceivedAmount' => 'BEFORE_RECEIVED_AMOUNT',
        'beforeCommission' => 'BEFORE_COMMISSION',
        'totalCommission' => 'TOTAL_COMMISSION',
        'meritBonuses' => 'MERIT_BONUSES',
        'vacationSubAmount' => 'VACATION_SUB_AMOUNT',
        'yearEndBonus' => 'YEAR_END_BONUS',
        'payableAmount' => 'PAYABLE_AMOUNT',
        'personalIncomeTax' => 'PERSONAL_INCOME_TAX',
        'socialSecurity' => 'SOCIAL_SECURITY',
        'actualAmount' => 'ACTUAL_AMOUNT',
        'publicAccount' => 'PUBLIC_ACCOUNT',
        'privateAccount' => 'PRIVATE_ACCOUNT',
    ];
    private const ADD_NUMERIC_FIELDS = [
        'senioritySalary' => 'SENIORITY_SALARY',
        'performanceSalary' => 'PERFORMANCE_SALARY',
        'workSalary' => 'WORK_SALARY',
        'basicSalary' => 'BASIC_SALARY',
        'postWage' => 'POST_WAGE',
        'rentSubsidies' => 'RENT_SUBSIDIES',
        'mealAllowance' => 'MEAL_ALLOWANCE',
        'dormitoryRent' => 'DORMITORY_RENT',
        'baseAmount' => 'BASE_AMOUNT',
        'transactionVolume' => 'TRANSACTION_VOLUME',
        'receivedAmount' => 'RECEIVED_AMOUNT',
        'taxFreight' => 'TAX_FREIGHT',
        'monthlyCommission' => 'MONTHLY_COMMISSION',
        'beforeReceivedAmount' => 'BEFORE_RECEIVED_AMOUNT',
        'beforeCommission' => 'BEFORE_COMMISSION',
        'rateCommission' => 'RATE_COMMISSION',
        'totalCommission' => 'TOTAL_COMMISSION',
        'meritBonuses' => 'MERIT_BONUSES',
        'vacation' => 'VACATION',
        'vacationSubAmount' => 'VACATION_SUB_AMOUNT',
        'yearEndBonus' => 'YEAR_END_BONUS',
        'payableAmount' => 'PAYABLE_AMOUNT',
        'personalIncomeTax' => 'PERSONAL_INCOME_TAX',
        'socialSecurity' => 'SOCIAL_SECURITY',
        'actualAmount' => 'ACTUAL_AMOUNT',
        'publicAccount' => 'PUBLIC_ACCOUNT',
        'privateAccount' => 'PRIVATE_ACCOUNT',
    ];
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
    private const EXPORT_COLUMNS = [
        ['groupName', '机构分组'],
        ['headName', '姓名'],
        ['basicSalary', '底薪工资'],
        ['postWage', '岗位工资'],
        ['workSalary', '加班工资'],
        ['senioritySalary', '工龄工资'],
        ['performanceSalary', '绩效工资'],
        ['rentSubsidies', '房租补贴'],
        ['mealAllowance', '餐补补贴'],
        ['dormitoryRent', '宿舍租金'],
        ['baseAmount', '基本工资(合计)'],
        ['transactionVolume', '当月成交额'],
        ['receivedAmount', '当月到账额'],
        ['taxFreight', '税运费'],
        ['monthlyCommission', '当月提成'],
        ['beforeReceivedAmount', '以往到账额'],
        ['beforeCommission', '以往提成'],
        ['totalCommission', '提成总计'],
        ['meritBonuses', '业绩奖金'],
        ['vacationSubAmount', '事假扣款'],
        ['yearEndBonus', '年终奖'],
        ['payableAmount', '应发金额'],
        ['personalIncomeTax', '个税'],
        ['socialSecurity', '社保'],
        ['actualAmount', '实发金额'],
        ['publicAccount', '公账打款'],
        ['privateAccount', '现金/私账'],
        ['remark', '备注'],
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

    public function add(array $input, array $payload = []): array
    {
        $userId = $this->requiredInputAny($input, ['user', 'userId', 'USER', 'USER_ID'], 'user');
        $orgId = $this->requiredInputAny($input, ['org', 'orgId', 'ORG', 'ORG_ID'], 'org');
        $salaryTime = $this->requiredDateTimeAny($input, ['salaryTime', 'SALARY_TIME'], 'salaryTime');

        return Db::transaction(function () use ($input, $payload, $userId, $orgId, $salaryTime): array {
            $users = $this->activeGenerateUsers([$userId], $payload);
            if (!isset($users[$userId])) {
                throw new RuntimeException('payroll user not found', 404);
            }

            $user = $users[$userId];
            $this->assertGenerateUserWritable($user, $payload);
            $userOrgId = trim((string)($user['ORG_ID'] ?? ''));
            if ($userOrgId === '' || $userOrgId !== $orgId) {
                throw new RuntimeException('payroll user org mismatch', 400);
            }

            $tenantId = trim((string)($user['TENANT_ID'] ?? $this->tenantId($payload)));
            if ($tenantId === '') {
                $tenantId = '1';
            }

            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $operatorId = $this->currentUserId($payload);
            $row = [
                'ID' => $id,
                'SALARY_TIME' => $salaryTime,
                'USER' => $userId,
                'ORG' => $orgId,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'REMARK' => trim((string)($input['remark'] ?? $input['REMARK'] ?? '')),
            ];

            foreach (self::ADD_NUMERIC_FIELDS as $field => $column) {
                if (array_key_exists($field, $input)) {
                    $row[$column] = $this->decimalAmount($input[$field]);
                }
            }

            Db::name('biz_payroll')->insert($row);

            return ['id' => $id, 'count' => 1];
        });
    }

    /**
     * @return array{filename:string, contentType:string, content:string}
     */
    public function downloadImportTemplate(): array
    {
        $path = app()->getRootPath() . 'app/resources/biz/payroll/userPayrollTemplate.xlsx';
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('payroll import template not found', 500);
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            throw new RuntimeException('payroll import template not readable', 500);
        }

        return [
            'filename' => '工资条导入模板.xlsx',
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content' => $content,
        ];
    }

    /**
     * @return array{filename:string, contentType:string, content:string}
     */
    public function export(array $filters = [], array $payload = []): array
    {
        if (trim((string)($filters['sortField'] ?? '')) === '') {
            $filters['sortField'] = 'org';
        }

        $rows = $this->applySort($this->payrollQuery($filters, $payload, false), $filters)
            ->field(self::FIELDS)
            ->select()
            ->toArray();
        $records = $this->payrollRows($rows);
        if ($records === []) {
            throw new RuntimeException('无数据可导出', 400);
        }

        $csvRows = [array_map(static fn (array $column): string => $column[1], self::EXPORT_COLUMNS)];
        foreach ($records as $record) {
            $record['groupName'] = trim((string)($record['orgName'] ?? '')) !== ''
                ? (string)$record['orgName']
                : '无组织';
            $csvRows[] = array_map(static fn (array $column): mixed => $record[$column[0]] ?? '', self::EXPORT_COLUMNS);
        }

        return [
            'filename' => $this->exportFilename($filters),
            'contentType' => 'text/csv; charset=UTF-8',
            'content' => $this->csvContent($csvRows),
        ];
    }

    public function importExcel(mixed $file, array $input = [], array $payload = []): array
    {
        if (!$file instanceof UploadedFile) {
            throw new RuntimeException('missing file', 400);
        }

        $sourcePath = $file->getRealPath() ?: $file->getPathname();
        if (!is_string($sourcePath) || $sourcePath === '' || !is_file($sourcePath)) {
            throw new RuntimeException('invalid uploaded file', 400);
        }

        $size = filesize($sourcePath);
        $size = $size === false ? 0 : $size;
        if ($size <= 0) {
            throw new RuntimeException('invalid uploaded file', 400);
        }
        if ($size > self::MAX_IMPORT_BYTES) {
            throw new RuntimeException('payroll import file is too large', 400);
        }

        $originalName = $this->uploadedOriginalName($file);
        if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('unsupported payroll import file type', 400);
        }

        $workbook = $this->readPayrollImportWorkbook($sourcePath);
        $salaryTime = $this->payrollImportSalaryTime((string)$workbook['title']);
        $rows = $workbook['rows'];
        $orgId = trim((string)($input['orgId'] ?? $input['org'] ?? $input['ORG_ID'] ?? ''));
        $users = $this->payrollImportUsers($orgId, $payload);

        return Db::transaction(function () use ($rows, $salaryTime, $users, $payload): array {
            $successCount = 0;
            $errorCount = 0;
            $errorDetail = [];

            foreach ($rows as $index => $row) {
                try {
                    $this->importPayrollRow($row, $users, $salaryTime, $payload);
                    $successCount++;
                } catch (\Throwable $exception) {
                    $errorCount++;
                    $errorDetail[] = [
                        'index' => $index + 1,
                        'success' => false,
                        'msg' => $exception->getMessage() !== '' ? $exception->getMessage() : 'payroll import row failed',
                    ];
                }
            }

            return [
                'totalCount' => count($rows),
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'errorDetail' => $errorDetail,
            ];
        });
    }

    public function generate(array $input, array $payload = []): array
    {
        $userIds = $this->generateUserIds($input);
        $salaryTime = $this->requiredDateTime($input, 'salaryTime');
        $salaryTimestamp = strtotime($salaryTime);
        if ($salaryTimestamp === false) {
            throw new RuntimeException('invalid salaryTime', 400);
        }

        $monthStart = date('Y-m-01 00:00:00', $salaryTimestamp);
        $monthEnd = date('Y-m-t 23:59:59', $salaryTimestamp);
        $socialSecurity = $this->nonNegativeDecimal($input['socialSecurity'] ?? 0, 'socialSecurity');

        return Db::transaction(function () use ($userIds, $salaryTime, $monthStart, $monthEnd, $socialSecurity, $payload): array {
            $users = $this->activeGenerateUsers($userIds, $payload);
            if (count($users) !== count($userIds)) {
                throw new RuntimeException('payroll generate user not found', 404);
            }

            $operatorId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $payrolls = [];
            foreach ($userIds as $userId) {
                $user = $users[$userId];
                $this->assertGenerateUserWritable($user, $payload);
                $payrolls[$userId] = $this->initialGeneratedPayroll($user, $salaryTime, $socialSecurity, $operatorId, $now, $payload);
            }

            $this->applyGeneratedTransactionVolume($payrolls, $userIds, $monthStart, $monthEnd, $payload);
            $this->applyGeneratedReceivedAmounts($payrolls, $monthStart, $monthEnd, $payload);
            $this->applyGeneratedLeaveAmounts($payrolls, $userIds, $monthStart, $monthEnd, $payload);
            $this->recalculateGeneratedPayrolls($payrolls);

            Db::name('biz_payroll')->insertAll(array_values($payrolls));

            return [
                'count' => count($payrolls),
                'ids' => array_values(array_map(static fn (array $row): string => (string)$row['ID'], $payrolls)),
                'salaryTime' => $salaryTime,
            ];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $this->assertPayrollWritable($id, $payload, 'edit payroll');
            $row = $this->editableUpdate($input, $payload);
            $updated = Db::name('biz_payroll')
                ->where('ID', $id)
                ->update($row);

            return ['id' => $id, 'count' => $updated];
        });
    }

    public function bathEdit(array $input, array $payload = []): array
    {
        $list = $input['list'] ?? null;
        if (!is_array($list) || $list === []) {
            throw new RuntimeException('missing list', 400);
        }

        $ids = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid payroll item', 400);
            }

            $ids[] = $this->requiredInput($item, 'id');
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('duplicate payroll id', 400);
        }

        return Db::transaction(function () use ($list, $ids, $payload): array {
            $rows = $this->activePayrollRows($ids, $payload);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('payroll batch contains missing rows', 400);
            }

            foreach ($list as $item) {
                $id = $this->requiredInput($item, 'id');
                $this->assertPayrollRowWritable($rows[$id], $payload, 'edit payroll');
                Db::name('biz_payroll')
                    ->where('ID', $id)
                    ->update($this->editableUpdate($item, $payload));
            }

            return ['count' => count($ids)];
        });
    }

    public function delete(array $input, array $payload = []): array
    {
        $ids = $this->deleteIds($input);

        return Db::transaction(function () use ($ids, $payload): array {
            $rows = $this->activePayrollRows($ids, $payload);
            if (count($rows) !== count($ids)) {
                throw new RuntimeException('payroll batch contains missing rows', 400);
            }

            foreach ($ids as $id) {
                $this->assertPayrollRowWritable($rows[$id], $payload, 'delete payroll');
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('biz_payroll')
                ->whereIn('ID', $ids)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return ['count' => $updated];
        });
    }

    /**
     * @return array{title:string, rows:array<int, array<string, mixed>>}
     */
    private function readPayrollImportWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('invalid xlsx file', 400);
        }

        try {
            $sharedStrings = $this->xlsxSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false || $sheetXml === '') {
                throw new RuntimeException('payroll import sheet not found', 400);
            }

            $sheet = simplexml_load_string($sheetXml);
            if ($sheet === false) {
                throw new RuntimeException('invalid payroll import sheet', 400);
            }

            $title = '';
            $rows = [];
            foreach ($sheet->sheetData->row as $row) {
                $rowNumber = (int)$row['r'];
                $values = [];
                foreach ($row->c as $cell) {
                    $columnIndex = $this->xlsxColumnIndex((string)$cell['r']);
                    $value = $this->xlsxCellValue($cell, $sharedStrings);
                    if ($rowNumber === 1 && $columnIndex === 0) {
                        $title = $value;
                    }

                    $field = self::IMPORT_COLUMNS[$columnIndex] ?? null;
                    if ($rowNumber > 3 && $field !== null) {
                        $values[$field] = $value;
                    }
                }

                if ($rowNumber > 3 && !$this->blankImportRow($values)) {
                    $rows[] = $values;
                }
            }

            return [
                'title' => $title,
                'rows' => $rows,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }

        $shared = simplexml_load_string($xml);
        if ($shared === false) {
            throw new RuntimeException('invalid xlsx shared strings', 400);
        }

        $strings = [];
        foreach ($shared->si as $item) {
            $parts = [];
            if (isset($item->t)) {
                $parts[] = (string)$item->t;
            }
            foreach ($item->r as $run) {
                $parts[] = (string)$run->t;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string)$cell['t'];
        if ($type === 'inlineStr') {
            return trim((string)($cell->is->t ?? ''));
        }

        $value = isset($cell->v) ? (string)$cell->v : '';
        if ($type === 's' && $value !== '') {
            return trim((string)($sharedStrings[(int)$value] ?? $value));
        }

        return trim($value);
    }

    private function xlsxColumnIndex(string $cellRef): int
    {
        if (preg_match('/^([A-Z]+)/i', $cellRef, $matches) !== 1) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function payrollImportSalaryTime(string $title): string
    {
        $title = trim($title);
        if (preg_match('/(\d{4})\s*年\s*(\d{1,2})\s*月\s*工资表/u', $title, $matches) !== 1) {
            throw new RuntimeException('invalid payroll import salary month', 400);
        }

        $month = (int)$matches[2];
        if ($month < 1 || $month > 12) {
            throw new RuntimeException('invalid payroll import salary month', 400);
        }

        return sprintf('%04d-%02d-01 00:00:00', (int)$matches[1], $month);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function payrollImportUsers(string $orgId, array $payload): array
    {
        $query = Db::name('sys_user')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if ($orgId !== '') {
            $orgIds = $this->orgAndChildren($orgId);
            if ($orgIds === []) {
                return [];
            }
            $query->whereIn('ORG_ID', $orgIds);
        }

        $rows = $query
            ->field('ID,NAME,ORG_ID,TENANT_ID,CREATE_USER')
            ->select()
            ->toArray();

        $users = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['NAME'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (isset($users[$name])) {
                throw new RuntimeException('duplicate payroll import user name: ' . $name, 400);
            }
            $this->assertPayrollImportUserWritable($row, $payload);
            $users[$name] = $row;
        }

        return $users;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array<string, mixed>> $users
     */
    private function importPayrollRow(array $row, array $users, string $salaryTime, array $payload): void
    {
        $name = $this->payrollImportName((string)($row['name'] ?? ''));
        if ($name === '' || !isset($users[$name])) {
            throw new RuntimeException('payroll import user not found', 400);
        }

        $user = $users[$name];
        $tenantId = trim((string)($user['TENANT_ID'] ?? $this->tenantId($payload)));
        if ($tenantId === '') {
            $tenantId = '1';
        }

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->currentUserId($payload);
        $payroll = [
            'ID' => $this->newId(),
            'RATE_COMMISSION' => '0.00',
            'VACATION' => '0.00',
            'SALARY_TIME' => $salaryTime,
            'USER' => (string)$user['ID'],
            'ORG' => (string)($user['ORG_ID'] ?? ''),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId,
            'REMARK' => trim((string)($row['remark'] ?? '')),
        ];

        foreach (self::IMPORT_NUMERIC_FIELDS as $field => $column) {
            $payroll[$column] = $this->decimalAmount($row[$field] ?? 0);
        }

        Db::name('biz_payroll')->insert($payroll);
    }

    private function payrollImportName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', '', $name);

        return trim($normalized === null ? $name : $normalized);
    }

    private function assertPayrollImportUserWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $orgId = trim((string)($row['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $orgId !== '' && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId !== '' && (string)($row['ID'] ?? '') === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to import payroll for this user', 403);
    }

    private function uploadedOriginalName(UploadedFile $file): string
    {
        $name = trim($file->getOriginalName());
        $name = str_replace('\\', '/', $name);
        $name = basename($name);

        return $name === '' ? 'payroll-import.xlsx' : $name;
    }

    private function blankImportRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
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

    private function assertPayrollWritable(string $id, array $payload, string $action): array
    {
        $rows = $this->activePayrollRows([$id], $payload);
        if ($rows === []) {
            throw new RuntimeException('payroll not found', 404);
        }

        return $this->assertPayrollRowWritable(reset($rows), $payload, $action);
    }

    private function assertPayrollRowWritable(array $row, array $payload, string $action): array
    {
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $rowOrg = trim((string)($row['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $rowOrg !== '' && in_array($rowOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $rowUser = trim((string)($row['USER'] ?? ''));
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && ($rowUser === $currentUserId || $createUser === $currentUserId)) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function activePayrollRows(array $ids, array $payload): array
    {
        $query = Db::name('biz_payroll')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,`USER`,ORG,CREATE_USER,TENANT_ID')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['ID']] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function generateUserIds(array $input): array
    {
        $source = $input['user'] ?? $input['users'] ?? $input['userIds'] ?? $input['userIdList'] ?? null;
        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing user', 400);
        }

        $ids = [];
        foreach ($source as $id) {
            if (is_array($id)) {
                throw new RuntimeException('invalid user', 400);
            }
            $id = trim((string)$id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            throw new RuntimeException('missing user', 400);
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('duplicate user', 400);
        }

        return $ids;
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function activeGenerateUsers(array $ids, array $payload): array
    {
        $query = Db::name('sys_user')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,ORG_ID,BASIC_SALARY,TENANT_ID')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['ID']] = $row;
        }

        return $result;
    }

    private function assertGenerateUserWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $rowOrg = trim((string)($row['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $rowOrg !== '' && in_array($rowOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId !== '' && trim((string)($row['ID'] ?? '')) === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to generate payroll', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function initialGeneratedPayroll(
        array $user,
        string $salaryTime,
        string $socialSecurity,
        string $operatorId,
        string $now,
        array $payload
    ): array {
        $zero = '0.00';
        $tenantId = trim((string)($user['TENANT_ID'] ?? ''));
        if ($tenantId === '') {
            $tenantId = $this->tenantId($payload);
        }
        if ($tenantId === '') {
            $tenantId = '1';
        }

        return [
            'ID' => $this->newId(),
            'SENIORITY_SALARY' => $zero,
            'PERFORMANCE_SALARY' => $zero,
            'WORK_SALARY' => $zero,
            'BASIC_SALARY' => $this->decimalAmount($user['BASIC_SALARY'] ?? 0),
            'POST_WAGE' => $zero,
            'RENT_SUBSIDIES' => $zero,
            'MEAL_ALLOWANCE' => $zero,
            'DORMITORY_RENT' => $zero,
            'BASE_AMOUNT' => $zero,
            'TRANSACTION_VOLUME' => $zero,
            'RECEIVED_AMOUNT' => $zero,
            'TAX_FREIGHT' => $zero,
            'MONTHLY_COMMISSION' => $zero,
            'BEFORE_RECEIVED_AMOUNT' => $zero,
            'BEFORE_COMMISSION' => $zero,
            'RATE_COMMISSION' => $zero,
            'TOTAL_COMMISSION' => $zero,
            'MERIT_BONUSES' => $zero,
            'VACATION' => $zero,
            'VACATION_SUB_AMOUNT' => $zero,
            'YEAR_END_BONUS' => $zero,
            'PAYABLE_AMOUNT' => $zero,
            'PERSONAL_INCOME_TAX' => $zero,
            'SOCIAL_SECURITY' => $socialSecurity,
            'ACTUAL_AMOUNT' => $zero,
            'PRIVATE_ACCOUNT' => $zero,
            'PUBLIC_ACCOUNT' => $zero,
            'SALARY_TIME' => $salaryTime,
            'USER' => (string)$user['ID'],
            'ORG' => (string)($user['ORG_ID'] ?? ''),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId,
            'REMARK' => '',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $payrolls
     * @param array<int, string> $userIds
     */
    private function applyGeneratedTransactionVolume(array &$payrolls, array $userIds, string $monthStart, string $monthEnd, array $payload): void
    {
        $query = Db::name('biz_sale_project')
            ->whereIn('PROJECT_STATE', self::DEAL_PROJECT_STATES)
            ->whereIn('USER', $userIds)
            ->whereBetweenTime('CREATE_TIME', $monthStart, $monthEnd)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('`USER` AS USER_ID,TOTAL_PRICE')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $userId = (string)($row['USER_ID'] ?? '');
            if (!isset($payrolls[$userId])) {
                continue;
            }
            $this->addGeneratedAmount($payrolls[$userId], 'TRANSACTION_VOLUME', $row['TOTAL_PRICE'] ?? 0);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $payrolls
     */
    private function applyGeneratedReceivedAmounts(array &$payrolls, string $monthStart, string $monthEnd, array $payload): void
    {
        $paymentQuery = Db::name('biz_payment_record')
            ->where('SETTLEMENT_CATEGORY', self::PROJECT_PLAY)
            ->whereBetweenTime('CREATE_TIME', $monthStart, $monthEnd)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $paymentQuery->where('TENANT_ID', $tenantId);
        }

        $projectIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $paymentQuery->column('OBJECT_ID')
        ))));
        if ($projectIds === []) {
            return;
        }

        $projectQuery = Db::name('biz_sale_project')
            ->where('PLAY_STATE', self::PAID)
            ->whereIn('ID', $projectIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $projectQuery->where('TENANT_ID', $tenantId);
        }

        $projects = $projectQuery
            ->field('ID,`USER` AS USER_ID,TOTAL_PRICE,REBATE_AMOUNT,CREATE_TIME')
            ->select()
            ->toArray();
        foreach ($projects as $project) {
            $userId = (string)($project['USER_ID'] ?? '');
            if (!isset($payrolls[$userId])) {
                continue;
            }

            $amount = $this->numericAmount($project['TOTAL_PRICE'] ?? 0) - $this->numericAmount($project['REBATE_AMOUNT'] ?? 0);
            $column = $this->isInTimeRange((string)($project['CREATE_TIME'] ?? ''), $monthStart, $monthEnd)
                ? 'RECEIVED_AMOUNT'
                : 'BEFORE_RECEIVED_AMOUNT';
            $this->addGeneratedAmount($payrolls[$userId], $column, $amount);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $payrolls
     * @param array<int, string> $userIds
     */
    private function applyGeneratedLeaveAmounts(array &$payrolls, array $userIds, string $monthStart, string $monthEnd, array $payload): void
    {
        $query = Db::name('biz_leave_application')
            ->where('category', self::LEAVE_OF_ABSENCE)
            ->whereIn('USER_ID', $userIds)
            ->whereRaw('((START_TIME BETWEEN ? AND ?) OR (END_TIME BETWEEN ? AND ?))', [
                $monthStart,
                $monthEnd,
                $monthStart,
                $monthEnd,
            ])
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('USER_ID,AMOUNT,START_TIME,END_TIME')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $userId = (string)($row['USER_ID'] ?? '');
            if (!isset($payrolls[$userId])) {
                continue;
            }

            $amount = $this->generatedVacationAmount(
                (string)($row['START_TIME'] ?? ''),
                (string)($row['END_TIME'] ?? ''),
                $monthStart,
                $monthEnd,
                $row['AMOUNT'] ?? 0
            );
            $this->addGeneratedAmount($payrolls[$userId], 'VACATION', $amount);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $payrolls
     */
    private function recalculateGeneratedPayrolls(array &$payrolls): void
    {
        foreach ($payrolls as &$payroll) {
            $baseAmount = $this->numericAmount($payroll['SENIORITY_SALARY'])
                + $this->numericAmount($payroll['PERFORMANCE_SALARY'])
                + $this->numericAmount($payroll['WORK_SALARY'])
                + $this->numericAmount($payroll['BASIC_SALARY'])
                + $this->numericAmount($payroll['RENT_SUBSIDIES'])
                + $this->numericAmount($payroll['MEAL_ALLOWANCE'])
                - $this->numericAmount($payroll['DORMITORY_RENT']);
            $payroll['BASE_AMOUNT'] = $this->decimalAmount($baseAmount);

            $vacationSubAmount = 0.0;
            $basicSalary = $this->numericAmount($payroll['BASIC_SALARY']);
            if ($basicSalary > 0) {
                $dailyDeduction = floor(($basicSalary / 24) * 100) / 100;
                $vacationSubAmount = $dailyDeduction * $this->numericAmount($payroll['VACATION']);
            }
            $payroll['VACATION_SUB_AMOUNT'] = $this->decimalAmount($vacationSubAmount);

            $payableAmount = $this->numericAmount($payroll['BASE_AMOUNT'])
                + $this->numericAmount($payroll['TOTAL_COMMISSION'])
                - $this->numericAmount($payroll['VACATION_SUB_AMOUNT']);
            $payroll['PAYABLE_AMOUNT'] = $this->decimalAmount($payableAmount);

            $actualAmount = $this->numericAmount($payroll['PAYABLE_AMOUNT'])
                - $this->numericAmount($payroll['PERFORMANCE_SALARY'])
                - $this->numericAmount($payroll['SOCIAL_SECURITY']);
            $payroll['ACTUAL_AMOUNT'] = $this->decimalAmount($actualAmount);
        }
        unset($payroll);
    }

    private function editableUpdate(array $input, array $payload): array
    {
        $row = [];
        foreach (self::EDITABLE_FIELDS as $field => $column) {
            if (array_key_exists($field, $input)) {
                $row[$column] = $this->decimalAmount($input[$field]);
            }
        }

        $userId = $this->currentUserId($payload);
        $row['UPDATE_TIME'] = date('Y-m-d H:i:s');
        $row['UPDATE_USER'] = $userId !== '' ? $userId : null;

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $source = $input['ids'] ?? $input['idList'] ?? null;
        if ($source === null && array_key_exists('id', $input)) {
            $source = [$input['id']];
        }
        if ($source === null && $this->isListArray($input)) {
            $source = array_map(static function (mixed $item): mixed {
                return is_array($item) ? ($item['id'] ?? null) : $item;
            }, $input);
        }
        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            throw new RuntimeException('missing id', 400);
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $source
        ))));
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        return $ids;
    }

    private function isListArray(array $input): bool
    {
        $index = 0;
        foreach (array_keys($input) as $key) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
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

    private function exportFilename(array $filters): string
    {
        $time = trim((string)($filters['startSalaryTime'] ?? $filters['salaryTime'] ?? ''));
        $timestamp = $time !== '' ? strtotime($time) : false;
        $month = $timestamp === false ? date('Y-m') : date('Y-m', $timestamp);

        return $month . '系统人员工资清单.csv';
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function csvContent(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('文件下载失败', 500);
        }

        foreach ($rows as $row) {
            fputcsv($handle, array_map(static function (mixed $value): string {
                if (is_array($value)) {
                    $json = json_encode($value, JSON_UNESCAPED_UNICODE);

                    return $json === false ? '' : $json;
                }

                return (string)$value;
            }, $row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . ($content === false ? '' : $content);
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

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    /**
     * @param array<int, string> $keys
     */
    private function requiredInputAny(array $input, array $keys, string $name): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        throw new RuntimeException("missing {$name}", 400);
    }

    /**
     * @param array<int, string> $keys
     */
    private function requiredDateTimeAny(array $input, array $keys, string $name): string
    {
        $value = $this->requiredInputAny($input, $keys, $name);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$name}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function requiredDateTime(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function nonNegativeDecimal(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (!is_numeric($value) || (float)$value < 0) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return $this->decimalAmount($value);
    }

    private function decimalAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid amount', 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function numericAmount(mixed $value): float
    {
        return (float)($value ?? 0);
    }

    private function addGeneratedAmount(array &$row, string $column, mixed $amount): void
    {
        $row[$column] = $this->decimalAmount($this->numericAmount($row[$column] ?? 0) + $this->numericAmount($amount));
    }

    private function generatedVacationAmount(string $leaveStart, string $leaveEnd, string $monthStart, string $monthEnd, mixed $storedAmount): string
    {
        if ($this->isInTimeRange($leaveStart, $monthStart, $monthEnd) && $this->isInTimeRange($leaveEnd, $monthStart, $monthEnd)) {
            return $this->decimalAmount($storedAmount);
        }

        $startTs = strtotime($leaveStart);
        $endTs = strtotime($leaveEnd);
        $monthStartTs = strtotime($monthStart);
        $monthEndTs = strtotime($monthEnd);
        if ($startTs === false || $endTs === false || $monthStartTs === false || $monthEndTs === false) {
            return '0.00';
        }

        $effectiveStart = $this->timestampInRange($startTs, $monthStartTs, $monthEndTs) ? $startTs : $monthStartTs;
        $effectiveEnd = $this->timestampInRange($endTs, $monthStartTs, $monthEndTs) ? $endTs : $monthEndTs;
        if ($effectiveEnd < $effectiveStart) {
            return '0.00';
        }

        $days = floor(($effectiveEnd - $effectiveStart) / 86400) + 1;
        if ((int)date('G', $effectiveStart) === 12) {
            $days -= 0.5;
        }
        if ((int)date('G', $effectiveEnd) === 12) {
            $days -= 0.5;
        }

        return $this->decimalAmount(max(0, $days));
    }

    private function isInTimeRange(string $time, string $start, string $end): bool
    {
        $timestamp = strtotime($time);
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        if ($timestamp === false || $startTs === false || $endTs === false) {
            return false;
        }

        return $this->timestampInRange($timestamp, $startTs, $endTs);
    }

    private function timestampInRange(int $timestamp, int $start, int $end): bool
    {
        return $timestamp >= $start && $timestamp <= $end;
    }

    private function tenantId(array $payload): string
    {
        return trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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
