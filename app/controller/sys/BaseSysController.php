<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\BaseController;
use app\support\ApiResponse;
use RuntimeException;
use Throwable;
use think\Request;
use think\Response;

abstract class BaseSysController extends BaseController
{
    protected function guard(callable $callback): Response
    {
        try {
            return ApiResponse::ok($callback());
        } catch (RuntimeException $exception) {
            $code = $exception->getCode();
            $status = is_int($code) && $code >= 400 && $code <= 599 ? $code : 400;

            return ApiResponse::fail($exception->getMessage(), $status);
        } catch (Throwable) {
            return ApiResponse::fail('服务器错误', 500);
        }
    }

    protected function requiredString(Request $request, string $key): string
    {
        $value = trim((string)$request->param($key, ''));
        if ($value === '') {
            throw new RuntimeException("缺少必要参数：{$key}", 400);
        }

        return $value;
    }

    protected function currentUserId(Request $request): string
    {
        $payload = $request->middleware('auth_payload', []);
        if (is_array($payload)) {
            $userId = (string)($payload['userId'] ?? $payload['user_id'] ?? $payload['id'] ?? '');
            if ($userId !== '') {
                return $userId;
            }
        }

        return $this->requiredString($request, 'userId');
    }

    /**
     * @return array<int, string>
     */
    protected function idList(Request $request): array
    {
        $value = $request->post('idList', $request->post('ids', $request->param('idList', [])));
        if ($value === [] || $value === null || $value === '') {
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
                    if (array_is_list($decoded)) {
                        $value = $decoded;
                    } else {
                        $value = $decoded['idList'] ?? $decoded['ids'] ?? $decoded['id'] ?? [];
                    }
                }
            }
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value) && $value !== null && $value !== '') {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($id): string {
            if (is_array($id)) {
                $id = $id['id'] ?? $id['ID'] ?? $id['userId'] ?? $id['USER_ID'] ?? $id['value'] ?? '';
            }

            return trim((string)$id);
        }, $value)));
    }
}
