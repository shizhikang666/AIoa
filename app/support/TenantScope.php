<?php

declare(strict_types=1);

namespace app\support;

use RuntimeException;

final class TenantScope
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function tenantId(array $payload): string
    {
        return trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
    }

    /**
     * Only the platform super administrator may cross tenant boundaries.
     *
     * @param array<string, mixed> $payload
     */
    public static function canCrossTenant(array $payload): bool
    {
        if (strtolower(trim((string)($payload['account'] ?? ''))) === 'superadmin') {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
        if (is_string($roleCodes)) {
            $roleCodes = explode(',', $roleCodes);
        }
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (is_array($roleCode)) {
                $roleCode = $roleCode['code'] ?? $roleCode['roleCode'] ?? $roleCode['value'] ?? '';
            }
            if (strtolower(trim((string)$roleCode)) === 'superadmin') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed>|mixed $payload
     * @return array<string, mixed>
     */
    public static function scopedFilters(array $filters, mixed $payload): array
    {
        if (!is_array($payload) || $payload === [] || self::canCrossTenant($payload)) {
            return $filters;
        }

        $tenantId = self::tenantId($payload);
        if ($tenantId === '') {
            throw new RuntimeException('permission denied', 403);
        }

        $filters['tenantId'] = $tenantId;

        return $filters;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function assertCompatible(array $payload, mixed $tenantId): void
    {
        if ($payload === [] || self::canCrossTenant($payload)) {
            return;
        }

        $payloadTenantId = self::tenantId($payload);
        $rowTenantId = trim((string)$tenantId);
        if ($payloadTenantId === '' || $rowTenantId === '' || $payloadTenantId !== $rowTenantId) {
            throw new RuntimeException('permission denied', 403);
        }
    }
}
