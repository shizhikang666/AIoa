<?php

namespace app\service\auth;

use think\facade\Cache;
use think\facade\Db;
use think\Request;

class TokenService
{
    private const KEY_PREFIX = 'oa:auth:';
    private const TOKEN_TTL = 7200;
    private const ONLINE_USERS_KEY = self::KEY_PREFIX . 'online-users';

    public function create(array $user, array $authContext): string
    {
        $token = bin2hex(random_bytes(32));
        $now = time();
        $expiresAt = $now + self::TOKEN_TTL;

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
            'data_scope_org_ids' => $authContext['data_scope_org_ids'] ?? [],
            'data_scopes' => $authContext['data_scopes'] ?? [],
            'login_at' => $now,
            'expires_at' => $expiresAt,
            'token_hash' => $this->tokenHash($token),
        ];

        Cache::set($this->tokenKey($token), $payload, self::TOKEN_TTL);
        $this->indexToken($token, $payload);

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

        $normalizedPayload = $this->normalizePayload($payload);
        if ($normalizedPayload !== $payload) {
            Cache::set(
                $this->tokenKey($token),
                $normalizedPayload,
                max(1, (int)($normalizedPayload['expires_at'] ?? time()) - time())
            );
        }

        return $normalizedPayload;
    }

    public function revoke(?string $token): void
    {
        if ($token !== null && $token !== '') {
            $payload = Cache::get($this->tokenKey($token));
            Cache::delete($this->tokenKey($token));
            if (is_array($payload)) {
                $this->removeIndexedToken($token, (string)($payload['user_id'] ?? ''));
            }
        }
    }

    public function revokeUserTokens(string $userId): int
    {
        $tokens = $this->tokensForUser($userId);
        $count = 0;

        foreach ($tokens as $token) {
            $tokenValue = (string)($token['tokenValue'] ?? '');
            if ($tokenValue === '') {
                continue;
            }

            $this->revoke($tokenValue);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<int, string> $tokenValues
     */
    public function revokeTokens(array $tokenValues): int
    {
        $count = 0;
        $seen = [];

        foreach ($tokenValues as $tokenValue) {
            $tokenValue = trim((string)$tokenValue);
            if ($tokenValue === '' || isset($seen[$tokenValue])) {
                continue;
            }
            $seen[$tokenValue] = true;

            $payload = Cache::get($this->tokenKey($tokenValue));
            $this->revoke($tokenValue);
            if (is_array($payload)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tokensForUser(string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            return [];
        }

        $indexedTokens = $this->readUserTokens($userId);
        $activeIndex = [];
        $activeRows = [];
        $now = time();

        foreach ($indexedTokens as $entry) {
            $tokenValue = (string)($entry['tokenValue'] ?? '');
            if ($tokenValue === '') {
                continue;
            }

            $payload = Cache::get($this->tokenKey($tokenValue));
            if (
                !is_array($payload)
                || (string)($payload['user_id'] ?? '') !== $userId
                || (int)($payload['expires_at'] ?? 0) < $now
            ) {
                Cache::delete($this->tokenKey($tokenValue));
                continue;
            }

            $tokenHash = $this->tokenHash($tokenValue);
            $row = [
                'tokenValue' => $tokenValue,
                'tokenHash' => $tokenHash,
                'userId' => $userId,
                'device' => $entry['device'] ?? $payload['device'] ?? 'PC',
                'loginAt' => (int)($entry['loginAt'] ?? $payload['login_at'] ?? 0),
                'expiresAt' => (int)($entry['expiresAt'] ?? $payload['expires_at'] ?? 0),
                'payload' => $payload,
            ];

            $activeRows[] = $row;
            $activeIndex[$tokenHash] = [
                'tokenValue' => $tokenValue,
                'device' => $row['device'],
                'loginAt' => $row['loginAt'],
                'expiresAt' => $row['expiresAt'],
            ];
        }

        $this->writeUserTokens($userId, $activeIndex);

        return $activeRows;
    }

    /**
     * @return array<int, string>
     */
    public function onlineUserIds(): array
    {
        $users = $this->readOnlineUsers();
        $active = [];

        foreach (array_keys($users) as $userId) {
            if ($this->tokensForUser((string)$userId) !== []) {
                $active[] = (string)$userId;
            }
        }

        return array_values(array_unique($active));
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
        return self::KEY_PREFIX . 'token:' . $this->tokenHash($token);
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function userTokensKey(string $userId): string
    {
        return self::KEY_PREFIX . 'user-tokens:' . $userId;
    }

    private function indexToken(string $token, array $payload): void
    {
        $userId = (string)($payload['user_id'] ?? '');
        if ($userId === '') {
            return;
        }

        $tokens = $this->readUserTokens($userId);
        $tokens[$this->tokenHash($token)] = [
            'tokenValue' => $token,
            'device' => $payload['device'] ?? 'PC',
            'loginAt' => (int)($payload['login_at'] ?? time()),
            'expiresAt' => (int)($payload['expires_at'] ?? time() + self::TOKEN_TTL),
        ];

        $this->writeUserTokens($userId, $tokens);
    }

    private function removeIndexedToken(string $token, string $userId): void
    {
        if ($userId === '') {
            return;
        }

        $tokens = $this->readUserTokens($userId);
        unset($tokens[$this->tokenHash($token)]);

        $this->writeUserTokens($userId, $tokens);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if ($this->needsAuthContextRefresh($payload)) {
            $payload = $this->refreshAuthContext($payload);
        }

        if (!$this->needsLegacyOrgScopeFallback($payload)) {
            return $payload;
        }

        $orgIds = [];
        $orgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($orgId !== '') {
            $orgIds[] = $orgId;
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (!is_array($scope)) {
                    continue;
                }

                $scopeOrgId = trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
                if ($scopeOrgId !== '') {
                    $orgIds[] = $scopeOrgId;
                }
            }
        }

        $payload['data_scope_org_ids'] = $this->expandOrgIds($orgIds);

        return $payload;
    }

    private function needsAuthContextRefresh(array $payload): bool
    {
        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (is_array($scope) && array_key_exists('scopeCategory', $scope)) {
                    return false;
                }
            }
        }

        $permissionCodes = $payload['permission_codes'] ?? $payload['permissionCodeList'] ?? [];

        return is_array($permissionCodes) && $permissionCodes !== [];
    }

    private function needsLegacyOrgScopeFallback(array $payload): bool
    {
        if (empty($payload['data_scope_org_ids']) || !is_array($payload['data_scope_org_ids'])) {
            return true;
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (!is_array($scopes) || $scopes === []) {
            return false;
        }

        foreach ($scopes as $scope) {
            if (is_array($scope) && array_key_exists('scopeCategory', $scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function refreshAuthContext(array $payload): array
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            return $payload;
        }

        $user = Db::name('sys_user')->where('ID', $userId)->find();
        if (!is_array($user) || $user === []) {
            return $payload;
        }

        $authContext = (new RbacService())->buildForUser($user);
        foreach ([
            'role_ids',
            'role_codes',
            'button_codes',
            'mobile_button_codes',
            'permission_codes',
            'menu_ids',
            'data_scope_org_ids',
            'data_scopes',
        ] as $key) {
            $payload[$key] = $authContext[$key] ?? [];
        }

        $payload['tenant_id'] = (string)($user['TENANT_ID'] ?? ($payload['tenant_id'] ?? ''));
        $payload['account'] = $user['ACCOUNT'] ?? ($payload['account'] ?? null);
        $payload['name'] = $user['NAME'] ?? ($payload['name'] ?? null);
        $payload['org_id'] = $user['ORG_ID'] ?? ($payload['org_id'] ?? null);

        return $payload;
    }

    /**
     * @param array<int, string> $orgIds
     * @return array<int, string>
     */
    private function expandOrgIds(array $orgIds): array
    {
        $seen = [];
        $queue = [];
        foreach ($orgIds as $orgId) {
            $orgId = trim((string)$orgId);
            if ($orgId === '' || isset($seen[$orgId])) {
                continue;
            }
            $seen[$orgId] = true;
            $queue[] = $orgId;
        }

        while ($queue !== []) {
            $children = Db::name('sys_org')
                ->whereIn('PARENT_ID', $queue)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
                })
                ->column('ID');

            $queue = [];
            foreach ($children as $childId) {
                $childId = trim((string)$childId);
                if ($childId === '' || isset($seen[$childId])) {
                    continue;
                }
                $seen[$childId] = true;
                $queue[] = $childId;
            }
        }

        return array_keys($seen);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readUserTokens(string $userId): array
    {
        $tokens = Cache::get($this->userTokensKey($userId));
        if (!is_array($tokens)) {
            return [];
        }

        $normalized = [];
        foreach ($tokens as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $tokenValue = (string)($entry['tokenValue'] ?? '');
            if ($tokenValue === '') {
                continue;
            }

            $tokenHash = is_string($key) && $key !== '' ? $key : $this->tokenHash($tokenValue);
            $normalized[$tokenHash] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $tokens
     */
    private function writeUserTokens(string $userId, array $tokens): void
    {
        if ($tokens === []) {
            Cache::delete($this->userTokensKey($userId));
            $this->removeOnlineUser($userId);
            return;
        }

        Cache::set($this->userTokensKey($userId), $tokens, self::TOKEN_TTL);
        $this->addOnlineUser($userId);
    }

    private function addOnlineUser(string $userId): void
    {
        $users = $this->readOnlineUsers();
        $users[$userId] = [
            'userId' => $userId,
            'updatedAt' => time(),
        ];

        Cache::set(self::ONLINE_USERS_KEY, $users, self::TOKEN_TTL);
    }

    private function removeOnlineUser(string $userId): void
    {
        $users = $this->readOnlineUsers();
        unset($users[$userId]);

        if ($users === []) {
            Cache::delete(self::ONLINE_USERS_KEY);
            return;
        }

        Cache::set(self::ONLINE_USERS_KEY, $users, self::TOKEN_TTL);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readOnlineUsers(): array
    {
        $users = Cache::get(self::ONLINE_USERS_KEY);
        if (!is_array($users)) {
            return [];
        }

        $normalized = [];
        foreach ($users as $key => $entry) {
            $userId = '';
            if (is_string($key) && $key !== '' && is_array($entry)) {
                $userId = $key;
            } elseif (is_array($entry)) {
                $userId = (string)($entry['userId'] ?? '');
            } else {
                $userId = (string)$entry;
            }

            if ($userId === '') {
                continue;
            }

            $normalized[$userId] = [
                'userId' => $userId,
                'updatedAt' => is_array($entry) ? (int)($entry['updatedAt'] ?? time()) : time(),
            ];
        }

        return $normalized;
    }
}
