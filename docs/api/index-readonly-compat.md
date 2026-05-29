# Index Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Expose the read-only `/sys/index/*` endpoints used by the old Vue homepage and message panel while deferring schedule writes, message mark-read writes, and SSE.

## Java Inputs

- `snowy-admin-web/src/api/sys/indexApi.js`
- `snowy-plugin-sys/.../index/controller/SysIndexController.java`
- `snowy-plugin-sys/.../index/service/impl/SysIndexServiceImpl.java`
- `snowy-plugin-dev/.../message/service/impl/DevMessageServiceImpl.java`
- `snowy-plugin-dev/.../log/provider/DevLogApiProvider.java`
- `oa2026.sql`

## Added Endpoints

- `GET /sys/index/schedule/list`
- `GET /sys/index/message/list`
- `GET /sys/index/message/page`
- `GET /sys/index/message/detail`
- `GET /sys/index/visLog/list`
- `GET /sys/index/opLog/list`

All routes are protected by `AuthMiddleware`.

## Data Sources

- Schedule list: `sys_relation` category `SYS_USER_SCHEDULE_DATA`
- Message list/page/detail: `dev_message` and `dev_relation` category `MSG_TO_USER`
- Visit logs: `dev_log` categories `LOGIN`, `LOGOUT`
- Operation logs: `dev_log` categories `OPERATE`, `EXCEPTION`

## Read-Only Differences From Java

The Java message detail and all-message-mark-read flows can update read status. This ThinkPHP phase does not update `dev_relation.EXT_JSON`, so message detail remains read-only.

## Deferred

- `POST /sys/index/schedule/add`
- `POST /sys/index/schedule/deleteSchedule`
- `POST /sys/index/message/allMessageMarkRead`
- `GET /dev/message/createSseConnect`

These endpoints require mutation/SSE handling, validation, and audit behavior before implementation.
