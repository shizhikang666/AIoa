<?php

declare(strict_types=1);

namespace app\service\gen;

use think\facade\Db;

/**
 * Read-only generator field configuration queries compatible with Java GenConfigController.
 */
class ConfigService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'basicId' => 'BASIC_ID',
        'isTableKey' => 'IS_TABLE_KEY',
        'fieldName' => 'FIELD_NAME',
        'fieldRemark' => 'FIELD_REMARK',
        'fieldType' => 'FIELD_TYPE',
        'fieldJavaType' => 'FIELD_JAVA_TYPE',
        'effectType' => 'EFFECT_TYPE',
        'dictTypeCode' => 'DICT_TYPE_CODE',
        'whetherTable' => 'WHETHER_TABLE',
        'whetherRetract' => 'WHETHER_RETRACT',
        'whetherAddUpdate' => 'WHETHER_ADD_UPDATE',
        'whetherRequired' => 'WHETHER_REQUIRED',
        'queryWhether' => 'QUERY_WHETHER',
        'queryType' => 'QUERY_TYPE',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $basicId = trim((string)($filters['basicId'] ?? ''));
        if ($basicId === '') {
            return [];
        }

        $rows = $this->applySort($this->configQuery($filters), $filters)
            ->limit(500)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->configRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->configQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->configRow(is_array($row) ? $row : $row->toArray());
    }

    private function configQuery(array $filters)
    {
        $query = Db::name('gen_config')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['basicId'])) {
            $query->where('BASIC_ID', (string)$filters['basicId']);
        }

        if (!empty($filters['fieldName'])) {
            $query->whereLike('FIELD_NAME', '%' . trim((string)$filters['fieldName']) . '%');
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

    private function configRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'basicId' => $row['BASIC_ID'] ?? null,
            'isTableKey' => $row['IS_TABLE_KEY'] ?? null,
            'fieldName' => $row['FIELD_NAME'] ?? null,
            'fieldRemark' => $row['FIELD_REMARK'] ?? null,
            'fieldType' => $row['FIELD_TYPE'] ?? null,
            'fieldJavaType' => $row['FIELD_JAVA_TYPE'] ?? null,
            'effectType' => $row['EFFECT_TYPE'] ?? null,
            'dictTypeCode' => $row['DICT_TYPE_CODE'] ?? null,
            'whetherTable' => $row['WHETHER_TABLE'] ?? null,
            'whetherRetract' => $row['WHETHER_RETRACT'] ?? null,
            'whetherAddUpdate' => $row['WHETHER_ADD_UPDATE'] ?? null,
            'whetherRequired' => $row['WHETHER_REQUIRED'] ?? null,
            'queryWhether' => $row['QUERY_WHETHER'] ?? null,
            'queryType' => $row['QUERY_TYPE'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }
}
