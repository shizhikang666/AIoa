<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectBillingService;
use think\Request;
use think\Response;

class SaleProjectInvoicingController extends BaseSysController
{
    public function __construct(private readonly SaleProjectBillingService $billingService = new SaleProjectBillingService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->invoicingPage($request->get(), $this->authPayload($request)));
    }

    public function customer(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->invoicingCustomer($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->invoicingDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function complete(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->invoicingComplete($this->requiredBodyString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function requiredBodyString(Request $request, string $key): string
    {
        $input = $this->body($request);
        $value = trim((string)($input[$key] ?? $request->param($key, '')));
        if ($value === '') {
            throw new \RuntimeException("missing {$key}", 400);
        }

        return $value;
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
