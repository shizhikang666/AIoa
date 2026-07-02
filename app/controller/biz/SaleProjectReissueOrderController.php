<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectBillingService;
use app\service\biz\SaleProjectService;
use think\Request;
use think\Response;

class SaleProjectReissueOrderController extends BaseSysController
{
    public function __construct(
        private readonly SaleProjectBillingService $billingService = new SaleProjectBillingService(),
        private readonly SaleProjectService $saleProjectService = new SaleProjectService()
    )
    {
    }

    public function listQuery(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->reissueOrderListQuery($this->requiredString($request, 'projectId'), $this->authPayload($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->addReissueOrder($this->body($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->editReissueOrder($this->body($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->deleteReissueOrder($this->body($request), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function body(Request $request): array
    {
        $input = $request->post();
        if ($input !== []) {
            return $input;
        }

        $raw = '';
        if (method_exists($request, 'getContent')) {
            $raw = trim((string)$request->getContent());
        }
        if ($raw === '' && method_exists($request, 'getInput')) {
            $raw = trim((string)$request->getInput());
        }
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
