# Biz User Vacation Read-Only Compatibility

Date: 2026-06-04

Agent: workflow-agent / api-agent

## Scope

This slice adds read-only ThinkPHP compatibility for the annual-leave balance endpoint used by copied leave-process pages.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Route

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/bizuservacation/detail` | Read the annual-leave balance for the requested user or current login user. |

The route is protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizUserVacationServiceImpl.detail` defaults to `StpUtil.getLoginId()` when `userId` is omitted.
- It defaults to category `annualLeave` when `category` is omitted.
- It filters records to the current year using `CREATE_TIME`.
- When no row exists, Java returns a new annual-leave object with zero amount. This ThinkPHP endpoint returns a zero-balance object with `amount` and `usedAmount` set to `0` for copied frontend compatibility.

## Deferred

The following remain intentionally deferred:

- `/biz/bizuservacation/page`
- `/biz/bizuservacation/add`
- `/biz/bizuservacation/edit`
- `/biz/bizuservacation/delete`
- vacation generation/reduction writes
- leave approval balance deductions
- workflow write side effects
- Java source changes
- database schema changes
