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
}
