<?php

declare(strict_types=1);

namespace app\controller\mobile;

use app\controller\sys\BaseSysController;
use app\service\mobile\MobileResourceService;
use think\Request;
use think\Response;

class MenuController extends BaseSysController
{
    public function __construct(private readonly MobileResourceService $resourceService = new MobileResourceService())
    {
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
