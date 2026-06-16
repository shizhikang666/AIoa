<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectService;
use app\support\ApiResponse;
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

    public function cost(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->cost($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function costDetails(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->costDetails($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function add(): Response
    {
        return $this->deferredWrite('sale project add');
    }

    public function edit(): Response
    {
        return $this->deferredWrite('sale project edit');
    }

    public function delete(): Response
    {
        return $this->deferredWrite('sale project delete');
    }

    public function amountEdit(): Response
    {
        return $this->deferredWrite('sale project amount edit');
    }

    public function dealEdit(): Response
    {
        return $this->deferredWrite('sale project deal edit');
    }

    public function cancel(): Response
    {
        return $this->deferredWrite('sale project cancel');
    }

    public function historyAdd(): Response
    {
        return $this->deferredWrite('sale project history add');
    }

    public function repeal(): Response
    {
        return $this->deferredWrite('sale project repeal');
    }

    public function specialAdd(): Response
    {
        return $this->deferredWrite('sale project special add');
    }

    public function visibilityEdit(): Response
    {
        return $this->deferredWrite('sale project visibility edit');
    }

    private function deferredWrite(string $operation): Response
    {
        return ApiResponse::fail($operation . ' is deferred', 400, [
            'operation' => $operation,
        ]);
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
