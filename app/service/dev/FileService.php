<?php

declare(strict_types=1);

namespace app\service\dev;

use app\support\FileDownloadUrl;
use RuntimeException;
use think\facade\Db;
use think\file\UploadedFile;

/**
 * File metadata, local upload, and local download compatibility for Java DevFileController.
 */
class FileService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const ENGINE_LOCAL = 'LOCAL';
    private const BUCKET_LOCAL = 'defaultBucketName';
    private const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;
    private const BLOCKED_SUFFIXES = [
        'bat',
        'cmd',
        'com',
        'exe',
        'js',
        'msi',
        'php',
        'ps1',
        'sh',
        'vbs',
    ];
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

    public function uploadReturnId(?string $engine, mixed $file, array $payload = []): string
    {
        return (string)$this->uploadReturnFile($engine, $file, $payload)['id'];
    }

    public function uploadReturnUrl(?string $engine, mixed $file, array $payload = []): string
    {
        return (string)$this->uploadReturnFile($engine, $file, $payload)['downloadPath'];
    }

    public function uploadReturnFile(?string $engine, mixed $file, array $payload = []): array
    {
        if (!$file instanceof UploadedFile) {
            throw new RuntimeException('missing file', 400);
        }

        $engine = $this->resolvedEngine($engine);
        if ($engine !== self::ENGINE_LOCAL) {
            throw new RuntimeException('unsupported file engine: ' . $engine, 501);
        }

        $sourcePath = $file->getRealPath() ?: $file->getPathname();
        if (!is_string($sourcePath) || $sourcePath === '' || !is_file($sourcePath)) {
            throw new RuntimeException('invalid uploaded file', 400);
        }

        $originalName = $this->originalName($file);
        $suffix = $this->suffix($originalName);
        $size = filesize($sourcePath);
        $size = $size === false ? 0 : $size;
        $this->assertUploadAllowed($suffix, $size);

        $id = $this->newId();
        $objName = $suffix !== null ? $id . '.' . $suffix : null;
        $relativeKey = date('Y') . DIRECTORY_SEPARATOR . (int)date('n') . DIRECTORY_SEPARATOR . (int)date('j');
        $targetDir = $this->localRoot() . DIRECTORY_SEPARATOR . self::BUCKET_LOCAL . DIRECTORY_SEPARATOR . $relativeKey;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('cannot create upload directory', 500);
        }

        $storagePath = $targetDir . DIRECTORY_SEPARATOR . ($objName ?? $id);
        if (!copy($sourcePath, $storagePath)) {
            throw new RuntimeException('cannot store uploaded file', 500);
        }

        $now = date('Y-m-d H:i:s');
        $downloadPath = '/api/dev/file/download?id=' . rawurlencode($id);
        $row = [
            'ID' => $id,
            'ENGINE' => self::ENGINE_LOCAL,
            'BUCKET' => self::BUCKET_LOCAL,
            'NAME' => $originalName,
            'SUFFIX' => $suffix,
            'SIZE_KB' => (string)(int)round($size / 1024),
            'SIZE_INFO' => $this->readableSize($size),
            'OBJ_NAME' => $objName,
            'STORAGE_PATH' => $storagePath,
            'DOWNLOAD_PATH' => $downloadPath,
            'THUMBNAIL' => null,
            'EXT_JSON' => null,
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $this->currentUserId($payload),
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $this->tenantId($payload),
        ];

        try {
            Db::name('dev_file')->insert($row);
        } catch (\Throwable $exception) {
            @unlink($storagePath);
            throw $exception;
        }

        return $this->fileRow($row);
    }

    /**
     * @param array<int, string> $ids
     */
    public function delete(array $ids, array $payload = []): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $tenantId = $this->requiredTenantId($payload);
        Db::transaction(function () use ($ids, $tenantId, $payload): void {
            Db::name('dev_file')
                ->whereIn('ID', $ids)
                ->where('TENANT_ID', $tenantId)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $this->currentUserId($payload),
                ]);
        });

        return null;
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

    private function resolvedEngine(?string $engine): string
    {
        $engine = strtoupper(trim((string)$engine));
        if ($engine !== '') {
            return $engine;
        }

        $configured = Db::name('dev_config')
            ->where('CONFIG_KEY', 'SNOWY_SYS_DEFAULT_FILE_ENGINE')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->value('CONFIG_VALUE');

        $configured = strtoupper(trim((string)$configured));

        return $configured === '' ? self::ENGINE_LOCAL : $configured;
    }

    private function localRoot(): string
    {
        $configured = trim((string)(getenv('DEV_FILE_LOCAL_ROOT') ?: ''));
        $root = $configured !== ''
            ? $configured
            : app()->getRuntimePath() . 'upload' . DIRECTORY_SEPARATOR . 'dev_file';
        $root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
        if (!$this->isAbsolutePath($root)) {
            $root = app()->getRootPath() . ltrim($root, DIRECTORY_SEPARATOR);
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function originalName(UploadedFile $file): string
    {
        $name = trim($file->getOriginalName());
        $name = str_replace('\\', '/', $name);
        $name = basename($name);

        return $name === '' ? 'upload' : $name;
    }

    private function suffix(string $filename): ?string
    {
        $suffix = trim((string)pathinfo($filename, PATHINFO_EXTENSION));

        return $suffix === '' ? null : $suffix;
    }

    private function assertUploadAllowed(?string $suffix, int $size): void
    {
        if ($size <= 0) {
            throw new RuntimeException('invalid uploaded file', 400);
        }

        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('uploaded file is too large', 400);
        }

        $suffix = strtolower((string)$suffix);
        if ($suffix !== '' && in_array($suffix, self::BLOCKED_SUFFIXES, true)) {
            throw new RuntimeException('unsupported uploaded file type', 400);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR);
    }

    private function readableSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $index => $unit) {
            if ($value < 1024 || $index === count($units) - 1) {
                $text = number_format($value, $value >= 100 ? 0 : 2);

                return rtrim(rtrim($text, '0'), '.') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return $bytes . ' B';
    }

    private function currentUserId(array $payload): ?string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));

        return $userId === '' ? null : $userId;
    }

    private function tenantId(array $payload): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId === '' ? '1' : $tenantId;
    }

    private function requiredTenantId(array $payload): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            return $tenantId;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== null) {
            $tenantId = trim((string)Db::name('sys_user')->where('ID', $userId)->value('TENANT_ID'));
            if ($tenantId !== '') {
                return $tenantId;
            }
        }

        throw new RuntimeException('missing tenantId', 400);
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
