# Biz User Vacation Compatibility

Date: 2026-06-05
Updated: 2026-06-22

Agent: workflow-agent / api-agent / test-agent

## Scope

This document tracks ThinkPHP compatibility for annual-leave balance reads used by copied leave-process pages and narrow manual maintenance for `biz_user_vacation` rows. Approved annual-leave workflow deductions are handled by `WorkflowRuntimeService`; direct vacation CRUD remains manual maintenance.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Route

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/bizuservacation/page` | Page existing vacation-balance rows. |
| GET | `/biz/bizuservacation/detail` | Read the annual-leave balance for the requested user or current login user. |
| POST | `/biz/bizuservacation/add` | Manually create one vacation-balance row. |
| POST | `/biz/bizuservacation/edit` | Manually update one vacation-balance row. |
| POST | `/biz/bizuservacation/delete` | Logically delete vacation-balance rows. |

The route is protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizUserVacationServiceImpl.detail` defaults to `StpUtil.getLoginId()` when `userId` is omitted.
- It defaults to category `annualLeave` when `category` is omitted.
- It filters records to the current year using `CREATE_TIME`.
- When no row exists, Java returns a new annual-leave object with zero amount. This ThinkPHP endpoint returns a zero-balance object with `amount` and `usedAmount` set to `0` for copied frontend compatibility.
- Java `BizUserVacationServiceImpl.page` exposes read-only pagination behavior; the copied frontend wrapper also calls `/biz/bizuservacation/page`.
- The ThinkPHP page endpoint returns `records`, `total`, `page`, `current`, `limit`, `size`, and `pages`.
- Java `BizUserVacationController` currently exposes only `detail`, but the Java service/generated params and copied frontend API wrapper contain add/edit/delete shapes. ThinkPHP implements these as narrow manual maintenance endpoints; workflow-owned approved `annualLeave` deduction is handled by `WorkflowRuntimeService`, not these CRUD routes.

## Write Behavior

`POST /biz/bizuservacation/add` requires:

- `userId`
- `amount`
- `usedAmount`
- `category`

`POST /biz/bizuservacation/edit` requires the same fields plus `id`.

Write safeguards:

- reject missing required fields and over-length `id`/`userId`/`category` before database writes;
- reject negative amounts and `usedAmount > amount`;
- require the target user to exist in the current token tenant when tenant data is present;
- reject an active duplicate row for the same user, category, tenant, and current year;
- use transactions for add/edit/delete;
- increment `VERSION` on edit and delete;
- validate every requested id before delete, then set `DELETE_FLAG = DELETED`.

The manual CRUD implementation deliberately does not call workflow, payroll, provider, scheduler, notification, or data-change behavior. Approved `annualLeave` workflow deduction is covered separately by the workflow task transition service. Direct leave-application edit/delete now performs current-year `annualLeave` `USED_AMOUNT` adjustments in `BizLeaveApplicationService`.

## Deferred

The following real behavior remains intentionally deferred:

- vacation generation writes
- workflow copy side effects outside approved `annualLeave` deduction
- payroll-facing recalculation
- notification and data-change events
- Java source changes
- database schema changes

## 2026-06-15 HTTP Smoke Coverage

`scripts/hr-read-http-smoke.ps1` now covers authenticated vacation-balance reads for:

- `GET /biz/bizuservacation/page`
- `GET /biz/bizuservacation/detail`

The smoke asserts Java-style paging keys for page reads and verifies that detail returns the current user's annual-leave object or the compatible zero-balance fallback. It intentionally does not call vacation generation, add, edit, delete, workflow deduction, or payroll side effects.

## 2026-06-16 Write Smoke Coverage

`scripts/biz-user-vacation-write-http-smoke.ps1` covers authenticated manual maintenance for:

- no-token rejection on `POST /biz/bizuservacation/add`;
- add and detail readback for a temporary category row;
- duplicate current-year row rejection;
- edit with `VERSION` increment;
- invalid edit rejection when `usedAmount > amount`;
- mixed missing-id delete rejection before partial delete;
- logical delete hiding from page reads;
- unchanged `biz_leave_application` and `biz_payroll` row counts;
- cleanup of only the temporary smoke category rows.

## 2026-06-22 Workflow Deduction Coverage

`scripts/workflow-task-transition-http-smoke.ps1` covers approved `annualLeave` workflow deduction by creating a temporary current-year balance row, approving an annual-leave process, asserting `USED_AMOUNT` and `VERSION` increment, and asserting insufficient-balance rollback leaves workflow, vacation, and leave-application rows unchanged.

`scripts/workflow-process-cancel-edit-http-smoke.ps1` covers the adjacent process paths: cancel before approval leaves the vacation balance unchanged, and editable annual-leave approval deducts the edited `amount` after `POST /biz/process/leave/edit`.

## 2026-06-22 Direct Leave Adjustment Coverage

`scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1` covers direct `POST /biz/bizleaveapplication/edit` and `/delete` annual-leave balance adjustment. It verifies amount-delta deduction, category-change restoration, insufficient-balance rollback, delete restoration, `VERSION` increments, and cleanup of temporary leave/vacation rows.
