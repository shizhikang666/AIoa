<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\MonitorService;
use think\Response;

class MonitorController extends BaseSysController
{
    public function __construct(private readonly MonitorService $monitorService = new MonitorService())
    {
    }

    public function serverInfo(): Response
    {
        return $this->guard(fn () => $this->monitorService->serverInfo());
    }
}
