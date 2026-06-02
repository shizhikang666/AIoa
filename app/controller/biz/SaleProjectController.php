<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectService;
use think\Request;
use think\Response;

class SaleProjectController extends BaseSysController
{
    public function __construct(private readonly SaleProjectService $saleProjectService = new SaleProjectService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->page($request->get(), $this->authPayload($request)));
    }

    public function casePage(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->casePage($request->get(), $this->authPayload($request)));
    }

    public function operationPage(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->operationPage($request->get(), $this->authPayload($request)));
    }

    public function publicPage(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->publicPage($request->get(), $this->authPayload($request)));
    }

    public function listDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->listDetail($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function product(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->product($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
