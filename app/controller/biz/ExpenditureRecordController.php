<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\ExpenditureRecordService;
use think\Request;
use think\Response;

class ExpenditureRecordController extends BaseSysController
{
    public function __construct(private readonly ExpenditureRecordService $expenditureRecordService = new ExpenditureRecordService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->expenditureRecordService->page($request->get(), $this->authPayload($request)));
    }

    public function listDetails(Request $request): Response
    {
        return $this->guard(fn () => $this->expenditureRecordService->listDetails($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->expenditureRecordService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->expenditureRecordService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
