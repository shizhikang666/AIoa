# Dev Log Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds read-only compatibility for old Java OA log APIs:

- `/dev/log/page`
- `/dev/log/detail`
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

## Deferred

The following Java endpoint remains intentionally deferred:

- `/dev/log/delete`

No Java source, database schema, seed data, Composer files, `.env`, or public config files were changed.
