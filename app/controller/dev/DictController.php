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

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->addBizDict(
            $this->bodyInput($request),
            $this->authPayload($request)
        ));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->editBizDict(
            $this->bodyInput($request),
            $this->authPayload($request)
        ));
    }

    public function bizEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->editBizDictBusiness(
            $this->bodyInput($request),
            $this->authPayload($request)
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->dictService->deleteBizDicts(
            $this->deleteIds($this->bodyInput($request)),
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

    /**
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $items = $input;
        if (isset($input['idList']) || isset($input['ids'])) {
            $items = $input['idList'] ?? $input['ids'];
        } elseif (isset($input['id']) || isset($input['ID'])) {
            $items = [$input];
        }

        if (is_string($items)) {
            $items = explode(',', $items);
        }
        if (!is_array($items)) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            $id = is_array($item) ? (string)($item['id'] ?? $item['ID'] ?? '') : (string)$item;
            $id = trim($id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
