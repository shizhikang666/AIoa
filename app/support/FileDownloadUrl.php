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
            return '/backend/dev/file/download?id=' . rawurlencode($id);
        }

        $downloadPath = self::normalizeLegacy($downloadPath);

        return $downloadPath === '' ? null : $downloadPath;
    }

    public static function normalizeLegacy(mixed $downloadPath): ?string
    {
        if ($downloadPath === null) {
            return null;
        }

        $downloadPath = trim((string)$downloadPath);
        if ($downloadPath === '') {
            return null;
        }

        $parts = parse_url($downloadPath);
        if (is_array($parts)) {
            $path = preg_replace('#/+#', '/', (string)($parts['path'] ?? '')) ?? '';
            parse_str((string)($parts['query'] ?? ''), $query);
            $fileId = trim((string)($query['id'] ?? ''));
            if ($fileId !== '' && preg_match('#(?:^|/)dev/file/download/?$#i', $path) === 1) {
                return '/backend/dev/file/download?id=' . rawurlencode($fileId);
            }
        }

        return $downloadPath;
    }
}
