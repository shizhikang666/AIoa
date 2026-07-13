<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectBillingService;
use think\Request;
use think\Response;

class SaleProjectRateController extends BaseSysController
{
    public function __construct(private readonly SaleProjectBillingService $billingService = new SaleProjectBillingService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->ratePage($request->get(), $this->authPayload($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->rateAdd($this->body($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->rateEdit($this->body($request), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->rateList($this->requiredString($request, 'projectId'), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->body($request);

            return $this->billingService->rateDelete($this->deleteIds($request, $input), $this->authPayload($request));
        });
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->rateDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
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

    private function deleteIds(Request $request, array $input): array
    {
        if (isset($input[0])) {
            return $input;
        }

        foreach (['idList', 'ids', 'id'] as $key) {
            if (array_key_exists($key, $input)) {
                return is_array($input[$key]) ? $input[$key] : [(string)$input[$key]];
            }
        }

        return $this->idList($request);
    }
}
