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
}
