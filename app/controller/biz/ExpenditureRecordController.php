<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\ExpenditureRecordService;
use app\support\ApiResponse;
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

    public function add(): Response
    {
        return $this->deferredWrite('expenditure record add');
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->expenditureRecordService->edit($this->body($request), $this->authPayload($request)));
    }

    public function editAccount(Request $request): Response
    {
        return $this->guard(fn () => $this->expenditureRecordService->editAccount($this->body($request), $this->authPayload($request)));
    }

    public function delete(): Response
    {
        return $this->deferredWrite('expenditure record delete');
    }

    private function deferredWrite(string $operation): Response
    {
        return ApiResponse::fail($operation . ' is deferred', 400, [
            'operation' => $operation,
        ]);
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
