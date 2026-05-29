<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\sys\ResourceService;
use think\Request;
use think\Response;

class ButtonController extends BaseSysController
{
    public function __construct(private readonly ResourceService $resourceService = new ResourceService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->buttonPage($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->detail($this->requiredString($request, 'id'), 'BUTTON'));
    }
}
