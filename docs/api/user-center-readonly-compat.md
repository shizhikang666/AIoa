# User Center Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Expose the remaining read-only user-center endpoints used by the old Vue frontend without enabling profile updates, workbench edits, message mark-read writes, or process config edits.

## Java Inputs

- `snowy-admin-web/src/api/sys/userCenterApi.js`
- `snowy-plugin-sys/.../user/controller/SysUserCenterController.java`
- `snowy-plugin-sys/.../user/service/impl/SysUserServiceImpl.java`
- `snowy-plugin-dev/.../message/service/impl/DevMessageServiceImpl.java`
- `oa2026.sql`

## Added Endpoints

- `GET /sys/userCenter/loginWorkbench`
- `GET /sys/userCenter/loginUnreadMessagePage`
- `GET /sys/userCenter/loginUnreadMessageDetail`
- `POST /sys/userCenter/process/config`

All routes are protected by `AuthMiddleware`.

## Data Sources

- Workbench: `sys_relation` with category `SYS_USER_WORKBENCH_DATA`
- Default workbench: `dev_config` key `SNOWY_SYS_DEFAULT_WORKBENCH_DATA`
- Process config: `sys_user_process_config`
- Messages: `dev_message`
- Message recipient/read relation: `dev_relation` with category `MSG_TO_USER`

## 2026-06-06 Message Mark-Read Compatibility

Agent: user-agent / frontend-agent

The protected message detail endpoint now matches Java `DevMessageService.detail()` read-state behavior:

| Method | Path | Scope |
| --- | --- | --- |
| GET | `/sys/userCenter/loginUnreadMessageDetail` | Current token user's receiver relation only |

Compatibility notes:

- The endpoint still verifies the current user owns the requested `MSG_TO_USER` relation before returning detail.
- Opening message detail updates only that relation's `dev_relation.EXT_JSON` to include `"read": true`.
- `dev_message` rows and other recipients' relations are not modified.
- `dev_relation` has no audit columns in the current SQL dump, so this slice does not invent update audit fields.

## Deferred

- `POST /sys/userCenter/updateUserInfo`
- `POST /sys/userCenter/updateUserWorkbench`
- `POST /sys/userCenter/updatePassword`
- `POST /sys/userCenter/updateAvatar`
- `POST /sys/userCenter/updateSignature`
- `POST /sys/userCenter/process/config/edit`
- message send/delete, all-mark-read, WebPush, and full realtime push

These endpoints require write validation, audit behavior, and conflict checks before implementation.

## 2026-06-06 Self-Service Write Compatibility

Agent: user-agent / frontend-agent

The following protected user-center write endpoints are now implemented for the copied Vue personal center:

| Method | Path | Scope |
| --- | --- | --- |
| POST | `/sys/userCenter/updatePassword` | Current user's password only |
| POST | `/sys/userCenter/updateAvatar` | Current user's avatar only |
| POST | `/sys/userCenter/updateSignature` | Current user's signature only |
| POST | `/sys/userCenter/updateUserInfo` | Current user's profile only; submitted `id` must match the token user |
| POST | `/sys/userCenter/updateUserWorkbench` | Current user's workbench relation only |
| POST | `/sys/userCenter/process/config/edit` | Current user's workflow process config only |

Compatibility notes:

- Password update reuses the existing transport decoder and stores a Java-compatible SM3 hash.
- Avatar upload stores a bounded base64 data URI on `sys_user.AVATAR`; full file-provider storage and cleanup remain deferred.
- Signature updates store a data URI on `sys_user.SIGNATURE`.
- Workbench writes upsert `sys_relation` with category `SYS_USER_WORKBENCH_DATA`.
- Process config writes update or create the current user's `sys_user_process_config` row.

Still deferred:

- User management add/edit/delete, enable/disable, reset-password-by-admin, grants, import/export.
- Java SM4 encrypted-field migration for phone/identity fields.
- Full file storage/provider integration for avatar uploads.
- Message send/delete, all-mark-read, WebPush, and full realtime push.

## 2026-06-11 Public Password-Recovery Captcha

Agent: main-agent

The public password-recovery captcha endpoint now matches the old frontend path:

| Method | Path | Scope |
| --- | --- | --- |
| GET | `/sys/userCenter/getPicCaptcha` | Public captcha generation for the find-password flow |

Compatibility notes:

- The route is intentionally registered outside `AuthMiddleware`, matching Java's anonymous access list.
- The response shape reuses the existing auth captcha payload: `validCodeBase64` and `validCodeReqNo`.
- Verification-code sending and password reset remain deferred because they involve SMS/email provider side effects and password writes.

## 2026-06-15 Public Password-Recovery Deferred Wrapper Compatibility

Agent: merge-agent / user-agent

The remaining copied password-recovery wrapper paths now return controlled deferred API responses instead of 404:

| Method | Path | Current Behavior |
| --- | --- | --- |
| GET | `/sys/userCenter/findPasswordGetPhoneValidCode` | Returns `code = 400`; no SMS is sent |
| GET | `/sys/userCenter/findPasswordGetEmailValidCode` | Returns `code = 400`; no email is sent |
| POST | `/sys/userCenter/findPasswordByPhone` | Returns `code = 400`; no password is changed |
| POST | `/sys/userCenter/findPasswordByEmail` | Returns `code = 400`; no password is changed |

These routes remain public like Java's anonymous password-recovery endpoints, but all provider sends and password reset mutations are still deferred until a dedicated security and provider plan exists.
