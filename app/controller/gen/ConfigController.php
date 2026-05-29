<?php

declare(strict_types=1);

namespace app\controller\gen;

use app\controller\sys\BaseSysController;
use app\service\gen\ConfigService;
use think\Request;
use think\Response;

class ConfigController extends BaseSysController
{
    public function __construct(private readonly ConfigService $configService = new ConfigService())
    {
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
