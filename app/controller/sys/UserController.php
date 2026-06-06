<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\user\OrgService;
use app\service\user\PositionService;
use app\service\user\UserDirectoryService;
use think\Request;
use think\Response;

class UserController extends BaseSysController
{
    public function __construct(
        private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService(),
        private readonly OrgService $orgService = new OrgService(),
        private readonly PositionService $positionService = new PositionService()
    ) {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->detail($this->requiredString($request, 'id')));
    }

    public function listDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->listDetail($request->get()));
    }

    public function ownRole(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->ownRole($this->requiredString($request, 'id')));
    }

    public function grantRole(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantRole(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function grantResource(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantResource(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function bizGrantRole(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantRole(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function ownResource(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->ownResource($this->requiredString($request, 'id')));
    }

    public function ownPermission(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->ownPermission($this->requiredString($request, 'id')));
    }

    public function orgTreeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->selector($request->get()));
    }

    public function positionSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->selector($request->get()));
    }

    public function roleSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->roleSelector($request->get()));
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
}
