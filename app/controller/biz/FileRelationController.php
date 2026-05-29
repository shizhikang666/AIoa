<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\FileRelationService;
use think\Request;
use think\Response;

class FileRelationController extends BaseSysController
{
    public function __construct(private readonly FileRelationService $fileRelationService = new FileRelationService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
