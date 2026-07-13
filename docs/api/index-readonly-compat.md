# Index Compatibility

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
- `POST /sys/index/schedule/add`
- `POST /sys/index/schedule/deleteSchedule`
- `POST /sys/index/message/allMessageMarkRead`

All routes are protected by `AuthMiddleware`.

## Data Sources

- Schedule list: `sys_relation` category `SYS_USER_SCHEDULE_DATA`
- Message list/page/detail: `dev_message` and `dev_relation` category `MSG_TO_USER`
- Visit logs: `dev_log` categories `LOGIN`, `LOGOUT`
- Operation logs: `dev_log` categories `OPERATE`, `EXCEPTION`

## 2026-06-06 Schedule Self-Service Compatibility

Agent: user-agent / frontend-agent

Homepage schedule writes are now implemented for the current token user:

| Method | Path | Scope |
| --- | --- | --- |
| POST | `/sys/index/schedule/add` | Current token user's schedule rows |
| POST | `/sys/index/schedule/deleteSchedule` | Current token user's schedule rows |

Compatibility notes:

- Add requires `scheduleDate`, `scheduleTime`, and `scheduleContent`.
- Add stores Java-compatible relation data in `sys_relation` with `CATEGORY = SYS_USER_SCHEDULE_DATA`.
- `OBJECT_ID` is the current token user id and `TARGET_ID` is the schedule date.
- Delete accepts Java-style array bodies and common `idList`, `ids`, or single `id` payloads.
- Delete is constrained to the current token user's schedule rows.
- `sys_relation` has no audit columns in the current SQL dump, so this slice does not invent update audit fields.

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

- message send/delete and full message management
- full realtime/WebPush implementation

These deferred areas require separate validation, audit, and realtime behavior before implementation.

## 2026-06-15 Authenticated Read Smoke

`scripts/auth-index-read-http-smoke.ps1` verifies homepage schedule list, message list/page, visit-log list, and operation-log list.

The smoke deliberately skips schedule add/delete, message detail mark-read, and all-message mark-read.
