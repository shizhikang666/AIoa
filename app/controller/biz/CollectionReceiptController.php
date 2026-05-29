<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\CollectionReceiptService;
use think\Request;
use think\Response;

class CollectionReceiptController extends BaseSysController
{
    public function __construct(private readonly CollectionReceiptService $collectionReceiptService = new CollectionReceiptService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->collectionReceiptService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->collectionReceiptService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->collectionReceiptService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
