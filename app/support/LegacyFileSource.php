<?php

declare(strict_types=1);

namespace app\support;

use RuntimeException;

final class LegacyFileSource
{
    public static function validateDownloadUrlTemplate(string $template): string
    {
        $template = trim($template);
        if (substr_count($template, '{id}') !== 1) {
            throw new RuntimeException('source download URL must contain exactly one {id} placeholder');
        }

        $parts = parse_url(str_replace('{id}', 'probe', $template));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || trim((string)($parts['host'] ?? '')) === '') {
            throw new RuntimeException('source download URL must be an absolute HTTP(S) URL');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('source download URL must not contain credentials');
        }

        return $template;
    }

    public static function host(string $template): string
    {
        $parts = parse_url(str_replace('{id}', 'probe', self::validateDownloadUrlTemplate($template)));

        return strtolower((string)($parts['host'] ?? ''));
    }

    public static function urlFor(string $template, string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('source file ID is empty');
        }

        return str_replace('{id}', rawurlencode($id), self::validateDownloadUrlTemplate($template));
    }

    public static function fetchToCache(string $template, string $id, string $cachePath): ?string
    {
        if (is_file($cachePath) && is_readable($cachePath)) {
            return $cachePath;
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is required for remote file migration');
        }

        $directory = dirname($cachePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('cannot create source cache directory');
        }

        $temporary = $cachePath . '.download-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('cannot create temporary source cache file');
        }

        $curl = curl_init(self::urlFor($template, $id));
        if ($curl === false) {
            fclose($handle);
            @unlink($temporary);
            throw new RuntimeException('cannot initialize source download');
        }

        $contentDisposition = '';
        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$contentDisposition): int {
                if (stripos($header, 'Content-Disposition:') === 0) {
                    $contentDisposition = trim(substr($header, strlen('Content-Disposition:')));
                }

                return strlen($header);
            },
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_USERAGENT => 'OA-Legacy-File-Migration/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        $result = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $declaredLength = (float)curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $contentTypeHeader = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $contentType = strtolower(trim(explode(';', $contentTypeHeader, 2)[0]));
        curl_close($curl);
        fflush($handle);
        fclose($handle);

        if ($result === false) {
            @unlink($temporary);
            throw new RuntimeException('source download failed: ' . ($error !== '' ? $error : 'unknown cURL error'));
        }
        if ($status === 404) {
            @unlink($temporary);
            return null;
        }
        if ($status < 200 || $status >= 300) {
            @unlink($temporary);
            throw new RuntimeException('source download returned HTTP ' . $status);
        }
        if (!str_contains(strtolower($contentDisposition), 'attachment')) {
            @unlink($temporary);
            throw new RuntimeException('source response is not an attachment');
        }
        if (in_array($contentType, ['application/json', 'text/html'], true)) {
            @unlink($temporary);
            throw new RuntimeException('source response has invalid content type: ' . $contentType);
        }

        $actualLength = filesize($temporary);
        if ($actualLength === false) {
            @unlink($temporary);
            throw new RuntimeException('cannot read downloaded source size');
        }
        if ($declaredLength >= 0 && $actualLength !== (int)$declaredLength) {
            @unlink($temporary);
            throw new RuntimeException('source download length mismatch');
        }

        if (@rename($temporary, $cachePath)) {
            return $cachePath;
        }
        if (is_file($cachePath) && hash_file('sha256', $temporary) === hash_file('sha256', $cachePath)) {
            @unlink($temporary);
            return $cachePath;
        }

        @unlink($temporary);
        throw new RuntimeException('cannot finalize source cache file');
    }
}
