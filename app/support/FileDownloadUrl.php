<?php

declare(strict_types=1);

namespace app\support;

class FileDownloadUrl
{
    public static function normalize(mixed $id, mixed $engine, mixed $downloadPath = null): ?string
    {
        $engine = strtoupper(trim((string)$engine));
        $id = trim((string)$id);
        if ($engine === 'LOCAL' && $id !== '') {
            return '/api/dev/file/download?id=' . rawurlencode($id);
        }

        $downloadPath = $downloadPath === null ? null : trim((string)$downloadPath);

        return $downloadPath === '' ? null : $downloadPath;
    }
}
