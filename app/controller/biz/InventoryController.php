<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\InventoryService;
use app\support\ApiResponse;
use think\Request;
use think\Response;

class InventoryController extends BaseSysController
{
    public function __construct(private readonly InventoryService $inventoryService = new InventoryService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->inventoryService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->inventoryService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->inventoryService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function add(): Response
    {
        return $this->deferredWrite('inventory add');
    }

    public function delete(): Response
    {
        return $this->deferredWrite('inventory delete');
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
