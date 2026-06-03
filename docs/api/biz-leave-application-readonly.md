# Biz Leave Application Read-Only Compatibility

Date: 2026-06-03

Agent: api-agent

## Scope

This slice maps the read-only endpoints from Java `BizLeaveApplicationController` used by the copied Vue leave-application pages:

- `GET /biz/bizleaveapplication/page`
- `GET /biz/bizleaveapplication/my/page`
- `GET /biz/bizleaveapplication/detail`

Java source remains read-only at `F:\AI\projects\testJava\OA`.

## Java Behavior

Java `BizLeaveApplicationServiceImpl`:

- joins leave records to the user table and exposes user `name` and `orgId`
- applies login-user data scope for normal `page`
- falls back to current login user when no data scope is available
- restricts `my/page` to the current login user
- filters by organization subtree, applicant name, category, amount, remark, start-time range, and end-time range
- sorts by requested field when present, otherwise by `id` ascending

## Added Routes

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| GET | `/biz/bizleaveapplication/page` | `BizLeaveApplicationController::page` | Paged list with data-scope fallback. |
| GET | `/biz/bizleaveapplication/my/page` | `BizLeaveApplicationController::myPage` | Paged list restricted to current user. |
| GET | `/biz/bizleaveapplication/detail` | `BizLeaveApplicationController::detail` | Detail by `id`. |

All routes are protected by `AuthMiddleware`.

## Response Shape

Rows include:

- `id`
- `userId`
- `name`
- `orgId`
- `orgName`
- `processId`
- `category`
- `amount`
- `remark`
- `startTime`
- `endTime`
- `objectId`
- `createTime`
- `createUserName`
- `updateTime`
- `updateUserName`
- `tenantId`

## Deferred

The following remain intentionally deferred:

- `POST /biz/bizleaveapplication/add`
- `POST /biz/bizleaveapplication/edit`
- `POST /biz/bizleaveapplication/delete`
- workflow start, approve, reject, or cancel side effects
- frontend component changes
- database schema changes

## Test Commands

```powershell
php -l app\controller\biz\BizLeaveApplicationController.php
php -l app\service\biz\BizLeaveApplicationService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```
