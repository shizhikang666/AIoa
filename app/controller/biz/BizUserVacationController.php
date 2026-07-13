<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizUserVacationService;
use think\Request;
use think\Response;

class BizUserVacationController extends BaseSysController
{
    public function __construct(private readonly BizUserVacationService $vacationService = new BizUserVacationService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->vacationService->page(
            $request->get(),
            $this->authPayload($request)
        ));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->vacationService->detail(
            $request->get(),
            $this->authPayload($request)
        ));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->vacationService->add(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->vacationService->edit(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->vacationService->delete(
            $this->body($request),
            $this->authPayload($request)
        ));
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
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
