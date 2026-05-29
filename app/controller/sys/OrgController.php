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

    public function userSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->userSelector($request->get()));
    }
}
