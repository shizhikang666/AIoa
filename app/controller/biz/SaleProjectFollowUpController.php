<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectFollowUpService;
use think\Request;
use think\Response;

class SaleProjectFollowUpController extends BaseSysController
{
    public function __construct(private readonly SaleProjectFollowUpService $saleProjectFollowUpService = new SaleProjectFollowUpService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectFollowUpService->page($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectFollowUpService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
