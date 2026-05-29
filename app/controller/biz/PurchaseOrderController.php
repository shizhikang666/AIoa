<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\PurchaseOrderService;
use think\Request;
use think\Response;

class PurchaseOrderController extends BaseSysController
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService = new PurchaseOrderService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->list($request->get(), $this->authPayload($request)));
    }

    public function detailList(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->detailList($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
