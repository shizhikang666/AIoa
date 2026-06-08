# Dev Log Compatibility

Date: 2026-06-08

Agent: merge-agent

## Scope

This slice adds read and category-clear compatibility for old Java OA log APIs:

- `/dev/log/page`
- `/dev/log/detail`
- `/dev/log/delete`
- `/dev/log/vis/lineChartData`
- `/dev/log/vis/pieChartData`
- `/dev/log/op/barChartData`
- `/dev/log/op/pieChartData`

All routes are protected by `AuthMiddleware`.

## Java Reference

- `DevLogController`
- `DevLogServiceImpl`

## Behavior

- `page` supports `category`, `searchKey`, and safe mapped sorting.
- `page` intentionally omits large fields, following Java behavior: `paramJson`, `resultJson`, `exeMessage`, and `signData` are returned by detail only.
- Chart endpoints aggregate current-tenant logs for login/logout and operate/exception categories.
- `detail` returns the full historical log row for authorized users.
- `delete` accepts a JSON object with `category`, such as `{ "category": "LOGIN" }`, and physically deletes logs in that category.
- The Java service clears all rows for the category. The ThinkPHP compatibility route adds the current tenant condition when a tenant id is present in the token payload, so one tenant's clear action cannot remove another tenant's logs.
- Empty or missing `category` fails with a business error and cannot clear the table.
- Successful `delete` returns the Java-compatible success envelope with `data = null`.

## Deferred

No dev log Java endpoints remain deferred in this slice.

No Java source, database schema, seed data, Composer files, `.env`, or public config files were changed.
