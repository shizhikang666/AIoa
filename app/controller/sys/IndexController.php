<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\sys\IndexService;
use think\Request;
use think\Response;

class IndexController extends BaseSysController
{
    public function __construct(private readonly IndexService $indexService = new IndexService())
    {
    }

    public function scheduleList(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->scheduleList(
            $this->currentUserId($request),
            $this->requiredString($request, 'scheduleDate')
        ));
    }

    public function addSchedule(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->addSchedule(
            $this->currentUserId($request),
            $this->body($request)
        ));
    }

    public function deleteSchedule(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->deleteSchedule(
            $this->currentUserId($request),
            $this->idListFromBody($request)
        ));
    }

    public function messageList(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->messageList(
            $this->currentUserId($request),
            $request->param()
        ));
    }

    public function messagePage(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->messagePage(
            $this->currentUserId($request),
            $request->param()
        ));
    }

    public function messageDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->messageDetail(
            $this->currentUserId($request),
            $this->requiredString($request, 'id')
        ));
    }

    public function allMessageMarkRead(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->allMessageMarkRead(
            $this->currentUserId($request)
        ));
    }

    public function visLogList(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->visLogList($this->currentUserId($request)));
    }

    public function opLogList(Request $request): Response
    {
        return $this->guard(fn () => $this->indexService->opLogList($this->currentUserId($request)));
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

    /**
     * @return array<int, string>
     */
    private function idListFromBody(Request $request): array
    {
        $body = $this->body($request);
        $value = $body['idList'] ?? $body['ids'] ?? $body['id'] ?? $body;

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item): string {
            if (is_array($item)) {
                return trim((string)($item['id'] ?? ''));
            }

            return trim((string)$item);
        }, $value)));
    }
}
