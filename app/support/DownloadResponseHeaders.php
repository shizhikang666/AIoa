<?php

declare(strict_types=1);

namespace app\support;

final class DownloadResponseHeaders
{
    public static function contentDisposition(string $filename, string $disposition = 'attachment'): string
    {
        $filename = self::cleanFilename($filename);
        $fallback = self::fallbackFilename($filename);

        return $disposition . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
    }

    private static function cleanFilename(string $filename): string
    {
        $filename = trim(str_replace(["\r", "\n", "\0"], '', $filename));

        return $filename !== '' ? $filename : 'download';
    }

    private static function fallbackFilename(string $filename): string
    {
        $extension = '';
        if (preg_match('/(\.[A-Za-z0-9]{1,12})$/', $filename, $matches) === 1) {
            $extension = $matches[1];
            $filename = substr($filename, 0, -strlen($extension));
        }

        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: '';
        $base = trim($base, '._-');
        if ($base === '') {
            $base = 'download';
        }

        return str_replace(['"', '\\'], '_', $base . $extension);
    }
}
