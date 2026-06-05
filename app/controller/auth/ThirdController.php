<?php

declare(strict_types=1);

namespace app\controller\auth;

use app\controller\sys\BaseSysController;
use app\service\auth\ThirdService;
use think\Request;
use think\Response;

class ThirdController extends BaseSysController
{
    public function __construct(private readonly ThirdService $thirdService = new ThirdService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->thirdService->page($request->get()));
    }
}
