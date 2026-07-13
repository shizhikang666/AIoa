<?php

declare(strict_types=1);

namespace app\service\sys;

use RuntimeException;
use think\facade\Db;

/**
 * System configuration queries and writes compatible with Java SysConfigController.
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

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function edit(array $input, mixed $payload = []): array
    {
        if (!array_key_exists('config', $input) || !is_array($input['config'])) {
            throw new RuntimeException('missing config', 400);
        }
        if (!array_key_exists('processConfigMap', $input['config']) || !is_array($input['config']['processConfigMap'])) {
            throw new RuntimeException('missing processConfigMap', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        if (!$this->isAdminCompatible($payload)) {
            throw new RuntimeException('permission denied', 403);
        }

        $tenantId = $this->tenantIdForWrite($payload);
        $config = $this->normalizeConfig($input['config']);
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($configJson === false) {
            throw new RuntimeException('invalid config', 400);
        }

        $row = $this->configRowForWrite($tenantId);
        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);

        if ($row === null) {
            Db::name('sys_config')->insert([
                'ID' => $this->newId(),
                'CONFIG_JSON' => $configJson,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ]);
        } else {
            Db::name('sys_config')
                ->where('ID', (string)$row['ID'])
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'CONFIG_JSON' => $configJson,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                ]);
        }

        return $config;
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

    private function configRowForWrite(string $tenantId): ?array
    {
        $query = Db::name('sys_config')
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->order('UPDATE_TIME', 'desc')
            ->order('CREATE_TIME', 'desc')
            ->order('ID', 'desc');

        $row = $query->find();

        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $config['processConfigMap'] = $this->normalizeProcessConfigMap($config['processConfigMap'] ?? []);

        return $config;
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

    private function tenantIdForWrite(array $payload): string
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '0';
    }

    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function isAdminCompatible(array $payload): bool
    {
        $account = strtolower(trim((string)($payload['account'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            return true;
        }

        foreach ($this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []) as $roleCode) {
            if (in_array(strtolower($roleCode), ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['code'] ?? $item['roleCode'] ?? $item['value'] ?? $item['key'] ?? '';
            }
            $item = trim((string)$item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
