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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
