<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\sys\ResourceService;
use think\Request;
use think\Response;

class MenuController extends BaseSysController
{
    public function __construct(private readonly ResourceService $resourceService = new ResourceService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->menuPage($request->get()));
    }

    public function tree(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->menuTree($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->detail($this->requiredString($request, 'id'), 'MENU'));
    }

    public function moduleSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->moduleSelector($request->get()));
    }

    public function menuTreeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->menuTreeSelector($request->get()));
    }
}
