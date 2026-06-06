<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\DictService;
use think\Request;
use think\Response;

class DictController extends BaseSysController
{
    public function __construct(private readonly DictService $dictService = new DictService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->page($request->get(), $this->tenantId($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->list($request->get(), $this->tenantId($request)));
    }

    public function tree(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->tree($request->get(), $this->tenantId($request)));
    }

    public function treeAll(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->treeAll($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->detail($this->requiredString($request, 'id'), $this->tenantId($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->editBizDict(
            $this->bodyInput($request),
            $this->authPayload($request)
        ));
    }

    /**
     * @return array<string, mixed>
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
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function tenantId(Request $request): ?string
    {
        $payload = $request->middleware('auth_payload', []);
        if (!is_array($payload)) {
            return null;
        }

        $tenantId = (string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '');

        return $tenantId === '' ? null : $tenantId;
    }
}
