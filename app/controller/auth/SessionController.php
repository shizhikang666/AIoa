<?php

declare(strict_types=1);

namespace app\controller\auth;

use app\controller\sys\BaseSysController;
use app\service\auth\SessionMonitorService;
use think\Request;
use think\Response;

class SessionController extends BaseSysController
{
    public function __construct(private readonly SessionMonitorService $sessionMonitorService = new SessionMonitorService())
    {
    }

    public function analysis(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->analysis($request));
    }

    public function pageForB(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->pageForB($request->get(), $request));
    }

    public function pageForC(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->pageForC($request->get()));
    }
}
