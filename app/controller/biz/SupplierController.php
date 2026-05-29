<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SupplierService;
use think\Request;
use think\Response;

class SupplierController extends BaseSysController
{
    public function __construct(private readonly SupplierService $supplierService = new SupplierService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->supplierService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->supplierService->list($request->get(), $this->authPayload($request)));
    }

    public function queryByName(Request $request): Response
    {
        return $this->guard(fn () => $this->supplierService->queryByName($this->requiredString($request, 'name'), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->supplierService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
