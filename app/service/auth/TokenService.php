<?php

namespace app\service\auth;

use think\facade\Cache;
use think\Request;

class TokenService
{
    private const KEY_PREFIX = 'oa:auth:';
    private const TOKEN_TTL = 7200;

    public function create(array $user, array $authContext): string
    {
        $token = bin2hex(random_bytes(32));
        $now = time();

        $payload = [
            'user_id' => (string)($user['ID'] ?? ''),
            'tenant_id' => (string)($user['TENANT_ID'] ?? ''),
            'account' => $user['ACCOUNT'] ?? null,
            'name' => $user['NAME'] ?? null,
            'org_id' => $user['ORG_ID'] ?? null,
            'device' => $authContext['device'] ?? 'PC',
            'role_ids' => $authContext['role_ids'] ?? [],
            'role_codes' => $authContext['role_codes'] ?? [],
            'button_codes' => $authContext['button_codes'] ?? [],
            'mobile_button_codes' => $authContext['mobile_button_codes'] ?? [],
            'permission_codes' => $authContext['permission_codes'] ?? [],
            'menu_ids' => $authContext['menu_ids'] ?? [],
            'login_at' => $now,
            'expires_at' => $now + self::TOKEN_TTL,
        ];

        Cache::set($this->tokenKey($token), $payload, self::TOKEN_TTL);

        return $token;
    }

    public function getPayload(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        $payload = Cache::get($this->tokenKey($token));
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['expires_at'] ?? 0) < time()) {
            $this->revoke($token);
            return null;
        }

        return $payload;
    }

    public function revoke(?string $token): void
    {
        if ($token !== null && $token !== '') {
            Cache::delete($this->tokenKey($token));
        }
    }

    public function bearerFromRequest(Request $request): ?string
    {
        $authorization = $request->header('Authorization') ?: $request->header('authorization');
        if (!is_string($authorization)) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function tokenKey(string $token): string
    {
        return self::KEY_PREFIX . 'token:' . hash('sha256', $token);
    }
}
