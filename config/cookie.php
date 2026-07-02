<?php
$cookieSecure = function_exists('env') ? env('COOKIE_SECURE', true) : true;
$cookieHttpOnly = function_exists('env') ? env('COOKIE_HTTPONLY', true) : true;
$cookieSameSite = function_exists('env') ? env('COOKIE_SAMESITE', 'lax') : 'lax';

// +----------------------------------------------------------------------
// | Cookie设置
// +----------------------------------------------------------------------
return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => '',
    //  cookie 启用安全传输
    'secure'    => filter_var($cookieSecure, FILTER_VALIDATE_BOOLEAN),
    // httponly设置
    'httponly'  => filter_var($cookieHttpOnly, FILTER_VALIDATE_BOOLEAN),
    // 是否使用 setcookie
    'setcookie' => true,
    // samesite 设置，支持 'strict' 'lax'
    'samesite'  => $cookieSameSite,
];
