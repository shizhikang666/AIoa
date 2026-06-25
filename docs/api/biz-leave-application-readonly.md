# Biz Leave Application Compatibility

Date: 2026-06-12
Updated: 2026-06-22

Agent: api-agent / merge-agent

## Scope

This document maps the Java `BizLeaveApplicationController` endpoints used by the copied Vue leave-application pages:

- `GET /biz/bizleaveapplication/page`
- `GET /biz/bizleaveapplication/my/page`
- `GET /biz/bizleaveapplication/detail`
- `POST /biz/bizleaveapplication/add`
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
- keeps `add` commented out in the Java controller; leave creation remains workflow-owned. ThinkPHP workflow approval now creates `Process_ask_leave` records for approved leave processes.

## Added Routes

| Method | Path | Controller | Notes |
| --- | --- | --- | --- |
| GET | `/biz/bizleaveapplication/page` | `BizLeaveApplicationController::page` | Paged list with data-scope fallback. |
| GET | `/biz/bizleaveapplication/my/page` | `BizLeaveApplicationController::myPage` | Paged list restricted to current user. |
| GET | `/biz/bizleaveapplication/detail` | `BizLeaveApplicationController::detail` | Detail by `id`. |
| POST | `/biz/bizleaveapplication/add` | `BizLeaveApplicationController::add` | Controlled deferred wrapper; direct leave creation remains workflow-owned. |
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
- `edit` adjusts current-year `biz_user_vacation.USED_AMOUNT` when the old or new row category is `annualLeave`.
- `delete` accepts `{ id }`, `{ ids }`, `{ idList }`, comma-separated ids, Java-style `[{ id }]`, or nested `{ ids: [{ id }] }` payloads.
- `delete` validates every id before writing so mixed missing-id batches do not partially update rows.
- `delete` restores current-year `annualLeave` amounts before logically deleting rows.
- Non-admin writes are guarded by data-scope organization, current applicant, or creator ownership.
- Direct `edit` and `delete` do not run workflow, payroll, notification, or data-change side effects.

## Controlled Deferred Write

`POST /biz/bizleaveapplication/add` returns a controlled `code = 400` deferred response. It does not directly create leave records, start workflow, deduct annual leave, automatically rewrite existing payroll rows, emit notifications, change database schema, or modify Java source.

## Deferred

The following remain intentionally deferred:

- real `POST /biz/bizleaveapplication/add` behavior
- copy-user generation outside active leave-start runtime
- annual-leave/vacation generation
- automatic existing-payroll row recalculation
- notification and data-change events
- frontend component changes
- database schema changes

## 2026-06-22 Workflow Side-Effect Coverage

`POST /biz/process/leave/start` now creates a runtime `Process_ask_leave` instance and first approval task. `POST /biz/process/leave/edit` can update `endTime`, `amount`, and `remark` once when `isEdit = true`, before approval creates any leave row. `POST /biz/process/cancel` can cancel an unapproved active leave process without creating a leave row. Approved `POST /biz/task/approve` creates or updates the workflow-owned `biz_leave_application` row from historic process variables; rejected `POST /biz/task/reject` completes workflow history without creating a leave row. Approved `annualLeave` processes also deduct `biz_user_vacation.USED_AMOUNT` in the same transaction, while insufficient balance rolls back the approval, leave row, and vacation update. Approved `leaveOfAbsence` rows are consumed later by explicit `/biz/bizpayroll/generate/add` payroll generation. When a leave process is edited before approval, the edited amount/end time/remark are used for the final leave row and annual-leave deduction.

## 2026-06-22 Direct Annual-Leave Adjustment Coverage

Direct `POST /biz/bizleaveapplication/edit` now adjusts the locked current-year annual-leave balance by the difference between the existing row and submitted row. Direct `POST /biz/bizleaveapplication/delete` restores annual-leave amounts before logical delete. Missing balances, insufficient remaining days, and restoration underflow return `400` and roll back the whole transaction.

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

## 2026-06-15 HTTP Smoke Coverage

`scripts/hr-read-http-smoke.ps1` now covers authenticated leave-application reads for:

- `GET /biz/bizleaveapplication/page`
- `GET /biz/bizleaveapplication/my/page`
- `GET /biz/bizleaveapplication/detail` when the visible page has a sample row

The smoke asserts Java-style paging keys and frontend-visible applicant, organization, process, category, amount, time, object, and tenant fields. It intentionally does not call edit, delete, workflow transition, annual-leave deduction, automatic existing-payroll row recalculation, notification, or data-change behavior.

## 2026-06-22 Vacation Adjustment HTTP Smoke

`scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1` covers direct edit/delete annual-leave balance deltas, category restoration, insufficient-balance rollback, delete restoration, and temporary row cleanup.
