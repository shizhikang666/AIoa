<?php

namespace app\middleware;

use app\service\auth\TokenService;
use app\support\ApiResponse;
use Closure;
use think\Request;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tokenService = new TokenService();
        $payload = $tokenService->getPayload($tokenService->bearerFromRequest($request));

        if ($payload === null) {
            return ApiResponse::fail('unauthenticated', 401);
        }

        $request->withMiddleware([
            'auth_payload' => $payload,
        ]);

        return $next($request);
    }
}
