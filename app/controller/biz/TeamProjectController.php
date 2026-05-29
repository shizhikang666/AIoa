<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\TeamProjectService;
use think\Request;
use think\Response;

class TeamProjectController extends BaseSysController
{
    public function __construct(private readonly TeamProjectService $teamProjectService = new TeamProjectService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->projectPage($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->projectDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
