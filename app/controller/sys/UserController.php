<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\user\UserDirectoryService;
use think\Request;
use think\Response;

class UserController extends BaseSysController
{
    public function __construct(private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->detail($this->requiredString($request, 'id')));
    }
}
