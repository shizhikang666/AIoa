<?php

declare(strict_types=1);

namespace app\controller\mobile;

use app\controller\sys\BaseSysController;
use app\service\mobile\MobileResourceService;
use think\Request;
use think\Response;

class ModuleController extends BaseSysController
{
    public function __construct(private readonly MobileResourceService $resourceService = new MobileResourceService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->modulePage($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->detail($this->requiredString($request, 'id'), 'MODULE'));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->moduleAdd(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->moduleEdit(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->resourceService->moduleDelete(
                $this->deleteIds($input),
                $request->middleware('auth_payload', [])
            );
        });
    }

    /**
     * @return array<string|int, mixed>
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

        foreach (['idList', 'ids', 'id', 'moduleIds'] as $key) {
            if (array_key_exists($key, $input)) {
                return is_array($input[$key]) ? $input[$key] : [(string)$input[$key]];
            }
        }

        return [];
    }
}
