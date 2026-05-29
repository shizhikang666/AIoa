<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\MessageService;
use think\Request;
use think\Response;

class MessageController extends BaseSysController
{
    public function __construct(private readonly MessageService $messageService = new MessageService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->messageService->page($request->get(), $this->tenantId($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->messageService->detail($this->requiredString($request, 'id'), $this->tenantId($request)));
    }

    private function tenantId(Request $request): ?string
    {
        $payload = $request->middleware('auth_payload', []);
        if (!is_array($payload)) {
            return null;
        }

        $tenantId = (string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '');

        return $tenantId === '' ? null : $tenantId;
    }
}
