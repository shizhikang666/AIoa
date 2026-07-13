<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectProductItemRelationService;
use think\Request;
use think\Response;

class SaleProjectProductItemRelationController extends BaseSysController
{
    public function __construct(private readonly SaleProjectProductItemRelationService $relationService = new SaleProjectProductItemRelationService())
    {
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->relationService->listByObjectIds($this->objectIds($request), $this->authPayload($request)));
    }

    public function editMark(Request $request): Response
    {
        return $this->guard(fn () => $this->relationService->editMark($this->body($request), $this->authPayload($request)));
    }

    /**
     * @return array<int, string>
     */
    private function objectIds(Request $request): array
    {
        $body = $request->post();
        if ($body === []) {
            $body = $request->param();
        }

        if (isset($body['idList']) || isset($body['ids']) || isset($body['id'])) {
            return $this->stringList($body['idList'] ?? $body['ids'] ?? $body['id']);
        }

        if (array_is_list($body)) {
            $ids = [];
            foreach ($body as $item) {
                if (is_array($item)) {
                    $ids[] = $item['id'] ?? $item['objectId'] ?? '';
                } else {
                    $ids[] = $item;
                }
            }

            return $this->stringList($ids);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $value)));
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
