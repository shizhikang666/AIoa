# Biz Leave Application Compatibility

Date: 2026-06-12

Agent: api-agent / merge-agent

## Scope

This document maps the Java `BizLeaveApplicationController` endpoints used by the copied Vue leave-application pages:

- `GET /biz/bizleaveapplication/page`
- `GET /biz/bizleaveapplication/my/page`
- `GET /biz/bizleaveapplication/detail`
- `POST /biz/bizleaveapplication/edit`
- `POST /biz/bizleaveapplication/delete`

Java source remains read-only at `F:\AI\projects\testJava\OA`.

## Java Behavior

Java `BizLeaveApplicationServiceImpl`:

- joins leave records to the user table and exposes user `name` and `orgId`
- applies login-user data scope for normal `page`
- falls back to current login user when no data scope is available
- restricts `my/page` to the current login user
- filters by organization subtree, applicant name, category, amount, remark, start-time range, and end-time range
- sorts by requested field when present, otherwise by `id` ascending
- exposes `edit` with Java `BizLeaveApplicationEditParam` fields: `id`, `userId`, `processId`, `category`, `amount`, `remark`, `startTime`, and `endTime`
- exposes `delete` with Java `BizLeaveApplicationIdParam` ids
- keeps `add` commented out in the Java controller; leave creation remains workflow-owned

## Added Routes

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| GET | `/biz/bizleaveapplication/page` | `BizLeaveApplicationController::page` | Paged list with data-scope fallback. |
| GET | `/biz/bizleaveapplication/my/page` | `BizLeaveApplicationController::myPage` | Paged list restricted to current user. |
| GET | `/biz/bizleaveapplication/detail` | `BizLeaveApplicationController::detail` | Detail by `id`. |
| POST | `/biz/bizleaveapplication/edit` | `BizLeaveApplicationController::edit` | Updates only Java edit-param fields plus update audit fields. |
| POST | `/biz/bizleaveapplication/delete` | `BizLeaveApplicationController::delete` | Logical delete with full-batch validation before update. |

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

## Write Behavior

- `edit` requires all Java edit fields and writes only `USER_ID`, `PROCESS_ID`, `category`, `AMOUNT`, `REMARK`, `START_TIME`, `END_TIME`, `UPDATE_TIME`, and `UPDATE_USER`.
- `edit` preserves `OBJECT_ID`, `TENANT_ID`, `CREATE_TIME`, `CREATE_USER`, and delete state.
- `delete` accepts `{ id }`, `{ ids }`, `{ idList }`, comma-separated ids, Java-style `[{ id }]`, or nested `{ ids: [{ id }] }` payloads.
- `delete` validates every id before writing so mixed missing-id batches do not partially update rows.
- Non-admin writes are guarded by data-scope organization, current applicant, or creator ownership.
- The slice does not run workflow, annual-leave, payroll, notification, or data-change side effects.

## Deferred

The following remain intentionally deferred:

- `POST /biz/bizleaveapplication/add`
- workflow start, approve, reject, or cancel side effects
- annual-leave/vacation deductions or generation
- payroll-facing leave recalculation
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

Focused DB smoke on 2026-06-12 inserted temporary leave rows and verified:

- `edit` updated only expected fields.
- create audit, tenant, and object metadata were preserved.
- nested `{ ids: [{ id }] }` delete payloads were accepted.
- mixed missing-id delete failed without changing the existing row.
- non-admin out-of-scope edit returned `403`.
- logical delete set `DELETE_FLAG = DELETED` and hid the row from `detail`.
- payroll and vacation table counts stayed unchanged.
- temporary smoke rows were physically cleaned up.
