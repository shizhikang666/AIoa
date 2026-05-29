<?php

declare(strict_types=1);

namespace app\service\dev;

/**
 * Safe read-only runtime monitor information.
 */
class MonitorService
{
    public function serverInfo(): array
    {
        $diskRoot = $this->diskRoot();
        $diskTotal = $diskRoot ? (int)@disk_total_space($diskRoot) : 0;
        $diskFree = $diskRoot ? (int)@disk_free_space($diskRoot) : 0;
        $diskUsed = max(0, $diskTotal - $diskFree);
        $memoryLimit = $this->memoryLimitBytes();
        $memoryUsed = memory_get_usage(true);
        $memoryFree = $memoryLimit > 0 ? max(0, $memoryLimit - $memoryUsed) : 0;

        return [
            'devMonitorCpuInfo' => [
                'cpuName' => php_uname('m') ?: 'unknown',
                'cpuNum' => $this->logicalCpuCount() . ' logical CPU',
                'cpuPhysicalCoreNum' => 'unknown',
                'cpuLogicalCoreNum' => $this->logicalCpuCount() . ' logical cores',
                'cpuSysUseRate' => '0%',
                'cpuUserUseRate' => '0%',
                'cpuTotalUseRate' => 0.0,
                'cpuWaitRate' => '0%',
                'cpuFreeRate' => '0%',
            ],
            'devMonitorMemoryInfo' => [
                'memoryTotal' => $memoryLimit > 0 ? $this->formatBytes($memoryLimit) : 'unlimited',
                'memoryUsed' => $this->formatBytes($memoryUsed),
                'memoryFree' => $memoryLimit > 0 ? $this->formatBytes($memoryFree) : 'unlimited',
                'memoryUseRate' => $memoryLimit > 0 ? $this->percent($memoryUsed, $memoryLimit) : 0.0,
            ],
            'devMonitorStorageInfo' => [
                'storageTotal' => $this->formatBytes($diskTotal),
                'storageUsed' => $this->formatBytes($diskUsed),
                'storageFree' => $this->formatBytes($diskFree),
                'storageUseRate' => $this->percent($diskUsed, $diskTotal),
            ],
            'devMonitorServerInfo' => [
                'serverName' => gethostname() ?: php_uname('n'),
                'serverOs' => php_uname('s') . ' ' . php_uname('r'),
                'serverIp' => gethostbyname(gethostname() ?: 'localhost'),
                'serverArchitecture' => php_uname('m'),
            ],
            'devMonitorJvmInfo' => [
                'jvmName' => 'PHP',
                'jvmVersion' => PHP_VERSION,
                'jvmMemoryTotal' => $memoryLimit > 0 ? $this->formatBytes($memoryLimit) : 'unlimited',
                'jvmMemoryUsed' => $this->formatBytes($memoryUsed),
                'jvmMemoryFree' => $memoryLimit > 0 ? $this->formatBytes($memoryFree) : 'unlimited',
                'jvmUseRate' => $memoryLimit > 0 ? $this->percent($memoryUsed, $memoryLimit) : 0.0,
                'jvmStartTime' => null,
                'jvmRunTime' => null,
                'javaVersion' => PHP_VERSION,
                'javaPath' => PHP_BINARY,
            ],
        ];
    }

    private function logicalCpuCount(): int
    {
        $count = (int)(getenv('NUMBER_OF_PROCESSORS') ?: 0);

        return max(1, $count);
    }

    private function diskRoot(): ?string
    {
        $root = realpath(getcwd() ?: __DIR__);
        if ($root === false) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $drive = substr($root, 0, 3);

            return $drive !== false ? $drive : $root;
        }

        return '/';
    }

    private function memoryLimitBytes(): int
    {
        $value = trim((string)ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float)$value;

        return match ($unit) {
            'g' => (int)($number * 1024 * 1024 * 1024),
            'm' => (int)($number * 1024 * 1024),
            'k' => (int)($number * 1024),
            default => (int)$number,
        };
    }

    private function percent(int|float $part, int|float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 2);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;
        $value = (float)$bytes;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, 2) . ' ' . $units[$index];
    }
}
