# Public File Change Request

## Request

Register read-only user, organization, and position API routes in `route/app.php`.

## Reason

The user-agent Phase 2 service layer adds read-only service methods for:

- organization tree and detail
- position page/detail/selector
- user page/detail
- current user organization tree
- current user position info
- list-by-id selectors

api-agent needs route registration to expose these service methods through ThinkPHP controllers after the final merge order brings `refactor/db`, `refactor/auth`, and `refactor/user` into `refactor/thinkphp-main`.

## Proposed Route Groups

Do not execute this change until approved.

```php
Route::group('sys/org', function () {
    Route::get('tree', 'sys.OrgController/tree');
    Route::get('orgTreeSelector', 'sys.OrgController/treeSelector');
    Route::get('detail', 'sys.OrgController/detail');
});

Route::group('sys/position', function () {
    Route::get('page', 'sys.PositionController/page');
    Route::get('detail', 'sys.PositionController/detail');
    Route::get('positionSelector', 'sys.PositionController/selector');
});

Route::group('sys/user', function () {
    Route::get('page', 'sys.UserController/page');
    Route::get('detail', 'sys.UserController/detail');
});

Route::group('sys/userCenter', function () {
    Route::get('loginOrgTree', 'sys.UserCenterController/loginOrgTree');
    Route::get('loginPositionInfo', 'sys.UserCenterController/loginPositionInfo');
    Route::post('getUserListByIdList', 'sys.UserCenterController/getUserListByIdList');
    Route::post('getPositionListByIdList', 'sys.UserCenterController/getPositionListByIdList');
});
```

## Files That Would Be Modified Later

- `route/app.php`

## Files Not To Modify In This Request

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `.env`
- `.env.example`
- `app/common.php`

## Explicit Exclusions

- Do not register `/sys/userCenter/loginMenu`; auth-agent owns it.
- Do not register write routes in this batch.
- Do not register import/export/upload routes in this batch.

## Approval Status

Pending user or merge-agent confirmation.
