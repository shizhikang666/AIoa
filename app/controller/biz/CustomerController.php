<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\CustomerService;
use think\Request;
use think\Response;

class CustomerController extends BaseSysController
{
    public function __construct(private readonly CustomerService $customerService = new CustomerService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->customerService->page($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->customerService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function detailList(Request $request): Response
    {
        $filters = array_merge($request->get(), $request->post());

        return $this->guard(fn () => $this->customerService->detailList($filters, $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
