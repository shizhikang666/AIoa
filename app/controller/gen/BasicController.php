<?php

declare(strict_types=1);

namespace app\controller\gen;

use app\controller\sys\BaseSysController;
use app\service\gen\BasicService;
use think\Request;
use think\Response;

class BasicController extends BaseSysController
{
    public function __construct(private readonly BasicService $basicService = new BasicService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->detail($this->requiredString($request, 'id')));
    }

    public function tables(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->tables($request->get()));
    }

    public function tableColumns(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->tableColumns($this->requiredString($request, 'tableName')));
    }

    public function mobileModuleSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->mobileModuleSelector($request->get()));
    }
}
