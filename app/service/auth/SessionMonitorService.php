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
        $session = $this->currentSession($request);
        $createdAt = (int)($session['payload']['login_at'] ?? 0);
        $isRecent = $createdAt > 0 && $createdAt >= time() - 3600;

        return [
            'currentSessionTotalCount' => '1',
            'maxTokenCount' => '1',
            'oneHourNewlyAdded' => $isRecent ? '1' : '0',
            'proportionOfBAndC' => '1/0',
        ];
    }

    public function pageForB(array $filters, Request $request): array
    {
        [$page, $limit] = $this->pagination($filters);
        $session = $this->currentSession($request);
        $userId = (string)($session['payload']['user_id'] ?? '');
        $filterUserId = trim((string)($filters['userId'] ?? ''));

        if ($filterUserId !== '' && $filterUserId !== $userId) {
            return $this->pageResult([], 0, $page, $limit);
        }

        $records = $page === 1 ? [$this->sessionRow($session)] : [];

        return $this->pageResult($records, 1, $page, $limit);
    }

    public function pageForC(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);

        return $this->pageResult([], 0, $page, $limit);
    }

    /**
     * @return array{token: string, payload: array<string, mixed>, user: array<string, mixed>}
     */
    private function currentSession(Request $request): array
    {
        $token = $this->tokenService->bearerFromRequest($request);
        $payload = $request->middleware('auth_payload', []);
        if (!is_array($payload) || $payload === []) {
            $payload = $this->tokenService->getPayload($token);
        }

        if (!is_string($token) || $token === '' || !is_array($payload)) {
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
        $payload = $session['payload'];
        $user = $session['user'];
        $createdAt = (int)($payload['login_at'] ?? time());
        $expiresAt = (int)($payload['expires_at'] ?? 0);
        $secondsLeft = $expiresAt > 0 ? max(0, $expiresAt - time()) : 0;
        $configuredTtl = $expiresAt > $createdAt ? max(1, $expiresAt - $createdAt) : self::DEFAULT_TOKEN_TTL;

        return [
            'id' => $user['ID'] ?? $payload['user_id'] ?? null,
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
            'sessionId' => 'b-session-' . substr(hash('sha256', (string)($payload['user_id'] ?? '')), 0, 16),
            'sessionCreateTime' => $this->timeString($createdAt),
            'sessionTimeout' => $this->formatSeconds($secondsLeft),
            'tokenCount' => 1,
            'tokenSignList' => [[
                'tokenValue' => $this->maskToken($session['token']),
                'tokenDevice' => $payload['device'] ?? 'PC',
                'tokenTimeout' => $this->formatSeconds($secondsLeft),
                'tokenTimeoutPercent' => round($secondsLeft / $configuredTtl, 4),
            ]],
        ];
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
