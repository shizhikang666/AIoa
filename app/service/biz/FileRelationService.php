<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only file-relation queries compatible with Java BizFileRelationController.
 */
class FileRelationService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const RELATION_FIELDS = <<<SQL
r.ID AS ID,
r.OBJECT_ID AS OBJECT_ID,
r.TARGET_ID AS TARGET_ID,
r.CATEGORY AS CATEGORY,
r.FILE_NAME AS FILE_NAME,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.EXT_JSON AS EXT_JSON,
r.TENANT_ID AS TENANT_ID,
f.ENGINE AS ENGINE,
f.BUCKET AS BUCKET,
f.NAME AS NAME,
f.SUFFIX AS SUFFIX,
f.SIZE_KB AS SIZE_KB,
f.SIZE_INFO AS SIZE_INFO,
f.OBJ_NAME AS OBJ_NAME,
f.STORAGE_PATH AS STORAGE_PATH,
f.DOWNLOAD_PATH AS DOWNLOAD_PATH,
f.THUMBNAIL AS THUMBNAIL,
f.EXT_JSON AS FILE_EXT_JSON,
u.NAME AS CREATE_USER_NAME,
u.AVATAR AS AVATAR
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'r.ID',
        'objectId' => 'r.OBJECT_ID',
        'targetId' => 'r.TARGET_ID',
        'category' => 'r.CATEGORY',
        'fileName' => 'r.FILE_NAME',
        'createTime' => 'r.CREATE_TIME',
        'createUser' => 'r.CREATE_USER',
        'tenantId' => 'r.TENANT_ID',
        'name' => 'f.NAME',
        'suffix' => 'f.SUFFIX',
        'sizeKb' => 'f.SIZE_KB',
        'createUserName' => 'u.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->relationQuery($filters, $payload)->count();
        $rows = $this->applySort($this->relationQuery($filters, $payload), $filters)
            ->field(self::RELATION_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->relationRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->relationQuery($filters, $payload), $filters)
            ->field(self::RELATION_FIELDS)
            ->select()
            ->toArray();

        return $this->relationRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->relationQuery(['id' => $id], $payload)
            ->field(self::RELATION_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('file relation not found', 404);
        }

        return $this->relationRows([$row])[0];
    }

    private function relationQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_file_relation')
            ->alias('r')
            ->leftJoin('dev_file f', 'f.ID = r.TARGET_ID')
            ->leftJoin('sys_user u', 'u.ID = r.CREATE_USER')
            ->where(function ($query): void {
                $query->whereNull('r.DELETE_FLAG')->whereOr('r.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'r.ID',
            'objectId' => 'r.OBJECT_ID',
            'targetId' => 'r.TARGET_ID',
            'category' => 'r.CATEGORY',
            'createUser' => 'r.CREATE_USER',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['fileName'])) {
            $keyword = '%' . trim((string)$filters['fileName']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('r.FILE_NAME', $keyword)
                    ->whereOr('f.NAME', 'like', $keyword);
            });
        }

        if (!empty($filters['name'])) {
            $query->whereLike('f.NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['suffix'])) {
            $query->where('f.SUFFIX', (string)$filters['suffix']);
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('r.FILE_NAME', $keyword)
                    ->whereOr('f.NAME', 'like', $keyword)
                    ->whereOr('r.CATEGORY', 'like', $keyword)
                    ->whereOr('u.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'r.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('r.ID', 'asc');
        }

        return $query->order('r.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function relationRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->relationRow($row), $rows);
    }

    private function relationRow(array $row): array
    {
        $fileName = $this->value($row, 'FILE_NAME', 'fileName');
        $linkedName = $this->value($row, 'NAME', 'name');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
            'targetId' => $this->value($row, 'TARGET_ID', 'targetId'),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'fileName' => $fileName ?: $linkedName,
            'relationFileName' => $fileName,
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'engine' => $this->value($row, 'ENGINE', 'engine'),
            'bucket' => $this->value($row, 'BUCKET', 'bucket'),
            'name' => $linkedName,
            'suffix' => $this->value($row, 'SUFFIX', 'suffix'),
            'sizeKb' => $this->intValue($this->value($row, 'SIZE_KB', 'sizeKb')),
            'sizeInfo' => $this->value($row, 'SIZE_INFO', 'sizeInfo'),
            'objName' => $this->value($row, 'OBJ_NAME', 'objName'),
            'storagePath' => $this->value($row, 'STORAGE_PATH', 'storagePath'),
            'downloadPath' => $this->value($row, 'DOWNLOAD_PATH', 'downloadPath'),
            'thumbnail' => $this->value($row, 'THUMBNAIL', 'thumbnail'),
            'fileExtJson' => $this->value($row, 'FILE_EXT_JSON', 'fileExtJson'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function intValue(mixed $value): ?int
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
