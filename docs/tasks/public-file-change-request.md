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

---

# Public File Change Request: Auth Middleware On Token-Owned Routes

## Request

Attach `AuthMiddleware` to route groups that need the current login user from the bearer token.

## Reason

Frontend compatibility smoke testing found that token-only requests failed with `missing userId` on routes whose controllers call `currentUserId()`:

- `GET /sys/userCenter/loginOrgTree`
- `GET /sys/userCenter/loginPositionInfo`
- `GET /biz/task/count`
- `GET /biz/task/page`
- `GET /biz/process/page`

The controllers already support reading `auth_payload` from request middleware, but the route groups were not using `AuthMiddleware`, so the payload was never attached.

## Applied Change

`merge-agent` added `AuthMiddleware` to these route groups:

- `sys/userCenter`
- `biz/task`
- `biz/process`

No new write routes were added. No Controller or Service business logic was changed.

## Verification

- Token-only requests to the affected routes now return `code=200`.
- Requests without token return `code=401 unauthenticated`.
- `php think route:list` passes.
- PHP lint passes.

---

# Public File Change Request: Frontend Read-Only Selector Routes

## Request

Register old-frontend-compatible read-only selector and list routes in `route/app.php`.

## Reason

The existing Vue API modules call selector/list endpoints beyond the first user directory route batch. These are needed for frontend pages to load selection controls without adding write behavior.

## Applied Change

`merge-agent` added only read-only route aliases for:

- `/sys/user/orgTreeSelector`
- `/sys/user/positionSelector`
- `/sys/user/roleSelector`
- `/sys/user/userSelector`
- `/sys/org/page`
- `/sys/org/list`
- `/sys/org/userSelector`
- `/sys/position/list`
- `/sys/position/orgTreeSelector`
- `/sys/userCenter/getOrgListByIdList`
- `/sys/userCenter/getRoleListByIdList`
- `/sys/userCenter/getAvatarById`

## Explicit Exclusions

- No add, edit, delete, disable, enable, import, export, upload, password, grant, approval, reject, cancel, or process-start routes were added.
- No database schema, seed data, Java source, `.env`, or public config files were changed.

---

# Public File Change Request: Auth Middleware On System Directory Routes

## Request

Attach `AuthMiddleware` to the read-only system directory route groups:

- `sys/org`
- `sys/position`
- `sys/user`

## Reason

The existing frontend uses these routes only after login, but leaving user, organization, and position directory data public is not appropriate for the OA system. These groups should behave like other token-owned routes.

## Applied Change

`merge-agent` attached `AuthMiddleware` to the three route groups. No route handlers, services, database fields, Java source, or write endpoints were changed.

## Verification

- Token requests to representative routes return `code=200`.
- No-token requests to representative routes return `code=401`.

---

# Public File Change Request: RBAC Role Read-Only Routes

## Request

Register read-only `/sys/role/*` compatibility routes in `route/app.php`.

## Reason

The existing Vue role management API calls several GET endpoints for role pagination, detail, existing grants, and selector trees. These endpoints are required for the role page to load without enabling mutation behavior.

## Applied Change

`merge-agent` registered a protected `sys/role` route group with only GET routes:

- `page`
- `detail`
- `ownResource`
- `ownMobileMenu`
- `ownPermission`
- `ownUser`
- `orgTreeSelector`
- `resourceTreeSelector`
- `mobileMenuTreeSelector`
- `permissionTreeSelector`
- `roleSelector`
- `userSelector`

## Explicit Exclusions

- No role add, edit, delete, grant resource, grant mobile menu, grant permission, or grant user routes were added.
- No relation writes, database schema changes, Java source changes, or frontend source changes were made.

---

# Public File Change Request: User Center Read-Only Compatibility Routes

## Request

Register protected read-only user-center compatibility routes in `route/app.php`.

## Reason

The existing Vue user-center API calls workbench, unread message, and process config endpoints after login. These are required for user-center tabs and workflow setup views to load while write behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes under `sys/userCenter`:

- `GET /sys/userCenter/loginWorkbench`
- `GET /sys/userCenter/loginUnreadMessagePage`
- `GET /sys/userCenter/loginUnreadMessageDetail`
- `POST /sys/userCenter/process/config`

## Explicit Exclusions

- No update user info, update workbench, update password, update avatar, update signature, process config edit, or message mark-read routes were added.
- No database schema, seed data, Java source, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes under `AuthMiddleware`.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Index Read-Only Compatibility Routes

## Request

Register protected read-only homepage routes in `route/app.php`.

## Reason

The existing Vue homepage and message panel call `/sys/index/*` endpoints after login for schedule list, message list/page/detail, and current user logs. These routes are needed for the first logged-in screen to render without enabling mutation behavior.

## Applied Change

`merge-agent` registered the following protected routes under `sys/index`:

- `GET /sys/index/schedule/list`
- `GET /sys/index/message/list`
- `GET /sys/index/message/page`
- `GET /sys/index/message/detail`
- `GET /sys/index/visLog/list`
- `GET /sys/index/opLog/list`

## Explicit Exclusions

- No schedule add, schedule delete, all-message-mark-read, or SSE routes were added.
- No message read status writes, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Log Read-Only Routes

## Request

Register protected read-only log routes in `route/app.php`.

## Reason

The existing Vue development log pages call `/dev/log/*` endpoints after login for visit logs, operation logs, details, and chart panels. These reads are useful for runtime verification while destructive log clearing remains disabled.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /dev/log/page`
- `GET /dev/log/detail`
- `GET /dev/log/vis/lineChartData`
- `GET /dev/log/vis/pieChartData`
- `GET /dev/log/op/barChartData`
- `GET /dev/log/op/pieChartData`

## Explicit Exclusions

- No `/dev/log/delete` route was added.
- No log clear/delete mutation was implemented.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Dict Read-Only Routes

## Request

Register protected read-only dictionary routes in `route/app.php`.

## Reason

The existing Vue app calls `/dev/dict/tree` after login to cache dictionary data, and many forms depend on that cached tree for select options and display translation. The dictionary management page also needs page/list/detail reads.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /dev/dict/page`
- `GET /dev/dict/list`
- `GET /dev/dict/tree`
- `GET /dev/dict/detail`

## Explicit Exclusions

- No dictionary add, edit, or delete routes were added.
- No dictionary translation cache mutation endpoint was added.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Message Read-Only Routes

## Request

Register protected read-only station-message routes in `route/app.php`.

## Reason

The existing Vue message pages call `/dev/message/page` and `/dev/message/detail` to load station-message data and receiver read status after login. These reads are needed for compatibility, while send, delete, and read-state mutation behavior must remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /dev/message/page`
- `GET /dev/message/detail`

## Explicit Exclusions

- No `/dev/message/send` route was added.
- No `/dev/message/delete` route was added.
- Message detail does not mark the message as read.
- No SSE push, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Config Safe Read-Only Routes

## Request

Register read-only configuration routes in `route/app.php`.

## Reason

The existing login page calls `/dev/config/sysBaseList` before authentication to load system base display settings, and the existing admin configuration pages call `/dev/config/page`, `/dev/config/list`, and `/dev/config/detail` after login.

## Applied Change

`merge-agent` registered the following route without `AuthMiddleware`, matching Java's public login-page base-config behavior:

- `GET /dev/config/sysBaseList`

`merge-agent` registered the following protected routes:

- `GET /dev/config/page`
- `GET /dev/config/list`
- `GET /dev/config/detail`

## Explicit Exclusions

- No config add, edit, delete, or editBatch routes were added.
- Sensitive config values are masked in read responses.
- `sysBaseList` excludes `SNOWY_SYS_DEFAULT_PASSWORD`.
- No Redis config cache mutation, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Public `GET /dev/config/sysBaseList` should return `code=200` without a token.
- Protected token requests should return `code=200` for representative routes.
- Protected requests without token should return `code=401`.

---

# Public File Change Request: Dev File Metadata Read-Only Routes

## Request

Register protected read-only file metadata routes in `route/app.php`.

## Reason

The existing Vue file management page calls `/dev/file/page`, `/dev/file/list`, and `/dev/file/detail` to load file metadata and detail drawer content after login. These routes are needed for compatibility while upload, delete, and actual file download behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /dev/file/page`
- `GET /dev/file/list`
- `GET /dev/file/detail`

## Explicit Exclusions

- No upload routes were added.
- No `/dev/file/download` route was added.
- No `/dev/file/delete` route was added.
- No local filesystem file content is read.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Mobile Resource Read-Only Routes

## Request

Register protected read-only mobile resource routes in `route/app.php`.

## Reason

The existing Vue mobile resource management pages call `/mobile/module/*`, `/mobile/menu/*`, and `/mobile/button/*` endpoints after login to load mobile modules, menu trees, and button lists. These routes are needed for compatibility while mutation behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /mobile/module/page`
- `GET /mobile/module/detail`
- `GET /mobile/menu/tree`
- `GET /mobile/menu/detail`
- `GET /mobile/menu/moduleSelector`
- `GET /mobile/menu/menuTreeSelector`
- `GET /mobile/button/page`
- `GET /mobile/button/detail`

## Explicit Exclusions

- No mobile module add, edit, or delete routes were added.
- No mobile menu add, edit, delete, or change-module routes were added.
- No mobile button add, edit, or delete routes were added.
- No mobile role grant mutations, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: System Resource Read-Only Routes

## Request

Register protected read-only system resource routes in `route/app.php`.

## Reason

The existing Vue system resource management pages call `/sys/module/*`, `/sys/menu/*`, and `/sys/button/*` endpoints after login to load modules, menu trees, and button lists. These read-only routes are needed before write/grant behavior is implemented.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /sys/module/page`
- `GET /sys/module/detail`
- `GET /sys/menu/page`
- `GET /sys/menu/tree`
- `GET /sys/menu/detail`
- `GET /sys/menu/moduleSelector`
- `GET /sys/menu/menuTreeSelector`
- `GET /sys/button/page`
- `GET /sys/button/detail`

## Explicit Exclusions

- No module add, edit, or delete routes were added.
- No menu add, edit, delete, or change-module routes were added.
- No button add, edit, or delete routes were added.
- No role/resource grant mutations, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Email And Sms Read-Only Routes

## Request

Register protected read-only email and SMS record routes in `route/app.php`.

## Reason

The existing Vue email and SMS management pages call `/dev/email/page`, `/dev/email/detail`, `/dev/sms/page`, and `/dev/sms/detail` after login to load historical send records. These routes are needed for compatibility while actual provider send and delete behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /dev/email/page`
- `GET /dev/email/detail`
- `GET /dev/sms/page`
- `GET /dev/sms/detail`

## Explicit Exclusions

- No email send routes were added.
- No SMS send routes were added.
- No email/SMS delete routes were added.
- No local mail, cloud email, or SMS provider integration is called.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Job Read-Only Routes

## Request

Register protected read-only scheduled-job routes in `route/app.php`.

## Reason

The existing Vue scheduled-job management page calls `/dev/job/page`, `/dev/job/list`, `/dev/job/detail`, and `/dev/job/getActionClass` after login to load job records and action-class selector data. These routes are needed for compatibility while scheduler mutations and execution remain disabled.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /dev/job/page`
- `GET /dev/job/list`
- `GET /dev/job/detail`
- `GET /dev/job/getActionClass`

## Explicit Exclusions

- No `/dev/job/add` route was added.
- No `/dev/job/edit` route was added.
- No `/dev/job/delete` route was added.
- No `/dev/job/stopJob` route was added.
- No `/dev/job/runJob` route was added.
- No `/dev/job/runJobNow` route was added.
- No scheduler is started, stopped, or mutated.
- No job action class is executed.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sys Config Read-Only Route

## Request

Register protected read-only system configuration detail route in `route/app.php`.

## Reason

The existing Vue login flow and process configuration page call `/sys/sysConfig/detail` after login to load workflow process settings. This read is needed for frontend compatibility, while edits and default generation remain disabled.

## Applied Change

`merge-agent` registered the following protected route:

- `GET /sys/sysConfig/detail`

## Explicit Exclusions

- No `/sys/sysConfig/edit` route was added.
- No `/sys/sysConfig/generateConfig` route was added.
- Missing config returns an in-memory default object and does not write to `sys_config`.
- No cache mutation, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return `code=200`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Gen Metadata Read-Only Routes

## Request

Register protected read-only generator metadata routes in `route/app.php`.

## Reason

The existing Vue generator pages call `/gen/basic/page`, `/gen/basic/detail`, `/gen/basic/mobileModuleSelector`, `/gen/config/list`, and `/gen/config/detail` after login to load saved generator metadata and field configuration. These reads are needed for compatibility while code generation, schema scanning, and write endpoints remain disabled.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /gen/basic/page`
- `GET /gen/basic/detail`
- `GET /gen/basic/mobileModuleSelector`
- `GET /gen/config/list`
- `GET /gen/config/detail`

## Explicit Exclusions

- No `/gen/basic/add`, `/gen/basic/edit`, or `/gen/basic/delete` route was added.
- No `/gen/config/edit`, `/gen/config/delete`, or `/gen/config/editBatch` route was added.
- No `/gen/basic/tables` or `/gen/basic/tableColumns` route was added.
- No `/gen/basic/execGenZip`, `/gen/basic/execGenPro`, or `/gen/basic/previewGen` route was added.
- No database schema scanning, code generation, file writing, ZIP generation, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Monitor Server Info Read-Only Route

## Request

Register a protected read-only server monitor route in `route/app.php`.

## Reason

The existing Vue monitor page calls `/dev/monitor/serverInfo` after login to load runtime, memory, storage, server, and JVM-shaped information. This read is needed for frontend compatibility, while network sampling and external command execution remain disabled.

## Applied Change

`merge-agent` registered the following protected route:

- `GET /dev/monitor/serverInfo`

## Explicit Exclusions

- No `/dev/monitor/networkInfo` route was added.
- No shell commands, `netstat`, or `ifconfig` calls were added.
- No long-running network sampling behavior was added.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return `code=200`.
- Requests without token should return `code=401`.
