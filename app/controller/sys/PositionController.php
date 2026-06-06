<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\user\OrgService;
use app\service\user\PositionService;
use think\Request;
use think\Response;

class PositionController extends BaseSysController
{
    public function __construct(
        private readonly PositionService $positionService = new PositionService(),
        private readonly OrgService $orgService = new OrgService()
    ) {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->detail($this->requiredString($request, 'id')));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->add(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->edit(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->positionService->delete(
                $this->deleteIds($input),
                $request->middleware('auth_payload', []),
                false
            );
        });
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->all($request->get()));
    }

    public function selector(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->selector($request->get()));
    }

    public function orgTreeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->selector($request->get()));
    }

    public function bizAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->add(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->edit(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizDelete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->positionService->delete(
                $this->deleteIds($input),
                $request->middleware('auth_payload', []),
                true
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyInput(Request $request): array
    {
        $input = $request->post();
        if ($input === []) {
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
        }

        return $input === [] ? $request->param() : $input;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, mixed>
     */
    private function deleteIds(array $input): array
    {
        if (isset($input[0])) {
            return $input;
        }

        foreach (['idList', 'ids', 'id', 'positionIds'] as $key) {
            if (array_key_exists($key, $input)) {
                return is_array($input[$key]) ? $input[$key] : [(string)$input[$key]];
            }
        }

        return [];
    }
}
