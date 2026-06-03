<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectProductInfoService;
use think\Request;
use think\Response;

class SaleProjectProductInfoController extends BaseSysController
{
    public function __construct(private readonly SaleProjectProductInfoService $productInfoService = new SaleProjectProductInfoService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->productInfoService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->productInfoService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->productInfoService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
