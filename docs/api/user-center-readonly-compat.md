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

## Read-Only Differences From Java

The Java message detail endpoint marks a message as read as part of the detail call. This ThinkPHP compatibility phase intentionally does not update `dev_relation.EXT_JSON`, because the current stage is read-only. A later write-capable user/message phase can add explicit mark-read behavior with validation and audit coverage.

## Deferred

- `POST /sys/userCenter/updateUserInfo`
- `POST /sys/userCenter/updateUserWorkbench`
- `POST /sys/userCenter/updatePassword`
- `POST /sys/userCenter/updateAvatar`
- `POST /sys/userCenter/updateSignature`
- `POST /sys/userCenter/process/config/edit`
- message mark-read writes

These endpoints require write validation, audit behavior, and conflict checks before implementation.
