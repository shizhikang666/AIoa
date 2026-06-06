<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\user\OrgService;
use app\service\user\UserDirectoryService;
use think\Request;
use think\Response;

class OrgController extends BaseSysController
{
    public function __construct(
        private readonly OrgService $orgService = new OrgService(),
        private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService()
    ) {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->page($request->get()));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->all($request->get()));
    }

    public function tree(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->tree($request->get()));
    }

    public function treeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->selector($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->detail($this->requiredString($request, 'id')));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->add(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->edit(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->orgService->delete(
                $this->deleteIds($input),
                $request->middleware('auth_payload', []),
                false
            );
        });
    }

    public function bizAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->add(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->edit(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizDelete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->orgService->delete(
                $this->deleteIds($input),
                $request->middleware('auth_payload', []),
                true
            );
        });
    }

    public function userSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->userSelector($request->get()));
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

        foreach (['idList', 'ids', 'id', 'orgIds'] as $key) {
            if (array_key_exists($key, $input)) {
                return is_array($input[$key]) ? $input[$key] : [(string)$input[$key]];
            }
        }

        return [];
    }
}
