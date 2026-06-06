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
}
