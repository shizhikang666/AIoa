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

    public static function fetchToCache(
        string $template,
        string $id,
        string $cachePath,
        string $cacheRoot
    ): ?string
    {
        $cacheRoot = self::assertPrivateCacheRoot($cacheRoot);
        self::assertSafeCachePath($cacheRoot, $cachePath, true);
        clearstatcache(true, $cachePath);
        if (@lstat($cachePath) !== false) {
            self::assertPrivateCacheFile($cachePath);
            return $cachePath;
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is required for remote file migration');
        }

        $directory = dirname($cachePath);
        self::ensurePrivateCacheDirectory($cacheRoot, $directory);
        self::assertSafeCachePath($cacheRoot, $cachePath, false);

        $temporary = $cachePath . '.download-' . getmypid() . '-' . bin2hex(random_bytes(4));
        self::assertSafeCachePath($cacheRoot, $temporary, false);
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('cannot create temporary source cache file');
        }
        if (DIRECTORY_SEPARATOR === '/' && !@chmod($temporary, 0600)) {
            fclose($handle);
            @unlink($temporary);
            throw new RuntimeException('cannot enforce private temporary cache permissions');
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
        $durable = fflush($handle) && function_exists('fsync') && fsync($handle);
        fclose($handle);

        if (!$durable) {
            @unlink($temporary);
            throw new RuntimeException('downloaded source cache file could not be flushed durably');
        }

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

        return self::finalizePreparedCacheFile($temporary, $cachePath, $cacheRoot);
    }

    public static function finalizePreparedCacheFile(
        string $temporary,
        string $cachePath,
        string $cacheRoot
    ): string {
        $cacheRoot = self::assertPrivateCacheRoot($cacheRoot);
        self::assertSafeCachePath($cacheRoot, $temporary, true);
        self::assertPrivateCacheFile($temporary);
        if (dirname($temporary) !== dirname($cachePath)) {
            throw new RuntimeException('prepared cache file is not in the destination directory');
        }
        $size = filesize($temporary);
        $sha256 = hash_file('sha256', $temporary);
        if ($size === false || !is_string($sha256)) {
            throw new RuntimeException('prepared cache file digest is unavailable');
        }

        self::assertSafeCachePath($cacheRoot, $cachePath, true);
        clearstatcache(true, $cachePath);
        if (@lstat($cachePath) !== false) {
            if (self::cacheFileMatches($cachePath, $size, $sha256)) {
                if (!@unlink($temporary)) {
                    throw new RuntimeException('matching cache race left an unsafe temporary file');
                }
                return $cachePath;
            }
            @unlink($temporary);
            throw new RuntimeException('source cache destination appeared with different content');
        }

        if (!@link($temporary, $cachePath)) {
            self::assertSafeCachePath($cacheRoot, $cachePath, true);
            clearstatcache(true, $cachePath);
            if (@lstat($cachePath) !== false && self::cacheFileMatches($cachePath, $size, $sha256)) {
                if (!@unlink($temporary)) {
                    throw new RuntimeException('matching cache race left an unsafe temporary file');
                }
                return $cachePath;
            }
            @unlink($temporary);
            throw new RuntimeException('cannot finalize source cache file without overwriting');
        }
        if (DIRECTORY_SEPARATOR === '/' && !@chmod($cachePath, 0600)) {
            @unlink($cachePath);
            @unlink($temporary);
            throw new RuntimeException('cannot enforce private source cache file permissions');
        }
        if (!self::cacheFileMatches($cachePath, $size, $sha256)) {
            @unlink($cachePath);
            @unlink($temporary);
            throw new RuntimeException('finalized source cache file failed digest verification');
        }
        if (!@unlink($temporary)) {
            throw new RuntimeException('source cache file was finalized but temporary cleanup failed');
        }

        return $cachePath;
    }

    private static function cacheFileMatches(string $path, int $size, string $sha256): bool
    {
        self::assertPrivateCacheFile($path);
        $actualSize = filesize($path);
        $actualSha256 = hash_file('sha256', $path);

        return $actualSize === $size
            && is_string($actualSha256)
            && hash_equals($sha256, $actualSha256);
    }

    private static function assertPrivateCacheRoot(string $cacheRoot): string
    {
        $real = realpath($cacheRoot);
        if ($real === false || !is_dir($real)) {
            throw new RuntimeException('source cache root must already exist');
        }
        self::assertNoUnsafeComponents($real);
        if (DIRECTORY_SEPARATOR === '/') {
            $permissions = fileperms($real);
            if ($permissions === false || ($permissions & 0777) !== 0700) {
                throw new RuntimeException('source cache root permissions must be exactly 0700');
            }
        }

        return rtrim($real, '/\\');
    }

    private static function assertPrivateCacheFile(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (!is_array($stat) || self::isLinkOrReparse($path, $stat) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('source cache entry is not a safe regular file');
        }
        if (DIRECTORY_SEPARATOR === '/') {
            $permissions = fileperms($path);
            if ($permissions === false || ($permissions & 0077) !== 0) {
                throw new RuntimeException('source cache file permissions are too broad');
            }
        }
    }

    private static function ensurePrivateCacheDirectory(string $cacheRoot, string $directory): void
    {
        self::assertSafeCachePath($cacheRoot, $directory, true, true);
        $relative = self::relativeCachePath($cacheRoot, $directory);
        $current = $cacheRoot;
        foreach ($relative as $part) {
            $current .= DIRECTORY_SEPARATOR . $part;
            clearstatcache(true, $current);
            $stat = @lstat($current);
            if ($stat === false) {
                if (!@mkdir($current, 0700, false)) {
                    clearstatcache(true, $current);
                    if (!is_dir($current)) {
                        throw new RuntimeException('cannot create private source cache directory');
                    }
                }
                clearstatcache(true, $current);
                $stat = @lstat($current);
            }
            if (!is_array($stat) || self::isLinkOrReparse($current, $stat) || !is_dir($current)) {
                throw new RuntimeException('source cache directory contains an unsafe component');
            }
            if (DIRECTORY_SEPARATOR === '/' && !@chmod($current, 0700)) {
                throw new RuntimeException('cannot enforce private source cache directory permissions');
            }
        }
        self::assertSafeCachePath($cacheRoot, $directory, true, true);
    }

    private static function assertSafeCachePath(
        string $cacheRoot,
        string $path,
        bool $allowExistingLeaf,
        bool $leafIsDirectory = false
    ): void {
        $cacheRoot = self::assertPrivateCacheRoot($cacheRoot);
        $parts = self::relativeCachePath($cacheRoot, $path);
        if ($parts === []) {
            if (!$leafIsDirectory) {
                throw new RuntimeException('cache file path equals the cache root');
            }
            return;
        }
        $current = $cacheRoot;
        foreach ($parts as $index => $part) {
            $current .= DIRECTORY_SEPARATOR . $part;
            clearstatcache(true, $current);
            $stat = @lstat($current);
            if ($stat === false) {
                continue;
            }
            if (self::isLinkOrReparse($current, $stat)) {
                throw new RuntimeException('source cache path contains a symlink or reparse point');
            }
            $leaf = $index === count($parts) - 1;
            if (!$leaf && !is_dir($current)) {
                throw new RuntimeException('source cache path contains a file-versus-directory conflict');
            }
            if ($leaf && (!$allowExistingLeaf
                || ($leafIsDirectory && !is_dir($current))
                || (!$leafIsDirectory && !is_file($current)))) {
                throw new RuntimeException('source cache destination already exists with an unsafe type');
            }
        }
    }

    /** @return list<string> */
    private static function relativeCachePath(string $cacheRoot, string $path): array
    {
        $rootKey = self::pathKey($cacheRoot);
        $pathKey = self::pathKey($path);
        if ($pathKey === $rootKey) {
            return [];
        }
        if (!str_starts_with($pathKey, $rootKey . '/')) {
            throw new RuntimeException('source cache path escaped the private cache root');
        }
        $portableRoot = rtrim(str_replace('\\', '/', $cacheRoot), '/');
        $portablePath = str_replace('\\', '/', $path);
        $relative = substr($portablePath, strlen($portableRoot) + 1);
        $parts = array_values(array_filter(explode('/', $relative), 'strlen'));
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || str_contains($part, "\0")) {
                throw new RuntimeException('source cache path contains an unsafe component');
            }
        }

        return $parts;
    }

    private static function pathKey(string $path): string
    {
        $portable = rtrim(str_replace('\\', '/', trim($path)), '/');
        if ($portable === '' || str_contains($portable, "\0")
            || preg_match('#(?:\A|/)\.\.?(?:/|\z)#', $portable) === 1) {
            throw new RuntimeException('source cache path is invalid');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $portable = strtolower($portable);
        }

        return $portable;
    }

    private static function assertNoUnsafeComponents(string $path): void
    {
        $portable = str_replace('\\', '/', $path);
        if (preg_match('#\A([A-Za-z]:)/(.*)\z#', $portable, $match) === 1) {
            $current = $match[1] . DIRECTORY_SEPARATOR;
            $parts = array_values(array_filter(explode('/', $match[2]), 'strlen'));
        } elseif (str_starts_with($portable, '/')) {
            $current = DIRECTORY_SEPARATOR;
            $parts = array_values(array_filter(explode('/', ltrim($portable, '/')), 'strlen'));
        } else {
            throw new RuntimeException('source cache root must be absolute');
        }
        foreach ($parts as $part) {
            $current = rtrim($current, '/\\') . DIRECTORY_SEPARATOR . $part;
            clearstatcache(true, $current);
            $stat = @lstat($current);
            if (!is_array($stat) || self::isLinkOrReparse($current, $stat)) {
                throw new RuntimeException('source cache root contains an unsafe component');
            }
        }
    }

    /** @param array<int|string, mixed> $stat */
    private static function isLinkOrReparse(string $path, array $stat): bool
    {
        $mode = (int)($stat['mode'] ?? $stat[2] ?? 0);
        if (($mode & 0170000) === 0120000 || is_link($path)) {
            return true;
        }
        // PHP reports Windows junction/reparse-point lstat mode as 0.
        return PHP_OS_FAMILY === 'Windows' && $mode === 0;
    }
}
