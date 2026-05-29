<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\DebitNoteService;
use think\Request;
use think\Response;

class DebitNoteController extends BaseSysController
{
    public function __construct(private readonly DebitNoteService $debitNoteService = new DebitNoteService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->debitNoteService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->debitNoteService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->debitNoteService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
