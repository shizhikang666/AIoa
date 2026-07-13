<?php

declare(strict_types=1);

namespace app\controller\auth;

use app\controller\sys\BaseSysController;
use app\service\auth\SessionMonitorService;
use think\Request;
use think\Response;

class SessionController extends BaseSysController
{
    public function __construct(private readonly SessionMonitorService $sessionMonitorService = new SessionMonitorService())
    {
    }

    public function analysis(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->analysis($request));
    }

    public function pageForB(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->pageForB($request->get(), $request));
    }

    public function pageForC(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->pageForC($request->get()));
    }

    public function exitSessionForB(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->exitSessionForB(
            $this->bodyList($request),
            $request
        ));
    }

    public function exitSessionForC(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->exitSessionForC(
            $this->bodyList($request),
            $request
        ));
    }

    public function exitTokenForB(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->exitTokenForB(
            $this->bodyList($request),
            $request
        ));
    }

    public function exitTokenForC(Request $request): Response
    {
        return $this->guard(fn () => $this->sessionMonitorService->exitTokenForC(
            $this->bodyList($request),
            $request
        ));
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
        $value = $input['idList'] ?? $input['ids'] ?? $input['userIdList'] ?? $input['tokenValueList'] ?? $input;

        if (isset($input['userId']) || isset($input['tokenValue'])) {
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

            $text = trim((string)$item);
            if ($text !== '') {
                $records[] = [
                    'id' => $text,
                    'userId' => $text,
                    'tokenValue' => $text,
                ];
            }
        }

        return $records;
    }
}
