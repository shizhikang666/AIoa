<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\TeamProjectTaskReadService;
use think\Request;
use think\Response;

class TeamProjectTaskController extends BaseSysController
{
    public function __construct(private readonly TeamProjectTaskReadService $readService = new TeamProjectTaskReadService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->readService->taskPage($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->readService->taskList($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->readService->taskDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function userEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->readService->taskUserEdit($this->body($request), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function body(Request $request): array
    {
        $input = $request->post();
        if ($input !== []) {
            return $input;
        }

        $raw = '';
        if (method_exists($request, 'getContent')) {
            $raw = trim((string)$request->getContent());
        }
        if ($raw === '' && method_exists($request, 'getInput')) {
            $raw = trim((string)$request->getInput());
        }
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
