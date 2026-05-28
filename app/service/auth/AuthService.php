<?php

namespace app\service\auth;

use RuntimeException;
use think\facade\Cache;
use think\facade\Db;
use think\Request;

class AuthService
{
    private const CAPTCHA_KEY_PREFIX = 'oa:auth:captcha:';

    public function __construct(
        private readonly TokenService $tokenService = new TokenService(),
        private readonly RbacService $rbacService = new RbacService(),
    ) {
    }

    public function getPicCaptcha(): array
    {
        $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $requestNo = $this->newId();
        Cache::set(self::CAPTCHA_KEY_PREFIX . $requestNo, strtolower($code), 300);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="38">'
            . '<rect width="100%" height="100%" fill="#f5f7fb"/>'
            . '<text x="50" y="25" text-anchor="middle" font-size="20" font-family="Arial" fill="#1f2937">'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            . '</text></svg>';

        return [
            'validCodeBase64' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'validCodeReqNo' => $requestNo,
        ];
    }

    public function login(array $input): string
    {
        $account = trim((string)($input['account'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? ''));
        $device = trim((string)($input['device'] ?? 'PC')) ?: 'PC';

        if ($account === '' || $password === '') {
            throw new RuntimeException('account and password are required', 400);
        }

        $this->validateCaptchaIfPresent($input);

        $query = Db::name('sys_user')->where('ACCOUNT', $account);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $user = $query->find();
        if (!is_array($user) || $user === []) {
            throw new RuntimeException('account or password is incorrect', 401);
        }

        if (($user['USER_STATUS'] ?? 'ENABLE') !== 'ENABLE') {
            throw new RuntimeException('account is disabled', 403);
        }

        if (!$this->verifyPassword($password, (string)($user['PASSWORD'] ?? ''))) {
            throw new RuntimeException('account or password is incorrect', 401);
        }

        $authContext = $this->rbacService->buildForUser($user);
        $authContext['device'] = $device;

        return $this->tokenService->create($user, $authContext);
    }

    public function logout(Request $request): void
    {
        $this->tokenService->revoke($this->tokenService->bearerFromRequest($request));
    }

    public function currentUser(Request $request): array
    {
        $payload = $this->tokenService->getPayload($this->tokenService->bearerFromRequest($request));
        if ($payload === null) {
            throw new RuntimeException('unauthenticated', 401);
        }

        $user = Db::name('sys_user')->where('ID', $payload['user_id'])->find();
        if (!is_array($user) || $user === []) {
            throw new RuntimeException('login user not found', 401);
        }

        unset($user['PASSWORD']);

        return [
            'user' => $user,
            'tenantId' => $payload['tenant_id'] ?? null,
            'roleCodeList' => $payload['role_codes'] ?? [],
            'buttonCodeList' => $payload['button_codes'] ?? [],
            'mobileButtonCodeList' => $payload['mobile_button_codes'] ?? [],
            'permissionCodeList' => $payload['permission_codes'] ?? [],
            'menuIdList' => $payload['menu_ids'] ?? [],
            'dataScopeList' => $payload['data_scopes'] ?? [],
        ];
    }

    public function openSafe(array $input, Request $request): string
    {
        $this->currentUser($request);
        $mark = trim((string)($input['mark'] ?? 'password'));

        Cache::set('oa:auth:safe:' . $mark . ':' . $this->tokenService->bearerFromRequest($request), true, 120);

        return 'verification passed';
    }

    private function validateCaptchaIfPresent(array $input): void
    {
        $validCode = strtolower(trim((string)($input['validCode'] ?? $input['valid_code'] ?? '')));
        $requestNo = trim((string)($input['validCodeReqNo'] ?? $input['valid_code_req_no'] ?? ''));

        if ($validCode === '' && $requestNo === '') {
            return;
        }

        if ($validCode === '' || $requestNo === '') {
            throw new RuntimeException('captcha code and request number are required together', 400);
        }

        $key = self::CAPTCHA_KEY_PREFIX . $requestNo;
        $cached = Cache::get($key);
        Cache::delete($key);

        if (!is_string($cached) || !hash_equals($cached, $validCode)) {
            throw new RuntimeException('captcha is incorrect or expired', 400);
        }
    }

    private function verifyPassword(string $inputPassword, string $storedPassword): bool
    {
        if ($storedPassword === '') {
            return false;
        }

        if (hash_equals($storedPassword, $inputPassword)) {
            return true;
        }

        if (password_get_info($storedPassword)['algo'] !== null && password_verify($inputPassword, $storedPassword)) {
            return true;
        }

        return hash_equals($storedPassword, hash('sha256', $inputPassword));
    }

    private function newId(): string
    {
        return str_replace('.', '', uniqid('', true)) . bin2hex(random_bytes(4));
    }
}
