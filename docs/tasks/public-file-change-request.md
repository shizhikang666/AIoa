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

# Public File Change Request: Dev Message SSE Compatibility Route

## Request

Register a protected SSE compatibility route in `route/app.php`.

## Reason

The copied Vue frontend opens an EventSource connection to `/dev/message/createSseConnect` from the layout message components after login. The Java OA project exposes the same path from `SysIndexController` and delegates to the dev SSE provider. The current ThinkPHP route table does not expose this path, so the browser logs a 404 even though the rest of the page can load.

## Proposed Route

Do not execute this change until approved or assigned to merge-agent.

```php
Route::get('createSseConnect', 'dev.MessageController/createSseConnect');
```

Target route group:

```php
Route::group('dev/message', function () {
    Route::get('page', 'dev.MessageController/page');
    Route::get('detail', 'dev.MessageController/detail');
    Route::get('createSseConnect', 'dev.MessageController/createSseConnect');
})->middleware(AuthMiddleware::class);
```

## Expected First-Slice Behavior

- Authenticate using the existing bearer-token middleware.
- Accept optional `clientId` from the query string.
- Return `text/event-stream`.
- Send an initial compatible event with `code = 0` and a generated or reused client id.
- Keep the connection alive with a lightweight heartbeat.

## Explicit Exclusions

- No frontend file change in this request.
- No message broadcast route.
- No manual send-message route.
- No mark-read mutation.
- No workflow push side effects.
- No Redis pub/sub fanout in this first slice.
- No database schema change, Java source change, `.env`, Composer file, or public config change.
- No production online realtime-data sync implementation yet.

## Verification

- `php think route:list` must list `dev/message/createSseConnect`.
- Token EventSource requests should return `text/event-stream`.
- Requests without token should return `code=401` or be rejected by existing auth middleware.

## Approval Status

Applied during the api-agent minimal SSE compatibility slice after the user continued from the public-file request.

Applied route:

- `GET /dev/message/createSseConnect`

Applied files:

- `route/app.php`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageSseService.php`

The implementation keeps broadcast, manual send-message, mark-read mutation, workflow push side effects, Redis pub/sub fanout, and production realtime-data sync deferred.

---

# Public File Change Request: Biz Team Project Read-Only Routes

## Request

Register protected read-only team-project and team-project-user routes in `route/app.php`.

## Reason

The existing Vue team-project pages call `/biz/bizteamproject/page`, `/biz/bizteamproject/detail`, `/biz/bizteamprojectuser/page`, `/biz/bizteamprojectuser/list`, and `/biz/bizteamprojectuser/detail` after login to load project cards, project detail context, current project role permissions, and member avatars. These reads are needed for the team-project workspace while project and member mutations remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizteamproject/page`
- `GET /biz/bizteamproject/detail`
- `GET /biz/bizteamprojectuser/page`
- `GET /biz/bizteamprojectuser/list`
- `GET /biz/bizteamprojectuser/detail`

## Explicit Exclusions

- No `/biz/bizteamproject/add` route was added.
- No `/biz/bizteamproject/edit` route was added.
- No `/biz/bizteamproject/delete` route was added.
- No `/biz/bizteamprojectuser/add` route was added.
- No `/biz/bizteamprojectuser/manage/add` route was added.
- No `/biz/bizteamprojectuser/edit` route was added.
- No `/biz/bizteamprojectuser/delete` route was added.
- No team project mutation, member mutation, role-permission write, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Customer Read-Only Routes

## Request

Register protected read-only customer and customer-follow-up routes in `route/app.php`.

## Reason

The copied Vue customer, customer detail, sale-project detail, follow-up, and export pages call `/biz/customer/page`, `/biz/customer/detail`, `/biz/customer/detail/list`, `/biz/customerfollowup/page`, and `/biz/customerfollowup/detail` after login. These reads are needed for customer browsing, detail drawers, sale-project customer base info, follow-up tabs, and export data preparation while all customer and follow-up mutations remain deferred.

## Applied Change

`api-agent` registered the following protected routes:

- `GET /biz/customer/page`
- `GET /biz/customer/detail`
- `POST /biz/customer/detail/list`
- `GET /biz/customerfollowup/page`
- `GET /biz/customerfollowup/detail`

## Explicit Exclusions

- No `/biz/customer/add` route will be added.
- No `/biz/customer/edit` route will be added.
- No `/biz/customer/delete` route will be added.
- No `/biz/customer/head/edit` route will be added.
- No `/biz/customerfollowup/add` route will be added.
- No `/biz/customerfollowup/edit` route will be added.
- No `/biz/customerfollowup/delete` route will be added.
- No customer mutation, follow-up mutation, SM4 crypto implementation, database schema change, Java source change, frontend change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Team Project Task Read-Only Routes

## Request

Register protected read-only team-project task, task-category, project-comment, and task-comment routes in `route/app.php`.

## Reason

The existing Vue team-project detail page calls these routes after login to display kanban columns, task cards, task details, project timeline comments, task comments, and nested project-comment replies. These reads are needed for the project detail screen while all task, category, member, comment, and reply mutations remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizteamprojecttaskcategory/page`
- `GET /biz/bizteamprojecttaskcategory/list`
- `GET /biz/bizteamprojecttaskcategory/detail`
- `GET /biz/bizteamprojecttask/page`
- `GET /biz/bizteamprojecttask/list`
- `GET /biz/bizteamprojecttask/detail`
- `GET /biz/bizteamprojectcomment/page`
- `GET /biz/bizteamprojectcomment/list`
- `GET /biz/bizteamprojecttaskcomment/page`
- `GET /biz/bizteamprojecttaskcomment/list`
- `GET /biz/bizteamprojecttaskcomment/detail`

## Explicit Exclusions

- No `/biz/bizteamprojecttask/add` route was added.
- No `/biz/bizteamprojecttask/edit` route was added.
- No `/biz/bizteamprojecttask/delete` route was added.
- No `/biz/bizteamprojecttask/user/edit` route was added.
- No `/biz/bizteamprojecttaskcategory/add` route was added.
- No `/biz/bizteamprojecttaskcategory/edit` route was added.
- No `/biz/bizteamprojecttaskcategory/sort/edit` route was added.
- No `/biz/bizteamprojecttaskcategory/delete` route was added.
- No `/biz/bizteamprojectcomment/add` or `/delete` route was added.
- No `/biz/bizteamprojectcommentreply/add`, `/edit`, or `/delete` route was added.
- No task/category/comment/reply mutation, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz File Relation Read-Only Routes

## Request

Register protected read-only file-relation routes in `route/app.php`.

## Reason

The existing Vue file-relation pages call `/biz/bizfilerelation/page`, `/biz/bizfilerelation/list`, and `/biz/bizfilerelation/detail` after login to load business attachment relations, linked file download metadata, and creator display data. These reads are needed by project, process, procurement, and reimbursement detail views while attachment link writes remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizfilerelation/page`
- `GET /biz/bizfilerelation/list`
- `GET /biz/bizfilerelation/detail`

## Explicit Exclusions

- No `/biz/bizfilerelation/add` route was added.
- No `/biz/bizfilerelation/edit` route was added.
- No `/biz/bizfilerelation/delete` route was added.
- No `/biz/bizfilerelation/projectCase/del` route was added.
- No attachment mutation, dev-file write, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Comment Reply Read-Only Routes

## Request

Register protected read-only team-project comment detail and project-comment reply page/detail routes in `route/app.php`.

## Reason

The copied Vue API wrappers call these endpoints from team-project comment and reply components. Existing ThinkPHP reads already load project comments and nested replies for timeline lists; this request only exposes the missing standalone read paths while preserving the current team-project membership visibility boundary.

## Applied Change

`api-agent/frontend-agent` registered the following protected routes:

- `GET /biz/bizteamprojectcomment/detail`
- `GET /biz/bizteamprojectcommentreply/page`
- `GET /biz/bizteamprojectcommentreply/detail`

## Explicit Exclusions

- No `/biz/bizteamprojectcomment/add` route was added.
- No `/biz/bizteamprojectcomment/delete` route was added.
- No `/biz/bizteamprojectcommentreply/add` route was added.
- No `/biz/bizteamprojectcommentreply/edit` route was added.
- No `/biz/bizteamprojectcommentreply/delete` route was added.
- No comment/reply mutation, notification, data-change event, database schema change, Java source change, frontend change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sys Field Read-Only Routes

## Request

Register protected read-only system field routes in `route/app.php`.

## Reason

The copied Vue system resource field drawer calls `/sys/field/page`, `/tree`, `/detail`, and `/MenuTreeSelector` to display field-level resource permissions under menus. The current ThinkPHP resource service already reads `sys_resource`; this change only exposes read paths for `CATEGORY = FIELD` while field mutations remain deferred.

## Applied Change

`user-agent/api-agent/frontend-agent` registered the following protected routes:

- `GET /sys/field/page`
- `GET /sys/field/tree`
- `GET /sys/field/detail`
- `GET /sys/field/MenuTreeSelector`

## Explicit Exclusions

- No `/sys/field/add` route was added.
- No `/sys/field/edit` route was added.
- No `/sys/field/delete` route was added.
- No menu, button, module, field, permission, database schema, Java source, frontend source, `.env`, Composer file, or public config mutation was added.

## Verification

- `php think route:list` must list the added routes.
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

# Public File Change Request: Biz Debit Note Read-Only Routes

## Request

Register protected read-only debit-note routes in `route/app.php`.

## Reason

The existing Vue debit-note pages call `/biz/bizdebitnote/page`, `/biz/bizdebitnote/list`, and `/biz/bizdebitnote/detail` after login to load loan/payment-on-behalf records, linked expenditure-record display fields, settlement account fields, and organization context. These reads are needed for finance views while history add, mark-success, batch-repayment, add, edit, and delete behavior remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizdebitnote/page`
- `GET /biz/bizdebitnote/list`
- `GET /biz/bizdebitnote/detail`

## Explicit Exclusions

- No `/biz/bizdebitnote/history/add` route was added.
- No `/biz/bizdebitnote/mark/success/edit` route was added.
- No `/biz/bizdebitnote/batchRepayment/edit` route was added.
- No `/biz/bizdebitnote/add` route was added.
- No `/biz/bizdebitnote/edit` route was added.
- No `/biz/bizdebitnote/delete` route was added.
- No debit-note mutation, payment-record creation, settlement-account balance mutation, settlement-state mutation, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Collection Receipt Read-Only Routes

## Request

Register protected read-only collection-receipt routes in `route/app.php`.

## Reason

The existing Vue collection-receipt pages call `/biz/bizcollectionreceipt/page`, `/biz/bizcollectionreceipt/list`, and `/biz/bizcollectionreceipt/detail` after login to load received-on-behalf records and related settlement-account display fields. These reads are needed for finance views while mark-success, batch-expenditure, add, edit, and delete behavior remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizcollectionreceipt/page`
- `GET /biz/bizcollectionreceipt/list`
- `GET /biz/bizcollectionreceipt/detail`

## Explicit Exclusions

- No `/biz/bizcollectionreceipt/batchExpenditure/edit` route was added.
- No `/biz/bizcollectionreceipt/mark/success/edit` route was added.
- No `/biz/bizcollectionreceipt/add` route was added.
- No `/biz/bizcollectionreceipt/edit` route was added.
- No `/biz/bizcollectionreceipt/delete` route was added.
- No collection-receipt mutation, expenditure correction, settlement-state mutation, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Supplier Read-Only Routes

## Request

Register protected read-only supplier master routes in `route/app.php`.

## Reason

The existing Vue business pages call `/biz/supplier/page`, `/biz/supplier/list`, `/biz/supplier/list/query/name`, and `/biz/supplier/detail` after login to load supplier master data for procurement and settlement flows. These reads are safe to expose before supplier write behavior is implemented.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/supplier/page`
- `GET /biz/supplier/list`
- `GET /biz/supplier/list/query/name`
- `GET /biz/supplier/detail`

## Explicit Exclusions

- No `/biz/supplier/add` route was added.
- No `/biz/supplier/edit` route was added.
- No `/biz/supplier/delete` route was added.
- No supplier write validation, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Tenants Read-Only Routes

## Request

Register protected read-only tenant routes in `route/app.php`.

## Reason

The existing Vue tenant management page calls `/tenants/tenant/page` and `/tenants/tenant/detail` after login. These reads are needed for compatibility while tenant creation, edit, deletion, and default-data generation remain disabled.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /tenants/tenant/page`
- `GET /tenants/tenant/detail`

## Explicit Exclusions

- No `/tenants/tenant/add` route was added.
- No `/tenants/tenant/edit` route was added.
- No `/tenants/tenant/delete` route was added.
- No default user, role, resource, or permission generation was added.
- No tenant cache/event mutation, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Auth Session Current Token Read-Only Routes

## Request

Register protected read-only session monitor routes in `route/app.php`.

## Reason

The existing Vue session monitor page calls `/auth/session/analysis`, `/auth/session/b/page`, and `/auth/session/c/page` after login. These reads are needed for compatibility, while session exit and token exit behavior remain disabled.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /auth/session/analysis`
- `GET /auth/session/b/page`
- `GET /auth/session/c/page`

## Explicit Exclusions

- No `/auth/session/b/exit` route was added.
- No `/auth/session/c/exit` route was added.
- No `/auth/token/b/exit` route was added.
- No `/auth/token/c/exit` route was added.
- No token/session revocation behavior was added.
- No token index write behavior was added to login.
- No database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

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

The existing Vue file management page calls `/dev/file/page`, `/dev/file/list`, and `/dev/file/detail` to load file metadata and detail drawer content after login. These routes were the first file-management compatibility slice; later slices added LOCAL upload, download, and metadata logical delete.

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

This exclusion list documents the original read-only change request only. The current project now includes separate compatibility slices for LOCAL upload, public local download, and `/dev/file/delete` logical delete.

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

# Public File Change Request: Biz Product Read-Only Routes

## Request

Register protected read-only product master routes in `route/app.php`.

## Reason

The existing Vue business pages call `/biz/bizproduct/page`, `/biz/bizproduct/list`, `/biz/bizproduct/detail`, and `/biz/bizproduct/children` after login to load product master data and kit-product child information. These reads are needed by sales, purchase, inventory, and product selector flows before write endpoints are enabled.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizproduct/page`
- `GET /biz/bizproduct/list`
- `GET /biz/bizproduct/detail`
- `POST /biz/bizproduct/children`

## Explicit Exclusions

- No `/biz/bizproduct/add` route was added.
- No `/biz/bizproduct/edit` route was added.
- No `/biz/bizproduct/delete` route was added.
- No `/biz/bizproduct/reconciliation/edit` route was added.
- No `/biz/bizproduct/edit/status` route was added.
- No product relation writes, cache events, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
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

---

# Public File Change Request: Biz Warehouses Read-Only Routes

## Request

Register protected read-only warehouse master routes in `route/app.php`.

## Reason

The existing Vue business pages call `/biz/warehouses/page`, `/biz/warehouses/list`, and `/biz/warehouses/detail` after login to load warehouse master data for purchase, sales, and inventory flows. These reads are needed for compatibility while warehouse write behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/warehouses/page`
- `GET /biz/warehouses/list`
- `GET /biz/warehouses/detail`

## Explicit Exclusions

- No `/biz/warehouses/add` route was added.
- No `/biz/warehouses/edit` route was added.
- No `/biz/warehouses/delete` route was added.
- No warehouse write validation, stock mutation, database schema changes, Java source changes, `.env`, Composer files, or public config files were changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Inventory Read-Only Routes

## Request

Register protected read-only warehouse inventory routes in `route/app.php`.

## Reason

The existing Vue inventory page calls `/biz/inventory/page`, `/biz/inventory/list`, and `/biz/inventory/detail` after login to load warehouse inventory rows and product display fields. These reads are needed for compatibility while stock-changing behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/inventory/page`
- `GET /biz/inventory/list`
- `GET /biz/inventory/detail`

## Explicit Exclusions

- No `/biz/inventory/add` route was added.
- No `/biz/inventory/delete` route was added.
- No stock in/out, batch stock update, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Delivery Record Read-Only Routes

## Request

Register protected read-only warehouse delivery-record routes in `route/app.php`.

## Reason

The existing Vue inventory and product-detail pages call `/biz/warehouses/delivery/page`, `/biz/warehouses/delivery/exportOtherCompanyRecordsList`, and retain a frontend wrapper for `/biz/warehouses/delivery/detail`. These reads are needed for compatibility while stock-changing delivery record behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/warehouses/delivery/page`
- `GET /biz/warehouses/delivery/exportOtherCompanyRecordsList`
- `GET /biz/warehouses/delivery/detail`

## Explicit Exclusions

- No `/biz/warehouses/delivery/add` route was added.
- No delivery record edit/delete route was added.
- No inventory stock update, batch stock movement, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Purchase Order Read-Only Routes

## Request

Register protected read-only purchase-order routes in `route/app.php`.

## Reason

The existing Vue purchase-order pages call `/biz/bizpurchaseorder/page`, `/biz/bizpurchaseorder/detail/list`, `/biz/bizpurchaseorder/list`, and `/biz/bizpurchaseorder/detail` after login to load purchase orders, order items, supplier display data, and related goods expenditure records. These reads are needed for procurement compatibility while purchase writes, audit edits, cancellations, and warehouse stock-in behavior remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizpurchaseorder/page`
- `GET /biz/bizpurchaseorder/detail/list`
- `GET /biz/bizpurchaseorder/list`
- `GET /biz/bizpurchaseorder/detail`

## Explicit Exclusions

- No `/biz/bizpurchaseorder/add` route was added.
- No `/biz/bizpurchaseorder/edit` route was added.
- No `/biz/bizpurchaseorder/audit/edit` route was added.
- No `/biz/bizpurchaseorder/delete` route was added.
- No `/biz/bizpurchaseorder/cancel` route was added.
- No `/biz/bizpurchaseorder/warehouse/add` route was added.
- No `/biz/bizpurchaseorder/warehouse/one/add` route was added.
- No inventory stock movement, workflow mutation, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Settlement Account Read-Only Routes

## Request

Register protected read-only settlement-account routes in `route/app.php`.

## Reason

The existing Vue settlement-account pages call `/biz/settlementaccount/page`, `/biz/settlementaccount/list`, `/biz/settlementaccount/detail`, and `/biz/settlementaccount/queryName` after login to load account master data and account names. These reads are needed for procurement, payment, and settlement selectors while amount-changing behavior remains deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/settlementaccount/page`
- `GET /biz/settlementaccount/list`
- `GET /biz/settlementaccount/detail`
- `GET /biz/settlementaccount/queryName`

## Explicit Exclusions

- No `/biz/settlementaccount/add` route was added.
- No `/biz/settlementaccount/edit` route was added.
- No `/biz/settlementaccount/delete` route was added.
- No `/biz/settlementaccount/edit/status` route was added.
- No `/biz/settlementaccount/expenses/add` route was added.
- No `/biz/settlementaccount/payment/add` route was added.
- No `/biz/settlementaccount/transfer/add` route was added.
- No settlement amount mutation, statement write, income/expense record creation, transfer behavior, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Payment Record Read-Only Routes

## Request

Register protected read-only payment-record routes in `route/app.php`.

## Reason

The existing Vue payment-record pages call `/biz/bizpaymentrecord/page`, `/biz/bizpaymentrecord/listdetails`, `/biz/bizpaymentrecord/list`, and `/biz/bizpaymentrecord/detail` after login to load income/payment records, settlement account display fields, and organization context. These reads are needed for settlement and project payment views while record correction and account-switch behavior remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizpaymentrecord/page`
- `GET /biz/bizpaymentrecord/listdetails`
- `GET /biz/bizpaymentrecord/list`
- `GET /biz/bizpaymentrecord/detail`

## Explicit Exclusions

- No `/biz/bizpaymentrecord/edit` route was added.
- No `/biz/bizpaymentrecord/edit/account` route was added.
- No payment-record mutation, settlement-account transfer, statement edit, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Expenditure Record Read-Only Routes

## Request

Register protected read-only expenditure-record routes in `route/app.php`.

## Reason

The existing Vue expenditure-record pages call `/biz/bizexpenditurerecord/page`, `/biz/bizexpenditurerecord/listDetails`, `/biz/bizexpenditurerecord/list`, and `/biz/bizexpenditurerecord/detail` after login to load expense records, settlement account display fields, and organization context. These reads are needed for expense, purchase, and settlement views while corrections and account-switch behavior remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/bizexpenditurerecord/page`
- `GET /biz/bizexpenditurerecord/listDetails`
- `GET /biz/bizexpenditurerecord/list`
- `GET /biz/bizexpenditurerecord/detail`

## Explicit Exclusions

- No `/biz/bizexpenditurerecord/add` route was added.
- No `/biz/bizexpenditurerecord/edit` route was added.
- No `/biz/bizexpenditurerecord/edit/account` route was added.
- No `/biz/bizexpenditurerecord/delete` route was added.
- No expenditure-record mutation, settlement-account transfer, statement edit, data-change event, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Return Order Read-Only Routes

## Request

Register protected read-only return-order routes in `route/app.php`.

## Reason

The existing Vue sale-project and return-order pages call `/biz/returnorder/page`, `/biz/returnorder/query`, and `/biz/returnorder/detail` after login to load return-order rows, project/warehouse/user display fields, and returned product items. These reads are needed for return/refund compatibility while warehouse stock-in, settlement status update, refund, and process mutation behavior remain deferred.

## Applied Change

`merge-agent` registered the following protected routes:

- `GET /biz/returnorder/page`
- `GET /biz/returnorder/query`
- `GET /biz/returnorder/detail`

## Explicit Exclusions

- No `/biz/returnorder/add` route was added.
- No `/biz/returnorder/edit` route was added.
- No `/biz/returnorder/delete` route was added.
- No `/biz/returnorder/status/update` or equivalent settlement-status route was added.
- No delivery-record creation, inventory stock mutation, refund mutation, workflow mutation, database schema change, Java source change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Sale Project Read-Only Routes

## Request

Register protected read-only sale-project routes in `route/app.php`.

## Reason

The copied Vue sale-project pages call `/biz/saleproject/page`, `/biz/saleproject/case/page`, `/biz/saleproject/operation/page`, `/biz/saleproject/public/page`, `/biz/saleproject/list/detail`, `/biz/saleproject/detail`, and `/biz/saleproject/product` after login. These reads are needed for project list, public project list, case list, detail drawer, export, and product item display while all sale-project mutations remain deferred.

## Applied Change

`api-agent` registered the following protected routes:

- `GET /biz/saleproject/page`
- `GET /biz/saleproject/case/page`
- `GET /biz/saleproject/operation/page`
- `GET /biz/saleproject/public/page`
- `GET /biz/saleproject/list/detail`
- `GET /biz/saleproject/detail`
- `GET /biz/saleproject/product`

The nested paths (`case/page`, `operation/page`, `public/page`, and `list/detail`) are registered as explicit full route paths in `route/app.php` to keep local route-cache refresh behavior stable during `php think run` smoke tests.

## Explicit Exclusions

- No `/biz/saleproject/add` route was added.
- No `/biz/saleproject/edit` route was added.
- No `/biz/saleproject/deal/edit` route was added.
- No `/biz/saleproject/delete` route was added.
- No `/biz/saleproject/repeal` route was added.
- No `/biz/saleproject/cancel` route was added.
- No `/biz/saleproject/history/add` route was added.
- No `/biz/saleproject/special/add` route was added.
- No `/biz/saleproject/visibility/edit` route was added.
- No `/biz/saleproject/amount/edit` route was added.
- No `/biz/saleproject/cost` or `/biz/saleproject/cost/details` route was added.
- No project mutation, inventory mutation, financial cost calculation, workflow mutation, database schema change, Java source change, frontend change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Sale Project Billing Read-Only Routes

## Request

Register protected read-only sales-project billing-adjacent routes in `route/app.php`.

## Reason

The copied Vue sale-project, invoicing, delivery invoice, reissue-order, and project-rate pages call these Java-compatible endpoints after login. These reads are needed so the existing frontend can load invoice applications, delivery invoice rows, reissue product items, and project rating/case data while all billing, workflow, inventory, and finance mutations remain deferred.

## Applied Change

`api-agent` registered the following protected routes:

- `GET /biz/saleprojectinvoicing/page`
- `GET /biz/saleprojectinvoicing/customer`
- `GET /biz/saleprojectinvoicing/detail`
- `GET /biz/saleprojectinvoice/page`
- `GET /biz/saleprojectinvoice/list`
- `GET /biz/saleprojectreissueorder/list/query`
- `GET /biz/projectrate/page`
- `GET /biz/projectrate/list`

## Explicit Exclusions

- No `/biz/saleprojectinvoicing/add` route was added.
- No `/biz/saleprojectinvoicing/edit` route was added.
- No `/biz/saleprojectinvoicing/complete` route was added.
- No `/biz/saleprojectinvoice/add`, `/edit`, `/delete`, or delivery mutation route was added.
- No `/biz/saleprojectreissueorder/add` or workflow start route was added.
- No `/biz/projectrate/add`, `/edit`, `/delete`, or rating mutation route was added.
- No workflow mutation, inventory stock mutation, finance settlement mutation, database schema change, Java source change, frontend change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return `code=200` for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Directory Alias Read-Only Routes

## Request

Register protected read-only legacy directory aliases in `route/app.php`.

## Reason

The copied Vue frontend contains business-side wrappers under `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict`. These paths load organization trees, user tables, selectors, user-owned roles, position selectors, and dictionary trees. The ThinkPHP project already has equivalent system/dev read services, so this slice adds narrow GET aliases instead of duplicating business logic.

## Applied Change

`user-agent` registered the following protected GET routes:

- `/biz/org/page`
- `/biz/org/list`
- `/biz/org/tree`
- `/biz/org/detail`
- `/biz/org/orgTreeSelector`
- `/biz/org/userSelector`
- `/biz/user/page`
- `/biz/user/list/detail`
- `/biz/user/detail`
- `/biz/user/ownRole`
- `/biz/user/orgTreeSelector`
- `/biz/user/positionSelector`
- `/biz/user/roleSelector`
- `/biz/user/userSelector`
- `/biz/position/page`
- `/biz/position/list`
- `/biz/position/detail`
- `/biz/position/orgTreeSelector`
- `/biz/position/positionSelector`
- `/biz/dict/page`
- `/biz/dict/tree`
- `/biz/dict/treeAll`

## Explicit Exclusions

- No `/biz/org/add`, `/edit`, or `/delete` route was added.
- No `/biz/user/add`, `/edit`, `/center/edit`, `/delete`, `/disableUser`, `/enableUser`, `/resetPassword`, `/grantRole`, `/export`, or `/exportUserInfo` route was added.
- No `/biz/position/add`, `/edit`, or `/delete` route was added.
- No `/biz/dict/edit` route was added.
- No user/role grant mutation, password mutation, import/export, database schema change, Java source change, frontend change, `.env`, Composer file, or public config change was added.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return read data for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Datareport Sale Project Details Read Route

## Request

Register the protected read-only sale-project details report route in `route/app.php`.

## Reason

The copied Vue sale-project-product-info page calls `POST /biz/bizdatareport/saleProjectList/details` to load the sale projects, nested product rows, package children, and return orders that feed the package/version table. The Java `BizDataReportServiceImp#getSaleProjectList` exposes this as a read-only list method, while other report endpoints have separate financial/reporting semantics and remain deferred.

## Applied Change

`api-agent` registered the following protected route:

- `POST /biz/bizdatareport/saleProjectList/details`

## Explicit Exclusions

- No `/biz/bizdatareport/saleProfit` route was added.
- No `/biz/bizdatareport/saleproject` route was added.
- No `/biz/bizdatareport/saleproject/list` route was added.
- No `/biz/bizdatareport/saleproject/report` route was added.
- No `/biz/bizdatareport/saleproject/UnpaidPayment` route was added.
- No `/biz/bizdatareport/summary/statistics` route was added.
- No Java source, database schema, frontend, Composer, `.env`, finance, workflow, inventory, or business write code was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return a sale-project row array with `productList` and `returnOrders`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Leave Application Read-Only Routes

## Request

Register protected read-only leave-application routes in `route/app.php`.

## Reason

The copied Vue leave-application page and workflow payment forms call Java-compatible leave-application reads after login. These routes are needed to browse leave/business-trip records, current-user leave records, and detail rows while workflow starts and mutations remain deferred.

## Applied Change

`api-agent` registered the following protected routes:

- `GET /biz/bizleaveapplication/page`
- `GET /biz/bizleaveapplication/my/page`
- `GET /biz/bizleaveapplication/detail`

## Explicit Exclusions

- No `/biz/bizleaveapplication/add` route was added.
- No `/biz/bizleaveapplication/edit` route was added.
- No `/biz/bizleaveapplication/delete` route was added.
- No workflow start, approve, reject, or cancel behavior was added.
- No Java source, database schema, frontend, Composer, `.env`, finance, inventory, or business write code was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return paged leave rows and detail data.
- Requests without token should return `code=401`.

---

# Public File Change Request: Settlement Account Payment Read-Only Routes

## Request

Register protected read-only settlement account statement routes in `route/app.php`.

## Reason

The copied Vue settlement account detail page calls `/biz/settlementaccountpayment/list` to show account statement rows. Java implements these routes through `SettlementAccountStatementController`, but the legacy frontend path is `settlementaccountpayment`. These routes are needed for read-only account-flow browsing while settlement account balance mutations remain deferred.

## Applied Change

`api-agent` registered the following protected routes:

- `GET /biz/settlementaccountpayment/page`
- `GET /biz/settlementaccountpayment/list`

## Explicit Exclusions

- No settlement account payment creation route was added.
- No settlement account transfer route was added.
- No settlement account income or expenses mutation route was added.
- No account balance mutation was added.
- No workflow side effect was added.
- No Java source, database schema, frontend, Composer, `.env`, or business write code was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return statement rows for representative accounts.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Product Info Read-Only Routes

## Request

Register protected read-only software package/product-info routes in `route/app.php`.

## Reason

The copied Vue page under `/biz/saleprojectproductinfo` calls Java-compatible read endpoints to load product package/version rows for each sale-project product item. The Java service exposes `page`, `list`, and `detail` reads, while add/edit/delete have mutation behavior and remain deferred.

## Applied Change

`api-agent` registered the following protected GET routes:

- `GET /biz/saleprojectproductinfo/page`
- `GET /biz/saleprojectproductinfo/list`
- `GET /biz/saleprojectproductinfo/detail`

## Explicit Exclusions

- No `/biz/saleprojectproductinfo/add` route was added.
- No `/biz/saleprojectproductinfo/edit` route was added.
- No `/biz/saleprojectproductinfo/delete` route was added.
- No Java source, database schema, frontend, Composer, `.env`, workflow, inventory, finance, or business write code was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return read data for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Workflow Read Alias Routes

## Request

Register protected read-only workflow query routes in `route/app.php`.

## Reason

The copied Vue workflow pages call Java-compatible workflow query endpoints after login. These routes are required for browsing all processes, querying runtime process ids, loading workflow-related files, checking project runtime process lists, and opening task runtime activity detail while workflow writes remain deferred.

## Applied Change

`workflow-agent` and `api-agent` registered the following protected routes:

- `GET /biz/process/all/page`
- `GET /biz/process/query`
- `POST /biz/process/query/list`
- `GET /biz/process/project/runtime/query/list`
- `POST /biz/process/fileList`
- `GET /biz/task/runtime/activity/detail`

## Explicit Exclusions

- No `/biz/task/approve` route was added.
- No `/biz/task/reject` route was added.
- No `/biz/task/sse/stream` route was added.
- No `/biz/process/cancel` route was added.
- No process start/edit routes were added.
- No Java source, database schema, frontend, Composer, `.env`, or workflow runtime side-effect code was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return read data for representative routes.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Payroll Read-Only Routes

## Request

Register protected read-only payroll routes in `route/app.php`.

## Reason

The copied Vue payroll pages call Java-compatible payroll page, personal page, and detail endpoints. These routes are needed for read-only salary browsing while payroll import/export/generate/edit/delete behavior remains deferred.

## Applied Change

`api-agent` registered the following protected GET routes:

- `GET /biz/bizpayroll/page`
- `GET /biz/bizpayroll/mypage`
- `GET /biz/bizpayroll/detail`

## Explicit Exclusions

- No payroll import route was added.
- No payroll export route was added.
- No payroll generate route was added.
- No payroll add, edit, batch edit, or delete route was added.
- No Java source, database schema, frontend, Composer, `.env`, workflow, finance, or business write code was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return payroll rows and detail data when imported payroll data exists.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Datareport Sale Project Summary Read-Only Routes

## Request

Register protected read-only sale-project summary report routes in `route/app.php`.

## Reason

The copied Vue data-report dashboard calls Java-compatible sale-project amount, list, and status report endpoints. These routes are required for dashboard cards and sale-project statistics charts while more complex profit, unpaid-payment, settlement, and summary-statistics reports remain deferred.

## Applied Change

`api-agent` registered the following protected POST routes:

- `POST /biz/bizdatareport/saleproject`
- `POST /biz/bizdatareport/saleproject/list`
- `POST /biz/bizdatareport/saleproject/report`

## Explicit Exclusions

- No sale profit report route was added.
- No unpaid-payment report route was added.
- No settlement income or expenses report route was added.
- No summary-statistics route was added.
- No Java source, database schema, frontend, Composer, `.env`, workflow, finance mutation, or business write code was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return sale-project amount/list/report read data for representative data-scope payloads.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Datareport Sale Project Unpaid Payment Read-Only Route

## Request

Register protected read-only sale-project unpaid-payment report route in `route/app.php`.

## Reason

The copied Vue data-report dashboard calls Java-compatible `/biz/bizdatareport/saleproject/UnpaidPayment` for the "current month newly unpaid" card. This route is a pure read aggregation over sale projects, while profit, settlement, and summary-statistics reports remain deferred.

## Applied Change

`api-agent` registered the following protected POST route:

- `POST /biz/bizdatareport/saleproject/UnpaidPayment`

## Explicit Exclusions

- No sale profit report route was added.
- No settlement income or expenses report route was added.
- No summary-statistics route was added.
- No Java source, database schema, frontend, Composer, `.env`, workflow, finance mutation, or business write code was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return an `amount` object using Java's unpaid-payment calculation.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Datareport Settlement Income And Expenses Read-Only Routes

## Request

Register protected read-only settlement report routes in `route/app.php`.

## Reason

The copied Vue data-report settlement page calls Java-compatible income and expenses endpoints to load settlement record lists for frontend aggregation. These routes are pure reads over payment and expenditure records and are required for the settlement statistics page while account-balance mutations remain deferred.

## Applied Change

`api-agent` registered the following protected POST routes:

- `POST /biz/bizdatareport/settlement/income`
- `POST /biz/bizdatareport/settlement/expenses`

## Explicit Exclusions

- No settlement account income/add route was added.
- No settlement account expenses/add route was added.
- No settlement account payment/add or transfer/add route was added.
- No sale profit report route was added.
- No summary-statistics route was added.
- No Java source, database schema, frontend, Composer, `.env`, workflow, finance mutation, account-balance update, or business write code was changed.

## Verification

- `php think route:list` must list both added routes.
- Token requests should return payment/expenditure record lists with Java-compatible `PAYER_TIME` filtering.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Datareport Sale Profit Read-Only Route

## Request

Register a protected read-only sale-profit report route in `route/app.php`.

## Reason

The copied Vue sale-profit dashboard calls Java-compatible `/biz/bizdatareport/saleProfit` and expects raw report collections for frontend WebWorker calculation. This route is required for the sale-profit page while purchase, sale, inventory, return, settlement, and workflow mutations remain deferred.

## Applied Change

`api-agent` registered the following protected POST route:

- `POST /biz/bizdatareport/saleProfit`

## Explicit Exclusions

- No summary-statistics route was added.
- No purchase order write route was added.
- No sale project write route was added.
- No inventory, return, settlement, payment, or workflow side-effect route was added.
- No Java source, database schema, frontend, Composer, `.env`, account-balance update, or business write code was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return `projectlist`, `orderList`, and `bizProducts`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Datareport Summary Statistics Read-Only Route

## Request

Register a protected read-only summary statistics report route in `route/app.php`.

## Reason

The copied Vue summary statistics page calls Java-compatible `/biz/bizdatareport/summary/statistics` and expects raw company-scoped report collections for frontend WebWorker calculation. This route is required for the annual/monthly summary page while finance, settlement, workflow, and account-balance mutations remain deferred.

## Applied Change

`api-agent` registered the following protected POST route:

- `POST /biz/bizdatareport/summary/statistics`

## Explicit Exclusions

- No settlement account income/add route was added.
- No settlement account expenses/add route was added.
- No settlement account payment/add or transfer/add route was added.
- No workflow start/approve/reject/cancel route was added.
- No Java source, database schema, frontend, Composer, `.env`, account-balance update, or business write code was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return company summary objects with `org`, `settlementAccounts`, `paymentRecords`, `bizExpenditureRecords`, `bizSaleProjects`, and `bizDebitNotes`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Cost Read-Only Routes

## Request

Register protected read-only sale-project cost routes in `route/app.php`.

## Reason

The copied Vue sale-project API wrapper calls Java-compatible `/biz/saleproject/cost/details`. Java also exposes `/biz/saleproject/cost`. These routes are needed to complete sale-project read/detail compatibility while sale-project writes, inventory writes, finance writes, and workflow side effects remain deferred.

## Applied Change

`api-agent` registered the following protected POST routes:

- `POST /biz/saleproject/cost`
- `POST /biz/saleproject/cost/details`

## Explicit Exclusions

- No sale project add/edit/delete/cancel/repeal route was added.
- No sale project history/special/visibility/amount mutation route was added.
- No purchase order, inventory, delivery, settlement, payment, return-order, workflow, or account-balance write behavior was added.
- No Java source, database schema, frontend files, Composer files, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return a numeric aggregate for `cost` and `items`, `productItems`, `returnOrders` for `cost/details`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Follow-Up Read-Only Routes

## Request

Register protected read-only sale-project follow-up routes in `route/app.php`.

## Reason

The copied Vue sale-project follow-up list and sale-project detail follow-up tab call Java-compatible `/biz/saleprojectfollowup/page` and `/biz/saleprojectfollowup/detail`. These routes are pure reads over follow-up records and sale-project data-scope joins, while follow-up add/edit/delete writes remain deferred.

## Applied Change

`api-agent` registered the following protected GET routes:

- `GET /biz/saleprojectfollowup/page`
- `GET /biz/saleprojectfollowup/detail`

## Explicit Exclusions

- No sale-project follow-up add/edit/delete route was added.
- No sale project, attachment upload, file persistence, workflow, finance, or account-balance write behavior was added.
- No Java source, database schema, frontend files, Composer files, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return paginated follow-up records and single detail records.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Product Item Relation List Read-Only Route

## Request

Register a protected read-only sale-project product item relation list route in `route/app.php`.

## Reason

The copied Vue sale-project delivery/invoice helpers include Java-compatible `/biz/saleprojectproductitemrelation/list` to read combo-product child relation rows by sale-project product item ids. This route reads relation rows only. Mark editing and product-item mutation routes remain deferred.

## Applied Change

`api-agent` registered the following protected POST route:

- `POST /biz/saleprojectproductitemrelation/list`

## Explicit Exclusions

- No `/biz/saleprojectproductitemrelation/mark/edit` route was added.
- No `/biz/saleprojectproductitem/mark/edit` route was added.
- No sale-project product item, invoice, delivery, inventory, workflow, finance, file upload, or account-balance write behavior was added.
- No Java source, database schema, frontend files, Composer files, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return relation rows for visible sale-project product item ids.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Product Mark Routes

## Request

Register protected sale-project product item mark edit routes in `route/app.php`.

## Reason

The copied Vue sale-project delivery/invoice helpers call Java-compatible mark endpoints to flag product item rows and kit child relation rows. Java updates only `MARK` for these endpoints, so this slice opens only the same single-field mutation and keeps delivery, invoice, inventory, workflow, finance, and sale-project state side effects deferred.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/saleprojectproductitemrelation/mark/edit`
- `POST /biz/saleprojectproductitem/mark/edit`

## Explicit Exclusions

- No sale-project product item add/edit/delete route was added.
- No delivery, invoice, return, inventory, workflow, finance, file upload, account-balance, sale-project state, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Writes are limited to `MARK` plus update audit fields on the target row.

## Verification

- `php think route:list` must list both added routes.
- Relation mark edit should validate the owning active sale project before updating.
- Product item mark edit should validate the owning active sale project before updating.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Cost Route Precedence Fix

## Request

Adjust sale-project cost route ordering in `route/app.php`.

## Reason

The copied Vue completed-project detail cost tab calls Java-compatible `POST /biz/saleproject/cost/details`. ThinkPHP route matching used the shorter `cost` route first when it was registered before `cost/details`, so the detail call returned the numeric aggregate response instead of the expected detail object. The frontend then rendered a 500 result in the cost tab.

## Applied Change

`api-agent` reordered the existing protected sale-project cost routes:

- `POST /biz/saleproject/cost/details`
- `POST /biz/saleproject/cost`

No new endpoint was added.

## Explicit Exclusions

- No sale-project add/edit/delete/cancel/repeal route was added.
- No sale-project cost calculation rule was changed.
- No delivery, invoice, return, inventory, workflow, finance, file upload, or account-balance write behavior was added.
- No Java source, database schema, frontend files, Composer files, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list both routes.
- Token requests to `/biz/saleproject/cost/details` should return `items`, `productItems`, and `returnOrders`.
- Token requests to `/biz/saleproject/cost` should still return a numeric aggregate.
- The completed sale-project cost tab should no longer render the 500 result caused by route precedence.

---

# Public File Change Request: Sys User Grant Read-Only Routes

## Request

Register protected read-only system user grant echo routes in `route/app.php`.

## Reason

The copied Vue system user page opens grant dialogs from `/sys/user` and reads Java-compatible `ownRole`, `ownResource`, and `ownPermission` endpoints before rendering existing grants. These reads use `sys_relation` only and do not mutate user, role, resource, permission, or data-scope state.

## Applied Change

`user-agent/frontend-agent` registered the following protected GET routes:

- `GET /sys/user/list/detail`
- `GET /sys/user/ownRole`
- `GET /sys/user/ownResource`
- `GET /sys/user/ownPermission`

## Explicit Exclusions

- No `/sys/user/grantRole` route was added.
- No `/sys/user/grantResource` route was added.
- No `/sys/user/grantPermission` route was added.
- No user add/edit/delete, enable/disable, reset-password, import/export, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return existing grant echo data from `sys_relation`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz CC Records Read-Only Routes

## Request

Register protected read-only copy/CC record routes in `route/app.php`.

## Reason

The copied Vue copy-task page calls Java-compatible `/biz/ccrecords/page` and `/biz/ccrecords/detail`. These endpoints read workflow copy/CC records only and are filtered to the current login user like Java `BizCcRecordsServiceImpl.page`.

## Applied Change

`api-agent/workflow-agent` registered the following protected GET routes:

- `GET /biz/ccrecords/page`
- `GET /biz/ccrecords/detail`

## Explicit Exclusions

- No `/biz/ccrecords/add`, `/biz/ccrecords/edit`, or `/biz/ccrecords/delete` route was added.
- No workflow copy delegate, approval/reject/start/cancel, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return only existing current-user copy/CC records.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz Draft Read-Only Detail Route

## Request

Register a protected read-only sale-project draft detail route in `route/app.php`.

## Reason

The copied Vue sale-project draft flow calls Java-compatible `/biz/bizdraft/detail` to reload a saved draft by sale-project target id. This endpoint only reads `biz_draft` and preserves the raw `EXT_JSON` payload for the copied form to parse.

## Applied Change

`api-agent` registered the following protected GET route:

- `GET /biz/bizdraft/detail`

## Explicit Exclusions

- No `/biz/bizdraft/saleproject/add` route was added.
- No draft save/update, sale-project add/edit, workflow start, file upload, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should read existing non-deleted draft rows by `TARGET_ID`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz User Vacation Read-Only Detail Route

## Request

Register a protected read-only annual-leave balance detail route in `route/app.php`.

## Reason

The copied leave-process pages call Java-compatible `/biz/bizuservacation/detail` to read the current or requested user's annual-leave balance. This endpoint only reads `biz_user_vacation` and is needed before leave form and process-detail screens can show remaining leave days.

## Applied Change

`workflow-agent/api-agent` registered the following protected GET route:

- `GET /biz/bizuservacation/detail`

## Explicit Exclusions

- No `/biz/bizuservacation/add`, `/edit`, or `/delete` route was added.
- `/biz/bizuservacation/page` was added later as a separate protected read-only slice.
- No vacation generation, vacation reduction, leave approval deduction, workflow write, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests without `userId` should read the current token user's annual-leave balance.
- Token requests with `userId` should read that user's annual-leave balance for the current year.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz User Vacation Page Read-Only Route

## Request

Register a protected read-only vacation-balance page route in `route/app.php`.

## Reason

The copied frontend wrapper calls `/biz/bizuservacation/page` for vacation-balance management pages. Java service code exposes a read-only `page` method, while vacation writes and leave approval deductions remain separate high-risk flows.

## Applied Change

`workflow-agent/api-agent/frontend-agent` registered the following protected GET route:

- `GET /biz/bizuservacation/page`

## Explicit Exclusions

- No `/biz/bizuservacation/add`, `/edit`, or `/delete` route was added.
- No vacation generation, vacation reduction, leave approval deduction, workflow write, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return existing non-deleted vacation-balance rows with pagination.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz History Excel Read-Only Routes

## Request

Register protected read-only historical EXCEL data routes in `route/app.php`.

## Reason

The copied Vue page under `/biz/bizhistoryexcel` calls Java-compatible `/biz/bizhistoryexcel/page` and `/biz/bizhistoryexcel/detail`. These endpoints only read existing `biz_history_excel` rows and preserve the raw `EXT_JSON` spreadsheet payload for display.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET routes:

- `GET /biz/bizhistoryexcel/page`
- `GET /biz/bizhistoryexcel/detail`

## Explicit Exclusions

- No `/biz/bizhistoryexcel/add` route was added.
- No `/biz/bizhistoryexcel/edit` route was added.
- No `/biz/bizhistoryexcel/delete` route was added.
- No Excel import/export, spreadsheet parsing changes, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return existing non-deleted history Excel rows and detail data.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Invoice Item Read-Only Page Route

## Request

Register a protected read-only sale-project delivery invoice item page route in `route/app.php`.

## Reason

Java exposes `/biz/saleprojectinvoiceItem/page` from `BizSaleProjectInvoiceItemController`. The copied sale-project invoice/detail frontend expects to page delivery invoice child rows by `invoiceId` and optionally `warehousesId`.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET route:

- `GET /biz/saleprojectinvoiceItem/page`

## Explicit Exclusions

- No invoice item add/edit/delete route was added.
- No invoice creation/edit, delivery shipment, stock, project state, finance side effect, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return existing non-deleted invoice item rows filtered by `invoiceId` and `warehousesId` when supplied.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sales Project Field Change Log Read-Only Routes

## Request

Register protected read-only sale-project field change log routes in `route/app.php`.

## Reason

Java exposes `/biz/salesprojectfieldchangelog/page` and `/biz/salesprojectfieldchangelog/detail` from `SalesProjectFieldChangeLogController`. These endpoints only read existing project field change logs and help preserve standalone controller-path compatibility for copied sale-project history/detail consumers.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET routes:

- `GET /biz/salesprojectfieldchangelog/page`
- `GET /biz/salesprojectfieldchangelog/detail`

## Explicit Exclusions

- No `/biz/salesprojectfieldchangelog/add`, `/edit`, or `/delete` route was added.
- No sale-project amount/change write, change-log generation write, workflow, finance, audit side effect, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return existing non-deleted change-log rows.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Task User Read-Only Routes

## Request

Register protected read-only team-project task user routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojecttaskuser/page` and `/biz/bizteamprojecttaskuser/detail` from `BizTeamProjectTaskUserController`. These endpoints only read task assignment rows and preserve standalone controller-path compatibility for copied team-project task/member consumers.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET routes:

- `GET /biz/bizteamprojecttaskuser/page`
- `GET /biz/bizteamprojecttaskuser/detail`

## Explicit Exclusions

- No `/biz/bizteamprojecttaskuser/add`, `/edit`, or `/delete` route was added.
- No task assignment write, task status/progress write, notification, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests should return existing non-deleted task-user rows visible to the current team-project member.
- Requests without token should return `code=401`.

---

# Public File Change Request: Dev Monitor Network Info Read-Only Route

## Request

Register a protected read-only dev monitor network info route in `route/app.php`.

## Reason

Java exposes `/dev/monitor/networkInfo` from `DevMonitorController`. The copied frontend monitor API calls this endpoint to display upload and download rates.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET route:

- `GET /dev/monitor/networkInfo`

## Explicit Exclusions

- No monitor write route was added.
- No server process control, metric persistence, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return `devMonitorNetworkInfo.upLinkRate` and `devMonitorNetworkInfo.downLinkRate`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Rate Detail Read-Only Route

## Request

Register a protected read-only sale-project customer rating detail route in `route/app.php`.

## Reason

The copied frontend rating API wrapper exposes `saleProjectRateDetail`, and the Java service has `detail/queryEntity` read logic even though the Java controller did not wire a concrete detail mapping. This route preserves safe frontend compatibility for detail consumers without opening rating writes.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET route:

- `GET /biz/projectrate/detail`

## Explicit Exclusions

- No `/biz/projectrate/add`, `/edit`, or `/delete` route was added.
- No rating image upload, project state, sale-project write, file storage, Java source, database schema, Composer, `.env`, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return one non-deleted rating row by `id`.
- Requests without token should return `code=401`.

---

# Public File Change Request: Gen Basic Metadata Read-Only Routes

## Request

Register protected read-only generator database metadata routes in `route/app.php`.

## Reason

Java exposes `/gen/basic/tables` and `/gen/basic/tableColumns` from `GenBasicController`. The copied generator basic form calls both endpoints when opening the form and choosing a database table.

## Applied Change

`api-agent/frontend-agent` registered the following protected GET routes:

- `GET /gen/basic/tables`
- `GET /gen/basic/tableColumns`

## Explicit Exclusions

- No `/gen/basic/add`, `/edit`, `/delete`, `/previewGen`, `/execGenZip`, or `/execGenPro` route was added.
- No generated code output, generator template, Java source, database schema, Composer, `.env`, frontend source, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added routes.
- Token requests to `/gen/basic/tables` should return current database table metadata and exclude `ACT_` workflow engine tables.
- Token requests to `/gen/basic/tableColumns?tableName=<table>` should return current database column metadata.
- Requests without token should return `code=401`.

---

# Public File Change Request: Auth Third User Page Read-Only Route

## Request

Register a protected read-only third-party user binding page route in `route/app.php`.

## Reason

Java exposes `/auth/third/page` from `AuthThirdController`, and the copied frontend `thirdApi.js` calls this endpoint for third-party user binding pagination.

## Applied Change

`auth-agent/frontend-agent` registered the following protected GET route:

- `GET /auth/third/page`

## Explicit Exclusions

- No `/auth/third/render` or `/auth/third/callback` route was added.
- No third-party OAuth provider configuration, login callback binding, user creation, token issuance, Java source, database schema, Composer, `.env`, frontend source, or deployment configuration was changed.

## Verification

- `php think route:list` must list the added route.
- Token requests should return `auth_third_user` page data or a stable empty page.
- Requests without token should return `code=401`.

---

# Public File Change Request: Customer Follow-Up Write Routes

## Request

Register protected customer follow-up write routes in `route/app.php`.

## Reason

Java exposes `/biz/customerfollowup/add`, `/edit`, and `/delete`, and the copied frontend `customerFollowUpApi.js` calls these endpoints from the customer follow-up form and table actions.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/customerfollowup/add`
- `POST /biz/customerfollowup/edit`
- `POST /biz/customerfollowup/delete`

## Explicit Exclusions

- No `/biz/customer/add`, `/edit`, `/delete`, or `/head/edit` route was added.
- No file upload/storage cleanup, notification, workflow, finance, stock, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add, edit, and logically delete a customer follow-up row when the current user can write the owning customer.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Follow-Up Write Routes

## Request

Register protected sale-project follow-up write routes in `route/app.php`.

## Reason

Java exposes `/biz/saleprojectfollowup/add`, `/edit`, and `/delete`, and the copied frontend `saleProjectFollowUpApi.js` calls these endpoints from the sale-project detail follow-up tab and standalone follow-up page.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/saleprojectfollowup/add`
- `POST /biz/saleprojectfollowup/edit`
- `POST /biz/saleprojectfollowup/delete`

## Explicit Exclusions

- No `/biz/saleproject/add`, `/edit`, `/delete`, status edit, amount edit, workflow start, finance, inventory, Java source, database schema, Composer, `.env`, or frontend source was changed.
- No file upload/storage implementation, physical file cleanup, or notification side effect was added.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add, edit, and logically delete a sale-project follow-up row when the current user can write the owning sale project.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Product Info Write Routes

## Request

Register protected sale-project product-info write routes in `route/app.php`.

## Reason

Java exposes `/biz/saleprojectproductinfo/add`, `/edit`, and `/delete`, and the copied frontend `bizSaleProjectProductInfoApi.js` calls these endpoints from the software package/version info page.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/saleprojectproductinfo/add`
- `POST /biz/saleprojectproductinfo/edit`
- `POST /biz/saleprojectproductinfo/delete`

## Explicit Exclusions

- No sale-project order/product-item, product master-data, inventory, delivery, workflow, finance, import/export, report generation, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add, edit, and logically delete package/version info rows.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Field Change Log Write Routes

## Request

Register protected sale-project field-change-log write routes in `route/app.php`.

## Reason

Java exposes `/biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete`, and the imported permission data includes these API permission points. The existing ThinkPHP implementation already had read-only `page` and `detail`; this slice completes the narrow base log-table CRUD route group without implementing sale-project amount/history generation side effects.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/salesprojectfieldchangelog/add`
- `POST /biz/salesprojectfieldchangelog/edit`
- `POST /biz/salesprojectfieldchangelog/delete`

## Explicit Exclusions

- No `/biz/saleproject/history/add`, amount edit, deal edit, visibility edit, sale-project state transition, workflow, finance, inventory, notification, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add, edit, and logically delete a sale-project field change log row when the current user can write the owning sale project.
- Requests without token should return `code=401`.

---

# Public File Change Request: Biz History Excel Write Routes

## Request

Register protected historical Excel data write routes in `route/app.php`.

## Reason

Java exposes `/biz/bizhistoryexcel/add`, `/edit`, and `/delete`, and the copied frontend `bizHistoryExcelApi.js` calls these endpoints from the historical Excel page and upload form. The existing ThinkPHP implementation already covered `page` and `detail`; this slice completes base row persistence without changing frontend Excel parsing.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizhistoryexcel/add`
- `POST /biz/bizhistoryexcel/edit`
- `POST /biz/bizhistoryexcel/delete`

## Explicit Exclusions

- No frontend Excel parser, import/export, file storage, `biz_history_excel_row` write, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add, edit, and logically delete a historical Excel row.
- Requests without token should return `code=401`.

---

# Public File Change Request: Sale Project Rate Write Routes

## Request

Register protected sale-project rating write routes in `route/app.php`.

## Reason

Java exposes `/biz/projectrate/add` and `/delete`, and the copied frontend `saleProjectRateApi.js` calls `add` from the sale-project case/rating tab. The existing ThinkPHP implementation already covered `page`, `list`, and `detail`; this slice completes the Java-exposed base rating row writes without implementing file storage or rating edit.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/projectrate/add`
- `POST /biz/projectrate/delete`

## Explicit Exclusions

- No `/biz/projectrate/edit`, image upload/storage cleanup, sale-project state, workflow, finance, inventory, notification, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add and logically delete a sale-project rating row when the current user can write the owning sale project.
- Requests without token should return `code=401`.

---

# Public File Change Request: CC Records Delete Route

## Request

Register the protected workflow copy/CC record delete route in `route/app.php`.

## Reason

Java exposes `/biz/ccrecords/delete`, and the copied Vue `biz/biztask/copytask.vue` page calls it for single and batch delete actions. Existing ThinkPHP routes already covered `page` and `detail`; this slice completes the Java-exposed delete endpoint while preserving the current-user guard.

## Applied Change

`api-agent/frontend-agent/workflow-agent` registered the following protected POST route:

- `POST /biz/ccrecords/delete`

## Explicit Exclusions

- No `/biz/ccrecords/add`, `/biz/ccrecords/edit`, workflow copy-user delegate write, approval/reject/start/cancel route, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete requires the row `USER` to match the current token user id and uses logical deletion through `DELETE_FLAG = DELETED`.

## Verification

- `php think route:list` must list the added route.
- Token requests should be able to logically delete only the current user's copy/CC rows.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Comment Add Routes

## Request

Register protected team-project timeline comment add and comment-reply add routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojectcomment/add` and `/biz/bizteamprojectcommentreply/add`, and the copied Vue team-project detail page calls both endpoints when a user submits a project timeline comment or replies to an existing comment.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizteamprojectcomment/add`
- `POST /biz/bizteamprojectcommentreply/add`

## Explicit Exclusions

- No `/biz/bizteamprojectcomment/delete` route was added.
- No `/biz/bizteamprojectcommentreply/edit` or `/delete` route was added.
- No notification push, data-change event, task state/progress write, team-project mutation, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Comment add stores mentioned users in `EXT_JSON`; notification delivery remains deferred.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to add a project timeline comment or reply only when the current user is a non-deleted member of the owning team project.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Comment Maintenance Routes

## Request

Register protected team-project timeline comment delete, comment-reply edit, and comment-reply delete routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojectcomment/delete`, `/biz/bizteamprojectcommentreply/edit`, and `/biz/bizteamprojectcommentreply/delete`. The copied frontend API wrappers include these maintenance calls, and the ThinkPHP route group already covers read and add compatibility for the same comment module.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizteamprojectcomment/delete`
- `POST /biz/bizteamprojectcommentreply/edit`
- `POST /biz/bizteamprojectcommentreply/delete`

## Explicit Exclusions

- No team-project add/edit/delete route was added.
- No task/category/task-user add/edit/delete route was added.
- No task comment add/edit/delete route was added.
- No task state/progress write, notification push, data-change event, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Comment delete should require imported `delComment` project resource permission.
- Reply edit/delete should allow the reply creator or a project user with imported `delComment` permission.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Task User Edit Route

## Request

Register the protected team-project task assignee synchronization route in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojecttask/user/edit` from `BizTeamProjectTaskController`, and the copied Vue task detail drawer calls it when a user changes task assignees through `bizTeamProjectTaskApi.bizTeamProjectTaskUserEdit`.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST route:

- `POST /biz/bizteamprojecttask/user/edit`

## Explicit Exclusions

- No `/biz/bizteamprojecttask/add`, `/edit`, or `/delete` route was added.
- No task-category add/edit/sort/delete route was added.
- No task-comment write, task status/progress/content write, notification push, data-change event, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Removed task-user rows use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added route.
- Token requests should be able to sync task assignees only when the current user is a non-deleted project member and has imported `addUser` project permission or task-level `MANAGE` role.
- Submitted assignees should be rejected when they are not non-deleted members of the same team project.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Task Comment Add Route

## Request

Register the protected team-project task comment add route in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojecttaskcomment/add`, and the copied Vue task detail drawer calls `bizTeamProjectTaskCommentSubmitForm` when a user submits a task comment. Existing ThinkPHP routes already cover task-comment `page`, `list`, and `detail`; this slice opens only the frontend-visible base add route.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST route:

- `POST /biz/bizteamprojecttaskcomment/add`

## Explicit Exclusions

- No `/biz/bizteamprojecttaskcomment/edit` or `/delete` route was added.
- No task add/edit/delete route was added.
- No task-category write, task-user standalone write, task status/progress/content write, notification push, data-change event, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Submitted `files` are stored in `EXT_JSON` as `{"file":[...]}` for compatibility with the copied frontend parser.

## Verification

- `php think route:list` must list the added route.
- Token requests should be able to add a task comment only when the current user is a non-deleted member of the owning team project.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Task Comment Maintenance Routes

## Request

Register protected team-project task comment edit and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojecttaskcomment/edit` and `/biz/bizteamprojecttaskcomment/delete`, and the copied Vue API wrapper includes both maintenance calls. Existing ThinkPHP routes now cover task-comment reads and add; this slice completes base user-comment maintenance without opening task state or generated-log mutations.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizteamprojecttaskcomment/edit`
- `POST /biz/bizteamprojecttaskcomment/delete`

## Explicit Exclusions

- No generated `CATEGORY = LOG` task-comment maintenance was added.
- No task add/edit/delete route was added.
- No task-category write, task-user standalone write, task status/progress/content write, notification push, data-change event, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to maintain only `CATEGORY = COMMENT` rows when the current user is the comment creator, has imported project `delComment`, or has task-level `MANAGE`.
- Generated `CATEGORY = LOG` rows should remain read-only.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Task Category Maintenance Routes

## Request

Register protected team-project task category add, edit, sort/edit, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojecttaskcategory/add`, `/edit`, `/sort/edit`, and `/delete`, and the copied Vue kanban task view calls these endpoints when maintaining columns. Existing ThinkPHP routes already cover category `page`, `list`, and `detail`; this slice opens only category-column maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizteamprojecttaskcategory/add`
- `POST /biz/bizteamprojecttaskcategory/edit`
- `POST /biz/bizteamprojecttaskcategory/sort/edit`
- `POST /biz/bizteamprojecttaskcategory/delete`

## Explicit Exclusions

- No task add/edit/delete route was added.
- No task drag-to-category, task status/progress/content write, notification push, data-change event, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.
- Deleting categories that still contain active tasks is rejected to avoid orphaning imported task cards.

## Verification

- `php think route:list` must list the added routes.
- Token requests should be able to maintain categories only when the current user is a team-project `LEADER`, team-project `MANAGE`, or has imported project `addUser` permission.
- Delete should reject non-empty categories.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Task Base Maintenance Routes

## Request

Register protected team-project task add, edit, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojecttask/add`, `/edit`, and `/delete`, and the copied Vue kanban task view calls these endpoints for adding tasks, toggling task status, editing task content/progress/category, and deleting tasks from the detail drawer. Existing ThinkPHP routes already cover task `page`, `list`, `detail`, and `user/edit`; this slice opens only base task-row maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizteamprojecttask/add`
- `POST /biz/bizteamprojecttask/edit`
- `POST /biz/bizteamprojecttask/delete`

## Explicit Exclusions

- No standalone `/biz/bizteamprojecttaskuser/add`, `/edit`, or `/delete` route was added.
- No generated `CATEGORY = LOG` task comments were added.
- No notification push, data-change event, workflow action, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Add should validate current-user project membership, category/project match, and selected task users as project members.
- Edit/delete should allow only the task creator, a task-level `MANAGE` user, or a project maintainer.
- Requests without token should return `code=401`.

---

# Public File Change Request: Team Project Member Maintenance Routes

## Request

Register protected team-project member add, manage/add, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/bizteamprojectuser/add`, `/manage/add`, and `/delete`, and the copied Vue team-project detail page calls these endpoints when adding normal members, adding project managers, and removing members from a project. Existing ThinkPHP routes already cover member `page`, `list`, and `detail`; this slice opens only member maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizteamprojectuser/add`
- `POST /biz/bizteamprojectuser/manage/add`
- `POST /biz/bizteamprojectuser/delete`

## Explicit Exclusions

- No `/biz/bizteamprojectuser/edit` route was added.
- No notification push, data-change event, team-project base write, task write, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Add should validate current-user project resource permission `addUser`, selected user existence, and active duplicate membership.
- Manage add should validate current-user project resource permission `addManage`.
- Delete should reject leader/current-user removal and require `addManage` when removing a project manager.
- Requests without token should return `code=401`.

---

# Public File Change Request: Customer Base Maintenance Routes

## Request

Register protected customer add, edit, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/customer/add`, `/edit`, and `/delete`, and the copied Vue customer page calls these endpoints from the customer table and form. Existing ThinkPHP routes already cover customer `page`, `detail`, and `detail/list`; this slice opens only base customer-row maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/customer/add`
- `POST /biz/customer/edit`
- `POST /biz/customer/delete`

## Explicit Exclusions

- No `/biz/customer/head/edit` route was added.
- No file upload/storage cleanup, customer ownership reassignment, SM4 crypto implementation, data-change event, sale-project/customer side effect, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Add should accept the copied customer form payload and default owner/org from the current token user when absent.
- Edit/delete should validate customer write access through owner/org data scope.
- Requests without token should return `code=401`.

---

# Public File Change Request: Customer Head Reassignment Route

## Request

Register protected customer owner reassignment route in `route/app.php`.

## Reason

Java exposes `/biz/customer/head/edit`, and the copied Vue customer API wrapper calls this endpoint for customer owner reassignment. Existing ThinkPHP routes already cover customer page/detail/base add/edit/delete; this slice opens only the Java-equivalent owner reassignment write.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST route:

- `POST /biz/customer/head/edit`

## Explicit Exclusions

- No customer import/export, file upload/storage cleanup, SM4 plaintext search, sale-project/customer side effect, notification, Java data-change event, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Writes are limited to `customer.USER`, `customer.ORG`, update audit fields, and `VERSION`.

## Verification

- `php think route:list` must list the added route.
- The current token must be allowed to edit the active customer.
- The target user must exist and be assignable by admin-compatible role, data-scope org ids, or current-user fallback.
- Requests without token should return `code=401`.

---

# Public File Change Request: Supplier Base Maintenance Routes

## Request

Register protected supplier add, edit, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/supplier/add`, `/edit`, and `/delete`, and the copied Vue supplier page calls these endpoints from the supplier table and form. Existing ThinkPHP routes already cover supplier `page`, `list`, `list/query/name`, and `detail`; this slice opens only base supplier-row maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/supplier/add`
- `POST /biz/supplier/edit`
- `POST /biz/supplier/delete`

## Explicit Exclusions

- No supplier import/export route was added.
- No purchase, payment, procurement, inventory, workflow, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Add should validate Java-required fields and default empty `status` to `ENABLE`.
- Edit/delete should validate supplier write access through admin, scoped organization, or creator scope.
- Requests without token should return `code=401`.

---

# Public File Change Request: Warehouse Base Maintenance Routes

## Request

Register protected warehouse add, edit, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/warehouses/add`, `/edit`, and `/delete`, and the copied Vue warehouse page calls these endpoints from the warehouse table and form. Existing ThinkPHP routes already cover warehouse `page`, `list`, and `detail`; this slice opens only base warehouse-row maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/warehouses/add`
- `POST /biz/warehouses/edit`
- `POST /biz/warehouses/delete`

## Explicit Exclusions

- No inventory stock update, delivery record write, purchase-order write, sale-project invoice write, workflow behavior, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.

## Verification

- `php think route:list` must list the added routes.
- Add should default owner user and organization from the current token user.
- Edit/delete should validate warehouse write access through admin, scoped organization, or warehouse owner scope.
- Requests without token should return `code=401`.

---

# Public File Change Request: Product Status And Reconciliation Routes

## Request

Register protected product status and reconciliation edit routes in `route/app.php`.

## Reason

Java exposes `/biz/bizproduct/edit/status` and `/biz/bizproduct/reconciliation/edit`, and the copied Vue product list page calls these endpoints from the status switch and reconciliation modal. Existing ThinkPHP routes already cover product `page`, `list`, `detail`, and `children`; this slice opens only lightweight product-row writes.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizproduct/edit/status`
- `POST /biz/bizproduct/reconciliation/edit`

## Explicit Exclusions

- No product add, edit, delete, kit product relation write, inventory, purchase, sale-project, finance transaction, workflow, Java source, database schema, Composer, `.env`, or frontend source was changed.

## Verification

- `php think route:list` must list the added routes.
- Status edit should validate `ENABLE`/`DISABLE` and product write access.
- Reconciliation edit should validate selected product ids, `ENABLE`/`DISABLE`, non-negative amount, and product write access.
- Requests without token should return `code=401`.

---

# Public File Change Request: Product Base Maintenance Routes

## Request

Register protected product add, edit, and delete routes in `route/app.php`.

## Reason

Java exposes `/biz/bizproduct/add`, `/edit`, and `/delete`, and the copied Vue product table/form calls these endpoints through `bizProductApi.js`. Existing ThinkPHP routes already cover product reads, children reads, status edits, and reconciliation edits; this slice opens only base product-row maintenance plus Java-compatible kit product relation maintenance.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST routes:

- `POST /biz/bizproduct/add`
- `POST /biz/bizproduct/edit`
- `POST /biz/bizproduct/delete`

## Explicit Exclusions

- No inventory stock update, purchase-order write, sale-project item write, finance transaction write, workflow action, file upload/storage implementation, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Deletes use logical deletion through `DELETE_FLAG = DELETED` instead of physically deleting imported product rows.
- Product relation writes are limited to clearing and replacing `KIT_PRODUCT_DATA` rows for the product object currently being added or edited.

## Verification

- `php think route:list` must list the added routes.
- Add should validate Java-required product fields and default `status` to `ENABLE`.
- Kit product add/edit should validate child product ids and quantities, then write `product_relation.CATEGORY = KIT_PRODUCT_DATA`.
- Delete should reject products referenced as kit children.
- Requests without token should return `code=401`.

## 2026-06-06 User Center Self-Service Write Routes

Agent: user-agent / frontend-agent

### Locked File Changed

- `route/app.php`

### Reason

The copied Vue personal center calls Java-compatible user-center self-service routes. These require explicit ThinkPHP route declarations under the locked public-file rule.

### Added Routes

- `POST /sys/userCenter/updatePassword`
- `POST /sys/userCenter/updateAvatar`
- `POST /sys/userCenter/updateSignature`
- `POST /sys/userCenter/updateUserInfo`
- `POST /sys/userCenter/updateUserWorkbench`
- `POST /sys/userCenter/process/config/edit`
- `POST /biz/user/center/edit`

### Guardrails

- All routes remain behind `AuthMiddleware`.
- All writes affect only the current token user.
- No admin-side user CRUD, grants, reset-password-by-admin, import/export, Java source change, database schema change, Composer change, `.env` change, or frontend source change was added.

---

# Public File Change Request: Index Message All-Mark-Read Route

## Request

Register protected homepage message bulk mark-read route in `route/app.php`.

## Reason

Java exposes `/sys/index/message/allMessageMarkRead`, and the copied Vue homepage message drawer calls it from the "mark all as read" button. Existing ThinkPHP routes already cover message list, page, and detail; this slice opens only the current user's bulk read-state update.

## Applied Change

`user-agent/frontend-agent` registered the following protected POST route:

- `POST /sys/index/message/allMessageMarkRead`

## Explicit Exclusions

- No message send, message delete, WebPush, full realtime push, schedule add/delete, Java source, database schema, Composer, `.env`, or frontend source was changed.
- The update is limited to current-token-user `dev_relation` rows where `CATEGORY = MSG_TO_USER`.

## Verification

- `php think route:list` must list the added route.
- Requests without token should return business `code=401`.
- Service smoke should mark sampled current-user message relations as `read=true` and then restore imported `EXT_JSON` test data.

---

# Public File Change Request: Index Schedule Self-Service Routes

## Request

Register protected homepage schedule add and delete routes in `route/app.php`.

## Reason

Java exposes `/sys/index/schedule/add` and `/sys/index/schedule/deleteSchedule`, and the copied Vue homepage schedule widget calls them from the add form and timeline delete icon. Existing ThinkPHP routes already cover schedule listing; this slice opens only current-user schedule maintenance.

## Applied Change

`user-agent/frontend-agent` registered the following protected POST routes:

- `POST /sys/index/schedule/add`
- `POST /sys/index/schedule/deleteSchedule`

## Explicit Exclusions

- No shared calendar, schedule editing, notifications, cross-user schedule management, Java source, database schema, Composer, `.env`, or frontend source was changed.
- Delete is constrained to current-token-user `sys_relation` rows where `CATEGORY = SYS_USER_SCHEDULE_DATA`.

## Verification

- `php think route:list` must list the added routes.
- Requests without token should return business `code=401`.
- Service smoke should add one temporary current-user schedule row and delete it without leaving test data.

---

# Public File Change Request: Auth Session And Token Exit Routes

## Request

Register protected auth monitor session/token exit routes in `route/app.php`.

## Reason

Java exposes the session and token exit endpoints, and the copied Vue auth monitor page calls them from session and token strong-exit buttons. The existing ThinkPHP routes already cover monitor analysis and page reads; this slice opens the matching protected mutation routes.

## Applied Change

`auth-agent/frontend-agent` registered the following protected POST routes:

- `POST /auth/session/b/exit`
- `POST /auth/session/c/exit`
- `POST /auth/token/b/exit`
- `POST /auth/token/c/exit`

## Guardrails

- All routes remain behind `AuthMiddleware`.
- B-side exit uses the cache-backed ThinkPHP token index and does not touch database schema.
- Ordinary users can only operate on their own user id/token.
- Admin-compatible accounts or roles may manage all indexed B-side sessions.
- C-side exit routes return success-compatible no-op data until client auth is implemented.
- No Java source, database schema, Composer, `.env`, frontend source, user CRUD, workflow, or business module behavior was changed.

## Verification

- `php think route:list` must list the added routes.
- Requests without token should return business `code=401`.
- Service smoke should create indexed temporary tokens, revoke them by token value and user id, and confirm the cache payload is removed.

---

# Public File Change Request: Dev Message Delete Route

## Request

Register protected station-message delete route in `route/app.php`.

## Reason

Java exposes `/dev/message/delete`, and the copied Vue station-message management page calls it from row delete and batch delete actions. Existing ThinkPHP routes already cover message page/detail reads and SSE compatibility; this slice opens only delete compatibility.

## Applied Change

`api-agent/frontend-agent` registered the following protected POST route:

- `POST /dev/message/delete`

## Guardrails

- The route remains behind `AuthMiddleware`.
- Delete removes selected `dev_message` rows and matching `dev_relation` rows where `CATEGORY = MSG_TO_USER`.
- Admin-compatible accounts or roles may delete tenant messages.
- Ordinary users may delete only messages they created.
- No message send, SSE/WebPush realtime push, Java source, database schema, Composer, `.env`, frontend source, user/workflow, or business module behavior was changed.

## Verification

- `php think route:list` must list the added route.
- Requests without token should return business `code=401`.
- Service smoke should insert one temporary message and receiver relation, delete it, and confirm both rows are removed.

---

# Public File Change Request: Dev Message Send Route

## Request

Register protected station-message send route in `route/app.php`.

## Reason

Java exposes `/dev/message/send`, and the copied Vue station-message management form calls it when sending a station message. Existing ThinkPHP routes already cover message page/detail reads, SSE compatibility, and delete; this slice opens the matching protected send mutation.

## Applied Change

`api-agent/frontend-agent` registers the following protected POST route:

- `POST /dev/message/send`

## Guardrails

- The route remains behind `AuthMiddleware`.
- Send creates one `dev_message` row and `MSG_TO_USER` receiver relations.
- Access is limited to admin-compatible accounts or roles until fine-grained route permission middleware is complete.
- Full SSE/WebPush realtime push behavior remains deferred.
- No Java source, database schema, Composer, `.env`, frontend source, user/workflow, or unrelated business module behavior is changed.

## Verification

- `php think route:list` must list the added route.
- Requests without token should return business `code=401`.
- Service smoke should insert one temporary message and receiver relation, then clean both rows.

---

# Public File Change Request: User Grant Role Routes

## Request

Register protected role-grant save routes in `route/app.php`.

## Reason

Java exposes `/sys/user/grantRole` and `/biz/user/grantRole`, and the copied Vue system/business user pages call these endpoints after the role selection dialog is saved. Existing ThinkPHP routes already expose `ownRole` and role selectors, so this slice opens the matching protected save mutation.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/user/grantRole`
- `POST /biz/user/grantRole`

## Guardrails

- Both routes remain behind `AuthMiddleware`.
- The service only rewrites `sys_relation` rows for `CATEGORY = SYS_USER_HAS_ROLE`.
- Access requires admin-compatible payloads or matching route/button permission codes.
- Biz route keeps a conservative data-scope guard before delegating to the shared relation save.
- No Java source, database schema, Composer, `.env`, frontend source, resource/permission grants, user CRUD, role CRUD, workflow, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list both added routes.
- Requests without token should return business `code=401`.
- Service smoke should save a temporary role assignment and restore the original target-user role relations.

---

# Public File Change Request: User Grant Resource Route

## Request

Register protected user resource-grant save route in `route/app.php`.

## Reason

Java exposes `/sys/user/grantResource`, and the copied Vue system user resource-grant dialog calls this endpoint after selecting menus and buttons. Existing ThinkPHP routes already expose `ownResource`, menu selectors, and button echo data, so this slice opens the matching protected save mutation.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST route:

- `POST /sys/user/grantResource`

## Guardrails

- The route remains behind `AuthMiddleware`.
- The service only rewrites `sys_relation` rows for `CATEGORY = SYS_USER_HAS_RESOURCE`.
- Access requires admin-compatible payloads or matching route/button permission codes.
- System-module resources are rejected when the target user is not super-admin-compatible, matching the Java safeguard.
- No Java source, database schema, Composer, `.env`, frontend source, permission grants, user CRUD, workflow, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list the added route.
- Requests without token should return business `code=401`.
- Service smoke should save a temporary resource assignment and restore the original target-user resource relations.

---

# Public File Change Request: User Grant Permission Route

## Request

Register protected user permission-grant save route in `route/app.php`.

## Reason

Java exposes `/sys/user/grantPermission`, and the copied Vue system user permission-grant dialog calls this endpoint after selecting API permissions and data scopes. Existing ThinkPHP routes already expose `ownPermission` and permission tree selectors, so this slice opens the matching protected save mutation.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST route:

- `POST /sys/user/grantPermission`

## Guardrails

- The route remains behind `AuthMiddleware`.
- The service only rewrites `sys_relation` rows for `CATEGORY = SYS_USER_HAS_PERMISSION`.
- Access requires admin-compatible payloads or matching route/button permission codes.
- `scopeCategory` stays constrained to the Java/frontend data-scope values.
- No Java source, database schema, Composer, `.env`, frontend source, user CRUD, workflow, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list the added route.
- Requests without token should return business `code=401`.
- Service smoke should save a temporary permission assignment and restore the original target-user permission relations.

---

# Public File Change Request: User Enable Disable Routes

## Request

Register protected user status switch routes in `route/app.php`.

## Reason

Java exposes system and business user enable/disable endpoints, and the copied Vue user tables call them from the row status switch. Existing ThinkPHP user directory routes already read `USER_STATUS`; this slice opens the matching protected status mutations.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/user/disableUser`
- `POST /sys/user/enableUser`
- `POST /biz/user/disableUser`
- `POST /biz/user/enableUser`

## Guardrails

- All four routes remain behind `AuthMiddleware`.
- The service only updates `sys_user.USER_STATUS`.
- Access requires admin-compatible payloads or matching route/button permission codes.
- Business routes also enforce conservative organization data-scope or current-user fallback.
- No Java source, database schema, Composer, `.env`, frontend source, user CRUD, password reset, import/export, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list all four routes.
- Requests without token should return business `code=401`.
- Service smoke should toggle one sampled active user's status and restore the original value.

---

# Public File Change Request: User Reset Password Routes

## Request

Register protected admin reset-password routes in `route/app.php`.

## Reason

Java exposes system and business user reset-password endpoints, and the copied Vue user tables call them from row action menus. Existing ThinkPHP password verification already supports Java-compatible SM3 hashes, so this slice opens the matching protected mutation while keeping the default password value internal to the service.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/user/resetPassword`
- `POST /biz/user/resetPassword`

## Guardrails

- Both routes remain behind `AuthMiddleware`.
- The service only updates `sys_user.PASSWORD`.
- Access requires admin-compatible payloads or matching route/button permission codes.
- Business route also enforces conservative organization data-scope or current-user fallback.
- The default password is read from `dev_config` and hashed through existing SM3 compatibility without printing the value.
- No Java source, database schema, Composer, `.env`, frontend source, user CRUD, import/export, token/session invalidation, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list both routes.
- Requests without token should return business `code=401`.
- Service smoke should reset one sampled active user's password and restore the original hash.

---

# Public File Change Request: User Delete Routes

## Request

Register protected user delete routes in `route/app.php`.

## Reason

Java exposes system and business user delete endpoints, and the copied Vue user tables call these endpoints from row delete and batch delete actions. Existing ThinkPHP user directory routes already filter by `DELETE_FLAG`, so this slice opens the matching protected logical-delete mutation.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/user/delete`
- `POST /biz/user/delete`

## Guardrails

- Both routes remain behind `AuthMiddleware`.
- The service only logically deletes `sys_user` rows by setting `DELETE_FLAG = DELETED`.
- Access requires admin-compatible payloads or matching route/button permission codes.
- Business route also enforces conservative organization data-scope or current-user fallback.
- Java-compatible cleanup clears affected user and organization director references.
- Built-in/admin-compatible accounts are rejected.
- No Java source, database schema, Composer, `.env`, frontend source, user add/edit/import/export, role/resource/permission grants, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list both routes.
- Requests without token should return business `code=401`.
- Service smoke should logically delete one sampled active user and restore the original user row plus touched director references.

---

# Public File Change Request: User Add Edit Routes

## Request

Register protected user add/edit routes in `route/app.php`.

## Reason

Java exposes system and business user add/edit endpoints, and the copied Vue user forms submit to these endpoints. The existing ThinkPHP user directory already supports read/detail/selector behavior, so this slice opens the matching protected base profile mutations without import/export, grant, workflow, or token side effects.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/user/add`
- `POST /sys/user/edit`
- `POST /biz/user/add`
- `POST /biz/user/edit`

## Guardrails

- All four routes remain behind `AuthMiddleware`.
- The service writes only known `sys_user` profile columns.
- Add sets default password hash, enabled status, not-deleted flag, tenant id, avatar fallback, bank defaults, and company employee id.
- Edit preserves password, status, delete flag, tenant id, and create metadata.
- Business routes enforce conservative organization data-scope or current-user edit fallback.
- No Java source, database schema, Composer, `.env`, frontend source, import/export, grants, token/session invalidation, Java data-change events, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list all four routes.
- Requests without token should return business `code=401`.
- Service smoke should create temporary system and business users, edit them, then remove only the temporary test rows.

---

# Public File Change Request: Organization Add Edit Delete Routes

## Request

Register protected organization add/edit/delete routes in `route/app.php`.

## Reason

Java exposes system and business organization maintenance endpoints, and the copied Vue organization pages submit add/edit/delete actions to these paths. The existing ThinkPHP organization service already supports page/list/tree/detail reads, so this slice opens the matching protected base organization mutations.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/org/add`
- `POST /sys/org/edit`
- `POST /sys/org/delete`
- `POST /biz/org/add`
- `POST /biz/org/edit`
- `POST /biz/org/delete`

## Guardrails

- All six routes remain behind `AuthMiddleware`.
- The service writes only known `sys_org` base columns.
- Add/edit validate parent organization, category, sort code, same-level duplicate names, optional director, tenant compatibility, and parent-cycle prevention.
- Delete expands selected child organizations, blocks active user, extra-position, role, and position references, then logically deletes safe rows with `DELETE_FLAG = DELETED`.
- Business routes enforce conservative organization data-scope checks.
- No Java source, database schema, Composer, `.env`, frontend source, position CRUD, user import/export, Java data-change events, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list all six routes.
- Requests without token should return business `code=401`.
- Service smoke should create temporary system and business organizations, edit them, logically delete them, then physically remove only the temporary smoke rows.

---

# Public File Change Request: Position Add Edit Delete Routes

## Request

Register protected position add/edit/delete routes in `route/app.php`.

## Reason

Java exposes system and business position maintenance endpoints, and the copied Vue position pages submit add/edit/delete actions to these paths. The existing ThinkPHP position service already supports page/list/detail/selector reads, so this slice opens the matching protected base position mutations.

## Applied Change

`user-agent/frontend-agent` registers the following protected POST routes:

- `POST /sys/position/add`
- `POST /sys/position/edit`
- `POST /sys/position/delete`
- `POST /biz/position/add`
- `POST /biz/position/edit`
- `POST /biz/position/delete`

## Guardrails

- All six routes remain behind `AuthMiddleware`.
- The service writes only known `sys_position` base columns.
- Add/edit validate active organization, category, sort code, same-organization duplicate names, and tenant compatibility.
- Delete blocks active direct-user and extra-position JSON references, then logically deletes safe rows with `DELETE_FLAG = DELETED`.
- Business routes enforce conservative organization data-scope checks.
- No Java source, database schema, Composer, `.env`, frontend source, user import/export, Java data-change events, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list all six routes.
- Requests without token should return business `code=401`.
- Service smoke should create temporary system and business positions, edit them, logically delete them, then physically remove only the temporary smoke rows.

---

# Public File Change Request: User Export Download Routes

## Request

Register protected user export and download routes in `route/app.php`.

## Reason

Java exposes system and business user download endpoints, and the copied Vue user pages call these paths with `responseType: 'blob'`. This slice opens the matching protected download routes without enabling import parsing, upload handling, or new Office export dependencies.

## Applied Change

`user-agent/frontend-agent` registers the following protected GET routes:

- `GET /sys/user/downloadImportUserTemplate`
- `GET /sys/user/export`
- `GET /sys/user/exportUserInfo`
- `GET /biz/user/export`
- `GET /biz/user/exportUserInfo`

## Guardrails

- All five routes remain behind `AuthMiddleware`.
- The template route returns a CSV template blob.
- Export routes return sanitized CSV blobs without `PASSWORD`, token data, or secrets.
- User-info routes return sanitized plain-text profile blobs.
- Business routes enforce conservative organization data-scope or current-user fallback.
- No Java source, database schema, Composer, `.env`, frontend source, import parser, upload/storage behavior, real `.xlsx` generation, real `.docx` rendering, route-permission middleware, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list all five routes.
- Requests without token should return business `code=401`.
- Service smoke should generate system template, system export, business export, and one-user profile blobs without exposing password fields.

---

# Public File Change Request: Business Dictionary Edit Route

## Request

Register the protected business dictionary edit route in `route/app.php`.

## Reason

The copied Vue business dictionary wrapper posts dictionary form edits to `/biz/dict/edit`. Existing ThinkPHP dictionary read routes already cover `/biz/dict/page`, `/biz/dict/tree`, and `/biz/dict/treeAll`, so this slice opens the matching narrow business edit route without enabling add/delete behavior.

## Applied Change

`api-agent` registers the following protected POST route:

- `POST /biz/dict/edit`

## Guardrails

- The route remains behind `AuthMiddleware`.
- The service edits only active `dev_dict` rows where `CATEGORY = BIZ`.
- The service validates `id`, `dictLabel`, numeric `sortCode`, optional business parent, tenant compatibility, and same-parent duplicate labels.
- The service preserves `CATEGORY`, `DICT_VALUE`, `TENANT_ID`, `CREATE_TIME`, and `CREATE_USER`.
- No `/biz/dict/add`, `/biz/dict/delete`, `/dev/dict` write route, Java source, database schema, Composer, `.env`, frontend source, cache invalidation service, or unrelated business behavior is changed.

## Verification

- `php think route:list` must list `POST /biz/dict/edit`.
- Direct service smoke should create temporary BIZ dictionary rows, edit one row, verify updated label/sort/operator metadata, verify duplicate label blocking, and physically remove only temporary smoke rows.

---

# Public File Change Request: Dev Config BIZ_DEFINE Maintenance Routes

## Request

Register protected dev config maintenance routes in `route/app.php`.

## Reason

The copied Vue "other config" page calls Java-style `/dev/config/add`, `/dev/config/edit`, and `/dev/config/delete` routes. Existing ThinkPHP config routes already cover read-only list/page/detail behavior, so this slice opens only narrow `BIZ_DEFINE` maintenance writes while keeping `SYS_BASE`, provider/system batch edits, and cache mutation deferred.

## Applied Change

`api-agent/test-agent` registers the following protected POST routes:

- `POST /dev/config/add`
- `POST /dev/config/edit`
- `POST /dev/config/delete`

## Guardrails

- All three routes remain behind `AuthMiddleware`.
- Add/edit/delete allow only active `BIZ_DEFINE` records.
- `SYS_BASE` edit/delete is rejected.
- Add/edit validate required `configKey`, `configValue`, and `sortCode`; active duplicate `configKey` values are rejected.
- Sensitive values remain masked in returned rows, and edit preserves the existing stored secret when the frontend submits `******`.
- Delete accepts Java-style array payloads, rejects malformed mixed payloads before any write, and marks rows with `DELETE_FLAG = DELETED`.
- Add/edit/delete return Java-compatible success envelopes with `data = null`.
- No Java source, database schema, Composer, `.env`, frontend source, Redis cache mutation, `editBatch`, provider credentials, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list all three routes.
- Requests without token should return business `code=401`.
- DB smoke should cover add/edit/delete, duplicate rejection, sensitive value preservation, `SYS_BASE` delete rejection, logical delete, and cleanup.
- Authenticated HTTP smoke should cover good and malformed delete payloads.
- Browser smoke should verify the copied `/dev/config` other-config tab can add, edit, delete, refresh the table, and clean temporary rows.

---

# Public File Change Request: Dev Log Category Delete Route

## Request

Register the protected dev log category-clear route in `route/app.php`.

## Reason

Java exposes `/dev/log/delete` for clearing logs by category, and the copied Vue API wrapper already contains `logDelete(data)`. Existing ThinkPHP log routes cover page/detail/chart reads, so this slice opens only the narrow category clear route.

## Applied Change

`api-agent/test-agent` registers the following protected POST route:

- `POST /dev/log/delete`

## Guardrails

- The route remains behind `AuthMiddleware`.
- The request body must contain a non-empty `category` field.
- Empty or missing category returns a business error and cannot clear the table.
- The service physically deletes rows with the target `CATEGORY`.
- ThinkPHP applies `TENANT_ID = current token tenant` when the token has a tenant id. This is intentionally more conservative than Java's global category clear.
- No Java source, database schema, Composer, `.env`, frontend source, soft-delete flags, chart logic, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list `POST /dev/log/delete`.
- Requests without token should return business `code=401`.
- DB smoke should insert temporary target-category, other-category, and other-tenant rows, then verify only the current-tenant target-category row is physically deleted.
- Authenticated HTTP smoke should call `/dev/log/delete` with `{ "category": "..." }`, verify `data = null`, and confirm only the temporary target category row is deleted.

---

# Public File Change Request: Dev Job Metadata Delete Route

## Request

Register the protected dev job metadata delete route in `route/app.php`.

## Reason

The copied Vue scheduled-job page calls `/dev/job/delete` for row and batch deletion. Existing ThinkPHP job routes already cover page/list/detail/action-class reads, so this slice opens only safe metadata delete compatibility while leaving scheduler lifecycle routes deferred.

## Applied Change

`api-agent/test-agent` registers the following protected POST route:

- `POST /dev/job/delete`

## Guardrails

- The route remains behind `AuthMiddleware`.
- Delete accepts Java-style array payloads such as `[{ "id": "..." }]`.
- Empty ids and malformed mixed payloads are rejected before any write.
- Missing ids cause the batch to fail without partially deleting valid rows.
- Rows are logically deleted with `DELETE_FLAG = DELETED` and audit update fields are written.
- Deleted rows are hidden from `/dev/job/page`, `/list`, and `/detail`.
- Java removes running cron entries before deleting. ThinkPHP does not yet run a scheduler, so this slice does not start, stop, remove, or execute scheduled jobs.
- No Java source, database schema, Composer, `.env`, frontend source, scheduler runtime, cron validation, action-class execution, or unrelated module behavior is changed.

## Verification

- `php think route:list` must list `POST /dev/job/delete`.
- Requests without token should return business `code=401`.
- DB smoke should insert temporary jobs, verify malformed batches do not partially delete, logically delete one job, verify page hiding, and clean temporary rows.
- Authenticated HTTP smoke should cover good and malformed delete payloads.
- Browser smoke should verify the copied `/dev/job/index` row delete posts to `/api/dev/job/delete`, refreshes the table, and marks the row as `DELETED`.

---

# Public File Change Request: Gen Config EditBatch Route

## Request

Register the protected generator field-configuration batch-save route in `route/app.php`.

## Reason

The copied Vue generator field configuration component posts the edited table rows to Java-style `/gen/config/editBatch`. Existing ThinkPHP generator routes already cover `/gen/config/list` and `/gen/config/detail`, so this slice opens only the saved metadata batch update that the copied component needs.

## Applied Change

`api-agent/test-agent` registers the following protected POST route:

- `POST /gen/config/editBatch`

## Guardrails

- The route remains behind `AuthMiddleware`.
- The request body must be a non-empty JSON array.
- The service validates the whole batch before writing and rejects deleted or missing `gen_config` rows.
- Only Java `GenConfigEditParam` fields are written.
- Client-supplied `deleteFlag`, `createTime`, `createUser`, `updateTime`, and `updateUser` are ignored.
- Batch updates run in a transaction and return Java-compatible success envelopes with `data = null`.
- No Java source, database schema, Composer, `.env`, frontend source, generator basic-row writes, code preview, ZIP generation, file writing, or code-generation side effects are changed.

## Verification

- `php think route:list` must list `POST /gen/config/editBatch`.
- Requests without token should return business `code=401`.
- DB smoke should insert temporary `gen_config` rows, verify whitelist writes, optional-field nulling, deleted-row rejection, and failed-batch rollback.
- Authenticated HTTP smoke should cover good and malformed mixed `editBatch` payloads.

---

# Public File Change Request: Sale Project Invoicing Complete Route

## Request

Register the protected sale-project invoicing complete route in `route/app.php`.

## Reason

The copied Vue sale-project invoicing page calls Java-style `/biz/saleprojectinvoicing/complete` from the visible row action that marks an invoice application as completed. Existing ThinkPHP billing routes already cover invoicing page/customer/detail reads, so this slice opens only the narrow complete marker.

## Applied Change

`api-agent/test-agent` registers the following protected POST route:

- `POST /biz/saleprojectinvoicing/complete`

## Guardrails

- The route remains behind `AuthMiddleware`.
- The request body must contain a non-empty `id`.
- The service resolves the row through existing sale-project scope and tenant filters.
- The service only updates `biz_sale_project_invoicing.INVOICING_STATE = INVOICING_STATE_COMPLETE` plus audit update fields.
- Cross-tenant or out-of-scope rows are rejected and are not updated.
- The endpoint returns a Java-compatible success envelope with `data = null`.
- No Java source, database schema, Composer, `.env`, frontend source, invoice add/edit/delete, delivery invoice writes, reissue order writes, workflow side effects, inventory stock mutations, finance mutations, settlement, payment, refund, or cost behavior is changed.

## Verification

- `php think route:list` must list `POST /biz/saleprojectinvoicing/complete`.
- Requests without token should return business `code=401`.
- DB smoke should insert temporary project and invoicing rows, complete the current-tenant row, verify idempotent state update, verify cross-tenant rejection, and clean temporary rows.
- Authenticated HTTP smoke should cover cross-tenant rejection and successful complete with `data = null`.

---

# Public File Change Request: Team Project Base Maintenance Routes

## Request

Register the protected team-project base maintenance routes in `route/app.php`.

## Reason

The copied Vue team-project list and detail pages call Java-style `/biz/bizteamproject/add`, `/edit`, and `/delete` when users create a project card, edit the project description, or delete a project. Existing ThinkPHP routes already cover project/member reads and later member/task/comment writes, so this slice opens the narrow project-row maintenance entry points.

## Applied Change

`api-agent/test-agent` registers the following protected POST routes:

- `POST /biz/bizteamproject/add`
- `POST /biz/bizteamproject/edit`
- `POST /biz/bizteamproject/delete`

## Guardrails

- The routes remain behind `AuthMiddleware`.
- Add requires `name`; `description` is optional.
- Add writes `biz_team_project`, creates the current user as `LEADER` in `biz_team_project_user`, and syncs `biz_relation.CATEGORY = TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION`.
- Edit writes only base Java project fields: `name`, `description`, `projectStatus`, and `completionTime`, plus audit fields and `VERSION + 1`.
- Edit and delete require the current user's imported `delProject` permission.
- Delete accepts Java-style array payloads such as `[{ "id": "..." }]` and logically deletes project rows plus active member rows.
- No Java source, database schema, Composer, `.env`, frontend source, notification push, data-change events, Java physical delete behavior, or unrelated task/comment/workflow side effects are changed.

## Verification

- `php think route:list` must list `POST /biz/bizteamproject/add`, `/edit`, and `/delete`.
- Requests without token should return business `code=401`.
- DB smoke should create a temporary project, verify current-user leader membership and permission relation, edit fields with version increment, delete project/member rows logically, and clean temporary rows.
- Authenticated HTTP smoke should cover add, edit, delete, database back-checks, and cleanup through `-TeamProjectHttpSmoke`.
