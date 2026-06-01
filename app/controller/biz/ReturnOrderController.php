<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\ReturnOrderService;
use think\Request;
use think\Response;

class ReturnOrderController extends BaseSysController
{
    public function __construct(private readonly ReturnOrderService $returnOrderService = new ReturnOrderService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->returnOrderService->page($request->get(), $this->authPayload($request)));
    }

    public function query(Request $request): Response
    {
        return $this->guard(fn () => $this->returnOrderService->query($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->returnOrderService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
