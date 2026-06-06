<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\MessageSseService;
use app\service\dev\MessageService;
use think\Request;
use think\Response;

class MessageController extends BaseSysController
{
    public function __construct(
        private readonly MessageService $messageService = new MessageService(),
        private readonly MessageSseService $messageSseService = new MessageSseService()
    )
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

    public function send(Request $request): Response
    {
        return $this->guard(fn () => $this->messageService->send(
            $this->bodyInput($request),
            $this->tenantId($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->messageService->delete(
            $this->bodyList($request),
            $this->tenantId($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function createSseConnect(Request $request): Response
    {
        return $this->messageSseService->connect(
            (string)$request->get('clientId', ''),
            $this->currentUserId($request)
        );
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

    /**
     * @return array<string, mixed>
     */
    private function bodyInput(Request $request): array
    {
        $input = $request->post();
        if ($input === []) {
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
        }

        return $input === [] ? $request->param() : $input;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bodyList(Request $request): array
    {
        $input = $request->post();
        if ($input === []) {
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
                    $input = $decoded;
                }
            }
        }
        if ($input === []) {
            $input = $request->param();
        }

        return $this->normalizeBodyList($input);
    }

    /**
     * @param array<mixed> $input
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBodyList(array $input): array
    {
        $value = $input['idList'] ?? $input['ids'] ?? $input['id'] ?? $input;
        if (isset($input['id'])) {
            $value = [$input];
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $records[] = $item;
                continue;
            }

            $id = trim((string)$item);
            if ($id !== '') {
                $records[] = ['id' => $id];
            }
        }

        return $records;
    }
}
