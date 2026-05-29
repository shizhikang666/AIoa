<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SettlementAccountService;
use think\Request;
use think\Response;

class SettlementAccountController extends BaseSysController
{
    public function __construct(private readonly SettlementAccountService $settlementAccountService = new SettlementAccountService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->settlementAccountService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->settlementAccountService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->settlementAccountService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function queryName(Request $request): Response
    {
        return $this->guard(fn () => $this->settlementAccountService->queryName($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
