<?php

declare(strict_types=1);

namespace app\service\gen;

use app\service\mobile\MobileResourceService;
use RuntimeException;
use think\facade\Db;

/**
 * Generator basic metadata queries and safe writes compatible with Java GenBasicController.
 */
class BasicService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const REQUIRED_FIELDS = [
        'dbTable',
        'dbTableKey',
        'pluginName',
        'moduleName',
        'tablePrefix',
        'generateType',
        'module',
        'menuPid',
        'functionName',
        'busName',
        'className',
        'formLayout',
        'gridWhether',
        'sortCode',
    ];
    private const BASIC_FIELD_MAP = [
        'dbTable' => 'DB_TABLE',
        'dbTableKey' => 'DB_TABLE_KEY',
        'pluginName' => 'PLUGIN_NAME',
        'moduleName' => 'MODULE_NAME',
        'tablePrefix' => 'TABLE_PREFIX',
        'generateType' => 'GENERATE_TYPE',
        'module' => 'MODULE',
        'menuPid' => 'MENU_PID',
        'mobileModule' => 'MOBILE_MODULE',
        'functionName' => 'FUNCTION_NAME',
        'busName' => 'BUS_NAME',
        'className' => 'CLASS_NAME',
        'formLayout' => 'FORM_LAYOUT',
        'gridWhether' => 'GRID_WHETHER',
        'packageName' => 'PACKAGE_NAME',
        'authorName' => 'AUTHOR_NAME',
        'sortCode' => 'SORT_CODE',
    ];
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'dbTable' => 'DB_TABLE',
        'dbTableKey' => 'DB_TABLE_KEY',
        'pluginName' => 'PLUGIN_NAME',
        'moduleName' => 'MODULE_NAME',
        'tablePrefix' => 'TABLE_PREFIX',
        'generateType' => 'GENERATE_TYPE',
        'module' => 'MODULE',
        'menuPid' => 'MENU_PID',
        'mobileModule' => 'MOBILE_MODULE',
        'functionName' => 'FUNCTION_NAME',
        'busName' => 'BUS_NAME',
        'className' => 'CLASS_NAME',
        'formLayout' => 'FORM_LAYOUT',
        'gridWhether' => 'GRID_WHETHER',
        'packageName' => 'PACKAGE_NAME',
        'authorName' => 'AUTHOR_NAME',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->basicQuery($filters)->count();
        $rows = $this->applySort($this->basicQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->basicRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id): ?array
    {
        $row = $this->basicQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->basicRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function add(array $input, array $payload = []): array
    {
        $data = $this->basicPayload($input, false);
        $columns = $this->validatedTableColumns($data['dbTable'], $data['dbTableKey']);

        return Db::transaction(function () use ($data, $columns, $payload): array {
            $id = $this->newId();
            $now = date('Y-m-d H:i:s');
            $userId = $this->payloadUserId($payload);

            Db::name('gen_basic')->insert(array_merge($this->basicInsertColumns($data), [
                'ID' => $id,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
            ]));

            $this->insertDefaultConfigs($id, $data['dbTableKey'], $columns, $now, $userId);

            return $this->activeBasicRow($id);
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredString($input, 'id');
        $this->assertMaxLength($id, 'id', 20);
        $data = $this->basicPayload($input, true);
        $columns = $this->validatedTableColumns($data['dbTable'], $data['dbTableKey']);

        return Db::transaction(function () use ($id, $data, $columns, $payload): array {
            $existing = $this->activeBasicRow($id);
            $tableChanged = strcasecmp((string)($existing['dbTable'] ?? ''), $data['dbTable']) !== 0;
            $keyChanged = strcasecmp((string)($existing['dbTableKey'] ?? ''), $data['dbTableKey']) !== 0;
            $now = date('Y-m-d H:i:s');
            $userId = $this->payloadUserId($payload);

            Db::name('gen_basic')
                ->where('ID', $id)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update(array_merge($this->basicInsertColumns($data), [
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]));

            if ($tableChanged) {
                $this->softDeleteConfigs([$id], $now, $userId);
                $this->insertDefaultConfigs($id, $data['dbTableKey'], $columns, $now, $userId);
            } elseif ($keyChanged) {
                $this->refreshConfigPrimaryKey($id, $data['dbTableKey'], $now, $userId);
            }

            return $this->activeBasicRow($id);
        });
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function delete(array $input, array $payload = []): ?array
    {
        $ids = $this->deleteIds($input);
        if ($ids === []) {
            throw new RuntimeException('missing id', 400);
        }

        return Db::transaction(function () use ($ids, $payload): ?array {
            $existingIds = Db::name('gen_basic')
                ->whereIn('ID', $ids)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->column('ID');
            $existingIds = array_map('strval', $existingIds);
            foreach ($ids as $id) {
                if (!in_array($id, $existingIds, true)) {
                    throw new RuntimeException('gen basic not found', 404);
                }
            }

            $now = date('Y-m-d H:i:s');
            $userId = $this->payloadUserId($payload);
            Db::name('gen_basic')
                ->whereIn('ID', $ids)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
            $this->softDeleteConfigs($ids, $now, $userId);

            return null;
        });
    }

    public function previewGen(string $id): array
    {
        $row = $this->basicQuery(['id' => $id])->find();
        if (!$row) {
            throw new RuntimeException('gen basic not found', 404);
        }

        $basic = is_array($row) ? $row : $row->toArray();
        $configs = $this->configRows($id);

        return [
            'genBasicCodeSqlResultList' => $this->sqlPreview($basic, $configs),
            'genBasicCodeFrontendResultList' => $this->frontendPreview($basic, $configs),
            'genBasicCodeBackendResultList' => $this->backendPreview($basic, $configs),
            'genBasicCodeMobileResultList' => $this->mobilePreview($basic, $configs),
        ];
    }

    /**
     * @return array{filename:string, contentType:string, content:string}
     */
    public function execGenZip(string $id): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('zip extension not available', 500);
        }

        $row = $this->basicQuery(['id' => $id])->find();
        if (!$row) {
            throw new RuntimeException('gen basic not found', 404);
        }

        $basic = is_array($row) ? $row : $row->toArray();
        $preview = $this->previewGen($id);
        $temp = tempnam(sys_get_temp_dir(), 'gen_basic_');
        if ($temp === false) {
            throw new RuntimeException('cannot create temporary zip file', 500);
        }

        $zipPath = $temp . '.zip';
        @unlink($temp);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('cannot open temporary zip file', 500);
        }

        $closed = false;
        try {
            $seen = [];
            $this->addPreviewZipEntries($zip, $preview['genBasicCodeSqlResultList'] ?? [], '', $seen);
            $this->addPreviewZipEntries($zip, $preview['genBasicCodeFrontendResultList'] ?? [], 'frontend', $seen);
            $this->addPreviewZipEntries($zip, $preview['genBasicCodeBackendResultList'] ?? [], 'backend', $seen);
            $this->addPreviewZipEntries($zip, $preview['genBasicCodeMobileResultList'] ?? [], 'mobile', $seen);

            $closed = $zip->close();
            if (!$closed) {
                throw new RuntimeException('cannot close temporary zip file', 500);
            }

            $content = file_get_contents($zipPath);
            if ($content === false || $content === '') {
                throw new RuntimeException('cannot read temporary zip file', 500);
            }

            return [
                'filename' => $this->zipFilename($basic),
                'contentType' => 'application/octet-stream;charset=UTF-8',
                'content' => $content,
            ];
        } finally {
            if (!$closed) {
                @$zip->close();
            }
            @unlink($zipPath);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mobileModuleSelector(array $filters = []): array
    {
        return (new MobileResourceService())->moduleSelector($filters);
    }

    /**
     * @return array<int, array{tableName: string, tableRemark: string}>
     */
    public function tables(array $filters = []): array
    {
        $sql = <<<SQL
SELECT TABLE_NAME, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = ?
  AND LEFT(UPPER(TABLE_NAME), 4) <> ?
SQL;
        $bind = ['BASE TABLE', 'ACT_'];

        if (!empty($filters['searchKey'])) {
            $sql .= "\n  AND (TABLE_NAME LIKE ? OR TABLE_COMMENT LIKE ?)";
            $searchKey = '%' . trim((string)$filters['searchKey']) . '%';
            $bind[] = $searchKey;
            $bind[] = $searchKey;
        }

        $sql .= "\nORDER BY TABLE_NAME ASC";

        return array_map(static function (array $row): array {
            $tableName = (string)($row['TABLE_NAME'] ?? '');
            $tableRemark = trim((string)($row['TABLE_COMMENT'] ?? ''));

            return [
                'tableName' => $tableName,
                'tableRemark' => $tableRemark !== '' ? $tableRemark : $tableName,
            ];
        }, Db::query($sql, $bind));
    }

    /**
     * @return array<int, array{columnName: string, typeName: string, columnRemark: string}>
     */
    public function tableColumns(string $tableName): array
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            throw new RuntimeException('missing tableName', 400);
        }

        $rows = Db::query(<<<SQL
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (TABLE_NAME = ? OR LOWER(TABLE_NAME) = LOWER(?))
ORDER BY ORDINAL_POSITION ASC
SQL, [$tableName, $tableName]);

        return array_map(static function (array $row): array {
            $columnName = strtoupper((string)($row['COLUMN_NAME'] ?? ''));
            $typeName = strtoupper((string)($row['DATA_TYPE'] ?? ''));
            $columnRemark = trim((string)($row['COLUMN_COMMENT'] ?? ''));

            return [
                'columnName' => $columnName,
                'typeName' => $typeName !== '' ? $typeName : 'NONE',
                'columnRemark' => $columnRemark !== '' ? $columnRemark : $columnName,
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configRows(string $basicId): array
    {
        return Db::name('gen_config')
            ->where('BASIC_ID', $basicId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->order('SORT_CODE', 'asc')
            ->order('ID', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * @param array<string, mixed> $basic
     * @param array<int, array<string, mixed>> $configs
     * @return array<int, array{codeFileName:string, codeFileWithPathName:string, codeFileContent:string}>
     */
    private function sqlPreview(array $basic, array $configs): array
    {
        $table = $this->str($basic['DB_TABLE'] ?? 'generated_table');
        $mysql = $this->mysqlCreateTable($table, $this->str($basic['DB_TABLE_KEY'] ?? 'ID'), $configs);
        $oracle = "-- Oracle preview for {$table}\n" . $this->oracleColumns($table, $configs);

        return [
            $this->codeResult('Mysql.sql', 'sql/Mysql.sql', $mysql),
            $this->codeResult('Oracle.sql', 'sql/Oracle.sql', $oracle),
        ];
    }

    /**
     * @param array<string, mixed> $basic
     * @param array<int, array<string, mixed>> $configs
     * @return array<int, array{codeFileName:string, codeFileWithPathName:string, codeFileContent:string}>
     */
    private function frontendPreview(array $basic, array $configs): array
    {
        $module = $this->str($basic['MODULE_NAME'] ?? 'biz');
        $bus = $this->str($basic['BUS_NAME'] ?? 'generated');
        $class = $this->str($basic['CLASS_NAME'] ?? 'Generated');
        $lowerClass = $this->lowerFirst($class);
        $endpoint = '/' . $module . '/' . $bus . '/';

        return [
            $this->codeResult($lowerClass . 'Api.js', "api/{$module}/{$lowerClass}Api.js", $this->apiJs($endpoint, $class)),
            $this->codeResult('form.vue', "views/{$module}/{$bus}/form.vue", $this->vueForm($class, $configs)),
            $this->codeResult('index.vue', "views/{$module}/{$bus}/index.vue", $this->vueIndex($class, $configs)),
        ];
    }

    /**
     * @param array<string, mixed> $basic
     * @param array<int, array<string, mixed>> $configs
     * @return array<int, array{codeFileName:string, codeFileWithPathName:string, codeFileContent:string}>
     */
    private function backendPreview(array $basic, array $configs): array
    {
        $module = $this->str($basic['MODULE_NAME'] ?? 'biz');
        $bus = $this->str($basic['BUS_NAME'] ?? 'generated');
        $class = $this->str($basic['CLASS_NAME'] ?? 'Generated');
        $package = str_replace('.', '/', $this->str($basic['PACKAGE_NAME'] ?? 'vip.xiaonuo'));
        $base = "{$package}/{$module}/modular/{$bus}";
        $fields = $this->fieldSummary($configs);

        return [
            $this->codeResult($class . 'Controller.java', "{$base}/controller/{$class}Controller.java", "public class {$class}Controller {\n    // Preview only. Fields: {$fields}\n}\n"),
            $this->codeResult($class . '.java', "{$base}/entity/{$class}.java", "public class {$class} {\n{$this->javaFields($configs)}}\n"),
            $this->codeResult($class . 'Enum.java', "{$base}/enums/{$class}Enum.java", "public enum {$class}Enum {\n}\n"),
            $this->codeResult($class . 'Mapper.java', "{$base}/mapper/{$class}Mapper.java", "public interface {$class}Mapper {\n}\n"),
            $this->codeResult($class . 'Mapper.xml', "{$base}/mapper/mapping/{$class}Mapper.xml", "<mapper namespace=\"{$class}Mapper\">\n</mapper>\n"),
            $this->codeResult($class . 'AddParam.java', "{$base}/param/{$class}AddParam.java", "public class {$class}AddParam {\n{$this->javaFields($configs)}}\n"),
            $this->codeResult($class . 'EditParam.java', "{$base}/param/{$class}EditParam.java", "public class {$class}EditParam {\n{$this->javaFields($configs)}}\n"),
            $this->codeResult($class . 'IdParam.java', "{$base}/param/{$class}IdParam.java", "public class {$class}IdParam {\n    private String id;\n}\n"),
            $this->codeResult($class . 'PageParam.java', "{$base}/param/{$class}PageParam.java", "public class {$class}PageParam {\n}\n"),
            $this->codeResult($class . 'Service.java', "{$base}/service/{$class}Service.java", "public interface {$class}Service {\n}\n"),
            $this->codeResult($class . 'ServiceImpl.java', "{$base}/service/impl/{$class}ServiceImpl.java", "public class {$class}ServiceImpl implements {$class}Service {\n}\n"),
        ];
    }

    /**
     * @param array<string, mixed> $basic
     * @param array<int, array<string, mixed>> $configs
     * @return array<int, array{codeFileName:string, codeFileWithPathName:string, codeFileContent:string}>|null
     */
    private function mobilePreview(array $basic, array $configs): ?array
    {
        $mobileModule = $this->str($basic['MOBILE_MODULE'] ?? '');
        if ($mobileModule === '') {
            return null;
        }

        $module = $this->str($basic['MODULE_NAME'] ?? 'biz');
        $bus = $this->str($basic['BUS_NAME'] ?? 'generated');
        $class = $this->str($basic['CLASS_NAME'] ?? 'Generated');
        $lowerClass = $this->lowerFirst($class);

        return [
            $this->codeResult('page.json', 'page.json', "{\n  \"preview\": true,\n  \"module\": \"{$mobileModule}\"\n}\n"),
            $this->codeResult($lowerClass . 'Api.js', "api/{$module}/{$lowerClass}Api.js", $this->apiJs('/' . $module . '/' . $bus . '/', $class)),
            $this->codeResult('search.vue', "pages/{$module}/{$bus}/search.vue", $this->vueForm($class . 'Search', $configs)),
            $this->codeResult('form.vue', "pages/{$module}/{$bus}/form.vue", $this->vueForm($class, $configs)),
            $this->codeResult('more.vue', "pages/{$module}/{$bus}/more.vue", "<template>\n  <view>{$class} more preview</view>\n</template>\n"),
            $this->codeResult('index.vue', "pages/{$module}/{$bus}/index.vue", $this->vueIndex($class, $configs)),
        ];
    }

    /**
     * @return array{codeFileName:string, codeFileWithPathName:string, codeFileContent:string}
     */
    private function codeResult(string $name, string $path, string $content): array
    {
        return [
            'codeFileName' => $name,
            'codeFileWithPathName' => str_replace('\\', '/', $path),
            'codeFileContent' => $content,
        ];
    }

    /**
     * @param array<int, array<string, mixed>>|mixed $items
     * @param array<string, bool> $seen
     */
    private function addPreviewZipEntries(\ZipArchive $zip, mixed $items, string $prefix, array &$seen): void
    {
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $fallbackName = $this->str($item['codeFileName'] ?? '');
            if ($fallbackName === '') {
                $fallbackName = 'generated-' . ((int)$index + 1) . '.txt';
            }

            $path = $this->zipPath(
                $prefix,
                $this->str($item['codeFileWithPathName'] ?? ''),
                $fallbackName
            );
            $path = $this->uniqueZipPath($path, $seen);
            if (!$zip->addFromString($path, (string)($item['codeFileContent'] ?? ''))) {
                throw new RuntimeException('cannot add generated file to zip', 500);
            }
        }
    }

    private function zipPath(string $prefix, string $path, string $fallbackName): string
    {
        $prefixSegments = $this->zipPathSegments($prefix);
        $pathSegments = $this->zipPathSegments($path);
        if ($pathSegments === []) {
            $pathSegments = $this->zipPathSegments($fallbackName);
        }
        if ($pathSegments === []) {
            $pathSegments = ['generated.txt'];
        }

        return implode('/', array_merge($prefixSegments, $pathSegments));
    }

    /**
     * @return array<int, string>
     */
    private function zipPathSegments(string $path): array
    {
        $path = str_replace("\0", '', str_replace('\\', '/', $path));
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * @param array<string, bool> $seen
     */
    private function uniqueZipPath(string $path, array &$seen): string
    {
        $candidate = $path;
        $index = 2;
        while (isset($seen[strtolower($candidate)])) {
            $dot = strrpos($path, '.');
            $candidate = $dot === false
                ? $path . '-' . $index
                : substr($path, 0, $dot) . '-' . $index . substr($path, $dot);
            $index++;
        }

        $seen[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * @param array<string, mixed> $basic
     */
    private function zipFilename(array $basic): string
    {
        $name = $this->str($basic['FUNCTION_NAME'] ?? '');
        if ($name === '') {
            $name = $this->str($basic['CLASS_NAME'] ?? '');
        }
        $name = str_replace(["\0", '/', '\\'], '', $name);
        if ($name === '') {
            $name = 'gen-basic';
        }

        return str_ends_with(strtolower($name), '.zip') ? $name : $name . '.zip';
    }

    private function basicQuery(array $filters)
    {
        $query = Db::name('gen_basic')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['dbTable'])) {
            $query->whereLike('DB_TABLE', '%' . trim((string)$filters['dbTable']) . '%');
        }

        if (!empty($filters['moduleName'])) {
            $query->whereLike('MODULE_NAME', '%' . trim((string)$filters['moduleName']) . '%');
        }

        if (!empty($filters['functionName'])) {
            $query->whereLike('FUNCTION_NAME', '%' . trim((string)$filters['functionName']) . '%');
        }

        if (!empty($filters['className'])) {
            $query->whereLike('CLASS_NAME', '%' . trim((string)$filters['className']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $searchKey = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($searchKey): void {
                $query->whereLike('DB_TABLE', $searchKey)
                    ->whereOr('FUNCTION_NAME', 'like', $searchKey)
                    ->whereOr('BUS_NAME', 'like', $searchKey)
                    ->whereOr('CLASS_NAME', 'like', $searchKey);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    private function basicRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'dbTable' => $row['DB_TABLE'] ?? null,
            'dbTableKey' => $row['DB_TABLE_KEY'] ?? null,
            'pluginName' => $row['PLUGIN_NAME'] ?? null,
            'moduleName' => $row['MODULE_NAME'] ?? null,
            'tablePrefix' => $row['TABLE_PREFIX'] ?? null,
            'generateType' => $row['GENERATE_TYPE'] ?? null,
            'module' => $row['MODULE'] ?? null,
            'menuPid' => $row['MENU_PID'] ?? null,
            'mobileModule' => $row['MOBILE_MODULE'] ?? null,
            'functionName' => $row['FUNCTION_NAME'] ?? null,
            'busName' => $row['BUS_NAME'] ?? null,
            'className' => $row['CLASS_NAME'] ?? null,
            'formLayout' => $row['FORM_LAYOUT'] ?? null,
            'gridWhether' => $row['GRID_WHETHER'] ?? null,
            'packageName' => $row['PACKAGE_NAME'] ?? null,
            'authorName' => $row['AUTHOR_NAME'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<string, mixed>
     */
    private function basicPayload(array $input, bool $isEdit): array
    {
        $payload = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            $payload[$field] = $field === 'sortCode'
                ? $this->requiredInt($input, $field)
                : $this->requiredString($input, $field);
        }

        $payload['mobileModule'] = $this->optionalString($input, 'mobileModule');
        $payload['packageName'] = $this->optionalString($input, 'packageName');
        $payload['authorName'] = $isEdit
            ? $this->requiredString($input, 'authorName')
            : $this->optionalString($input, 'authorName');

        foreach (['dbTable', 'dbTableKey', 'pluginName', 'moduleName', 'tablePrefix', 'generateType', 'module', 'menuPid', 'mobileModule', 'functionName', 'busName', 'className', 'formLayout', 'gridWhether', 'packageName', 'authorName'] as $field) {
            if ($payload[$field] !== null) {
                $this->assertMaxLength((string)$payload[$field], $field, 255);
            }
        }

        $payload['tablePrefix'] = strtoupper((string)$payload['tablePrefix']);
        $payload['generateType'] = strtoupper((string)$payload['generateType']);
        $payload['gridWhether'] = strtoupper((string)$payload['gridWhether']);
        $payload['formLayout'] = strtolower((string)$payload['formLayout']);
        $this->assertEnum((string)$payload['tablePrefix'], ['Y', 'N'], 'tablePrefix');
        $this->assertEnum((string)$payload['generateType'], ['ZIP', 'PRO'], 'generateType');
        $this->assertEnum((string)$payload['gridWhether'], ['Y', 'N'], 'gridWhether');
        $this->assertEnum((string)$payload['formLayout'], ['vertical', 'horizontal'], 'formLayout');

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function basicInsertColumns(array $data): array
    {
        $columns = [];
        foreach (self::BASIC_FIELD_MAP as $field => $column) {
            $columns[$column] = $data[$field] ?? null;
        }

        return $columns;
    }

    /**
     * @return array<int, array{columnName: string, typeName: string, columnRemark: string}>
     */
    private function validatedTableColumns(string $tableName, string $dbTableKey): array
    {
        $this->assertTableName($tableName);
        $columns = $this->tableColumns($tableName);
        if ($columns === []) {
            throw new RuntimeException('table not found', 404);
        }

        $key = strtoupper($dbTableKey);
        foreach ($columns as $column) {
            if (strtoupper((string)$column['columnName']) === $key) {
                return $columns;
            }
        }

        throw new RuntimeException('dbTableKey not found', 400);
    }

    private function assertTableName(string $tableName): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $tableName) !== 1) {
            throw new RuntimeException('invalid dbTable', 400);
        }
        if (str_starts_with(strtoupper($tableName), 'ACT_')) {
            throw new RuntimeException('workflow tables cannot be generated', 400);
        }
    }

    /**
     * @param array<int, array{columnName: string, typeName: string, columnRemark: string}> $columns
     */
    private function insertDefaultConfigs(string $basicId, string $dbTableKey, array $columns, string $now, ?string $userId): void
    {
        $rows = [];
        $key = strtoupper($dbTableKey);
        foreach ($columns as $index => $column) {
            $fieldName = strtoupper((string)$column['columnName']);
            $isTableKey = $fieldName === $key ? 'Y' : 'N';
            $editable = $isTableKey === 'Y' || in_array($fieldName, ['DELETE_FLAG', 'CREATE_USER', 'CREATE_TIME', 'UPDATE_USER', 'UPDATE_TIME'], true)
                ? 'N'
                : 'Y';

            $rows[] = [
                'ID' => $this->newId(),
                'BASIC_ID' => $basicId,
                'IS_TABLE_KEY' => $isTableKey,
                'FIELD_NAME' => $fieldName,
                'FIELD_REMARK' => (string)$column['columnRemark'],
                'FIELD_TYPE' => strtoupper((string)$column['typeName']),
                'FIELD_JAVA_TYPE' => $this->javaTypeForSqlType((string)$column['typeName']),
                'EFFECT_TYPE' => 'input',
                'DICT_TYPE_CODE' => null,
                'WHETHER_TABLE' => $editable,
                'WHETHER_RETRACT' => 'N',
                'WHETHER_ADD_UPDATE' => $editable,
                'WHETHER_REQUIRED' => 'N',
                'QUERY_WHETHER' => 'N',
                'QUERY_TYPE' => null,
                'SORT_CODE' => $index,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
            ];
        }

        if ($rows !== []) {
            Db::name('gen_config')->insertAll($rows);
        }
    }

    /**
     * @param array<int, string> $basicIds
     */
    private function softDeleteConfigs(array $basicIds, string $now, ?string $userId): void
    {
        Db::name('gen_config')
            ->whereIn('BASIC_ID', $basicIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId,
            ]);
    }

    private function refreshConfigPrimaryKey(string $basicId, string $dbTableKey, string $now, ?string $userId): void
    {
        $rows = $this->configRows($basicId);
        $key = strtoupper($dbTableKey);
        foreach ($rows as $row) {
            $fieldName = strtoupper((string)($row['FIELD_NAME'] ?? ''));
            Db::name('gen_config')
                ->where('ID', (string)($row['ID'] ?? ''))
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'IS_TABLE_KEY' => $fieldName === $key ? 'Y' : 'N',
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId,
                ]);
        }
    }

    private function javaTypeForSqlType(string $sqlType): string
    {
        $type = strtolower($sqlType);
        if (str_contains($type, 'bigint')) {
            return 'Long';
        }
        if (str_contains($type, 'int')) {
            return 'Integer';
        }
        if (str_contains($type, 'decimal') || str_contains($type, 'numeric')) {
            return 'BigDecimal';
        }
        if (str_contains($type, 'float')) {
            return 'Float';
        }
        if (str_contains($type, 'double')) {
            return 'Double';
        }
        if (str_contains($type, 'date') || str_contains($type, 'time') || str_contains($type, 'year')) {
            return 'Date';
        }
        if (str_contains($type, 'bit') || str_contains($type, 'bool')) {
            return 'Boolean';
        }

        return 'String';
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $source = null;
        if ($this->isList($input)) {
            $source = $input;
        } elseif (array_key_exists('idList', $input)) {
            $source = $input['idList'];
        } elseif (array_key_exists('ids', $input)) {
            $source = $input['ids'];
        } elseif (array_key_exists('id', $input)) {
            $source = [$input['id']];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            return [];
        }

        $ids = [];
        foreach ($source as $item) {
            $id = is_array($item) ? (string)($item['id'] ?? $item['ID'] ?? '') : (string)$item;
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            $this->assertMaxLength($id, 'id', 20);
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function requiredString(array $input, string $field): string
    {
        $value = $this->fieldValue($input, $field);
        $value = trim((string)$value);
        if ($value === '') {
            throw new RuntimeException('missing ' . $field, 400);
        }

        return $value;
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function optionalString(array $input, string $field): ?string
    {
        $value = $this->fieldValue($input, $field);
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function requiredInt(array $input, string $field): int
    {
        $value = $this->fieldValue($input, $field);
        $value = trim((string)$value);
        if ($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
            throw new RuntimeException('missing ' . $field, 400);
        }

        return (int)$value;
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function fieldValue(array $input, string $field): mixed
    {
        if (array_key_exists($field, $input)) {
            return $input[$field];
        }

        $column = self::BASIC_FIELD_MAP[$field] ?? strtoupper((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $field));
        if (array_key_exists($column, $input)) {
            return $input[$column];
        }

        return null;
    }

    private function assertMaxLength(string $value, string $label, int $maxLength): void
    {
        if (strlen($value) > $maxLength) {
            throw new RuntimeException($label . ' is too long', 400);
        }
    }

    /**
     * @param array<int, string> $allowed
     */
    private function assertEnum(string $value, array $allowed, string $label): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException('invalid ' . $label, 400);
        }
    }

    /**
     * @param array<string|int, mixed> $items
     */
    private function isList(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        return array_keys($items) === range(0, count($items) - 1);
    }

    private function activeBasicRow(string $id): array
    {
        $row = Db::name('gen_basic')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();
        if (!$row) {
            throw new RuntimeException('gen basic not found', 404);
        }

        return $this->basicRow(is_array($row) ? $row : $row->toArray());
    }

    private function payloadUserId(array $payload): ?string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));

        return $userId === '' ? null : $userId;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function mysqlCreateTable(string $table, string $primaryKey, array $configs): string
    {
        $lines = [];
        foreach ($configs as $config) {
            $field = $this->str($config['FIELD_NAME'] ?? '');
            if ($field === '') {
                continue;
            }

            $type = $this->str($config['FIELD_TYPE'] ?? 'varchar(255)');
            $remark = $this->str($config['FIELD_REMARK'] ?? $field);
            $lines[] = '  `' . $field . '` ' . ($type !== '' ? $type : 'varchar(255)') . " COMMENT '" . str_replace("'", "''", $remark) . "'";
        }

        if ($lines === []) {
            $lines[] = '  `ID` varchar(20) NOT NULL COMMENT ' . "'ID'";
        }

        $key = $primaryKey !== '' ? $primaryKey : $this->str($configs[0]['FIELD_NAME'] ?? 'ID');
        $lines[] = '  PRIMARY KEY (`' . $key . '`)';

        return "-- MySQL preview for {$table}\nCREATE TABLE `{$table}` (\n" . implode(",\n", $lines) . "\n);\n";
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function oracleColumns(string $table, array $configs): string
    {
        $lines = [];
        foreach ($configs as $config) {
            $field = $this->str($config['FIELD_NAME'] ?? '');
            if ($field === '') {
                continue;
            }

            $lines[] = '  ' . $field . ' ' . $this->oracleType($this->str($config['FIELD_TYPE'] ?? 'varchar2(255)'));
        }

        if ($lines === []) {
            $lines[] = '  ID varchar2(20)';
        }

        return "CREATE TABLE {$table} (\n" . implode(",\n", $lines) . "\n);\n";
    }

    private function oracleType(string $mysqlType): string
    {
        $type = strtolower($mysqlType);
        if (str_contains($type, 'int')) {
            return 'number';
        }
        if (str_contains($type, 'date') || str_contains($type, 'time')) {
            return 'date';
        }
        if (str_contains($type, 'text')) {
            return 'clob';
        }

        return 'varchar2(255)';
    }

    private function apiJs(string $endpoint, string $class): string
    {
        return "import { baseRequest } from '@/utils/request'\n\n"
            . "const request = (url, ...arg) => baseRequest('{$endpoint}' + url, ...arg)\n\n"
            . "export default {\n"
            . "  page(data) { return request('page', data, 'get') },\n"
            . "  detail(data) { return request('detail', data, 'get') }\n"
            . "}\n\n"
            . "// Preview for {$class}; generation writes are not executed.\n";
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function vueForm(string $class, array $configs): string
    {
        $fields = $this->fieldSummary($configs);

        return "<template>\n"
            . "  <div>{$class} form preview</div>\n"
            . "</template>\n\n"
            . "<script setup>\n"
            . "const fields = '" . str_replace("'", "\\'", $fields) . "'\n"
            . "</script>\n";
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function vueIndex(string $class, array $configs): string
    {
        $fields = $this->fieldSummary($configs);

        return "<template>\n"
            . "  <div>{$class} table preview</div>\n"
            . "</template>\n\n"
            . "<script setup>\n"
            . "const columns = '" . str_replace("'", "\\'", $fields) . "'\n"
            . "</script>\n";
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function fieldSummary(array $configs): string
    {
        $fields = array_values(array_filter(array_map(
            fn (array $config): string => $this->str($config['FIELD_NAME'] ?? ''),
            $configs
        )));

        return $fields === [] ? 'ID' : implode(', ', $fields);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function javaFields(array $configs): string
    {
        $lines = [];
        foreach ($configs as $config) {
            $field = $this->str($config['FIELD_NAME'] ?? '');
            if ($field === '') {
                continue;
            }

            $javaType = $this->str($config['FIELD_JAVA_TYPE'] ?? 'String');
            $lines[] = '    private ' . ($javaType !== '' ? $javaType : 'String') . ' ' . $this->camel($field) . ';';
        }

        if ($lines === []) {
            $lines[] = '    private String id;';
        }

        return implode("\n", $lines) . "\n";
    }

    private function camel(string $value): string
    {
        $value = strtolower($value);

        return preg_replace_callback('/_([a-z0-9])/', static fn (array $matches): string => strtoupper($matches[1]), $value) ?? $value;
    }

    private function lowerFirst(string $value): string
    {
        return $value === '' ? '' : strtolower($value[0]) . substr($value, 1);
    }

    private function str(mixed $value): string
    {
        return trim((string)$value);
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
