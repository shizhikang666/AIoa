<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\sys\ResourceService;
use think\Request;
use think\Response;

class FieldController extends BaseSysController
{
    public function __construct(private readonly ResourceService $resourceService = new ResourceService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->fieldPage($request->get()));
    }

    public function tree(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->fieldTree($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->detail($this->requiredString($request, 'id'), 'FIELD'));
    }

    public function menuTreeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->menuTreeSelector($request->get()));
    }
}
