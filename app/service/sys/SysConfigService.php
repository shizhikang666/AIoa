<?php

declare(strict_types=1);

namespace app\service\sys;

use think\facade\Db;

/**
 * Read-only system configuration queries compatible with Java SysConfigController.
 */
class SysConfigService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DEFAULT_PROCESS_KEYS = [
        'Process_reimbursement',
        'Process_sale_project_play',
        'Process_sale_project_init',
        'Process_sale_project_delivery',
        'Process_procure',
        'Process_project_reissue_product',
        'Process_make_payment',
        'Process_payment',
        'Process_procure_in_warehouse',
        'Process_sale_project_product_return',
        'Process_ask_leave',
    ];

    public function detail(?string $tenantId = null): array
    {
        $row = $this->configRow($tenantId);
        if (!$row) {
            return $this->defaultConfig();
        }

        $decoded = json_decode((string)($row['CONFIG_JSON'] ?? ''), true);
        if (!is_array($decoded)) {
            return $this->defaultConfig();
        }

        $decoded['processConfigMap'] = $this->normalizeProcessConfigMap($decoded['processConfigMap'] ?? []);

        return $decoded;
    }

    private function configRow(?string $tenantId): ?array
    {
        $query = Db::name('sys_config')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== null && $tenantId !== '') {
            $tenantRow = (clone $query)
                ->where('TENANT_ID', $tenantId)
                ->order('UPDATE_TIME', 'desc')
                ->order('CREATE_TIME', 'desc')
                ->order('ID', 'desc')
                ->find();

            if ($tenantRow) {
                return is_array($tenantRow) ? $tenantRow : $tenantRow->toArray();
            }
        }

        $row = $query
            ->where('TENANT_ID', '0')
            ->order('UPDATE_TIME', 'desc')
            ->order('CREATE_TIME', 'desc')
            ->order('ID', 'desc')
            ->find();

        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * @param mixed $value
     * @return array<string, array<string, mixed>>
     */
    private function normalizeProcessConfigMap(mixed $value): array
    {
        $map = is_array($value) ? $value : [];
        foreach (self::DEFAULT_PROCESS_KEYS as $key) {
            $map[$key] = $this->normalizeProcessConfig(is_array($map[$key] ?? null) ? $map[$key] : []);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeProcessConfig(array $config): array
    {
        return [
            'open' => array_key_exists('open', $config) ? (bool)$config['open'] : true,
            'approveUserIdList' => is_array($config['approveUserIdList'] ?? null) ? array_values($config['approveUserIdList']) : [],
            'copyUserIdList' => is_array($config['copyUserIdList'] ?? null) ? array_values($config['copyUserIdList']) : [],
            'treasurer' => (string)($config['treasurer'] ?? ''),
            'procure' => (string)($config['procure'] ?? ''),
        ];
    }

    private function defaultConfig(): array
    {
        return [
            'processConfigMap' => $this->normalizeProcessConfigMap([]),
        ];
    }
}
