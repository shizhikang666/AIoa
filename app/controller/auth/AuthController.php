<?php

namespace app\controller\auth;

use app\BaseController;
use app\service\auth\AuthService;
use app\support\ApiResponse;
use RuntimeException;
use Throwable;
use think\Request;
use think\Response;

class AuthController extends BaseController
{
    public function __construct(
        protected AuthService $authService = new AuthService(),
    ) {
    }

    public function getPicCaptcha(): Response
    {
        return ApiResponse::ok($this->authService->getPicCaptcha());
    }

    public function getPhoneValidCode(): Response
    {
        return ApiResponse::fail('手机验证码发送功能暂未开放', 400);
    }

    public function doLogin(Request $request): Response
    {
        return $this->guard(fn () => ApiResponse::ok($this->authService->login($request->post())));
    }

    public function doLoginByPhone(): Response
    {
        return ApiResponse::fail('手机号验证码登录功能暂未开放', 400);
    }

    public function subscription(): Response
    {
        return ApiResponse::fail('网页推送订阅功能暂未开放', 400);
    }

    public function doLogout(Request $request): Response
    {
        $this->authService->logout($request);

        return ApiResponse::ok();
    }

    public function getLoginUser(Request $request): Response
    {
        return $this->guard(fn () => ApiResponse::ok($this->authService->currentUser($request)));
    }

    public function openSafe(Request $request): Response
    {
        return $this->guard(fn () => ApiResponse::ok($this->authService->openSafe($request->post(), $request)));
    }

    private function guard(callable $callback): Response
    {
        try {
            return $callback();
        } catch (RuntimeException $exception) {
            $code = $exception->getCode();
            $status = is_int($code) && $code >= 400 && $code <= 599 ? $code : 400;

            return ApiResponse::fail($exception->getMessage(), $status);
        } catch (Throwable) {
            return ApiResponse::fail('服务器错误', 500);
        }
    }
}
