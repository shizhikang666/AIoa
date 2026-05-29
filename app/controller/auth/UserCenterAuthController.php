<?php

namespace app\controller\auth;

use app\BaseController;
use app\service\auth\MenuService;
use app\service\auth\TokenService;
use app\support\ApiResponse;
use think\Request;
use think\Response;

class UserCenterAuthController extends BaseController
{
    public function __construct(
        private readonly TokenService $tokenService = new TokenService(),
        private readonly MenuService $menuService = new MenuService(),
    ) {
    }

    public function loginMenu(Request $request): Response
    {
        $payload = $this->tokenService->getPayload($this->tokenService->bearerFromRequest($request));
        if ($payload === null) {
            return ApiResponse::fail('unauthenticated', 401);
        }

        return ApiResponse::ok($this->menuService->loginMenu($payload));
    }
}
