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
}
