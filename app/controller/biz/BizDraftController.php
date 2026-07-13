<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizDraftService;
use think\Request;
use think\Response;

class BizDraftController extends BaseSysController
{
    public function __construct(private readonly BizDraftService $draftService = new BizDraftService())
    {
    }

    public function addSaleProjectDraft(Request $request): Response
    {
        return $this->guard(fn () => $this->draftService->addOrEditSaleProjectDraft($this->body($request), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->draftService->detail(
            $this->requiredString($request, 'id'),
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
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
