<?php

namespace app\support;

use think\Response;

class ApiResponse
{
    private const MESSAGE_MAP = [
        'ok' => '成功',
        'server error' => '服务器错误',
        'unauthenticated' => '未登录或登录已过期',
        'permission denied' => '权限不足',
        'message not found or permission denied' => '消息不存在或权限不足',
        'account and password are required' => '请输入账号和密码',
        'account or password is incorrect' => '账号或密码错误',
        'account is disabled' => '账号已被禁用',
        'SM2 encrypted password requires AUTH_SM2_PRIVATE_KEY runtime configuration' => '加密密码需要配置SM2私钥',
        'password is required' => '请输入密码',
        'password is incorrect' => '密码错误',
        'captcha code and request number are required together' => '请输入验证码',
        'captcha is incorrect or expired' => '验证码错误或已过期',
        'login user not found' => '登录用户不存在',
        'phone verification code sending is deferred' => '手机验证码发送功能暂未开放',
        'phone-code login is deferred in auth-agent phase 2' => '手机号验证码登录功能暂未开放',
        'web push subscription is deferred' => '网页推送订阅功能暂未开放',
        'third-party auth render is deferred' => '第三方登录功能暂未开放',
        'third-party auth callback is deferred' => '第三方登录回调功能暂未开放',
        'password recovery phone verification is deferred' => '手机找回密码验证码功能暂未开放',
        'password recovery email verification is deferred' => '邮箱找回密码验证码功能暂未开放',
        'password recovery by phone is deferred' => '手机找回密码功能暂未开放',
        'password recovery by email is deferred' => '邮箱找回密码功能暂未开放',
        'safe verification required' => '需要安全验证',
        'sms sending is deferred' => '短信发送功能暂未开放',
        'non-super-admin user cannot be granted system module resources' => '只有超管角色用户可以授权系统模块资源',
        'non-super roles cannot be granted system module resources' => '只有超管角色可以授权系统模块资源',
    ];

    public static function ok(mixed $data = null, string $message = '成功', int $code = 200): Response
    {
        $message = self::normalizeMessage($message, '成功');

        return json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
        ]);
    }

    public static function fail(string $message, int $code = 400, mixed $data = null): Response
    {
        $message = self::normalizeMessage($message, '请求失败');

        return json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
        ]);
    }

    private static function normalizeMessage(string $message, string $fallback): string
    {
        $message = trim($message);
        if ($message === '') {
            return $fallback;
        }

        if (isset(self::MESSAGE_MAP[$message])) {
            return self::MESSAGE_MAP[$message];
        }

        $hasChinese = preg_match('/\p{Han}/u', $message) === 1;
        $hasEnglish = preg_match('/[A-Za-z]/', $message) === 1;

        return !$hasChinese && $hasEnglish ? $fallback : $message;
    }
}
