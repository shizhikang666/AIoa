<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\JobService;
use think\Request;
use think\Response;

class JobController extends BaseSysController
{
    public function __construct(private readonly JobService $jobService = new JobService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->jobService->page($request->get()));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->jobService->list($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->jobService->detail($this->requiredString($request, 'id')));
    }

    public function getActionClass(): Response
    {
        return $this->guard(fn () => $this->jobService->actionClasses());
    }
}
