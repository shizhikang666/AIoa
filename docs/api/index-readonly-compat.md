# Index Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Expose `/sys/index/*` endpoints used by the old Vue homepage and message panel, with read-only schedule/log queries and scoped current-user message read-state updates.

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

## 2026-06-06 Message Read-State Compatibility

Agent: user-agent / frontend-agent

Message read-state compatibility is now partially write-capable:

| Method | Path | Scope |
| --- | --- | --- |
| GET | `/sys/index/message/detail` | Current token user's receiver relation for the opened message |
| POST | `/sys/index/message/allMessageMarkRead` | All current token user's receiver relations |

Compatibility notes:

- Message detail reuses the user-center message detail service and marks only the current user's `MSG_TO_USER` relation as read.
- All-mark-read updates only current-token-user `dev_relation` rows where `CATEGORY = MSG_TO_USER`.
- Existing valid JSON keys in `EXT_JSON` are preserved while `read` is set to `true`.
- `dev_message` rows and other users' receiver relations are not modified.

## Deferred

- `POST /sys/index/schedule/add`
- `POST /sys/index/schedule/deleteSchedule`
- message send/delete and full message management
- full realtime/WebPush implementation

These endpoints require separate validation, audit, and realtime behavior before implementation.
