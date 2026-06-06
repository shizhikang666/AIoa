<?php

declare(strict_types=1);

namespace app\service\auth;

use RuntimeException;
use think\facade\Db;
use think\Request;

/**
 * Read-only session monitor for the current ThinkPHP bearer token.
 */
class SessionMonitorService
{
    private const DEFAULT_TOKEN_TTL = 7200;

    public function __construct(private readonly TokenService $tokenService = new TokenService())
    {
    }

    public function analysis(Request $request): array
    {
        $payload = $this->currentPayload($request);
        $rows = $this->pageRowsForPayload($payload, $request);
        $tokenCounts = array_map(static fn (array $row): int => (int)($row['tokenCount'] ?? 0), $rows);
        $recentCount = 0;

        foreach ($rows as $row) {
            $createdAt = strtotime((string)($row['sessionCreateTime'] ?? '')) ?: 0;
            if ($createdAt > 0 && $createdAt >= time() - 3600) {
                $recentCount++;
            }
        }

        return [
            'currentSessionTotalCount' => (string)count($rows),
            'maxTokenCount' => (string)($tokenCounts === [] ? 0 : max($tokenCounts)),
            'oneHourNewlyAdded' => (string)$recentCount,
            'proportionOfBAndC' => count($rows) . '/0',
        ];
    }

    public function pageForB(array $filters, Request $request): array
    {
        [$page, $limit] = $this->pagination($filters);
        $payload = $this->currentPayload($request);
        $filterUserId = trim((string)($filters['userId'] ?? ''));

        $records = array_values(array_filter(
            $this->pageRowsForPayload($payload, $request),
            static fn (array $row): bool => $filterUserId === '' || (string)($row['id'] ?? '') === $filterUserId
        ));

        $total = count($records);
        if ($total > 0) {
            $records = array_slice($records, ($page - 1) * $limit, $limit);
        }

        return $this->pageResult($records, $total, $page, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function exitSessionForB(array $items, Request $request): array
    {
        $payload = $this->currentPayload($request);
        $currentUserId = $this->payloadUserId($payload);
        $canManage = $this->canManageMonitor($payload);
        $userIds = $this->targetUserIds($items);
        $count = 0;

        foreach ($userIds as $userId) {
            if (!$canManage && $userId !== $currentUserId) {
                throw new RuntimeException('permission denied', 403);
            }

            $count += $this->tokenService->revokeUserTokens($userId);
            if ($userId === $currentUserId) {
                $currentToken = $this->tokenService->bearerFromRequest($request);
                if (is_string($currentToken) && $currentToken !== '') {
                    $this->tokenService->revoke($currentToken);
                }
            }
        }

        return ['count' => $count];
    }

    public function pageForC(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);

        return $this->pageResult([], 0, $page, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function exitSessionForC(array $items, Request $request): array
    {
        $payload = $this->currentPayload($request);
        $currentUserId = $this->payloadUserId($payload);
        $canManage = $this->canManageMonitor($payload);
        $userIds = $this->targetUserIds($items);

        foreach ($userIds as $userId) {
            if (!$canManage && $userId !== $currentUserId) {
                throw new RuntimeException('permission denied', 403);
            }
        }

        return [
            'count' => 0,
            'clientAuthDeferred' => true,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function exitTokenForB(array $items, Request $request): array
    {
        $payload = $this->currentPayload($request);
        $currentUserId = $this->payloadUserId($payload);
        $canManage = $this->canManageMonitor($payload);
        $tokenValues = $this->targetTokenValues($items);
        $allowedTokens = [];

        foreach ($tokenValues as $tokenValue) {
            $tokenPayload = $this->tokenService->getPayload($tokenValue);
            if (!$canManage) {
                if (!is_array($tokenPayload) || (string)($tokenPayload['user_id'] ?? '') !== $currentUserId) {
                    throw new RuntimeException('permission denied', 403);
                }
            }

            $allowedTokens[] = $tokenValue;
        }

        return ['count' => $this->tokenService->revokeTokens($allowedTokens)];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function exitTokenForC(array $items, Request $request): array
    {
        $payload = $this->currentPayload($request);
        if (!$this->canManageMonitor($payload)) {
            $currentToken = (string)$this->tokenService->bearerFromRequest($request);
            foreach ($this->targetTokenValues($items) as $tokenValue) {
                if ($tokenValue !== $currentToken) {
                    throw new RuntimeException('permission denied', 403);
                }
            }
        } else {
            $this->targetTokenValues($items);
        }

        return [
            'count' => 0,
            'clientAuthDeferred' => true,
        ];
    }

    /**
     * @return array{token: string, payload: array<string, mixed>, user: array<string, mixed>}
     */
    private function currentSession(Request $request): array
    {
        $token = $this->tokenService->bearerFromRequest($request);
        $payload = $this->currentPayload($request);

        if (!is_string($token) || $token === '') {
            throw new RuntimeException('unauthenticated', 401);
        }

        $userId = (string)($payload['user_id'] ?? '');
        $user = $userId !== '' ? Db::name('sys_user')->where('ID', $userId)->find() : null;

        return [
            'token' => $token,
            'payload' => $payload,
            'user' => is_array($user) ? $user : [],
        ];
    }

    /**
     * @param array{token: string, payload: array<string, mixed>, user: array<string, mixed>} $session
     */
    private function sessionRow(array $session): array
    {
        return $this->sessionRowFromTokenRows((string)($session['payload']['user_id'] ?? ''), [[
            'tokenValue' => $session['token'],
            'device' => $session['payload']['device'] ?? 'PC',
            'loginAt' => (int)($session['payload']['login_at'] ?? time()),
            'expiresAt' => (int)($session['payload']['expires_at'] ?? 0),
            'payload' => $session['payload'],
        ]], $session['user']);
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     * @param array<string, mixed>|null $user
     */
    private function sessionRowFromTokenRows(string $userId, array $tokens, ?array $user = null): array
    {
        $firstToken = $tokens[0] ?? [];
        $payload = is_array($firstToken['payload'] ?? null) ? $firstToken['payload'] : [];
        $user = $user ?? $this->userRecord($userId);
        $createdAt = $this->oldestLoginAt($tokens);
        $expiresAt = $this->latestExpiresAt($tokens);
        $secondsLeft = $expiresAt > 0 ? max(0, $expiresAt - time()) : 0;

        return [
            'id' => $user['ID'] ?? $payload['user_id'] ?? $userId,
            'avatar' => $user['AVATAR'] ?? null,
            'account' => $user['ACCOUNT'] ?? $payload['account'] ?? null,
            'name' => $user['NAME'] ?? $payload['name'] ?? null,
            'lastLoginIp' => $user['LAST_LOGIN_IP'] ?? null,
            'lastLoginAddress' => $user['LAST_LOGIN_ADDRESS'] ?? null,
            'lastLoginTime' => $user['LAST_LOGIN_TIME'] ?? null,
            'lastLoginDevice' => $user['LAST_LOGIN_DEVICE'] ?? null,
            'latestLoginIp' => $user['LATEST_LOGIN_IP'] ?? null,
            'latestLoginAddress' => $user['LATEST_LOGIN_ADDRESS'] ?? null,
            'latestLoginTime' => $user['LATEST_LOGIN_TIME'] ?? null,
            'latestLoginDevice' => $user['LATEST_LOGIN_DEVICE'] ?? null,
            'sessionId' => 'b-session-' . substr(hash('sha256', $userId), 0, 16),
            'sessionCreateTime' => $this->timeString($createdAt),
            'sessionTimeout' => $this->formatSeconds($secondsLeft),
            'tokenCount' => count($tokens),
            'tokenSignList' => array_map(fn (array $token): array => $this->tokenSignInfo($token), $tokens),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);
        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        $payload = $this->tokenService->getPayload($this->tokenService->bearerFromRequest($request));
        if (!is_array($payload)) {
            throw new RuntimeException('unauthenticated', 401);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function pageRowsForPayload(array $payload, Request $request): array
    {
        $currentUserId = $this->payloadUserId($payload);
        $userIds = $this->canManageMonitor($payload) ? $this->tokenService->onlineUserIds() : [$currentUserId];
        $rows = [];

        foreach ($userIds as $userId) {
            $tokens = $this->tokenService->tokensForUser($userId);
            if ($tokens === []) {
                continue;
            }

            $rows[] = $this->sessionRowFromTokenRows($userId, $tokens);
        }

        if (!$this->hasRowForUser($rows, $currentUserId)) {
            $rows[] = $this->sessionRow($this->currentSession($request));
        }

        usort($rows, static fn (array $left, array $right): int => strcmp(
            (string)($right['sessionCreateTime'] ?? ''),
            (string)($left['sessionCreateTime'] ?? '')
        ));

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function hasRowForUser(array $rows, string $userId): bool
    {
        foreach ($rows as $row) {
            if ((string)($row['id'] ?? '') === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function userRecord(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $user = Db::name('sys_user')->where('ID', $userId)->find();

        return is_array($user) ? $user : [];
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function oldestLoginAt(array $tokens): int
    {
        $times = array_values(array_filter(array_map(
            static fn (array $token): int => (int)($token['loginAt'] ?? ($token['payload']['login_at'] ?? 0)),
            $tokens
        )));

        return $times === [] ? time() : min($times);
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function latestExpiresAt(array $tokens): int
    {
        $times = array_values(array_filter(array_map(
            static fn (array $token): int => (int)($token['expiresAt'] ?? ($token['payload']['expires_at'] ?? 0)),
            $tokens
        )));

        return $times === [] ? 0 : max($times);
    }

    /**
     * @param array<string, mixed> $token
     */
    private function tokenSignInfo(array $token): array
    {
        $createdAt = (int)($token['loginAt'] ?? ($token['payload']['login_at'] ?? time()));
        $expiresAt = (int)($token['expiresAt'] ?? ($token['payload']['expires_at'] ?? 0));
        $secondsLeft = $expiresAt > 0 ? max(0, $expiresAt - time()) : 0;
        $configuredTtl = $expiresAt > $createdAt ? max(1, $expiresAt - $createdAt) : self::DEFAULT_TOKEN_TTL;

        return [
            'tokenValue' => (string)($token['tokenValue'] ?? ''),
            'tokenDevice' => $token['device'] ?? ($token['payload']['device'] ?? 'PC'),
            'tokenTimeout' => $this->formatSeconds($secondsLeft),
            'tokenTimeoutPercent' => round($secondsLeft / $configuredTtl, 4),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function targetUserIds(array $items): array
    {
        $userIds = [];
        foreach ($items as $item) {
            $userId = trim((string)($item['userId'] ?? $item['id'] ?? ''));
            if ($userId !== '') {
                $userIds[] = $userId;
            }
        }

        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            throw new RuntimeException('empty session list', 400);
        }

        return $userIds;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function targetTokenValues(array $items): array
    {
        $tokenValues = [];
        foreach ($items as $item) {
            $tokenValue = trim((string)($item['tokenValue'] ?? $item['id'] ?? ''));
            if ($tokenValue !== '') {
                $tokenValues[] = $tokenValue;
            }
        }

        $tokenValues = array_values(array_unique($tokenValues));
        if ($tokenValues === []) {
            throw new RuntimeException('empty token list', 400);
        }

        return $tokenValues;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('unauthenticated', 401);
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function canManageMonitor(array $payload): bool
    {
        $account = strtolower(trim((string)($payload['account'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            return true;
        }

        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );

        foreach ($codes as $code) {
            $code = strtolower($code);
            if (
                in_array($code, ['superadmin', 'bizadmin', 'tenantadmin', 'authmonitor'], true)
                || str_contains($code, 'auth:session')
                || str_contains($code, 'auth:token')
                || str_contains($code, 'authmonitor')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $value)));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function pageResult(array $records, int $total, int $page, int $limit): array
    {
        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function timeString(int $timestamp): ?string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0秒';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . '天';
        }
        if ($hours > 0) {
            $parts[] = $hours . '小时';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . '分';
        }
        if ($seconds > 0 || $parts === []) {
            $parts[] = $seconds . '秒';
        }

        return implode('', $parts);
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 16) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 8) . '...' . substr($token, -6);
    }
}
