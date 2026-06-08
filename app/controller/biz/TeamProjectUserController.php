<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\TeamProjectService;
use think\Request;
use think\Response;

class TeamProjectUserController extends BaseSysController
{
    public function __construct(private readonly TeamProjectService $teamProjectService = new TeamProjectService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberPage($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberList($request->get(), $this->authPayload($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberAdd($this->body($request), $this->authPayload($request)));
    }

    public function manageAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberAdd($this->body($request), $this->authPayload($request), 'MANAGE'));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberEdit($this->body($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberDelete($this->body($request), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->teamProjectService->memberDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
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
