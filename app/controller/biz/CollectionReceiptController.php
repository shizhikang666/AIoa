<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\CollectionReceiptService;
use app\support\ApiResponse;
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

    public function markSuccess(Request $request): Response
    {
        return $this->guard(fn () => $this->collectionReceiptService->markSuccess($this->body($request), $this->authPayload($request)));
    }

    public function add(): Response
    {
        return $this->deferredWrite('collection receipt add');
    }

    public function edit(): Response
    {
        return $this->deferredWrite('collection receipt edit');
    }

    public function batchExpenditure(): Response
    {
        return $this->deferredWrite('collection receipt batch expenditure');
    }

    public function delete(): Response
    {
        return $this->deferredWrite('collection receipt delete');
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
