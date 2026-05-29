<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\user\UserDirectoryService;
use think\Request;
use think\Response;

class UserCenterController extends BaseSysController
{
    public function __construct(private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService())
    {
    }

    public function loginOrgTree(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginOrgTree($this->currentUserId($request)));
    }

    public function loginPositionInfo(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginPositionInfo($this->currentUserId($request)));
    }

    public function getUserListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getUserListByIdList($this->idList($request)));
    }

    public function getOrgListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getOrgListByIdList($this->idList($request)));
    }

    public function getPositionListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getPositionListByIdList($this->idList($request)));
    }

    public function getRoleListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getRoleListByIdList($this->idList($request)));
    }

    public function getAvatarById(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getAvatarById($this->requiredString($request, 'id')));
    }
}
