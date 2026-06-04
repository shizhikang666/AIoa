<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\CcRecordsService;
use think\Request;
use think\Response;

class CcRecordsController extends BaseSysController
{
    public function __construct(private readonly CcRecordsService $ccRecordsService = new CcRecordsService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->ccRecordsService->page(
            $request->get(),
            $this->authPayload($request)
        ));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->ccRecordsService->detail(
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
