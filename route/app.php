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

Route::group('auth/session', function () {
    Route::get('analysis', 'auth.SessionController/analysis');
    Route::get('b/page', 'auth.SessionController/pageForB');
    Route::get('c/page', 'auth.SessionController/pageForC');
    Route::post('b/exit', 'auth.SessionController/exitSessionForB');
    Route::post('c/exit', 'auth.SessionController/exitSessionForC');
})->middleware(AuthMiddleware::class);

Route::group('auth/token', function () {
    Route::post('b/exit', 'auth.SessionController/exitTokenForB');
    Route::post('c/exit', 'auth.SessionController/exitTokenForC');
})->middleware(AuthMiddleware::class);

Route::group('auth/third', function () {
    Route::get('page', 'auth.ThirdController/page');
})->middleware(AuthMiddleware::class);

Route::get('dev/config/sysBaseList', 'dev.ConfigController/sysBaseList');

Route::group('sys/userCenter', function () {
    Route::get('loginMenu', 'auth.UserCenterAuthController/loginMenu');
    Route::get('loginOrgTree', 'sys.UserCenterController/loginOrgTree');
    Route::get('loginPositionInfo', 'sys.UserCenterController/loginPositionInfo');
    Route::get('loginWorkbench', 'sys.UserCenterController/loginWorkbench');
    Route::get('loginUnreadMessagePage', 'sys.UserCenterController/loginUnreadMessagePage');
    Route::get('loginUnreadMessageDetail', 'sys.UserCenterController/loginUnreadMessageDetail');
    Route::post('process/config', 'sys.UserCenterController/processConfig');
    Route::post('process/config/edit', 'sys.UserCenterController/editProcessConfig');
    Route::post('updatePassword', 'sys.UserCenterController/updatePassword');
    Route::post('updateAvatar', 'sys.UserCenterController/updateAvatar');
    Route::post('updateSignature', 'sys.UserCenterController/updateSignature');
    Route::post('updateUserInfo', 'sys.UserCenterController/updateUserInfo');
    Route::post('updateUserWorkbench', 'sys.UserCenterController/updateUserWorkbench');
    Route::post('getOrgListByIdList', 'sys.UserCenterController/getOrgListByIdList');
    Route::post('getUserListByIdList', 'sys.UserCenterController/getUserListByIdList');
    Route::post('getPositionListByIdList', 'sys.UserCenterController/getPositionListByIdList');
    Route::post('getRoleListByIdList', 'sys.UserCenterController/getRoleListByIdList');
    Route::get('getAvatarById', 'sys.UserCenterController/getAvatarById');
})->middleware(AuthMiddleware::class);

Route::group('sys/index', function () {
    Route::post('schedule/add', 'sys.IndexController/addSchedule');
    Route::post('schedule/deleteSchedule', 'sys.IndexController/deleteSchedule');
    Route::get('schedule/list', 'sys.IndexController/scheduleList');
    Route::get('message/list', 'sys.IndexController/messageList');
    Route::get('message/page', 'sys.IndexController/messagePage');
    Route::get('message/detail', 'sys.IndexController/messageDetail');
    Route::post('message/allMessageMarkRead', 'sys.IndexController/allMessageMarkRead');
    Route::get('visLog/list', 'sys.IndexController/visLogList');
    Route::get('opLog/list', 'sys.IndexController/opLogList');
})->middleware(AuthMiddleware::class);

Route::group('sys/sysConfig', function () {
    Route::get('detail', 'sys.SysConfigController/detail');
})->middleware(AuthMiddleware::class);

Route::group('sys/org', function () {
    Route::get('page', 'sys.OrgController/page');
    Route::get('list', 'sys.OrgController/list');
    Route::get('tree', 'sys.OrgController/tree');
    Route::get('orgTreeSelector', 'sys.OrgController/treeSelector');
    Route::get('userSelector', 'sys.OrgController/userSelector');
    Route::get('detail', 'sys.OrgController/detail');
    Route::post('add', 'sys.OrgController/add');
    Route::post('edit', 'sys.OrgController/edit');
    Route::post('delete', 'sys.OrgController/delete');
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
    Route::get('list/detail', 'sys.UserController/listDetail');
    Route::get('detail', 'sys.UserController/detail');
    Route::post('add', 'sys.UserController/add');
    Route::post('edit', 'sys.UserController/edit');
    Route::post('delete', 'sys.UserController/delete');
    Route::post('disableUser', 'sys.UserController/disableUser');
    Route::post('enableUser', 'sys.UserController/enableUser');
    Route::post('resetPassword', 'sys.UserController/resetPassword');
    Route::get('ownRole', 'sys.UserController/ownRole');
    Route::post('grantRole', 'sys.UserController/grantRole');
    Route::get('ownResource', 'sys.UserController/ownResource');
    Route::post('grantResource', 'sys.UserController/grantResource');
    Route::get('ownPermission', 'sys.UserController/ownPermission');
    Route::post('grantPermission', 'sys.UserController/grantPermission');
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

Route::group('sys/field', function () {
    Route::get('page', 'sys.FieldController/page');
    Route::get('tree', 'sys.FieldController/tree');
    Route::get('detail', 'sys.FieldController/detail');
    Route::get('MenuTreeSelector', 'sys.FieldController/menuTreeSelector');
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

Route::group('dev/dict', function () {
    Route::get('page', 'dev.DictController/page');
    Route::get('list', 'dev.DictController/list');
    Route::get('tree', 'dev.DictController/tree');
    Route::get('detail', 'dev.DictController/detail');
})->middleware(AuthMiddleware::class);

Route::group('dev/config', function () {
    Route::get('page', 'dev.ConfigController/page');
    Route::get('list', 'dev.ConfigController/list');
    Route::get('detail', 'dev.ConfigController/detail');
})->middleware(AuthMiddleware::class);

Route::group('dev/file', function () {
    Route::get('page', 'dev.FileController/page');
    Route::get('list', 'dev.FileController/list');
    Route::get('detail', 'dev.FileController/detail');
})->middleware(AuthMiddleware::class);

Route::group('dev/email', function () {
    Route::get('page', 'dev.EmailController/page');
    Route::get('detail', 'dev.EmailController/detail');
})->middleware(AuthMiddleware::class);

Route::group('dev/sms', function () {
    Route::get('page', 'dev.SmsController/page');
    Route::get('detail', 'dev.SmsController/detail');
})->middleware(AuthMiddleware::class);

Route::group('dev/job', function () {
    Route::get('page', 'dev.JobController/page');
    Route::get('list', 'dev.JobController/list');
    Route::get('detail', 'dev.JobController/detail');
    Route::get('getActionClass', 'dev.JobController/getActionClass');
})->middleware(AuthMiddleware::class);

Route::group('dev/monitor', function () {
    Route::get('serverInfo', 'dev.MonitorController/serverInfo');
    Route::get('networkInfo', 'dev.MonitorController/networkInfo');
})->middleware(AuthMiddleware::class);

Route::group('dev/log', function () {
    Route::get('page', 'dev.LogController/page');
    Route::get('detail', 'dev.LogController/detail');
    Route::get('vis/lineChartData', 'dev.LogController/visLineChartData');
    Route::get('vis/pieChartData', 'dev.LogController/visPieChartData');
    Route::get('op/barChartData', 'dev.LogController/opBarChartData');
    Route::get('op/pieChartData', 'dev.LogController/opPieChartData');
})->middleware(AuthMiddleware::class);

Route::group('dev/message', function () {
    Route::get('page', 'dev.MessageController/page');
    Route::get('detail', 'dev.MessageController/detail');
    Route::post('send', 'dev.MessageController/send');
    Route::post('delete', 'dev.MessageController/delete');
    Route::get('createSseConnect', 'dev.MessageController/createSseConnect');
})->middleware(AuthMiddleware::class);

Route::group('gen/basic', function () {
    Route::get('page', 'gen.BasicController/page');
    Route::get('detail', 'gen.BasicController/detail');
    Route::get('tables', 'gen.BasicController/tables');
    Route::get('tableColumns', 'gen.BasicController/tableColumns');
    Route::get('mobileModuleSelector', 'gen.BasicController/mobileModuleSelector');
})->middleware(AuthMiddleware::class);

Route::group('gen/config', function () {
    Route::get('list', 'gen.ConfigController/list');
    Route::get('detail', 'gen.ConfigController/detail');
})->middleware(AuthMiddleware::class);

Route::group('tenants/tenant', function () {
    Route::get('page', 'tenant.TenantsController/page');
    Route::get('detail', 'tenant.TenantsController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizproduct', function () {
    Route::get('page', 'biz.ProductController/page');
    Route::get('list', 'biz.ProductController/list');
    Route::get('detail', 'biz.ProductController/detail');
    Route::post('children', 'biz.ProductController/children');
    Route::post('add', 'biz.ProductController/add');
    Route::post('edit', 'biz.ProductController/edit');
    Route::post('delete', 'biz.ProductController/delete');
    Route::post('edit/status', 'biz.ProductController/editStatus');
    Route::post('reconciliation/edit', 'biz.ProductController/editReconciliation');
})->middleware(AuthMiddleware::class);

Route::group('biz/supplier', function () {
    Route::get('page', 'biz.SupplierController/page');
    Route::get('list', 'biz.SupplierController/list');
    Route::get('list/query/name', 'biz.SupplierController/queryByName');
    Route::post('add', 'biz.SupplierController/add');
    Route::post('edit', 'biz.SupplierController/edit');
    Route::post('delete', 'biz.SupplierController/delete');
    Route::get('detail', 'biz.SupplierController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/settlementaccount', function () {
    Route::get('page', 'biz.SettlementAccountController/page');
    Route::get('list', 'biz.SettlementAccountController/list');
    Route::get('detail', 'biz.SettlementAccountController/detail');
    Route::get('queryName', 'biz.SettlementAccountController/queryName');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizpaymentrecord', function () {
    Route::get('page', 'biz.PaymentRecordController/page');
    Route::get('listdetails', 'biz.PaymentRecordController/listDetails');
    Route::get('list', 'biz.PaymentRecordController/list');
    Route::get('detail', 'biz.PaymentRecordController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizexpenditurerecord', function () {
    Route::get('page', 'biz.ExpenditureRecordController/page');
    Route::get('listDetails', 'biz.ExpenditureRecordController/listDetails');
    Route::get('list', 'biz.ExpenditureRecordController/list');
    Route::get('detail', 'biz.ExpenditureRecordController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizcollectionreceipt', function () {
    Route::get('page', 'biz.CollectionReceiptController/page');
    Route::get('list', 'biz.CollectionReceiptController/list');
    Route::get('detail', 'biz.CollectionReceiptController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizdebitnote', function () {
    Route::get('page', 'biz.DebitNoteController/page');
    Route::get('list', 'biz.DebitNoteController/list');
    Route::get('detail', 'biz.DebitNoteController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizfilerelation', function () {
    Route::get('page', 'biz.FileRelationController/page');
    Route::get('list', 'biz.FileRelationController/list');
    Route::get('detail', 'biz.FileRelationController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizhistoryexcel', function () {
    Route::get('page', 'biz.BizHistoryExcelController/page');
    Route::post('add', 'biz.BizHistoryExcelController/add');
    Route::post('edit', 'biz.BizHistoryExcelController/edit');
    Route::post('delete', 'biz.BizHistoryExcelController/delete');
    Route::get('detail', 'biz.BizHistoryExcelController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamproject', function () {
    Route::get('page', 'biz.TeamProjectController/page');
    Route::get('detail', 'biz.TeamProjectController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojectuser', function () {
    Route::get('page', 'biz.TeamProjectUserController/page');
    Route::get('list', 'biz.TeamProjectUserController/list');
    Route::post('add', 'biz.TeamProjectUserController/add');
    Route::post('manage/add', 'biz.TeamProjectUserController/manageAdd');
    Route::post('delete', 'biz.TeamProjectUserController/delete');
    Route::get('detail', 'biz.TeamProjectUserController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojecttaskcategory', function () {
    Route::get('page', 'biz.TeamProjectTaskCategoryController/page');
    Route::get('list', 'biz.TeamProjectTaskCategoryController/list');
    Route::post('add', 'biz.TeamProjectTaskCategoryController/add');
    Route::post('edit', 'biz.TeamProjectTaskCategoryController/edit');
    Route::post('sort/edit', 'biz.TeamProjectTaskCategoryController/sortEdit');
    Route::post('delete', 'biz.TeamProjectTaskCategoryController/delete');
    Route::get('detail', 'biz.TeamProjectTaskCategoryController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojecttask', function () {
    Route::get('page', 'biz.TeamProjectTaskController/page');
    Route::get('list', 'biz.TeamProjectTaskController/list');
    Route::post('add', 'biz.TeamProjectTaskController/add');
    Route::post('edit', 'biz.TeamProjectTaskController/edit');
    Route::post('delete', 'biz.TeamProjectTaskController/delete');
    Route::get('detail', 'biz.TeamProjectTaskController/detail');
    Route::post('user/edit', 'biz.TeamProjectTaskController/userEdit');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojecttaskuser', function () {
    Route::get('page', 'biz.TeamProjectTaskUserController/page');
    Route::get('detail', 'biz.TeamProjectTaskUserController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojectcomment', function () {
    Route::get('page', 'biz.TeamProjectCommentController/page');
    Route::get('list', 'biz.TeamProjectCommentController/list');
    Route::post('add', 'biz.TeamProjectCommentController/add');
    Route::post('delete', 'biz.TeamProjectCommentController/delete');
    Route::get('detail', 'biz.TeamProjectCommentController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojectcommentreply', function () {
    Route::get('page', 'biz.TeamProjectCommentReplyController/page');
    Route::post('add', 'biz.TeamProjectCommentReplyController/add');
    Route::post('edit', 'biz.TeamProjectCommentReplyController/edit');
    Route::post('delete', 'biz.TeamProjectCommentReplyController/delete');
    Route::get('detail', 'biz.TeamProjectCommentReplyController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizteamprojecttaskcomment', function () {
    Route::get('page', 'biz.TeamProjectTaskCommentController/page');
    Route::get('list', 'biz.TeamProjectTaskCommentController/list');
    Route::post('add', 'biz.TeamProjectTaskCommentController/add');
    Route::post('edit', 'biz.TeamProjectTaskCommentController/edit');
    Route::post('delete', 'biz.TeamProjectTaskCommentController/delete');
    Route::get('detail', 'biz.TeamProjectTaskCommentController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/warehouses', function () {
    Route::get('page', 'biz.WarehousesController/page');
    Route::get('list', 'biz.WarehousesController/list');
    Route::post('add', 'biz.WarehousesController/add');
    Route::post('edit', 'biz.WarehousesController/edit');
    Route::post('delete', 'biz.WarehousesController/delete');
    Route::get('detail', 'biz.WarehousesController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/inventory', function () {
    Route::get('page', 'biz.InventoryController/page');
    Route::get('list', 'biz.InventoryController/list');
    Route::get('detail', 'biz.InventoryController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/warehouses/delivery', function () {
    Route::get('page', 'biz.DeliveryRecordController/page');
    Route::get('exportOtherCompanyRecordsList', 'biz.DeliveryRecordController/exportOtherCompanyRecordsList');
    Route::get('detail', 'biz.DeliveryRecordController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizpurchaseorder', function () {
    Route::get('page', 'biz.PurchaseOrderController/page');
    Route::get('detail/list', 'biz.PurchaseOrderController/detailList');
    Route::get('list', 'biz.PurchaseOrderController/list');
    Route::get('detail', 'biz.PurchaseOrderController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleproject', function () {
    Route::get('page', 'biz.SaleProjectController/page');
    Route::get('detail', 'biz.SaleProjectController/detail');
    Route::get('product', 'biz.SaleProjectController/product');
    Route::post('cost/details', 'biz.SaleProjectController/costDetails');
    Route::post('cost', 'biz.SaleProjectController/cost');
})->middleware(AuthMiddleware::class);
Route::get('biz/saleproject/case/page', 'biz.SaleProjectController/casePage')->middleware(AuthMiddleware::class);
Route::get('biz/saleproject/operation/page', 'biz.SaleProjectController/operationPage')->middleware(AuthMiddleware::class);
Route::get('biz/saleproject/public/page', 'biz.SaleProjectController/publicPage')->middleware(AuthMiddleware::class);
Route::get('biz/saleproject/list/detail', 'biz.SaleProjectController/listDetail')->middleware(AuthMiddleware::class);

Route::group('biz/customer', function () {
    Route::get('page', 'biz.CustomerController/page');
    Route::post('add', 'biz.CustomerController/add');
    Route::post('edit', 'biz.CustomerController/edit');
    Route::post('delete', 'biz.CustomerController/delete');
    Route::get('detail', 'biz.CustomerController/detail');
    Route::post('detail/list', 'biz.CustomerController/detailList');
    Route::post('head/edit', 'biz.CustomerController/headEdit');
})->middleware(AuthMiddleware::class);

Route::group('biz/customerfollowup', function () {
    Route::get('page', 'biz.CustomerFollowUpController/page');
    Route::post('add', 'biz.CustomerFollowUpController/add');
    Route::post('edit', 'biz.CustomerFollowUpController/edit');
    Route::post('delete', 'biz.CustomerFollowUpController/delete');
    Route::get('detail', 'biz.CustomerFollowUpController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectfollowup', function () {
    Route::get('page', 'biz.SaleProjectFollowUpController/page');
    Route::post('add', 'biz.SaleProjectFollowUpController/add');
    Route::post('edit', 'biz.SaleProjectFollowUpController/edit');
    Route::post('delete', 'biz.SaleProjectFollowUpController/delete');
    Route::get('detail', 'biz.SaleProjectFollowUpController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/salesprojectfieldchangelog', function () {
    Route::get('page', 'biz.SalesProjectFieldChangeLogController/page');
    Route::post('add', 'biz.SalesProjectFieldChangeLogController/add');
    Route::post('edit', 'biz.SalesProjectFieldChangeLogController/edit');
    Route::post('delete', 'biz.SalesProjectFieldChangeLogController/delete');
    Route::get('detail', 'biz.SalesProjectFieldChangeLogController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectinvoicing', function () {
    Route::get('page', 'biz.SaleProjectInvoicingController/page');
    Route::get('customer', 'biz.SaleProjectInvoicingController/customer');
    Route::get('detail', 'biz.SaleProjectInvoicingController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectinvoice', function () {
    Route::get('page', 'biz.SaleProjectInvoiceController/page');
    Route::get('list', 'biz.SaleProjectInvoiceController/list');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectinvoiceItem', function () {
    Route::get('page', 'biz.SaleProjectInvoiceItemController/page');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectreissueorder', function () {
    Route::get('list/query', 'biz.SaleProjectReissueOrderController/listQuery');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectproductinfo', function () {
    Route::get('page', 'biz.SaleProjectProductInfoController/page');
    Route::get('list', 'biz.SaleProjectProductInfoController/list');
    Route::post('add', 'biz.SaleProjectProductInfoController/add');
    Route::post('edit', 'biz.SaleProjectProductInfoController/edit');
    Route::post('delete', 'biz.SaleProjectProductInfoController/delete');
    Route::get('detail', 'biz.SaleProjectProductInfoController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectproductitemrelation', function () {
    Route::post('list', 'biz.SaleProjectProductItemRelationController/list');
    Route::post('mark/edit', 'biz.SaleProjectProductItemRelationController/editMark');
})->middleware(AuthMiddleware::class);

Route::group('biz/saleprojectproductitem', function () {
    Route::post('mark/edit', 'biz.SaleProjectProductItemController/editMark');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizdatareport', function () {
    Route::post('saleproject', 'biz.BizDataReportController/saleProjectAmount');
    Route::post('saleproject/list', 'biz.BizDataReportController/saleProjectList');
    Route::post('saleproject/report', 'biz.BizDataReportController/saleProjectReport');
    Route::post('saleproject/UnpaidPayment', 'biz.BizDataReportController/saleProjectUnpaidPayment');
    Route::post('settlement/income', 'biz.BizDataReportController/settlementIncome');
    Route::post('settlement/expenses', 'biz.BizDataReportController/settlementExpenses');
    Route::post('saleProfit', 'biz.BizDataReportController/saleProfit');
    Route::post('summary/statistics', 'biz.BizDataReportController/summaryStatistics');
    Route::post('saleProjectList/details', 'biz.BizDataReportController/saleProjectListDetails');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizleaveapplication', function () {
    Route::get('page', 'biz.BizLeaveApplicationController/page');
    Route::get('my/page', 'biz.BizLeaveApplicationController/myPage');
    Route::get('detail', 'biz.BizLeaveApplicationController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizuservacation', function () {
    Route::get('page', 'biz.BizUserVacationController/page');
    Route::get('detail', 'biz.BizUserVacationController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizdraft', function () {
    Route::get('detail', 'biz.BizDraftController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/settlementaccountpayment', function () {
    Route::get('page', 'biz.SettlementAccountPaymentController/page');
    Route::get('list', 'biz.SettlementAccountPaymentController/list');
})->middleware(AuthMiddleware::class);

Route::group('biz/bizpayroll', function () {
    Route::get('page', 'biz.BizPayrollController/page');
    Route::get('mypage', 'biz.BizPayrollController/myPage');
    Route::get('detail', 'biz.BizPayrollController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/ccrecords', function () {
    Route::get('page', 'biz.CcRecordsController/page');
    Route::post('delete', 'biz.CcRecordsController/delete');
    Route::get('detail', 'biz.CcRecordsController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/projectrate', function () {
    Route::get('page', 'biz.SaleProjectRateController/page');
    Route::post('add', 'biz.SaleProjectRateController/add');
    Route::get('list', 'biz.SaleProjectRateController/list');
    Route::post('delete', 'biz.SaleProjectRateController/delete');
    Route::get('detail', 'biz.SaleProjectRateController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/org', function () {
    Route::get('page', 'sys.OrgController/page');
    Route::get('list', 'sys.OrgController/list');
    Route::get('tree', 'sys.OrgController/tree');
    Route::get('detail', 'sys.OrgController/detail');
    Route::get('orgTreeSelector', 'sys.OrgController/treeSelector');
    Route::get('userSelector', 'sys.OrgController/userSelector');
    Route::post('add', 'sys.OrgController/bizAdd');
    Route::post('edit', 'sys.OrgController/bizEdit');
    Route::post('delete', 'sys.OrgController/bizDelete');
})->middleware(AuthMiddleware::class);

Route::group('biz/user', function () {
    Route::get('page', 'sys.UserController/page');
    Route::get('list/detail', 'sys.UserController/listDetail');
    Route::get('detail', 'sys.UserController/detail');
    Route::post('add', 'sys.UserController/bizAdd');
    Route::post('edit', 'sys.UserController/bizEdit');
    Route::post('center/edit', 'sys.UserCenterController/centerEdit');
    Route::post('delete', 'sys.UserController/bizDelete');
    Route::post('disableUser', 'sys.UserController/bizDisableUser');
    Route::post('enableUser', 'sys.UserController/bizEnableUser');
    Route::post('resetPassword', 'sys.UserController/bizResetPassword');
    Route::get('ownRole', 'sys.UserController/ownRole');
    Route::post('grantRole', 'sys.UserController/bizGrantRole');
    Route::get('orgTreeSelector', 'sys.UserController/orgTreeSelector');
    Route::get('positionSelector', 'sys.UserController/positionSelector');
    Route::get('roleSelector', 'sys.UserController/roleSelector');
    Route::get('userSelector', 'sys.UserController/userSelector');
})->middleware(AuthMiddleware::class);

Route::group('biz/position', function () {
    Route::get('page', 'sys.PositionController/page');
    Route::get('list', 'sys.PositionController/list');
    Route::get('detail', 'sys.PositionController/detail');
    Route::get('orgTreeSelector', 'sys.PositionController/orgTreeSelector');
    Route::get('positionSelector', 'sys.PositionController/selector');
})->middleware(AuthMiddleware::class);

Route::group('biz/dict', function () {
    Route::get('page', 'dev.DictController/page');
    Route::get('tree', 'dev.DictController/tree');
    Route::get('treeAll', 'dev.DictController/treeAll');
})->middleware(AuthMiddleware::class);

Route::group('biz/returnorder', function () {
    Route::get('page', 'biz.ReturnOrderController/page');
    Route::get('query', 'biz.ReturnOrderController/query');
    Route::get('detail', 'biz.ReturnOrderController/detail');
})->middleware(AuthMiddleware::class);

Route::group('biz/task', function () {
    Route::get('count', 'biz.TaskController/count');
    Route::get('list', 'biz.TaskController/list');
    Route::get('page', 'biz.TaskController/page');
    Route::get('history/page', 'biz.TaskController/historyPage');
    Route::get('runtime/activity/detail', 'biz.TaskController/runtimeActivityDetail');
})->middleware(AuthMiddleware::class);

Route::group('biz/process', function () {
    Route::get('page', 'biz.ProcessController/page');
    Route::get('all/page', 'biz.ProcessController/allPage');
    Route::get('query', 'biz.ProcessController/query');
    Route::post('query/list', 'biz.ProcessController/queryList');
    Route::get('project/runtime/query/list', 'biz.ProcessController/projectRuntimeQueryList');
    Route::post('fileList', 'biz.ProcessController/fileList');
    Route::get('detail', 'biz.ProcessController/detail');
    Route::post('variable', 'biz.ProcessController/variable');
})->middleware(AuthMiddleware::class);
