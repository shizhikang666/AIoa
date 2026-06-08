# Dev Job Compatibility

## Scope

This slice adds authenticated query compatibility plus safe metadata delete compatibility for Java scheduled-job endpoints.

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
- `delete` accepts Java-style array payloads such as `[{ "id": "..." }]`, rejects malformed mixed payloads before any write, marks rows with `DELETE_FLAG = DELETED`, and returns `data = null`.
- Java removes running cron entries through `CronUtil.remove` before deleting metadata. ThinkPHP does not yet run a scheduler, so this compatibility route only changes metadata and does not start, stop, or execute jobs.

## Deliberate Exclusions

- No `/dev/job/add` route is implemented.
- No `/dev/job/edit` route is implemented.
- No `/dev/job/stopJob` route is implemented.
- No `/dev/job/runJob` route is implemented.
- No `/dev/job/runJobNow` route is implemented.
- No scheduler is started or stopped.
- No job class is executed.
- No database schema or Java source files are changed.

## Later Work

Job mutation and execution need a dedicated scheduler design for ThinkPHP, permission checks, audit logging, cron validation, class allow-listing, and a safe migration strategy for Java task classes.
