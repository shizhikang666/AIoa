<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectService;
use RuntimeException;
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

    public function deliveryPlanList(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->deliveryPlanList(
            $this->requiredString($request, 'projectId'),
            $this->authPayload($request)
        ));
    }

    public function cost(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->cost($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function costDetails(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->costDetails($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->add($this->bodyInput($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->edit($this->bodyInput($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->delete($this->bodyInput($request), $this->authPayload($request)));
    }

    public function amountEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->editAmount($this->bodyInput($request), $this->authPayload($request)));
    }

    public function dealEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->editDeal($this->bodyInput($request), $this->authPayload($request)));
    }

    public function cancel(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->cancel($this->bodyInput($request), $this->authPayload($request)));
    }

    public function historyAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->historyAdd($this->bodyInput($request), $this->authPayload($request)));
    }

    public function repeal(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->repeal($this->bodyInput($request), $this->authPayload($request)));
    }

    public function specialAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->specialAdd($this->bodyInput($request), $this->authPayload($request)));
    }

    public function visibilityEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->saleProjectService->editVisibility($this->bodyInput($request), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string|int, mixed>
     */
    private function bodyInput(Request $request): array
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
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            throw new RuntimeException('invalid request data format', 400);
        }

        return $request->param();
    }
}
