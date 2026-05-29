<?php

namespace app\support;

use think\Response;

class ApiResponse
{
    public static function ok(mixed $data = null, string $message = 'ok', int $code = 200): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
        ]);
    }

    public static function fail(string $message, int $code = 400, mixed $data = null): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
        ]);
    }
}
