<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\ConfigService;
use think\Request;
use think\Response;

class ConfigController extends BaseSysController
{
    public function __construct(private readonly ConfigService $configService = new ConfigService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->page($request->get()));
    }

    public function sysBaseList(): Response
    {
        return $this->guard(fn () => $this->configService->sysBaseList());
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->list($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->detail($this->requiredString($request, 'id')));
    }
}
