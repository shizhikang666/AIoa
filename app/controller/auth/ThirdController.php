<?php

declare(strict_types=1);

namespace app\controller\auth;

use app\controller\sys\BaseSysController;
use app\service\auth\ThirdService;
use app\support\ApiResponse;
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

    public function render(Request $request): Response
    {
        return ApiResponse::fail('第三方登录功能暂未开放', 400, [
            'platform' => (string)$request->get('platform', ''),
        ]);
    }

    public function callback(Request $request): Response
    {
        return ApiResponse::fail('第三方登录回调功能暂未开放', 400, [
            'platform' => (string)$request->get('platform', ''),
        ]);
    }
}
