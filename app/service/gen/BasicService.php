<?php

declare(strict_types=1);

namespace app\service\gen;

use app\service\mobile\MobileResourceService;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only generator basic metadata queries compatible with Java GenBasicController.
 */
class BasicService
{
    private const NOT_DELETE = 'NOT_DELETE';
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
