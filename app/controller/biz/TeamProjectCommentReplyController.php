<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\TeamProjectTaskReadService;
use think\Request;
use think\Response;

class TeamProjectCommentReplyController extends BaseSysController
{
    public function __construct(private readonly TeamProjectTaskReadService $readService = new TeamProjectTaskReadService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->readService->projectReplyPage($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->readService->projectReplyDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
