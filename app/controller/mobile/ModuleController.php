<?php

declare(strict_types=1);

namespace app\controller\mobile;

use app\controller\sys\BaseSysController;
use app\service\mobile\MobileResourceService;
use think\Request;
use think\Response;

class ModuleController extends BaseSysController
{
    public function __construct(private readonly MobileResourceService $resourceService = new MobileResourceService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->modulePage($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->resourceService->detail($this->requiredString($request, 'id'), 'MODULE'));
    }
}
