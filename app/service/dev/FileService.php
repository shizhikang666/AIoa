<?php

declare(strict_types=1);

namespace app\service\dev;

use app\support\FileDownloadUrl;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only file metadata queries compatible with Java DevFileController.
 */
class FileService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const LIST_LIMIT = 200;
    private const METADATA_COLUMNS = [
        'ID',
        'ENGINE',
        'BUCKET',
        'NAME',
        'SUFFIX',
        'SIZE_KB',
        'SIZE_INFO',
        'OBJ_NAME',
        'STORAGE_PATH',
        'DOWNLOAD_PATH',
        'EXT_JSON',
        'TENANT_ID',
        'CREATE_TIME',
        'CREATE_USER',
        'UPDATE_TIME',
        'UPDATE_USER',
    ];
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'engine' => 'ENGINE',
        'bucket' => 'BUCKET',
        'name' => 'NAME',
        'suffix' => 'SUFFIX',
        'sizeKb' => 'SIZE_KB',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->fileQuery($filters, $tenantId, true)->count();
        $rows = $this->applySort($this->fileQuery($filters, $tenantId, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->fileRow($row), $rows),
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
    public function list(array $filters = [], ?string $tenantId = null): array
    {
        $rows = $this->applySort($this->fileQuery($filters, $tenantId, true), $filters)
            ->field(self::METADATA_COLUMNS)
            ->limit(self::LIST_LIMIT)
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->fileRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->fileQuery(['id' => $id], null, false)->find();
        if (!$row) {
            return null;
        }

        return $this->fileRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @return array{filename:string, contentType:string, content:string}
     */
    public function download(string $id): array
    {
        $row = $this->fileQuery(['id' => $id], null, false)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('文件不存在，id值为：' . $id, 500);
        }

        $engine = strtoupper(trim((string)($row['ENGINE'] ?? '')));
        if ($engine !== 'LOCAL') {
            throw new RuntimeException('非本地文件不支持此方式下载，id值为：' . $id, 500);
        }

        $path = trim((string)($row['STORAGE_PATH'] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('找不到存储的文件，id值为：' . $id, 500);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('找不到存储的文件，id值为：' . $id, 500);
        }

        return [
            'filename' => trim((string)($row['NAME'] ?? '')) !== '' ? (string)$row['NAME'] : basename($path),
            'contentType' => 'application/octet-stream;charset=UTF-8',
            'content' => $content,
        ];
    }

    private function fileQuery(array $filters, ?string $tenantId, bool $tenantScoped)
    {
        $query = Db::name('dev_file')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['engine'])) {
            $query->where('ENGINE', (string)$filters['engine']);
        }

        if (!empty($filters['suffix'])) {
            $query->where('SUFFIX', (string)$filters['suffix']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if ($tenantScoped && $tenantId !== null && $tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
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

        return $query->order('CREATE_TIME', 'desc')->order('ID', 'desc');
    }

    private function fileRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'engine' => $row['ENGINE'] ?? null,
            'bucket' => $row['BUCKET'] ?? null,
            'name' => $row['NAME'] ?? null,
            'suffix' => $row['SUFFIX'] ?? null,
            'sizeKb' => $row['SIZE_KB'] ?? null,
            'sizeInfo' => $row['SIZE_INFO'] ?? null,
            'objName' => $row['OBJ_NAME'] ?? null,
            'storagePath' => $row['STORAGE_PATH'] ?? null,
            'downloadPath' => FileDownloadUrl::normalize($row['ID'] ?? null, $row['ENGINE'] ?? null, $row['DOWNLOAD_PATH'] ?? null),
            'thumbnail' => $row['THUMBNAIL'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(100, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
