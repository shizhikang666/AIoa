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

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->add($this->body($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->edit($this->body($request), $this->authPayload($request)));
    }

    public function auditEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->auditEdit($this->body($request), $this->authPayload($request)));
    }

    public function warehouseAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->warehouseAdd($this->body($request), $this->authPayload($request)));
    }

    public function warehouseOneAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->warehouseOneAdd($this->body($request), $this->authPayload($request)));
    }

    public function cancel(Request $request): Response
    {
        return $this->guard(fn () => $this->purchaseOrderService->cancel($this->body($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        $input = $this->body($request);

        return $this->guard(fn () => $this->purchaseOrderService->delete($this->deleteIds($request, $input), $this->authPayload($request)));
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
        if (array_is_list($input)) {
            return $input;
        }

        foreach (['idList', 'ids', 'id'] as $key) {
            if (array_key_exists($key, $input)) {
                return is_array($input[$key]) ? $input[$key] : [$input[$key]];
            }
        }

        return $this->idList($request);
    }
}
