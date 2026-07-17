<?php

declare(strict_types=1);

namespace app\service\user;

use app\service\auth\Sm3Hasher;
use app\model\SysOrg;
use app\model\SysPosition;
use app\model\SysRelation;
use app\model\SysRole;
use app\model\SysUser;
use app\model\SysUserProcessConfig;
use app\support\SensitiveFieldCodec;
use app\support\TenantScope;
use RuntimeException;
use Throwable;
use think\facade\Db;

/**
 * Read-only user directory queries compatible with Java SysUserService selectors.
 */
class UserDirectoryService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const WORKBENCH_CATEGORY = 'SYS_USER_WORKBENCH_DATA';
    private const DEFAULT_WORKBENCH_KEY = 'SNOWY_SYS_DEFAULT_WORKBENCH_DATA';
    private const DEFAULT_PASSWORD_KEY = 'SNOWY_SYS_DEFAULT_PASSWORD';
    private const MESSAGE_TO_USER_CATEGORY = 'MSG_TO_USER';
    private const USER_HAS_ROLE = 'SYS_USER_HAS_ROLE';
    private const USER_HAS_RESOURCE = 'SYS_USER_HAS_RESOURCE';
    private const USER_HAS_PERMISSION = 'SYS_USER_HAS_PERMISSION';
    private const USER_STATUS_ENABLE = 'ENABLE';
    private const USER_STATUS_DISABLED = 'DISABLED';
    private const IMPORT_MAX_BYTES = 5242880;
    private const IMPORT_MAX_ROWS = 1000;
    private const IMPORT_MAX_SHEET_BYTES = 2097152;
    private const USER_ID_LIST_MAX = 200;
    private const USER_ID_LIST_FIELDS = [
        'ID',
        'ORG_ID',
        'AVATAR',
        'ACCOUNT',
        'NAME',
        'SORT_CODE',
    ];
    private const USER_SELECTOR_FIELDS = [
        'ID',
        'ORG_ID',
        'AVATAR',
        'ACCOUNT',
        'NAME',
        'SORT_CODE',
        'POSITION_ID',
        'GENDER',
        'ENTRY_DATE',
    ];
    private const SCOPE_CATEGORIES = [
        'SCOPE_ALL',
        'SCOPE_SELF',
        'SCOPE_ORG',
        'SCOPE_ORG_CHILD',
        'SCOPE_ORG_DEFINE',
    ];
    private const DEFAULT_PROCESS_NAMES = [
        'Process_reimbursement',
        'Process_make_payment',
        'Process_project_reissue_product',
        'Process_sale_project_play',
        'Process_payment',
        'Process_sale_project_init',
        'Process_sale_project_delivery',
        'Process_procure',
        'Process_ask_leave',
    ];

    private readonly SensitiveFieldCodec $sensitiveFields;

    public function __construct(
        private readonly OrgService $orgService = new OrgService(),
        private readonly PositionService $positionService = new PositionService(),
        ?SensitiveFieldCodec $sensitiveFields = null
    ) {
        $this->sensitiveFields = $sensitiveFields ?? new SensitiveFieldCodec();
    }

    public function page(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $records = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->sanitizeUserRows($records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id, mixed $payload = []): ?array
    {
        $row = $this->baseQuery(TenantScope::scopedFilters(['id' => $id], $payload))->find();
        if (!$row) {
            return null;
        }

        $rows = $this->sanitizeUserRows([$row->toArray()]);

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDetail(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        $queryFilters = $filters;
        unset($queryFilters['orgId']);
        $query = $this->baseQuery($queryFilters);
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('ORG_ID', $orgIds);
        }

        $rows = $query
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        $rows = $this->sanitizeUserRows($rows);

        return array_map(static fn (array $row): array => array_merge($row, [
            'value' => $row['id'] ?? null,
            'label' => $row['name'] ?? $row['account'] ?? null,
            'title' => $row['name'] ?? $row['account'] ?? null,
        ]), $rows);
    }

    /**
     * @param array<string, mixed>|mixed $payload
     * @return array{filename:string, contentType:string, content:string}
     */
    public function downloadImportUserTemplate(mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        if (!$this->isAdminCompatible($payload) && !$this->hasExportUserPermission($payload, false)) {
            throw new RuntimeException('permission denied', 403);
        }

        return [
            'filename' => 'userImportTemplate.xlsx',
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content' => $this->xlsxContent([
                ['系统用户导入模板。前四列为必填；组织名称使用 - 分隔完整路径，例如：总部-技术部。'],
                $this->userImportHeaders(),
                ['zhangsan', '张三', '总部-技术部', '工程师', '13800000000', 'zhangsan@example.com', '', 'EMP001', date('Y-m-d'), 'P6'],
            ]),
        ];
    }

    /**
     * @param array<string, mixed>|mixed $payload
     * @return array{totalCount:int, successCount:int, errorCount:int, errorDetail:array<int, array<string, mixed>>}
     */
    public function importUsers(mixed $file, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $this->ensureImportAllowed($payload);
        $path = $this->uploadedFilePath($file);
        $rows = $this->readUserImportRows($path);

        $successCount = 0;
        $errorDetail = [];

        Db::transaction(function () use ($rows, $payload, &$successCount, &$errorDetail): void {
            foreach ($rows as $index => $row) {
                try {
                    $this->importUserRow($row, $payload);
                    $successCount++;
                } catch (Throwable $exception) {
                    $errorDetail[] = [
                        'index' => $index + 1,
                        'success' => false,
                        'msg' => $exception->getMessage() !== '' ? $exception->getMessage() : 'data import failed',
                    ];
                }
            }
        });

        return [
            'totalCount' => count($rows),
            'successCount' => $successCount,
            'errorCount' => count($errorDetail),
            'errorDetail' => $errorDetail,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function userImportHeaders(): array
    {
        return [
            '账号',
            '姓名',
            '组织名称',
            '职位名称',
            '手机',
            '邮箱',
            '主管名称',
            '员工编号',
            '入职日期',
            '职级',
            '昵称',
            '性别',
            '年龄',
            '出生日期',
            '民族',
            '籍贯',
            '家庭住址',
            '通信地址',
            '证件类型',
            '证件号码',
            '文化程度',
            '政治面貌',
            '毕业院校',
            '学历',
            '学制',
            '学位',
            '家庭电话',
            '办公电话',
            '紧急联系人',
            '紧急联系人电话',
            '紧急联系人地址',
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function xlsxContent(array $rows): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'xlsx-');
        if ($temp === false) {
            throw new RuntimeException('template build failed', 500);
        }

        $zip = new \ZipArchive();
        if ($zip->open($temp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($temp);
            throw new RuntimeException('template build failed', 500);
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="用户导入" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->close();

        $content = file_get_contents($temp);
        @unlink($temp);
        if ($content === false || $content === '') {
            throw new RuntimeException('template build failed', 500);
        }

        return $content;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $columnIndex => $value) {
                $xml .= $this->xlsxCellXml($rowIndex + 1, $columnIndex, $value);
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    private function xlsxCellXml(int $row, int $columnIndex, mixed $value): string
    {
        $ref = $this->xlsxColumnName($columnIndex) . $row;
        $value = $this->xmlText((string)$value);

        return '<c r="' . $ref . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function uploadedFilePath(mixed $file): string
    {
        if ($file === null || $file === '') {
            throw new RuntimeException('missing file', 400);
        }

        if (is_string($file)) {
            $path = $file;
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        } elseif (is_object($file)) {
            $path = '';
            foreach (['getRealPath', 'getPathname'] as $method) {
                if (method_exists($file, $method)) {
                    $path = (string)$file->{$method}();
                    if ($path !== '') {
                        break;
                    }
                }
            }

            $extension = '';
            foreach (['getOriginalExtension', 'extension'] as $method) {
                if (method_exists($file, $method)) {
                    $extension = strtolower(trim((string)$file->{$method}()));
                    if ($extension !== '') {
                        break;
                    }
                }
            }
            if ($extension === '' && method_exists($file, 'getOriginalName')) {
                $extension = strtolower(pathinfo((string)$file->getOriginalName(), PATHINFO_EXTENSION));
            }
            if ($extension === '') {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            }
        } else {
            throw new RuntimeException('invalid file', 400);
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('invalid file', 400);
        }
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            throw new RuntimeException('invalid file', 400);
        }
        if ($size > self::IMPORT_MAX_BYTES) {
            throw new RuntimeException('import file too large', 400);
        }
        if ($extension !== 'xlsx') {
            throw new RuntimeException('only .xlsx import is supported', 400);
        }

        return $path;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readUserImportRows(string $path): array
    {
        $rawRows = $this->readXlsxRows($path);
        $fields = [
            'account',
            'name',
            'orgName',
            'positionName',
            'phone',
            'email',
            'directorName',
            'empNo',
            'entryDate',
            'positionLevel',
            'nickname',
            'gender',
            'age',
            'birthday',
            'nation',
            'nativePlace',
            'homeAddress',
            'mailingAddress',
            'idCardType',
            'idCardNumber',
            'cultureLevel',
            'politicalOutlook',
            'college',
            'education',
            'eduLength',
            'degree',
            'homeTel',
            'officeTel',
            'emergencyContact',
            'emergencyPhone',
            'emergencyAddress',
        ];

        $rows = [];
        foreach (array_slice($rawRows, 2) as $rawRow) {
            $mapped = [];
            foreach ($fields as $index => $field) {
                $mapped[$field] = trim((string)($rawRow[$index] ?? ''));
            }
            if (implode('', $mapped) === '') {
                continue;
            }

            $rows[] = $mapped;
            if (count($rows) > self::IMPORT_MAX_ROWS) {
                throw new RuntimeException('too many import rows', 400);
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readXlsxRows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('invalid xlsx file', 400);
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $sheetXml = $this->xlsxFirstSheetXml($zip);
        }
        $zip->close();

        if (!is_string($sheetXml) || trim($sheetXml) === '') {
            throw new RuntimeException('invalid xlsx file', 400);
        }
        if (strlen($sheetXml) > self::IMPORT_MAX_SHEET_BYTES) {
            throw new RuntimeException('import sheet too large', 400);
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false) {
            throw new RuntimeException('invalid xlsx file', 400);
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $ref = (string)($cell['r'] ?? '');
                $columnIndex = $this->xlsxColumnIndex($ref);
                if ($columnIndex < 0) {
                    $columnIndex = count($values);
                }
                $values[$columnIndex] = $this->xlsxCellValue($cell, $sharedStrings);
            }
            if ($values !== []) {
                ksort($values);
                $rows[] = $values;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || trim($xml) === '') {
            return [];
        }

        $sharedXml = simplexml_load_string($xml);
        if ($sharedXml === false) {
            return [];
        }

        $strings = [];
        foreach ($sharedXml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string)$item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string)$run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function xlsxFirstSheetXml(\ZipArchive $zip): string|false
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $zip->getFromName($name);
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string)($cell['t'] ?? '');
        if ($type === 's') {
            $index = (int)((string)($cell->v ?? '0'));

            return trim($sharedStrings[$index] ?? '');
        }
        if ($type === 'inlineStr') {
            return trim((string)($cell->is->t ?? ''));
        }

        return trim((string)($cell->v ?? ''));
    }

    private function xlsxColumnIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)/i', $ref, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, mixed> $payload
     */
    private function importUserRow(array $row, array $payload): void
    {
        foreach (['account', 'name', 'orgName', 'positionName'] as $field) {
            if (trim((string)($row[$field] ?? '')) === '') {
                throw new RuntimeException('required field is empty', 400);
            }
        }

        $org = $this->findOrCreateImportOrg((string)$row['orgName'], $payload);
        $position = $this->findOrCreateImportPosition((string)$org['ID'], (string)$row['positionName'], $payload, $org);
        $account = trim((string)$row['account']);
        $existing = $this->activeUserByAccount($account);
        $existingId = is_array($existing) ? (string)($existing['ID'] ?? '') : null;

        $data = $this->importUserData($row);
        $data['ACCOUNT'] = $account;
        $data['NAME'] = trim((string)$row['name']);
        $data['ORG_ID'] = (string)$org['ID'];
        $data['POSITION_ID'] = (string)$position['ID'];
        $this->removeImportDuplicateContact($data, $existingId);
        $this->ensureUserWriteAllowed($payload, $data, false, $existingId === null || $existingId === '' ? 'add' : 'edit', $existing);
        $this->ensureImportTargetScopeAllowed($payload, (string)$org['ID']);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);

        if ($existingId === null || $existingId === '') {
            $id = $this->newId();
            $insert = array_merge($data, [
                'ID' => $id,
                'PASSWORD' => $this->defaultPasswordHash(),
                'AVATAR' => $this->defaultAvatar((string)$data['NAME']),
                'USER_STATUS' => self::USER_STATUS_ENABLE,
                'SORT_CODE' => 99,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                'TENANT_ID' => $this->tenantIdForWrite($payload, $org),
                'BANK_NAME' => '',
                'BANK_ACCOUNT' => '',
                'BASIC_SALARY' => '0.00',
                'COMPANY_EMPLOYEE_ID' => trim((string)($data['COMPANY_EMPLOYEE_ID'] ?? '')) !== ''
                    ? $data['COMPANY_EMPLOYEE_ID']
                    : $this->nextCompanyEmployeeId($org),
            ]);
            $insert = $this->sensitiveFields->encodeRow('sys_user', $insert);
            Db::name('sys_user')->insert($insert);

            return;
        }

        $this->ensureTenantCompatible($payload, $existing);
        $existingAccount = strtolower(trim((string)($existing['ACCOUNT'] ?? '')));
        if (in_array($existingAccount, ['superadmin', 'bizadmin', 'tenantadmin'], true) && !$this->isAdminCompatible($payload)) {
            throw new RuntimeException('built-in user cannot be imported by non-admin', 403);
        }
        $update = array_merge($data, [
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);
        unset($update['PASSWORD'], $update['USER_STATUS'], $update['SORT_CODE'], $update['DELETE_FLAG'], $update['CREATE_TIME'], $update['CREATE_USER'], $update['TENANT_ID']);
        $update = $this->sensitiveFields->encodeRow('sys_user', $update);

        Db::name('sys_user')
            ->where('ID', $existingId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function importUserData(array $row): array
    {
        $fieldMap = [
            'phone' => 'PHONE',
            'email' => 'EMAIL',
            'empNo' => 'EMP_NO',
            'entryDate' => 'ENTRY_DATE',
            'positionLevel' => 'POSITION_LEVEL',
            'nickname' => 'NICKNAME',
            'gender' => 'GENDER',
            'age' => 'AGE',
            'birthday' => 'BIRTHDAY',
            'nation' => 'NATION',
            'nativePlace' => 'NATIVE_PLACE',
            'homeAddress' => 'HOME_ADDRESS',
            'mailingAddress' => 'MAILING_ADDRESS',
            'idCardType' => 'ID_CARD_TYPE',
            'idCardNumber' => 'ID_CARD_NUMBER',
            'cultureLevel' => 'CULTURE_LEVEL',
            'politicalOutlook' => 'POLITICAL_OUTLOOK',
            'college' => 'COLLEGE',
            'education' => 'EDUCATION',
            'eduLength' => 'EDU_LENGTH',
            'degree' => 'DEGREE',
            'homeTel' => 'HOME_TEL',
            'officeTel' => 'OFFICE_TEL',
            'emergencyContact' => 'EMERGENCY_CONTACT',
            'emergencyPhone' => 'EMERGENCY_PHONE',
            'emergencyAddress' => 'EMERGENCY_ADDRESS',
        ];

        $data = [];
        foreach ($fieldMap as $field => $column) {
            $value = trim((string)($row[$field] ?? ''));
            $data[$column] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function removeImportDuplicateContact(array &$data, ?string $existingId): void
    {
        foreach (['PHONE', 'EMAIL'] as $column) {
            $value = trim((string)($data[$column] ?? ''));
            if ($value === '') {
                continue;
            }

            $query = Db::name('sys_user')
                ->where(
                    $column,
                    $column === 'PHONE'
                        ? $this->sensitiveFields->lookupValue('sys_user', 'PHONE', $value)
                        : $value
                )
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                });
            if ($existingId !== null && $existingId !== '') {
                $query->where('ID', '<>', $existingId);
            }

            if ($query->count() > 0) {
                unset($data[$column]);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function findOrCreateImportOrg(string $orgFullName, array $payload): array
    {
        $parts = array_values(array_filter(array_map(static fn (string $part): string => trim($part), explode('-', $orgFullName))));
        if ($parts === []) {
            throw new RuntimeException('missing orgName', 400);
        }

        $parentId = '0';
        $parent = null;
        foreach ($parts as $part) {
            $query = Db::name('sys_org')
                ->where('PARENT_ID', $parentId)
                ->where('NAME', $part)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                });

            $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
            if ($tenantId !== '') {
                $query->where('TENANT_ID', $tenantId);
            }

            $row = $query->order('ID', 'asc')->find();
            if (!is_array($row) || $row === []) {
                if (!$this->isAdminCompatible($payload)) {
                    throw new RuntimeException('org not found', 404);
                }
                $row = $this->createImportOrg($parentId, $part, $payload, $parent);
            }
            $parentId = (string)$row['ID'];
            $parent = $row;
        }

        return $parent ?? [];
    }

    /**
     * @param array<string, mixed>|null $parent
     * @return array<string, mixed>
     */
    private function createImportOrg(string $parentId, string $name, array $payload, ?array $parent): array
    {
        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $tenantId = $parent !== null && trim((string)($parent['TENANT_ID'] ?? '')) !== ''
            ? (string)$parent['TENANT_ID']
            : (trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '')) !== '' ? trim((string)($payload['tenant_id'] ?? $payload['tenantId'])) : '0');
        $row = [
            'ID' => $this->newId(),
            'PARENT_ID' => $parentId,
            'DIRECTOR_ID' => null,
            'NAME' => $name,
            'CODE' => $this->newRandomCode('sys_org'),
            'CATEGORY' => $parentId === '0' ? 'COMPANY' : 'DEPT',
            'SORT_CODE' => 99,
            'EXT_JSON' => null,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
            'TENANT_ID' => $tenantId,
        ];
        Db::name('sys_org')->insert($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $org
     * @return array<string, mixed>
     */
    private function findOrCreateImportPosition(string $orgId, string $positionName, array $payload, array $org): array
    {
        $positionName = trim($positionName);
        if ($positionName === '') {
            throw new RuntimeException('missing positionName', 400);
        }

        $row = Db::name('sys_position')
            ->where('ORG_ID', $orgId)
            ->where('NAME', $positionName)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->order('ID', 'asc')
            ->find();
        if (is_array($row) && $row !== []) {
            return $row;
        }
        if (!$this->isAdminCompatible($payload)) {
            throw new RuntimeException('position not found', 404);
        }

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $row = [
            'ID' => $this->newId(),
            'ORG_ID' => $orgId,
            'NAME' => $positionName,
            'CODE' => $this->newRandomCode('sys_position'),
            'CATEGORY' => 'LOW',
            'SORT_CODE' => 99,
            'EXT_JSON' => null,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
            'TENANT_ID' => trim((string)($org['TENANT_ID'] ?? '')) !== '' ? (string)$org['TENANT_ID'] : '0',
        ];
        Db::name('sys_position')->insert($row);

        return $row;
    }

    private function newRandomCode(string $table): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Db::name($table)
                ->where('CODE', $code)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->count();
            if ($exists === 0) {
                return $code;
            }
        }

        return substr($this->newId(), -10);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeUserByAccount(string $account): ?array
    {
        $row = Db::name('sys_user')
            ->where('ACCOUNT', $account)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->order('ID', 'asc')
            ->find();

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureImportTargetScopeAllowed(array $payload, string $orgId): void
    {
        if ($this->isAdminCompatible($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        $payloadUserId = $this->payloadUserId($payload);
        if ($payloadUserId !== '') {
            $userOrgId = Db::name('sys_user')
                ->where('ID', $payloadUserId)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->value('ORG_ID');
            if (is_string($userOrgId) && $userOrgId === $orgId) {
                return;
            }
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed>|mixed $payload
     * @return array{filename:string, contentType:string, content:string}
     */
    public function exportUsers(array $filters = [], mixed $payload = [], bool $bizScope = false): array
    {
        $payload = is_array($payload) ? $payload : [];
        $this->ensureExportAllowed($payload, $bizScope);

        $rows = $this->exportUserRows($filters, $payload, $bizScope);
        $csvRows = [[
            'id',
            'account',
            'name',
            'gender',
            'phone',
            'email',
            'orgName',
            'positionName',
            'userStatus',
            'entryDate',
            'sortCode',
            'createTime',
        ]];

        foreach ($rows as $row) {
            $csvRows[] = [
                $row['id'] ?? '',
                $row['account'] ?? '',
                $row['name'] ?? '',
                $row['genderName'] ?? ($row['gender'] ?? ''),
                $row['phone'] ?? '',
                $row['email'] ?? '',
                $row['orgName'] ?? '',
                $row['positionName'] ?? '',
                $row['userStatus'] ?? '',
                $row['entryDate'] ?? '',
                $row['sortCode'] ?? '',
                $row['createTime'] ?? '',
            ];
        }

        return [
            'filename' => ($bizScope ? 'biz-user-export-' : 'sys-user-export-') . date('YmdHis') . '.csv',
            'contentType' => 'text/csv',
            'content' => $this->csvContent($csvRows),
        ];
    }

    /**
     * @param array<string, mixed>|mixed $payload
     * @return array{filename:string, contentType:string, content:string}
     */
    public function exportUserInfoFile(string $id, mixed $payload = [], bool $bizScope = false): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $this->ensureExportAllowed($payload, $bizScope);
        $user = $this->activeUserRow($id);
        $this->ensureTenantCompatible($payload, $user);
        if ($bizScope) {
            $this->ensureExportUserScopeAllowed($payload, $user);
        }

        $row = $this->sanitizeUserRows([$user])[0] ?? [];
        $lines = [
            'User Profile',
            '============',
            'ID: ' . (string)($row['id'] ?? ''),
            'Account: ' . (string)($row['account'] ?? ''),
            'Name: ' . (string)($row['name'] ?? ''),
            'Gender: ' . (string)($row['genderName'] ?? ($row['gender'] ?? '')),
            'Phone: ' . (string)($row['phone'] ?? ''),
            'Email: ' . (string)($row['email'] ?? ''),
            'Organization: ' . (string)($row['orgName'] ?? ''),
            'Position: ' . (string)($row['positionName'] ?? ''),
            'Status: ' . (string)($row['userStatus'] ?? ''),
            'Entry Date: ' . (string)($row['entryDate'] ?? ''),
            'Employee No: ' . (string)($row['empNo'] ?? ''),
            'Company Employee ID: ' . (string)($row['companyEmployeeId'] ?? ''),
            'Native Place: ' . (string)($row['nativePlace'] ?? ''),
            'Home Address: ' . (string)($row['homeAddress'] ?? ''),
            'Mailing Address: ' . (string)($row['mailingAddress'] ?? ''),
            'Education: ' . (string)($row['education'] ?? ''),
            'Degree: ' . (string)($row['degree'] ?? ''),
            'Job Title: ' . (string)($row['jobTitle'] ?? ''),
            'Export Time: ' . date('Y-m-d H:i:s'),
        ];

        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string)($row['name'] ?? $id));
        $safeName = trim((string)$safeName, '-');

        return [
            'filename' => ($bizScope ? 'biz-user-info-' : 'sys-user-info-') . ($safeName !== '' ? $safeName : $id) . '.txt',
            'contentType' => 'text/plain',
            'content' => implode("\n", $lines) . "\n",
        ];
    }

    /**
     * @return array<int, string>
     */
    public function ownRole(string $id, mixed $payload = []): array
    {
        $id = trim($id);
        if ($id === '') {
            return [];
        }
        if ($this->detail($id, $payload) === null) {
            return [];
        }

        return array_values(array_map('strval', Db::name('sys_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', self::USER_HAS_ROLE)
            ->column('TARGET_ID')));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function setUserStatus(array $input, mixed $payload, string $status, bool $bizScope = false): array
    {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }
        if (!in_array($status, [self::USER_STATUS_ENABLE, self::USER_STATUS_DISABLED], true)) {
            throw new RuntimeException('invalid userStatus', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $user = $this->activeUserRow($id);
        $this->ensureUserStatusAllowed($payload, $user, $bizScope, $status);
        $this->ensureTenantCompatible($payload, $user);

        Db::name('sys_user')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update(['USER_STATUS' => $status]);

        return [
            'id' => $id,
            'userStatus' => $status,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function resetPassword(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $user = $this->activeUserRow($id);
        $this->ensureResetPasswordAllowed($payload, $user, $bizScope);
        $this->ensureTenantCompatible($payload, $user);

        Db::name('sys_user')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update(['PASSWORD' => $this->defaultPasswordHash()]);

        return ['id' => $id];
    }

    /**
     * @param array<int, mixed> $ids
     * @param array<string, mixed>|mixed $payload
     */
    public function deleteUsers(array $ids, mixed $payload = [], bool $bizScope = false): array
    {
        $idList = $this->idInputList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $users = $this->activeUserRows($idList);
        $this->ensureDeleteUsersAllowed($payload, $users, $bizScope);
        foreach ($users as $user) {
            $this->ensureTenantCompatible($payload, $user);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $this->clearUserDirectorReferences($idList, $payload);

            $updated = Db::name('sys_user')
                ->whereIn('ID', $idList)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $this->payloadUserId($payload) !== '' ? $this->payloadUserId($payload) : null,
                ]);

            return [
                'ids' => $idList,
                'count' => $updated,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function addUser(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->userWriteData($input);
        $org = $this->assertUserWriteReferences($data, null);
        $this->assertUniqueUserFields($data, null);
        $this->ensureUserWriteAllowed($payload, $data, $bizScope, 'add', null);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $id = $this->newId();
        $data = array_merge($data, [
            'ID' => $id,
            'PASSWORD' => $this->defaultPasswordHash(),
            'USER_STATUS' => self::USER_STATUS_ENABLE,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
            'TENANT_ID' => $this->tenantIdForWrite($payload, $org),
            'BANK_NAME' => (string)($data['BANK_NAME'] ?? ''),
            'BANK_ACCOUNT' => (string)($data['BANK_ACCOUNT'] ?? ''),
            'BASIC_SALARY' => $data['BASIC_SALARY'] ?? '0.00',
        ]);

        if (trim((string)($data['AVATAR'] ?? '')) === '') {
            $data['AVATAR'] = $this->defaultAvatar((string)$data['NAME']);
        }
        if (trim((string)($data['COMPANY_EMPLOYEE_ID'] ?? '')) === '') {
            $data['COMPANY_EMPLOYEE_ID'] = $this->nextCompanyEmployeeId($org);
        }

        $data = $this->sensitiveFields->encodeRow('sys_user', $data);
        Db::name('sys_user')->insert($data);

        return [
            'id' => $id,
            'detail' => $this->detail($id),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function editUser(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $id = trim((string)($input['id'] ?? $input['ID'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $user = $this->activeUserRow($id);
        $data = $this->userWriteData($input);
        $org = $this->assertUserWriteReferences($data, $user);
        $this->assertUniqueUserFields($data, $id);
        $this->ensureUserWriteAllowed($payload, $data, $bizScope, 'edit', $user);
        $this->ensureTenantCompatible($payload, $user);
        $this->ensureOrgTenantCompatibleWithUser($org, $user);

        $oldAccount = strtolower(trim((string)($user['ACCOUNT'] ?? '')));
        $newAccount = strtolower(trim((string)($data['ACCOUNT'] ?? '')));
        if (in_array($oldAccount, ['superadmin', 'bizadmin', 'tenantadmin'], true) && $oldAccount !== $newAccount) {
            throw new RuntimeException('built-in user account cannot be changed', 403);
        }

        $operatorId = $this->payloadUserId($payload);
        $data['UPDATE_TIME'] = date('Y-m-d H:i:s');
        $data['UPDATE_USER'] = $operatorId !== '' ? $operatorId : null;
        unset(
            $data['ID'],
            $data['PASSWORD'],
            $data['USER_STATUS'],
            $data['DELETE_FLAG'],
            $data['CREATE_TIME'],
            $data['CREATE_USER'],
            $data['TENANT_ID']
        );
        $data = $this->sensitiveFields->encodeRow('sys_user', $data);

        Db::name('sys_user')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($data);

        return [
            'id' => $id,
            'detail' => $this->detail($id),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function grantRole(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        if (!array_key_exists('roleIdList', $input) && !array_key_exists('roleIds', $input) && !array_key_exists('ids', $input)) {
            throw new RuntimeException('missing roleIdList', 400);
        }

        $roleIds = $this->roleIdList($input['roleIdList'] ?? $input['roleIds'] ?? $input['ids'] ?? []);
        $payload = is_array($payload) ? $payload : [];
        $user = $this->activeUserRow($id);

        $this->ensureGrantRoleAllowed($payload, $user, $bizScope);
        $this->ensureTenantCompatible($payload, $user);
        $this->assertActiveRoles($roleIds, (string)($user['TENANT_ID'] ?? ''), $payload);

        return Db::transaction(function () use ($id, $roleIds): array {
            Db::name('sys_relation')
                ->where('OBJECT_ID', $id)
                ->where('CATEGORY', self::USER_HAS_ROLE)
                ->delete();

            $rows = [];
            foreach ($roleIds as $roleId) {
                $rows[] = [
                    'ID' => $this->newId(),
                    'OBJECT_ID' => $id,
                    'TARGET_ID' => $roleId,
                    'CATEGORY' => self::USER_HAS_ROLE,
                    'EXT_JSON' => null,
                ];
            }

            if ($rows !== []) {
                Db::name('sys_relation')->insertAll($rows);
            }

            return [
                'id' => $id,
                'roleIdList' => $roleIds,
                'count' => count($roleIds),
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function grantResource(array $input, mixed $payload = []): array
    {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        if (!array_key_exists('grantInfoList', $input)) {
            throw new RuntimeException('missing grantInfoList', 400);
        }

        $grantInfoList = $this->resourceGrantInfoList($input['grantInfoList']);
        $payload = is_array($payload) ? $payload : [];
        $user = $this->activeUserRow($id);

        if (!$this->isAdminCompatible($payload) && !$this->hasGrantResourcePermission($payload)) {
            throw new RuntimeException('当前账号没有用户资源授权权限', 403);
        }

        $this->ensureTenantCompatible($payload, $user);
        $this->assertActiveResourceGrantInfo($grantInfoList);
        $this->assertSystemModuleResourceGrant($id, $grantInfoList);

        return Db::transaction(function () use ($id, $grantInfoList): array {
            Db::name('sys_relation')
                ->where('OBJECT_ID', $id)
                ->where('CATEGORY', self::USER_HAS_RESOURCE)
                ->delete();

            $rows = [];
            foreach ($grantInfoList as $grantInfo) {
                $extJson = json_encode($grantInfo, JSON_UNESCAPED_UNICODE);
                $rows[] = [
                    'ID' => $this->newId(),
                    'OBJECT_ID' => $id,
                    'TARGET_ID' => $grantInfo['menuId'],
                    'CATEGORY' => self::USER_HAS_RESOURCE,
                    'EXT_JSON' => $extJson === false ? '{}' : $extJson,
                ];
            }

            if ($rows !== []) {
                Db::name('sys_relation')->insertAll($rows);
            }

            return [
                'id' => $id,
                'grantInfoList' => $grantInfoList,
                'count' => count($grantInfoList),
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function grantPermission(array $input, mixed $payload = []): array
    {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        if (!array_key_exists('grantInfoList', $input)) {
            throw new RuntimeException('missing grantInfoList', 400);
        }

        $grantInfoList = $this->permissionGrantInfoList($input['grantInfoList']);
        $payload = is_array($payload) ? $payload : [];
        $user = $this->activeUserRow($id);

        if (!$this->isAdminCompatible($payload) && !$this->hasGrantPermissionPermission($payload)) {
            throw new RuntimeException('permission denied', 403);
        }

        $this->ensureTenantCompatible($payload, $user);
        $this->assertActivePermissionScopeOrgs(
            $grantInfoList,
            (string)($user['TENANT_ID'] ?? ''),
            $payload
        );

        return Db::transaction(function () use ($id, $grantInfoList): array {
            Db::name('sys_relation')
                ->where('OBJECT_ID', $id)
                ->where('CATEGORY', self::USER_HAS_PERMISSION)
                ->delete();

            $rows = [];
            foreach ($grantInfoList as $grantInfo) {
                $extJson = json_encode($grantInfo, JSON_UNESCAPED_UNICODE);
                $rows[] = [
                    'ID' => $this->newId(),
                    'OBJECT_ID' => $id,
                    'TARGET_ID' => $grantInfo['apiUrl'],
                    'CATEGORY' => self::USER_HAS_PERMISSION,
                    'EXT_JSON' => $extJson === false ? '{}' : $extJson,
                ];
            }

            if ($rows !== []) {
                Db::name('sys_relation')->insertAll($rows);
            }

            return [
                'id' => $id,
                'grantInfoList' => $grantInfoList,
                'count' => count($grantInfoList),
            ];
        });
    }

    public function ownResource(string $id, mixed $payload = []): array
    {
        if ($this->detail($id, $payload) === null) {
            throw new RuntimeException('user not found', 404);
        }

        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::USER_HAS_RESOURCE, 'menuId'),
        ];
    }

    public function ownPermission(string $id, mixed $payload = []): array
    {
        if ($this->detail($id, $payload) === null) {
            throw new RuntimeException('user not found', 404);
        }

        return [
            'id' => $id,
            'grantInfoList' => $this->grantInfoList($id, self::USER_HAS_PERMISSION, 'apiUrl'),
        ];
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getUserListByIdList(array $ids, mixed $payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $tenantId = $this->userIdListTenantId($payload);

        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }
        if (count($ids) > self::USER_ID_LIST_MAX) {
            throw new RuntimeException('too many user ids', 400);
        }

        $query = SysUser::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids);
        if ($tenantId !== null) {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field(self::USER_ID_LIST_FIELDS)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->selectorUserRows($rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getOrgListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = SysOrg::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => array_merge($row, [
            'id' => $row['ID'] ?? null,
            'value' => $row['ID'] ?? null,
            'label' => $row['NAME'] ?? $row['CODE'] ?? null,
            'title' => $row['NAME'] ?? $row['CODE'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
        ]), $rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getPositionListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = SysPosition::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => array_merge($row, [
            'id' => $row['ID'] ?? null,
            'value' => $row['ID'] ?? null,
            'label' => $row['NAME'] ?? $row['CODE'] ?? null,
            'title' => $row['NAME'] ?? $row['CODE'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
        ]), $rows);
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function getRoleListByIdList(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = SysRole::where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $ids)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => array_merge($row, [
            'id' => $row['ID'] ?? null,
            'value' => $row['ID'] ?? null,
            'label' => $row['NAME'] ?? $row['CODE'] ?? null,
            'title' => $row['NAME'] ?? $row['CODE'] ?? null,
            'name' => $row['NAME'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
        ]), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function userSelector(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $rows = $this->baseQuery($filters)
            ->field(self::USER_SELECTOR_FIELDS)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $records = $this->selectorUserRows($rows);

        return $this->pageResult($records, $total, $page, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function roleSelector(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        [$page, $limit] = $this->pagination($filters);
        $total = $this->roleQuery($filters)->count();
        $rows = $this->roleQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $records = array_map(static function (array $row): array {
            return [
                'id' => $row['ID'] ?? null,
                'value' => $row['ID'] ?? null,
                'label' => $row['NAME'] ?? $row['CODE'] ?? null,
                'title' => $row['NAME'] ?? $row['CODE'] ?? null,
                'name' => $row['NAME'] ?? null,
                'code' => $row['CODE'] ?? null,
                'category' => $row['CATEGORY'] ?? null,
                'orgId' => $row['ORG_ID'] ?? null,
                'sortCode' => $row['SORT_CODE'] ?? null,
            ];
        }, $rows);

        return $this->pageResult($records, $total, $page, $limit);
    }

    public function getAvatarById(string $id): array
    {
        $user = $this->detail($id);

        return [
            'id' => $id,
            'avatar' => $user['AVATAR'] ?? null,
        ];
    }

    public function loginWorkbench(string $userId): string
    {
        if (!$this->detail($userId)) {
            throw new RuntimeException('user not found', 404);
        }

        $relation = SysRelation::where('OBJECT_ID', $userId)
            ->where('CATEGORY', self::WORKBENCH_CATEGORY)
            ->find();

        $workbench = $relation ? trim((string)$relation->getAttr('EXT_JSON')) : '';
        if ($workbench !== '') {
            return $workbench;
        }

        return $this->defaultWorkbenchData();
    }

    public function processConfig(string $userId): array
    {
        $row = SysUserProcessConfig::where('CREATE_USER', $userId)
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->order(['UPDATE_TIME' => 'desc', 'CREATE_TIME' => 'desc', 'ID' => 'desc'])
            ->find();

        if (!$row) {
            $config = $this->defaultProcessConfig();
            $configJson = json_encode(['config' => $config], JSON_UNESCAPED_UNICODE);

            return [
                'id' => null,
                'configJson' => $configJson,
                'deleteFlag' => self::NOT_DELETE,
                'createUser' => $userId,
                'config' => $config,
            ];
        }

        $data = $row->toArray();
        return [
            'id' => $data['ID'] ?? null,
            'configJson' => $data['CONFIG_JSON'] ?? null,
            'deleteFlag' => $data['DELETE_FLAG'] ?? null,
            'createTime' => $data['CREATE_TIME'] ?? null,
            'createUser' => $data['CREATE_USER'] ?? null,
            'updateTime' => $data['UPDATE_TIME'] ?? null,
            'updateUser' => $data['UPDATE_USER'] ?? null,
            'tenantId' => $data['TENANT_ID'] ?? null,
            'version' => $data['VERSION'] ?? null,
            'config' => $this->decodeProcessConfig((string)($data['CONFIG_JSON'] ?? '')),
        ];
    }

    public function loginUnreadMessagePage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $relations = $this->messageRelationsForUser($userId);
        $messageIds = array_keys($relations);

        if ($messageIds === []) {
            return [
                'records' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
            ];
        }

        $total = $this->messageQuery($messageIds, $filters)->count();
        $rows = $this->messageQuery($messageIds, $filters)
            ->order('CREATE_TIME', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $records = array_map(fn (array $row): array => $this->messageRow(
            $row,
            $relations[(string)($row['ID'] ?? '')] ?? null
        ), $rows);

        usort($records, static function (array $left, array $right): int {
            return (int)($left['read'] ?? false) <=> (int)($right['read'] ?? false);
        });

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loginUnreadMessageList(string $userId, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $relations = array_filter(
            $this->messageRelationsForUser($userId),
            fn (array $relation): bool => $this->relationReadStatus($relation) === false
        );
        $messageIds = array_keys($relations);

        if ($messageIds === []) {
            return [];
        }

        $rows = $this->messageQuery($messageIds, [])
            ->order('CREATE_TIME', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->messageRow(
            $row,
            $relations[(string)($row['ID'] ?? '')] ?? null
        ), $rows);
    }

    public function loginUnreadMessageDetail(string $userId, string $id): ?array
    {
        $ownRelation = Db::name('dev_relation')
            ->where('OBJECT_ID', $id)
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->find();

        if (!$ownRelation) {
            return null;
        }

        $message = Db::name('dev_message')
            ->where('ID', $id)
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->find();

        if (!$message) {
            return null;
        }

        $ownRelation = $this->markMessageRead($ownRelation, $userId, $id);

        $receiveRelations = Db::name('dev_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->select()
            ->toArray();
        $receiveUserIds = array_values(array_filter(array_map(static fn (array $relation): string => (string)($relation['TARGET_ID'] ?? ''), $receiveRelations)));
        $userNames = $receiveUserIds === []
            ? []
            : SysUser::whereIn('ID', $receiveUserIds)->column('NAME', 'ID');

        $detail = $this->messageRow($message, $ownRelation);
        $detail['receiveInfoList'] = array_map(function (array $relation) use ($userNames): array {
            $receiveUserId = (string)($relation['TARGET_ID'] ?? '');

            return [
                'receiveUserId' => $receiveUserId,
                'receiveUserName' => $userNames[$receiveUserId] ?? 'unknown user',
                'read' => $this->relationReadStatus($relation) ?? false,
            ];
        }, $receiveRelations);

        return $detail;
    }

    public function markAllMessagesRead(string $userId): void
    {
        $relations = Db::name('dev_relation')
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->select()
            ->toArray();

        foreach ($relations as $relation) {
            if ($this->relationReadStatus($relation) === true) {
                continue;
            }

            $extJson = $this->messageRelationExtWithRead($relation);
            Db::name('dev_relation')
                ->where('ID', (string)($relation['ID'] ?? ''))
                ->where('TARGET_ID', $userId)
                ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
                ->update(['EXT_JSON' => $extJson]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loginOrgTree(string $userId): array
    {
        $user = $this->detail($userId);
        if (!$user || empty($user['ORG_ID'])) {
            return [];
        }

        return $this->orgService->tree(['tenantId' => $user['TENANT_ID'] ?? null]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loginPositionInfo(string $userId): array
    {
        $user = $this->detail($userId);
        if (!$user) {
            return [];
        }

        $positionIds = [];
        if (!empty($user['POSITION_ID'])) {
            $positionIds[] = (string)$user['POSITION_ID'];
        }

        $extraPositions = json_decode((string)($user['POSITION_JSON'] ?? ''), true);
        if (is_array($extraPositions)) {
            foreach ($extraPositions as $position) {
                if (is_array($position) && !empty($position['id'])) {
                    $positionIds[] = (string)$position['id'];
                }
            }
        }

        return $this->getPositionListByIdList($positionIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function activeUserRow(string $id): array
    {
        $row = Db::name('sys_user')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('user not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeUserRows(array $ids): array
    {
        $rows = Db::name('sys_user')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(string)($row['ID'] ?? '')] = $row;
        }

        $missing = array_values(array_diff($ids, array_keys($rowsById)));
        if ($missing !== []) {
            throw new RuntimeException('user not found', 404);
        }

        return array_values(array_intersect_key($rowsById, array_flip($ids)));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function userWriteData(array $input): array
    {
        $data = [];
        foreach ($this->userWriteFieldMap() as $camel => $column) {
            $hasCamel = array_key_exists($camel, $input);
            $hasColumn = array_key_exists($column, $input);
            if (!$hasCamel && !$hasColumn) {
                continue;
            }

            $value = $hasCamel ? $input[$camel] : $input[$column];
            if ($this->isUserJsonColumn($column)) {
                $data[$column] = $this->jsonTextValue($value);
            } elseif ($column === 'SORT_CODE') {
                $data[$column] = $value === '' || $value === null ? null : (int)$value;
            } elseif ($column === 'BASIC_SALARY') {
                $salary = $value === '' || $value === null ? '0.00' : (string)$value;
                if (!is_numeric($salary) || (float)$salary < 0) {
                    throw new RuntimeException('invalid basicSalary', 400);
                }
                $data[$column] = $salary;
            } elseif (in_array($column, ['BANK_NAME', 'BANK_ACCOUNT'], true)) {
                $data[$column] = $value === null ? '' : trim((string)$value);
            } else {
                $data[$column] = is_array($value) ? $this->jsonTextValue($value) : $this->nullableString($value);
            }
        }

        foreach (['ACCOUNT' => 'account', 'NAME' => 'name', 'ORG_ID' => 'orgId', 'POSITION_ID' => 'positionId'] as $column => $name) {
            if (trim((string)($data[$column] ?? '')) === '') {
                throw new RuntimeException("missing {$name}", 400);
            }
        }

        $data['ACCOUNT'] = trim((string)$data['ACCOUNT']);
        $data['NAME'] = trim((string)$data['NAME']);
        $data['ORG_ID'] = trim((string)$data['ORG_ID']);
        $data['POSITION_ID'] = trim((string)$data['POSITION_ID']);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function userWriteFieldMap(): array
    {
        return [
            'account' => 'ACCOUNT',
            'name' => 'NAME',
            'avatar' => 'AVATAR',
            'signature' => 'SIGNATURE',
            'nickname' => 'NICKNAME',
            'gender' => 'GENDER',
            'age' => 'AGE',
            'birthday' => 'BIRTHDAY',
            'nation' => 'NATION',
            'nativePlace' => 'NATIVE_PLACE',
            'homeAddress' => 'HOME_ADDRESS',
            'mailingAddress' => 'MAILING_ADDRESS',
            'idCardType' => 'ID_CARD_TYPE',
            'idCardNumber' => 'ID_CARD_NUMBER',
            'cultureLevel' => 'CULTURE_LEVEL',
            'politicalOutlook' => 'POLITICAL_OUTLOOK',
            'college' => 'COLLEGE',
            'education' => 'EDUCATION',
            'eduLength' => 'EDU_LENGTH',
            'degree' => 'DEGREE',
            'phone' => 'PHONE',
            'email' => 'EMAIL',
            'homeTel' => 'HOME_TEL',
            'officeTel' => 'OFFICE_TEL',
            'emergencyContact' => 'EMERGENCY_CONTACT',
            'emergencyPhone' => 'EMERGENCY_PHONE',
            'emergencyAddress' => 'EMERGENCY_ADDRESS',
            'empNo' => 'EMP_NO',
            'entryDate' => 'ENTRY_DATE',
            'orgId' => 'ORG_ID',
            'positionId' => 'POSITION_ID',
            'positionLevel' => 'POSITION_LEVEL',
            'directorId' => 'DIRECTOR_ID',
            'positionJson' => 'POSITION_JSON',
            'sortCode' => 'SORT_CODE',
            'extJson' => 'EXT_JSON',
            'bankName' => 'BANK_NAME',
            'bankAccount' => 'BANK_ACCOUNT',
            'basicSalary' => 'BASIC_SALARY',
            'workStartDate' => 'WORK_START_DATE',
            'healthStatus' => 'HEALTH_STATUS',
            'specialtySkills' => 'SPECIALTY_SKILLS',
            'onJobEducationJson' => 'ON_JOB_EDUCATION_JSON',
            'fullTimeEducationJson' => 'FULL_TIME_EDUCATION_JSON',
            'jobTitle' => 'JOB_TITLE',
            'socialAppointments' => 'SOCIAL_APPOINTMENTS',
            'departmentAttribute' => 'DEPARTMENT_ATTRIBUTE',
            'personalInformation' => 'PERSONAL_INFORMATION',
            'mainStudyAndWorkExperience' => 'MAIN_STUDY_AND_WORK_EXPERIENCE',
            'awardsAndAchievements' => 'AWARDS_AND_ACHIEVEMENTS',
            'familyMembersAndSocialRelationshipsJson' => 'FAMILY_MEMBERS_AND_SOCIAL_RELATIONSHIPS_JSON',
            'partyOrganizationOpinion' => 'PARTY_ORGANIZATION_OPINION',
            'entryMethod' => 'ENTRY_METHOD',
            'companyEmployeeId' => 'COMPANY_EMPLOYEE_ID',
        ];
    }

    private function isUserJsonColumn(string $column): bool
    {
        return in_array($column, [
            'POSITION_JSON',
            'EXT_JSON',
            'ON_JOB_EDUCATION_JSON',
            'FULL_TIME_EDUCATION_JSON',
            'FAMILY_MEMBERS_AND_SOCIAL_RELATIONSHIPS_JSON',
        ], true);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function jsonTextValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);

            return $json === false ? null : $json;
        }

        return trim((string)$value) === '' ? null : (string)$value;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existingUser
     * @return array<string, mixed>
     */
    private function assertUserWriteReferences(array $data, ?array $existingUser): array
    {
        $org = $this->activeOrgRow((string)$data['ORG_ID']);
        $position = $this->activePositionRow((string)$data['POSITION_ID']);
        $positionOrgId = trim((string)($position['ORG_ID'] ?? ''));
        if ($positionOrgId !== '' && $positionOrgId !== (string)$data['ORG_ID']) {
            throw new RuntimeException('position does not belong to org', 400);
        }

        $directorId = trim((string)($data['DIRECTOR_ID'] ?? ''));
        if ($directorId !== '') {
            $director = $this->activeUserRow($directorId);
            $this->ensureOrgTenantCompatibleWithUser($org, $director);
        }

        $this->assertPositionJsonReferences((string)($data['POSITION_JSON'] ?? ''));
        if ($existingUser !== null) {
            $this->ensureOrgTenantCompatibleWithUser($org, $existingUser);
        }

        return $org;
    }

    /**
     * @return array<string, mixed>
     */
    private function activeOrgRow(string $id): array
    {
        $row = Db::name('sys_org')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('org not found', 404);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function activePositionRow(string $id): array
    {
        $row = Db::name('sys_position')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('position not found', 404);
        }

        return $row;
    }

    private function assertPositionJsonReferences(string $json): void
    {
        $json = trim($json);
        if ($json === '') {
            return;
        }

        $items = json_decode($json, true);
        if (!is_array($items)) {
            throw new RuntimeException('invalid positionJson', 400);
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid positionJson', 400);
            }
            $orgId = trim((string)($item['orgId'] ?? $item['ORG_ID'] ?? ''));
            $positionId = trim((string)($item['positionId'] ?? $item['POSITION_ID'] ?? ''));
            if ($orgId === '' || $positionId === '') {
                throw new RuntimeException('invalid positionJson', 400);
            }

            $this->activeOrgRow($orgId);
            $position = $this->activePositionRow($positionId);
            $positionOrgId = trim((string)($position['ORG_ID'] ?? ''));
            if ($positionOrgId !== '' && $positionOrgId !== $orgId) {
                throw new RuntimeException('positionJson position does not belong to org', 400);
            }

            $directorId = trim((string)($item['directorId'] ?? $item['DIRECTOR_ID'] ?? ''));
            if ($directorId !== '') {
                $this->activeUserRow($directorId);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertUniqueUserFields(array $data, ?string $excludeId): void
    {
        $account = trim((string)($data['ACCOUNT'] ?? ''));
        $this->assertUniqueUserColumn('ACCOUNT', $account, $excludeId, 'account already exists');

        $phone = trim((string)($data['PHONE'] ?? ''));
        if ($phone !== '') {
            if (!preg_match('/^1[3-9][0-9]{9}$/', $phone)) {
                throw new RuntimeException('invalid phone', 400);
            }
            $this->assertUniqueUserColumn('PHONE', $phone, $excludeId, 'phone already exists');
        }

        $email = trim((string)($data['EMAIL'] ?? ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('invalid email', 400);
            }
            $this->assertUniqueUserColumn('EMAIL', $email, $excludeId, 'email already exists');
        }
    }

    private function assertUniqueUserColumn(string $column, string $value, ?string $excludeId, string $message): void
    {
        if ($value === '') {
            return;
        }

        $query = Db::name('sys_user')
            ->where(
                $column,
                $column === 'PHONE'
                    ? $this->sensitiveFields->lookupValue('sys_user', 'PHONE', $value)
                    : $value
            )
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($excludeId !== null && $excludeId !== '') {
            $query->where('ID', '<>', $excludeId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException($message, 400);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existingUser
     */
    private function ensureUserWriteAllowed(array $payload, array $data, bool $bizScope, string $action, ?array $existingUser): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasUserWritePermission($payload, $bizScope, $action)) {
            throw new RuntimeException('permission denied', 403);
        }

        $targetOrgId = trim((string)($data['ORG_ID'] ?? ''));
        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $targetOrgId !== '' && in_array($targetOrgId, $scopeOrgIds, true)) {
            return;
        }

        if ($action === 'edit' && $existingUser !== null && $this->payloadUserId($payload) === (string)($existingUser['ID'] ?? '')) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasUserWritePermission(array $payload, bool $bizScope, string $action): bool
    {
        $action = strtolower($action) === 'add' ? 'add' : 'edit';
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ["/biz/user/{$action}", "bizuser{$action}"]
            : ["/sys/user/{$action}", "sysuser{$action}"];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $org
     */
    private function tenantIdForWrite(array $payload, array $org): string
    {
        $orgTenantId = trim((string)($org['TENANT_ID'] ?? ''));
        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($payloadTenantId !== '' && $orgTenantId !== '' && $payloadTenantId !== $orgTenantId && !TenantScope::canCrossTenant($payload)) {
            throw new RuntimeException('permission denied', 403);
        }

        return $orgTenantId !== '' ? $orgTenantId : ($payloadTenantId !== '' ? $payloadTenantId : '0');
    }

    /**
     * @param array<string, mixed> $org
     * @param array<string, mixed> $user
     */
    private function ensureOrgTenantCompatibleWithUser(array $org, array $user): void
    {
        $orgTenantId = trim((string)($org['TENANT_ID'] ?? ''));
        $userTenantId = trim((string)($user['TENANT_ID'] ?? ''));
        if ($orgTenantId !== '' && $userTenantId !== '' && $orgTenantId !== $userTenantId) {
            throw new RuntimeException('tenant mismatch', 403);
        }
    }

    /**
     * @param array<string, mixed> $org
     */
    private function nextCompanyEmployeeId(array $org): string
    {
        $orgCode = strtoupper(trim((string)($org['CODE'] ?? '')));
        $orgCode = preg_replace('/[^A-Z0-9]/', '', $orgCode) ?: '';
        if ($orgCode === '') {
            $orgCode = substr((string)($org['ID'] ?? 'ORG'), -6);
        }

        return date('Ymd') . '-' . $orgCode . '-' . ((int)Db::name('sys_user')->count() + 1);
    }

    private function defaultAvatar(string $name): string
    {
        $label = trim($name);
        if (function_exists('mb_substr')) {
            $label = mb_substr($label, 0, 1, 'UTF-8');
        } else {
            $label = strtoupper(substr($label, 0, 1));
        }
        if ($label === '') {
            $label = 'U';
        }

        $text = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
            . '<rect width="96" height="96" rx="48" fill="#2563eb"/>'
            . '<text x="48" y="57" text-anchor="middle" font-family="Arial, sans-serif" font-size="40" fill="#fff">'
            . $text
            . '</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * @param array<int, string> $roleIds
     */
    private function assertActiveRoles(array $roleIds, string $tenantId, array $payload): void
    {
        if ($roleIds === []) {
            return;
        }

        $query = Db::name('sys_role')
            ->whereIn('ID', $roleIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '' && !TenantScope::canCrossTenant($payload)) {
            $query->where('TENANT_ID', $tenantId);
        }

        $validIds = array_values(array_filter(array_map('strval', $query->column('ID'))));
        $missing = array_values(array_diff($roleIds, $validIds));
        if ($missing !== []) {
            throw new RuntimeException('role not found or tenant mismatch', 404);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     */
    private function ensureGrantRoleAllowed(array $payload, array $user, bool $bizScope): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasGrantRolePermission($payload, $bizScope)) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $targetOrgId = trim((string)($user['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $targetOrgId !== '' && in_array($targetOrgId, $scopeOrgIds, true)) {
            return;
        }

        if ($this->payloadUserId($payload) === (string)($user['ID'] ?? '')) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     */
    private function ensureUserStatusAllowed(array $payload, array $user, bool $bizScope, string $status): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasUserStatusPermission($payload, $bizScope, $status)) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $targetOrgId = trim((string)($user['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $targetOrgId !== '' && in_array($targetOrgId, $scopeOrgIds, true)) {
            return;
        }

        if ($this->payloadUserId($payload) === (string)($user['ID'] ?? '')) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     */
    private function ensureResetPasswordAllowed(array $payload, array $user, bool $bizScope): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasResetPasswordPermission($payload, $bizScope)) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $targetOrgId = trim((string)($user['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $targetOrgId !== '' && in_array($targetOrgId, $scopeOrgIds, true)) {
            return;
        }

        if ($this->payloadUserId($payload) === (string)($user['ID'] ?? '')) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $users
     */
    private function ensureDeleteUsersAllowed(array $payload, array $users, bool $bizScope): void
    {
        foreach ($users as $user) {
            $account = strtolower(trim((string)($user['ACCOUNT'] ?? '')));
            if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
                throw new RuntimeException('built-in user cannot be deleted', 403);
            }
        }

        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasDeleteUserPermission($payload, $bizScope)) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            foreach ($users as $user) {
                $targetOrgId = trim((string)($user['ORG_ID'] ?? ''));
                if ($targetOrgId === '' || !in_array($targetOrgId, $scopeOrgIds, true)) {
                    throw new RuntimeException('permission denied', 403);
                }
            }

            return;
        }

        if (count($users) === 1 && $this->payloadUserId($payload) === (string)($users[0]['ID'] ?? '')) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureExportAllowed(array $payload, bool $bizScope): void
    {
        if ($this->isAdminCompatible($payload)) {
            return;
        }

        if ($this->hasExportUserPermission($payload, $bizScope)) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureImportAllowed(array $payload): void
    {
        if ($this->isAdminCompatible($payload)) {
            return;
        }

        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = ['/sys/user/import', 'sysuserimport'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return;
                }
            }
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     */
    private function ensureExportUserScopeAllowed(array $payload, array $user): void
    {
        if ($this->isAdminCompatible($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $targetOrgId = trim((string)($user['ORG_ID'] ?? ''));
        if ($scopeOrgIds !== [] && $targetOrgId !== '' && in_array($targetOrgId, $scopeOrgIds, true)) {
            return;
        }

        if ($scopeOrgIds === [] && $this->payloadUserId($payload) === (string)($user['ID'] ?? '')) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     */
    private function ensureTenantCompatible(array $payload, array $user): void
    {
        TenantScope::assertCompatible($payload, $user['TENANT_ID'] ?? null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasGrantRolePermission(array $payload, bool $bizScope): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ['/biz/user/grantrole', 'bizusergrantrole']
            : ['/sys/user/grantrole', 'sysusergrantrole'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace([':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasUserStatusPermission(array $payload, bool $bizScope, string $status): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $action = $status === self::USER_STATUS_ENABLE ? 'enableuser' : 'disableuser';
        $needles = $bizScope
            ? ["/biz/user/{$action}", "bizuser{$action}", 'bizuserupdatastatus']
            : ["/sys/user/{$action}", "sysuser{$action}", 'sysuserupdatestatus'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasResetPasswordPermission(array $payload, bool $bizScope): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ['/biz/user/resetpassword', 'bizuserresetpassword', 'bizuserpwdreset']
            : ['/sys/user/resetpassword', 'sysuserresetpassword', 'sysuserpwdreset'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasDeleteUserPermission(array $payload, bool $bizScope): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ['/biz/user/delete', 'bizuserdelete']
            : ['/sys/user/delete', 'sysuserdelete'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasExportUserPermission(array $payload, bool $bizScope): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ['/biz/user/export', '/biz/user/exportuserinfo', 'bizuserexport', 'bizuserexportuserinfo']
            : ['/sys/user/export', '/sys/user/exportuserinfo', '/sys/user/downloadimportusertemplate', 'sysuserexport', 'sysuserexportuserinfo'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasGrantPermissionPermission(array $payload): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = ['/sys/user/grantpermission', 'sysusergrantpermission'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{apiUrl: string, scopeCategory: string, scopeDefineOrgIdList: array<int, string>}>
     */
    private function permissionGrantInfoList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $byApiUrl = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid grantInfoList', 400);
            }

            $apiUrl = trim((string)($item['apiUrl'] ?? $item['api_url'] ?? ''));
            if ($apiUrl === '') {
                throw new RuntimeException('missing apiUrl', 400);
            }

            $scopeCategory = trim((string)($item['scopeCategory'] ?? $item['scope_category'] ?? ''));
            if (!in_array($scopeCategory, self::SCOPE_CATEGORIES, true)) {
                throw new RuntimeException('invalid scopeCategory', 400);
            }

            if (!array_key_exists('scopeDefineOrgIdList', $item) && !array_key_exists('scope_define_org_id_list', $item)) {
                throw new RuntimeException('missing scopeDefineOrgIdList', 400);
            }

            $orgIds = $this->roleIdList($item['scopeDefineOrgIdList'] ?? $item['scope_define_org_id_list'] ?? []);
            $byApiUrl[$apiUrl] = [
                'apiUrl' => $apiUrl,
                'scopeCategory' => $scopeCategory,
                'scopeDefineOrgIdList' => $orgIds,
            ];
        }

        return array_values($byApiUrl);
    }

    /**
     * @param array<int, array{apiUrl: string, scopeCategory: string, scopeDefineOrgIdList: array<int, string>}> $grantInfoList
     */
    private function assertActivePermissionScopeOrgs(array $grantInfoList, string $tenantId, array $payload): void
    {
        $orgIds = [];
        foreach ($grantInfoList as $grantInfo) {
            $orgIds = array_merge($orgIds, $grantInfo['scopeDefineOrgIdList']);
        }
        $orgIds = array_values(array_unique($orgIds));
        if ($orgIds === []) {
            return;
        }

        $query = Db::name('sys_org')
            ->whereIn('ID', $orgIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if (!TenantScope::canCrossTenant($payload)) {
            $query->where('TENANT_ID', $tenantId);
        }
        $validIds = array_values(array_filter(array_map('strval', $query->column('ID'))));
        $missing = array_values(array_diff($orgIds, $validIds));
        if ($missing !== []) {
            throw new RuntimeException('scope org not found', 404);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasGrantResourcePermission(array $payload): bool
    {
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = ['/sys/user/grantresource', 'sysusergrantresource'];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isAdminCompatible(array $payload): bool
    {
        $account = strtolower(trim((string)($payload['account'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            return true;
        }

        foreach ($this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []) as $roleCode) {
            if (in_array(strtolower($roleCode), ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
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

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private function roleIdList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $id = trim((string)($item['id'] ?? $item['roleId'] ?? $item['value'] ?? $item['key'] ?? ''));
            } else {
                $id = trim((string)$item);
            }

            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function idInputList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $id = trim((string)($item['id'] ?? $item['userId'] ?? $item['value'] ?? $item['key'] ?? ''));
            } else {
                $id = trim((string)$item);
            }

            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, string> $userIds
     * @param array<string, mixed> $payload
     */
    private function clearUserDirectorReferences(array $userIds, array $payload): void
    {
        $updateUser = $this->payloadUserId($payload);
        $audit = [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $updateUser !== '' ? $updateUser : null,
        ];

        Db::name('sys_user')
            ->whereIn('DIRECTOR_ID', $userIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update(array_merge(['DIRECTOR_ID' => null], $audit));

        Db::name('sys_org')
            ->whereIn('DIRECTOR_ID', $userIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update(array_merge(['DIRECTOR_ID' => null], $audit));

        $positionRows = Db::name('sys_user')
            ->whereNotNull('POSITION_JSON')
            ->where('POSITION_JSON', '<>', '')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'POSITION_JSON'])
            ->select()
            ->toArray();

        foreach ($positionRows as $row) {
            $positionJson = (string)($row['POSITION_JSON'] ?? '');
            $decoded = json_decode($positionJson, true);
            if (!is_array($decoded)) {
                continue;
            }

            $changed = false;
            foreach ($decoded as &$position) {
                if (!is_array($position)) {
                    continue;
                }
                $directorId = trim((string)($position['directorId'] ?? ''));
                if ($directorId !== '' && in_array($directorId, $userIds, true)) {
                    unset($position['directorId']);
                    $changed = true;
                }
            }
            unset($position);

            if (!$changed) {
                continue;
            }

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            Db::name('sys_user')
                ->where('ID', (string)($row['ID'] ?? ''))
                ->update(array_merge([
                    'POSITION_JSON' => $encoded === false ? '[]' : $encoded,
                ], $audit));
        }
    }

    /**
     * @return array<int, array{menuId: string, buttonInfo: array<int, string>}>
     */
    private function resourceGrantInfoList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $byMenuId = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid grantInfoList', 400);
            }

            $menuId = trim((string)($item['menuId'] ?? $item['menu_id'] ?? $item['id'] ?? ''));
            if ($menuId === '') {
                throw new RuntimeException('missing menuId', 400);
            }

            if (!array_key_exists('buttonInfo', $item) && !array_key_exists('button_info', $item)) {
                throw new RuntimeException('missing buttonInfo', 400);
            }

            $buttonInfo = $this->roleIdList($item['buttonInfo'] ?? $item['button_info'] ?? []);
            $byMenuId[$menuId] = [
                'menuId' => $menuId,
                'buttonInfo' => array_values(array_unique(array_merge($byMenuId[$menuId]['buttonInfo'] ?? [], $buttonInfo))),
            ];
        }

        return array_values($byMenuId);
    }

    /**
     * @param array<int, array{menuId: string, buttonInfo: array<int, string>}> $grantInfoList
     */
    private function assertActiveResourceGrantInfo(array $grantInfoList): void
    {
        $menuIds = array_values(array_unique(array_map(static fn (array $grantInfo): string => $grantInfo['menuId'], $grantInfoList)));
        $buttonIds = [];
        foreach ($grantInfoList as $grantInfo) {
            $buttonIds = array_merge($buttonIds, $grantInfo['buttonInfo']);
        }
        $buttonIds = array_values(array_unique($buttonIds));

        $this->assertActiveResourceIds($menuIds, 'menu resource not found');
        $this->assertActiveResourceIds($buttonIds, 'button resource not found');
    }

    /**
     * @param array<int, string> $ids
     */
    private function assertActiveResourceIds(array $ids, string $message): void
    {
        if ($ids === []) {
            return;
        }

        $validIds = array_values(array_filter(array_map('strval', Db::name('sys_resource')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID'))));
        $missing = array_values(array_diff($ids, $validIds));
        if ($missing !== []) {
            throw new RuntimeException($message, 404);
        }
    }

    /**
     * @param array<int, array{menuId: string, buttonInfo: array<int, string>}> $grantInfoList
     */
    private function assertSystemModuleResourceGrant(string $userId, array $grantInfoList): void
    {
        $menuIds = array_values(array_unique(array_map(static fn (array $grantInfo): string => $grantInfo['menuId'], $grantInfoList)));
        if ($menuIds === []) {
            return;
        }

        $moduleIds = array_values(array_unique(array_filter(array_map('strval', Db::name('sys_resource')
            ->whereIn('ID', $menuIds)
            ->column('MODULE')))));
        if ($moduleIds === []) {
            return;
        }

        $moduleCodes = array_map('strtolower', array_filter(array_map('strval', Db::name('sys_resource')
            ->whereIn('ID', $moduleIds)
            ->column('CODE'))));
        if (!in_array('system', $moduleCodes, true)) {
            return;
        }

        if (!$this->targetHasSuperAdminRole($userId)) {
            throw new RuntimeException('只有超管角色用户可以授权系统模块资源', 403);
        }
    }

    private function targetHasSuperAdminRole(string $userId): bool
    {
        $roleIds = $this->ownRole($userId);
        if ($roleIds === []) {
            return false;
        }

        $roleCodes = Db::name('sys_role')
            ->whereIn('ID', $roleIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('CODE');
        foreach ($roleCodes as $roleCode) {
            if (strtolower((string)$roleCode) === 'superadmin') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
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
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function exportUserRows(array $filters, array $payload, bool $bizScope): array
    {
        $query = $this->baseQuery($filters);
        $ids = $this->idInputList($filters['userIds'] ?? $filters['ids'] ?? $filters['idList'] ?? []);
        if ($ids !== []) {
            $query->whereIn('ID', $ids);
        }

        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($payloadTenantId !== '' && !TenantScope::canCrossTenant($payload)) {
            $query->where('TENANT_ID', $payloadTenantId);
        }

        if ($bizScope && !$this->isAdminCompatible($payload)) {
            $scopeOrgIds = $this->scopeOrgIds($payload);
            if ($scopeOrgIds !== []) {
                $query->whereIn('ORG_ID', $scopeOrgIds);
            } else {
                $payloadUserId = $this->payloadUserId($payload);
                $payloadUserId === '' ? $query->whereRaw('1 = 0') : $query->where('ID', $payloadUserId);
            }
        }

        $rows = $query
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->limit(10000)
            ->select()
            ->toArray();

        return $this->sanitizeUserRows($rows);
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function csvContent(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('download failed', 500);
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

    private function baseQuery(array $filters)
    {
        $query = SysUser::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['account'])) {
            $query->whereLike('ACCOUNT', '%' . trim((string)$filters['account']) . '%');
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['phone'])) {
            $query->where(
                'PHONE',
                $this->sensitiveFields->lookupValue('sys_user', 'PHONE', trim((string)$filters['phone']))
            );
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
        }

        if (!empty($filters['positionId'])) {
            $query->where('POSITION_ID', (string)$filters['positionId']);
        }

        if (!empty($filters['userStatus'])) {
            $query->where('USER_STATUS', (string)$filters['userStatus']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    private function roleQuery(array $filters)
    {
        $query = SysRole::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['code'])) {
            $query->whereLike('CODE', '%' . trim((string)$filters['code']) . '%');
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeUserRows(array $rows): array
    {
        $orgNames = $this->orgNames($rows);
        $positionNames = $this->positionNames($rows);
        $genderLabels = $this->dictLabels('GENDER');

        return array_map(
            fn (array $row): array => $this->sanitizeUserRow($row, $orgNames, $positionNames, $genderLabels),
            $rows
        );
    }

    /**
     * Build selector-compatible aliases from a deliberately narrow projection.
     * Sensitive fields are never selected or passed through this path.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function selectorUserRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $id = $this->rowValue($row, 'ID', 'id');
            $account = $this->rowValue($row, 'ACCOUNT', 'account');
            $name = $this->rowValue($row, 'NAME', 'name');

            $result = array_merge($row, [
                'id' => $id,
                'orgId' => $this->rowValue($row, 'ORG_ID', 'orgId'),
                'avatar' => $this->rowValue($row, 'AVATAR', 'avatar'),
                'account' => $account,
                'name' => $name,
                'sortCode' => $this->rowValue($row, 'SORT_CODE', 'sortCode'),
                'value' => $id,
                'label' => $name ?? $account,
                'title' => $name ?? $account,
            ]);

            foreach ([
                'positionId' => 'POSITION_ID',
                'gender' => 'GENDER',
                'entryDate' => 'ENTRY_DATE',
            ] as $alias => $column) {
                if (array_key_exists($column, $row) || array_key_exists($alias, $row)) {
                    $result[$alias] = $this->rowValue($row, $column, $alias);
                }
            }

            return $result;
        }, $rows);
    }

    /**
     * Normal authenticated users are tenant-scoped. Platform super admins keep
     * the existing cross-tenant directory behavior.
     *
     * @param array<string, mixed> $payload
     */
    private function userIdListTenantId(array $payload): ?string
    {
        if ($payload === []) {
            throw new RuntimeException('permission denied', 403);
        }
        if (TenantScope::canCrossTenant($payload)) {
            return null;
        }

        $tenantId = TenantScope::tenantId($payload);
        if ($tenantId === '') {
            throw new RuntimeException('permission denied', 403);
        }

        return $tenantId;
    }

    /**
     * @param array<string, string> $orgNames
     * @param array<string, string> $positionNames
     * @param array<string, string> $genderLabels
     */
    private function sanitizeUserRow(array $row, array $orgNames = [], array $positionNames = [], array $genderLabels = []): array
    {
        $row = $this->sensitiveFields->decodeRow('sys_user', $row);
        unset($row['PASSWORD']);

        $orgId = $this->rowValue($row, 'ORG_ID', 'orgId');
        $positionId = $this->rowValue($row, 'POSITION_ID', 'positionId');
        $gender = $this->rowValue($row, 'GENDER', 'gender');

        return array_merge($row, [
            'id' => $this->rowValue($row, 'ID', 'id'),
            'avatar' => $this->rowValue($row, 'AVATAR', 'avatar'),
            'signature' => $this->rowValue($row, 'SIGNATURE', 'signature'),
            'account' => $this->rowValue($row, 'ACCOUNT', 'account'),
            'name' => $this->rowValue($row, 'NAME', 'name'),
            'nickname' => $this->rowValue($row, 'NICKNAME', 'nickname'),
            'gender' => $gender,
            'genderName' => $gender !== null ? ($genderLabels[(string)$gender] ?? $gender) : null,
            'age' => $this->rowValue($row, 'AGE', 'age'),
            'birthday' => $this->rowValue($row, 'BIRTHDAY', 'birthday'),
            'nation' => $this->rowValue($row, 'NATION', 'nation'),
            'nativePlace' => $this->rowValue($row, 'NATIVE_PLACE', 'nativePlace'),
            'homeAddress' => $this->rowValue($row, 'HOME_ADDRESS', 'homeAddress'),
            'mailingAddress' => $this->rowValue($row, 'MAILING_ADDRESS', 'mailingAddress'),
            'idCardType' => $this->rowValue($row, 'ID_CARD_TYPE', 'idCardType'),
            'idCardNumber' => $this->rowValue($row, 'ID_CARD_NUMBER', 'idCardNumber'),
            'cultureLevel' => $this->rowValue($row, 'CULTURE_LEVEL', 'cultureLevel'),
            'politicalOutlook' => $this->rowValue($row, 'POLITICAL_OUTLOOK', 'politicalOutlook'),
            'college' => $this->rowValue($row, 'COLLEGE', 'college'),
            'education' => $this->rowValue($row, 'EDUCATION', 'education'),
            'eduLength' => $this->rowValue($row, 'EDU_LENGTH', 'eduLength'),
            'degree' => $this->rowValue($row, 'DEGREE', 'degree'),
            'phone' => $this->rowValue($row, 'PHONE', 'phone'),
            'email' => $this->rowValue($row, 'EMAIL', 'email'),
            'homeTel' => $this->rowValue($row, 'HOME_TEL', 'homeTel'),
            'officeTel' => $this->rowValue($row, 'OFFICE_TEL', 'officeTel'),
            'emergencyContact' => $this->rowValue($row, 'EMERGENCY_CONTACT', 'emergencyContact'),
            'emergencyPhone' => $this->rowValue($row, 'EMERGENCY_PHONE', 'emergencyPhone'),
            'emergencyAddress' => $this->rowValue($row, 'EMERGENCY_ADDRESS', 'emergencyAddress'),
            'empNo' => $this->rowValue($row, 'EMP_NO', 'empNo'),
            'entryDate' => $this->rowValue($row, 'ENTRY_DATE', 'entryDate'),
            'orgId' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? $this->rowValue($row, 'orgName', 'ORG_NAME')) : null,
            'positionId' => $positionId,
            'positionName' => $positionId !== null ? ($positionNames[(string)$positionId] ?? $this->rowValue($row, 'positionName', 'POSITION_NAME')) : null,
            'positionLevel' => $this->rowValue($row, 'POSITION_LEVEL', 'positionLevel'),
            'directorId' => $this->rowValue($row, 'DIRECTOR_ID', 'directorId'),
            'positionJson' => $this->rowValue($row, 'POSITION_JSON', 'positionJson'),
            'bankName' => $this->rowValue($row, 'BANK_NAME', 'bankName'),
            'bankAccount' => $this->rowValue($row, 'BANK_ACCOUNT', 'bankAccount'),
            'basicSalary' => $this->rowValue($row, 'BASIC_SALARY', 'basicSalary'),
            'workStartDate' => $this->rowValue($row, 'WORK_START_DATE', 'workStartDate'),
            'healthStatus' => $this->rowValue($row, 'HEALTH_STATUS', 'healthStatus'),
            'specialtySkills' => $this->rowValue($row, 'SPECIALTY_SKILLS', 'specialtySkills'),
            'onJobEducationJson' => $this->rowValue($row, 'ON_JOB_EDUCATION_JSON', 'onJobEducationJson'),
            'fullTimeEducationJson' => $this->rowValue($row, 'FULL_TIME_EDUCATION_JSON', 'fullTimeEducationJson'),
            'jobTitle' => $this->rowValue($row, 'JOB_TITLE', 'jobTitle'),
            'socialAppointments' => $this->rowValue($row, 'SOCIAL_APPOINTMENTS', 'socialAppointments'),
            'departmentAttribute' => $this->rowValue($row, 'DEPARTMENT_ATTRIBUTE', 'departmentAttribute'),
            'personalInformation' => $this->rowValue($row, 'PERSONAL_INFORMATION', 'personalInformation'),
            'mainStudyAndWorkExperience' => $this->rowValue($row, 'MAIN_STUDY_AND_WORK_EXPERIENCE', 'mainStudyAndWorkExperience'),
            'awardsAndAchievements' => $this->rowValue($row, 'AWARDS_AND_ACHIEVEMENTS', 'awardsAndAchievements'),
            'familyMembersAndSocialRelationshipsJson' => $this->rowValue($row, 'FAMILY_MEMBERS_AND_SOCIAL_RELATIONSHIPS_JSON', 'familyMembersAndSocialRelationshipsJson'),
            'partyOrganizationOpinion' => $this->rowValue($row, 'PARTY_ORGANIZATION_OPINION', 'partyOrganizationOpinion'),
            'entryMethod' => $this->rowValue($row, 'ENTRY_METHOD', 'entryMethod'),
            'companyEmployeeId' => $this->rowValue($row, 'COMPANY_EMPLOYEE_ID', 'companyEmployeeId'),
            'userStatus' => $this->rowValue($row, 'USER_STATUS', 'userStatus'),
            'sortCode' => $this->rowValue($row, 'SORT_CODE', 'sortCode'),
            'extJson' => $this->rowValue($row, 'EXT_JSON', 'extJson'),
            'deleteFlag' => $this->rowValue($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->rowValue($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->rowValue($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->rowValue($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->rowValue($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->rowValue($row, 'TENANT_ID', 'tenantId'),
        ]);
    }

    private function rowValue(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function orgNames(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            fn (array $row): string => (string)$this->rowValue($row, 'ORG_ID', 'orgId'),
            $rows
        ))));

        return $orgIds === []
            ? []
            : SysOrg::whereIn('ID', $orgIds)->column('NAME', 'ID');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function positionNames(array $rows): array
    {
        $positionIds = array_values(array_unique(array_filter(array_map(
            fn (array $row): string => (string)$this->rowValue($row, 'POSITION_ID', 'positionId'),
            $rows
        ))));

        return $positionIds === []
            ? []
            : SysPosition::whereIn('ID', $positionIds)->column('NAME', 'ID');
    }

    /**
     * @return array<string, string>
     */
    private function dictLabels(string $parentDictValue): array
    {
        $parentId = Db::name('dev_dict')
            ->where('DICT_VALUE', $parentDictValue)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', self::NOT_DELETE);
            })
            ->value('ID');

        if ($parentId === null || $parentId === '') {
            return [];
        }

        return Db::name('dev_dict')
            ->where('PARENT_ID', (string)$parentId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', self::NOT_DELETE);
            })
            ->column('DICT_LABEL', 'DICT_VALUE');
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string)$id);
        }, $ids))));
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? $filters['size'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function pageResult(array $records, int $total, int $page, int $limit): array
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

    private function defaultWorkbenchData(): string
    {
        $value = Db::name('dev_config')
            ->where('CONFIG_KEY', self::DEFAULT_WORKBENCH_KEY)
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->value('CONFIG_VALUE');

        $workbench = is_string($value) ? trim($value) : '';

        return $workbench !== '' ? $workbench : '{"shortcut":[]}';
    }

    private function defaultPasswordHash(): string
    {
        $value = Db::name('dev_config')
            ->where('CONFIG_KEY', self::DEFAULT_PASSWORD_KEY)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->value('CONFIG_VALUE');

        $defaultPassword = is_string($value) ? trim($value) : '';
        if ($defaultPassword === '') {
            throw new RuntimeException('default password not configured', 500);
        }

        return Sm3Hasher::hash($defaultPassword);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultProcessConfig(): array
    {
        return array_map(static fn (string $processName): array => [
            'processName' => $processName,
            'approveUserIdList' => [],
            'copyUserIdList' => [],
        ], self::DEFAULT_PROCESS_NAMES);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeProcessConfig(string $configJson): array
    {
        $decoded = json_decode($configJson, true);
        if (!is_array($decoded) || !isset($decoded['config']) || !is_array($decoded['config'])) {
            return $this->defaultProcessConfig();
        }

        $config = $this->normalizeProcessConfigList($decoded['config']);

        return $config === [] ? $this->defaultProcessConfig() : $config;
    }

    /**
     * @param array<int|string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProcessConfigList(array $config): array
    {
        $result = [];
        foreach ($config as $item) {
            if (!is_array($item)) {
                continue;
            }

            $processName = trim((string)($item['processName'] ?? $item['key'] ?? ''));
            if ($processName === '') {
                continue;
            }

            $row = [
                'processName' => $processName,
                'approveUserIdList' => $this->userIdListValue($item['approveUserIdList'] ?? []),
                'copyUserIdList' => $this->userIdListValue($item['copyUserIdList'] ?? []),
            ];

            if (array_key_exists('treasurer', $item) || array_key_exists('treasurerId', $item)) {
                $row['treasurer'] = $this->singleUserIdValue($item['treasurer'] ?? $item['treasurerId'] ?? '');
            }
            if (array_key_exists('procure', $item) || array_key_exists('procureId', $item)) {
                $row['procure'] = $this->singleUserIdValue($item['procure'] ?? $item['procureId'] ?? '');
            }
            if (array_key_exists('open', $item)) {
                $row['open'] = (bool)$item['open'];
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function userIdListValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
        }

        if (!is_array($value)) {
            $id = $this->userIdFromSelection($value);

            return $id === '' ? [] : [$id];
        }

        $items = $this->isAssociativeArray($value) ? [$value] : $value;
        $ids = [];
        foreach ($items as $item) {
            $id = $this->userIdFromSelection($item);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function singleUserIdValue(mixed $value): string
    {
        return $this->userIdListValue($value)[0] ?? '';
    }

    private function userIdFromSelection(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['userId', 'id', 'value', 'USER_ID', 'ID'] as $key) {
                if (!array_key_exists($key, $value)) {
                    continue;
                }

                $id = $this->userIdFromSelection($value[$key]);
                if ($id !== '') {
                    return $id;
                }
            }

            return '';
        }

        return trim((string)$value);
    }

    private function isAssociativeArray(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grantInfoList(string $id, string $category, string $targetKey): array
    {
        $id = trim($id);
        if ($id === '') {
            return [];
        }

        $relations = Db::name('sys_relation')
            ->where('OBJECT_ID', $id)
            ->where('CATEGORY', $category)
            ->select()
            ->toArray();

        return array_map(function (array $relation) use ($targetKey): array {
            $decoded = $this->decodeRelationExt((string)($relation['EXT_JSON'] ?? ''));
            if ($decoded === []) {
                $decoded[$targetKey] = $relation['TARGET_ID'] ?? null;
            }

            return $decoded;
        }, $relations);
    }

    private function decodeRelationExt(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function messageRelationsForUser(string $userId): array
    {
        $rows = Db::name('dev_relation')
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->select()
            ->toArray();

        $relations = [];
        foreach ($rows as $row) {
            $messageId = (string)($row['OBJECT_ID'] ?? '');
            if ($messageId !== '') {
                $relations[$messageId] = $row;
            }
        }

        return $relations;
    }

    /**
     * @param array<int, string> $messageIds
     */
    private function messageQuery(array $messageIds, array $filters)
    {
        $query = Db::name('dev_message')
            ->where('DELETE_FLAG', self::NOT_DELETE)
            ->whereIn('ID', $messageIds);

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('SUBJECT', '%' . trim((string)$filters['searchKey']) . '%');
        }

        return $query;
    }

    private function messageRow(array $row, ?array $relation): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'subject' => $row['SUBJECT'] ?? null,
            'content' => $row['CONTENT'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'read' => $this->relationReadStatus($relation) ?? false,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function markMessageRead(array $relation, string $userId, string $messageId): array
    {
        if ($this->relationReadStatus($relation) === true) {
            return $relation;
        }

        $extJson = $this->messageRelationExtWithRead($relation);

        Db::name('dev_relation')
            ->where('OBJECT_ID', $messageId)
            ->where('TARGET_ID', $userId)
            ->where('CATEGORY', self::MESSAGE_TO_USER_CATEGORY)
            ->update(['EXT_JSON' => $extJson]);

        $relation['EXT_JSON'] = $extJson;

        return $relation;
    }

    private function messageRelationExtWithRead(array $relation): string
    {
        $decoded = json_decode((string)($relation['EXT_JSON'] ?? '{}'), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded['read'] = true;
        $extJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);

        return $extJson === false ? '{"read":true}' : $extJson;
    }

    private function relationReadStatus(?array $relation): ?bool
    {
        if (!$relation) {
            return null;
        }

        $decoded = json_decode((string)($relation['EXT_JSON'] ?? '{}'), true);
        if (!is_array($decoded) || !array_key_exists('read', $decoded)) {
            return null;
        }

        return (bool)$decoded['read'];
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
