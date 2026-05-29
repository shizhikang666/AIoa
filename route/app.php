<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use app\middleware\AuthMiddleware;
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');

Route::group('auth/b', function () {
    Route::get('getPicCaptcha', 'auth.AuthController/getPicCaptcha');
    Route::post('doLogin', 'auth.AuthController/doLogin');
    Route::post('doLoginByPhone', 'auth.AuthController/doLoginByPhone');
    Route::get('doLogout', 'auth.AuthController/doLogout');
    Route::get('getLoginUser', 'auth.AuthController/getLoginUser');
    Route::post('safe/password', 'auth.AuthController/openSafe');
});

Route::group('sys/userCenter', function () {
    Route::get('loginMenu', 'auth.UserCenterAuthController/loginMenu');
    Route::get('loginOrgTree', 'sys.UserCenterController/loginOrgTree');
    Route::get('loginPositionInfo', 'sys.UserCenterController/loginPositionInfo');
    Route::get('loginWorkbench', 'sys.UserCenterController/loginWorkbench');
    Route::get('loginUnreadMessagePage', 'sys.UserCenterController/loginUnreadMessagePage');
    Route::get('loginUnreadMessageDetail', 'sys.UserCenterController/loginUnreadMessageDetail');
    Route::post('process/config', 'sys.UserCenterController/processConfig');
    Route::post('getOrgListByIdList', 'sys.UserCenterController/getOrgListByIdList');
    Route::post('getUserListByIdList', 'sys.UserCenterController/getUserListByIdList');
    Route::post('getPositionListByIdList', 'sys.UserCenterController/getPositionListByIdList');
    Route::post('getRoleListByIdList', 'sys.UserCenterController/getRoleListByIdList');
    Route::get('getAvatarById', 'sys.UserCenterController/getAvatarById');
})->middleware(AuthMiddleware::class);

Route::group('sys/index', function () {
    Route::get('schedule/list', 'sys.IndexController/scheduleList');
    Route::get('message/list', 'sys.IndexController/messageList');
    Route::get('message/page', 'sys.IndexController/messagePage');
    Route::get('message/detail', 'sys.IndexController/messageDetail');
    Route::get('visLog/list', 'sys.IndexController/visLogList');
    Route::get('opLog/list', 'sys.IndexController/opLogList');
})->middleware(AuthMiddleware::class);

Route::group('sys/org', function () {
    Route::get('page', 'sys.OrgController/page');
    Route::get('list', 'sys.OrgController/list');
    Route::get('tree', 'sys.OrgController/tree');
    Route::get('orgTreeSelector', 'sys.OrgController/treeSelector');
    Route::get('userSelector', 'sys.OrgController/userSelector');
    Route::get('detail', 'sys.OrgController/detail');
})->middleware(AuthMiddleware::class);

Route::group('sys/position', function () {
    Route::get('page', 'sys.PositionController/page');
    Route::get('list', 'sys.PositionController/list');
    Route::get('detail', 'sys.PositionController/detail');
    Route::get('orgTreeSelector', 'sys.PositionController/orgTreeSelector');
    Route::get('positionSelector', 'sys.PositionController/selector');
})->middleware(AuthMiddleware::class);

Route::group('sys/user', function () {
    Route::get('page', 'sys.UserController/page');
    Route::get('detail', 'sys.UserController/detail');
    Route::get('orgTreeSelector', 'sys.UserController/orgTreeSelector');
    Route::get('positionSelector', 'sys.UserController/positionSelector');
    Route::get('roleSelector', 'sys.UserController/roleSelector');
    Route::get('userSelector', 'sys.UserController/userSelector');
})->middleware(AuthMiddleware::class);

Route::group('sys/role', function () {
    Route::get('page', 'sys.RoleController/page');
    Route::get('detail', 'sys.RoleController/detail');
    Route::get('ownResource', 'sys.RoleController/ownResource');
    Route::get('ownMobileMenu', 'sys.RoleController/ownMobileMenu');
    Route::get('ownPermission', 'sys.RoleController/ownPermission');
    Route::get('ownUser', 'sys.RoleController/ownUser');
    Route::get('orgTreeSelector', 'sys.RoleController/orgTreeSelector');
    Route::get('resourceTreeSelector', 'sys.RoleController/resourceTreeSelector');
    Route::get('mobileMenuTreeSelector', 'sys.RoleController/mobileMenuTreeSelector');
    Route::get('permissionTreeSelector', 'sys.RoleController/permissionTreeSelector');
    Route::get('roleSelector', 'sys.RoleController/roleSelector');
    Route::get('userSelector', 'sys.RoleController/userSelector');
})->middleware(AuthMiddleware::class);

Route::group('sys/module', function () {
    Route::get('page', 'sys.ModuleController/page');
    Route::get('detail', 'sys.ModuleController/detail');
})->middleware(AuthMiddleware::class);

Route::group('sys/menu', function () {
    Route::get('page', 'sys.MenuController/page');
    Route::get('tree', 'sys.MenuController/tree');
    Route::get('detail', 'sys.MenuController/detail');
    Route::get('moduleSelector', 'sys.MenuController/moduleSelector');
    Route::get('menuTreeSelector', 'sys.MenuController/menuTreeSelector');
})->middleware(AuthMiddleware::class);

Route::group('sys/button', function () {
    Route::get('page', 'sys.ButtonController/page');
    Route::get('detail', 'sys.ButtonController/detail');
})->middleware(AuthMiddleware::class);

Route::group('mobile/module', function () {
    Route::get('page', 'mobile.ModuleController/page');
    Route::get('detail', 'mobile.ModuleController/detail');
})->middleware(AuthMiddleware::class);

Route::group('mobile/menu', function () {
    Route::get('tree', 'mobile.MenuController/tree');
    Route::get('detail', 'mobile.MenuController/detail');
    Route::get('moduleSelector', 'mobile.MenuController/moduleSelector');
    Route::get('menuTreeSelector', 'mobile.MenuController/menuTreeSelector');
})->middleware(AuthMiddleware::class);

Route::group('mobile/button', function () {
    Route::get('page', 'mobile.ButtonController/page');
    Route::get('detail', 'mobile.ButtonController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/task', function () {
    Route::get('count', 'biz.TaskController/count');
    Route::get('list', 'biz.TaskController/list');
    Route::get('page', 'biz.TaskController/page');
    Route::get('history/page', 'biz.TaskController/historyPage');
})->middleware(AuthMiddleware::class);

Route::group('biz/process', function () {
    Route::get('page', 'biz.ProcessController/page');
    Route::get('detail', 'biz.ProcessController/detail');
    Route::post('variable', 'biz.ProcessController/variable');
})->middleware(AuthMiddleware::class);
