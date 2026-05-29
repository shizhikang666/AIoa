# Public File Change Request

Agent: auth-agent

## Requested File

- `F:\AI\projects\testJava\OA-auth\route\app.php`

## Reason

The Java OA auth module exposes fixed HTTP paths such as:

- `GET /auth/b/getPicCaptcha`
- `POST /auth/b/doLogin`
- `POST /auth/b/doLoginByPhone`
- `GET /auth/b/doLogout`
- `GET /auth/b/getLoginUser`
- `POST /auth/b/safe/password`

To keep frontend/API compatibility during the ThinkPHP refactor, auth-agent needs explicit ThinkPHP route definitions for these paths.

## Proposed Change

After confirmation, auth-agent will add only auth-scoped routes to `route/app.php`.

Expected route group:

```php
Route::group('auth/b', function () {
    Route::get('getPicCaptcha', 'auth.AuthController/getPicCaptcha');
    Route::post('doLogin', 'auth.AuthController/doLogin');
    Route::post('doLoginByPhone', 'auth.AuthController/doLoginByPhone');
    Route::get('doLogout', 'auth.AuthController/doLogout');
    Route::get('getLoginUser', 'auth.AuthController/getLoginUser');
    Route::post('safe/password', 'auth.AuthController/openSafe');
});
```

Final controller path may be adjusted to the actual ThinkPHP controller namespace chosen during implementation.

## Safety Constraints

- Do not alter existing demo routes unless explicitly requested.
- Do not add routes for user, workflow, api, or frontend modules.
- Do not modify `config/app.php`, `config/database.php`, `.env`, `.env.example`, or `app/common.php`.
- Do not write secrets or credentials.

## Status

Applied during auth-agent Phase 2 after the user continued from this request. The change added only the listed `/auth/b/*` routes.

## Additional Request: login menu compatibility route

### Requested File

- `F:\AI\projects\testJava\OA-auth\route\app.php`

### Reason

The old frontend calls `GET /sys/userCenter/loginMenu` immediately after login to load the authorized menu tree. The route path lives under user center, but the data is derived from auth/RBAC menu permissions.

This creates a module-boundary risk:

- auth-agent owns menus and permissions.
- user-agent owns user center APIs.
- `route/app.php` is locked by project rules.

### Proposed Options

1. auth-agent implements only the menu tree service and waits for user-agent/api-agent to expose `GET /sys/userCenter/loginMenu`.
2. auth-agent adds a compatibility route for `GET /sys/userCenter/loginMenu` that delegates to an auth-scoped menu controller.
3. Defer this route to merge-agent after auth-agent and user-agent are both committed.

### Safety Constraints

- Do not implement user profile, organization, position, workbench, or message APIs in auth-agent.
- Do not modify `route/app.php` for this route until the module ownership decision is confirmed.
- Do not modify Java source files.
- Do not modify database schema or seed data.

### Status

Applied during auth-agent Phase 4 after the user allowed the main agent to decide the next parallel plan. auth-agent added only `GET /sys/userCenter/loginMenu` and kept the rest of user center for user-agent.
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

Applied by merge-agent during integration after api-agent merge. Only the listed read-only user, organization, and position routes were registered.

---

# Public File Change Request: Workflow Read-Only Routes

## Request

Register read-only workflow task/process routes in `route/app.php`.

## Reason

workflow-agent has added read-only query services for pending tasks, history tasks, started processes, process detail, and variable normalization. api-agent has prepared thin controller adapters that delegate to those services.

## Proposed Route Groups

Do not execute this change until approved.

```php
Route::group('biz/task', function () {
    Route::get('count', 'biz.TaskController/count');
    Route::get('list', 'biz.TaskController/list');
    Route::get('page', 'biz.TaskController/page');
    Route::get('history/page', 'biz.TaskController/historyPage');
});

Route::group('biz/process', function () {
    Route::get('page', 'biz.ProcessController/page');
    Route::get('detail', 'biz.ProcessController/detail');
    Route::post('variable', 'biz.ProcessController/variable');
});
```

## Files That Would Be Modified Later

- `route/app.php`

## Explicit Exclusions

- Do not register `/biz/task/approve`.
- Do not register `/biz/task/reject`.
- Do not register `/biz/process/cancel`.
- Do not register `/biz/process/*/start`.
- Do not add workflow mutation routes in this batch.

## Approval Status

Applied by merge-agent during integration after api-agent merge. Only the listed read-only workflow query routes were registered.
