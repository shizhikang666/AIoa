# Dev Job Compatibility

## Scope

This slice adds authenticated query compatibility plus safe metadata add/edit/delete/action-status compatibility for Java scheduled-job endpoints.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevJobController`
- Service: `DevJobServiceImpl`
- SQL table: `dev_job`
- Frontend API: `snowy-admin-web/src/api/dev/jobApi.js`

## Added Routes

Protected routes:

- `GET /dev/job/page`
- `GET /dev/job/list`
- `GET /dev/job/detail`
- `GET /dev/job/getActionClass`
- `POST /dev/job/add`
- `POST /dev/job/edit`
- `POST /dev/job/stopJob`
- `POST /dev/job/runJob`
- `POST /dev/job/runJobNow`
- `POST /dev/job/delete`

## Response Shape

Job rows return:

- `id`
- `name`
- `code`
- `category`
- `actionClass`
- `cronExpression`
- `jobStatus`
- `sortCode`
- `extJson`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

Page responses return:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

## Supported Filters

- `id`
- `category`
- `jobStatus`
- `searchKey`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

Supported sort fields are:

- `id`
- `name`
- `code`
- `category`
- `actionClass`
- `cronExpression`
- `jobStatus`
- `sortCode`
- `createTime`
- `updateTime`

## Endpoint Notes

- `page` and `list` follow Java filtering behavior for `category`, `jobStatus`, and `searchKey`.
- Default sort is `SORT_CODE asc, ID asc`, matching Java's default `sortCode` ordering.
- Java `getActionClass` scans Spring `CommonTimerTaskRunner` beans. ThinkPHP cannot execute Java beans, so this slice returns distinct stored `ACTION_CLASS` values from active `dev_job` rows as read-only compatibility data.
- `add` and `edit` are narrow metadata maintenance endpoints. They require Java-style fields, validate `FRM`/`BIZ` category, validate Java-style cron text shape, require `actionClass` to come from the current ThinkPHP compatibility action-class list, reject duplicate active `ACTION_CLASS + CRON_EXPRESSION`, create new jobs as `STOPPED`, and preserve `CODE` plus `JOB_STATUS` on edit.
- `edit` rejects active rows currently marked `RUNNING`.
- `delete` accepts Java-style array payloads such as `[{ "id": "..." }]`, rejects malformed mixed payloads before any write, marks rows with `DELETE_FLAG = DELETED`, and returns `data = null`.
- `stopJob` accepts `{ id }`, rejects jobs already marked `STOPPED`, and sets `JOB_STATUS = STOPPED`.
- `runJob` accepts `{ id }`, rejects jobs already marked `RUNNING`, and sets `JOB_STATUS = RUNNING`.
- `runJobNow` accepts `{ id }` and sets `JOB_STATUS = RUNNING` when the row is currently stopped.
- Java starts, stops, removes, and executes cron entries through Hutool/Spring runtime hooks. ThinkPHP does not yet run a scheduler, so these compatibility routes only change status metadata and do not register, remove, schedule, or execute jobs.

## Deliberate Exclusions

- Real scheduler stop, run, and run-now lifecycle remains deferred beyond database status updates.
- No scheduler is started or stopped.
- No job class is executed.
- No database schema or Java source files are changed.

## Later Work

Job execution needs a dedicated scheduler design for ThinkPHP, permission checks, audit logging, full cron validation, class allow-listing, and a safe migration strategy for Java task classes.

## 2026-06-15 HTTP Smoke Coverage

`scripts/dev-read-http-smoke.ps1` now covers authenticated job metadata reads for:

- `GET /dev/job/page`
- `GET /dev/job/list`
- `GET /dev/job/detail` when a visible job sample exists
- `GET /dev/job/getActionClass`

The smoke asserts Java-style paging keys and action-class list shape. It intentionally does not call delete, add, edit, stop, run, run-now, scheduler lifecycle, job class execution, or data-change behavior.

## 2026-06-16 Write Smoke Coverage

`scripts/dev-job-write-http-smoke.ps1` now covers authenticated metadata writes for:

- no-token rejection on `POST /dev/job/add`;
- add and detail readback for a temporary job row;
- generated `CODE` length and default `JOB_STATUS = STOPPED`;
- duplicate active `ACTION_CLASS + CRON_EXPRESSION` rejection;
- invalid category rejection;
- invalid cron-expression rejection;
- unsupported action-class rejection;
- edit field updates while preserving `CODE` and `JOB_STATUS`;
- running-job edit rejection;
- cleanup of only temporary `CODEX_JOB_` rows.

The write smoke intentionally does not call `stopJob`, `runJob`, `runJobNow`, scheduler registration/removal, job class execution, provider calls, notifications, cache invalidation, data-change events, Java source changes, or schema changes.

## 2026-06-18 Action Status Smoke Coverage

`scripts/dev-job-write-http-smoke.ps1` now also covers:

- no-token rejection on `POST /dev/job/runJob`;
- missing-id rejection on `runJob`;
- already-running rejection on `runJob`;
- successful `stopJob` status transition to `STOPPED`;
- already-stopped rejection on `stopJob`;
- successful `runJob` status transition to `RUNNING`;
- successful `runJobNow` while already running;
- successful `runJobNow` while stopped, including the Java-compatible transition back to `RUNNING`.

The action-status smoke intentionally still does not register/remove scheduler jobs, execute PHP or Java task classes, call external providers, mutate cache, or start a scheduler runtime.
