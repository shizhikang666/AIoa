# Frontend Read-Only Selector Compatibility

Date: 2026-05-29

Agent: merge-agent

## Goal

Add the next small compatibility slice for the existing Vue frontend API modules without implementing write operations or broad business behavior.

## Java Frontend Inputs

Read-only files checked under `F:\AI\projects\testJava\OA\snowy-admin-web\src\api`:

- `sys/userApi.js`
- `sys/orgApi.js`
- `sys/positionApi.js`
- `sys/userCenterApi.js`

## Added Endpoint Coverage

- `GET /sys/user/orgTreeSelector`
- `GET /sys/user/positionSelector`
- `GET /sys/user/roleSelector`
- `GET /sys/user/userSelector`
- `GET /sys/user/list/detail`
- `GET /sys/user/ownRole`
- `GET /sys/user/ownResource`
- `GET /sys/user/ownPermission`
- `GET /sys/org/page`
- `GET /sys/org/list`
- `GET /sys/org/userSelector`
- `GET /sys/position/list`
- `GET /sys/position/orgTreeSelector`
- `POST /sys/userCenter/getOrgListByIdList`
- `POST /sys/userCenter/getRoleListByIdList`
- `GET /sys/userCenter/getAvatarById`

## Safety Notes

- User selector, page, detail, and list-by-id responses remove the `PASSWORD` field.
- User grant echo routes are read-only and only return existing `sys_relation` records.
- The new routes are read-only and do not add user, organization, position, role, permission, import, export, upload, or workflow mutation behavior.
- `sys/userCenter` additions stay behind `AuthMiddleware`.

## Deferred

- User, organization, position, and role write endpoints.
- Grant role/resource/permission write endpoints.
- Import, export, upload, avatar update, and signature update.
- User-center workbench, message, process config, and password recovery flows.
- Workflow approval/reject/cancel/start endpoints.

## 2026-06-15 Selector Pagination Shape Compatibility

Agent: merge-agent / user-agent

The copied `XnPageSelect` and `XnUserSelector` components expect Java-style paged selector payloads:

```json
{
  "records": [],
  "total": 0,
  "current": 1,
  "size": 20
}
```

The following existing routes now return that paged shape while preserving each record's existing selector fields such as `id`, `value`, `label`, and `title`:

- `GET /sys/user/positionSelector`
- `GET /biz/user/positionSelector`
- `GET /sys/position/positionSelector`
- `GET /biz/position/positionSelector`
- `GET /sys/user/userSelector`
- `GET /biz/user/userSelector`
- `GET /sys/org/userSelector`
- `GET /biz/org/userSelector`

## 2026-06-15 Role Selector Pagination Shape Compatibility

The copied `roleSelectorPlus` component expects the same Java-style paged payload:

```json
{
  "records": [],
  "total": 0,
  "current": 1,
  "size": 20
}
```

The following existing routes now return that paged shape:

- `GET /sys/user/roleSelector`
- `GET /biz/user/roleSelector`
- `GET /sys/role/roleSelector`

Role selector records keep the existing fields and add selector aliases where needed:

- `id`
- `value`
- `label`
- `title`
- `name`
- `code`
- `category`
- `orgId`
- `sortCode`

Compatibility notes:

- The endpoints remain read-only.
- The selector services now accept copied frontend `size` pagination in addition to existing `limit` and `pageSize`.
- User selector records still remove password data and keep the richer display fields used by table selectors, including `name`, `account`, `avatar`, `orgId`, `orgName`, `positionId`, and `positionName`.
- Position selector records still include full position row aliases such as `id`, `name`, `orgId`, `category`, and `sortCode`, plus `value`, `label`, and `title`.
- Role selector records still include full role row aliases such as `id`, `name`, `code`, `orgId`, `category`, and `sortCode`, plus `value`, `label`, and `title`.
- Current ThinkPHP business selector aliases reuse the system controllers; Java business selectors apply additional data scope and child-organization behavior that remains a future hardening task.

## 2026-06-15 Role Selector HTTP Smoke

`scripts/role-selector-http-smoke.ps1` verifies the backend payloads used by copied `roleSelectorPlus` without requiring browser automation dependencies.

Covered read-only checks:

- short-lived local auth token from ignored `.env` account, without printing credentials or token;
- `GET /sys/user/ownRole`;
- `GET /sys/user/roleSelector?current=1&size=2`;
- `GET /biz/user/roleSelector?current=1&size=2&category=ORG`;
- `GET /sys/role/roleSelector?current=1&size=2`;
- required paged keys: `records`, `total`, `current`, `size`, `pages`;
- required role record aliases: `id`, `value`, `label`, `title`, `name`, `code`, `category`.

This is not a replacement for a true browser click-through smoke of the `/sys/user` and `/biz/user` grant-role dialogs. It is the project-local fallback while no Playwright/Puppeteer dependency is available.

## 2026-06-15 User Display HTTP Smoke

`scripts/user-display-http-smoke.ps1` verifies the authenticated read-only payloads used by copied system and business user pages.

Covered checks:

- `GET /sys/user/page`
- `GET /biz/user/page`
- `GET /sys/user/detail`
- `GET /sys/user/list/detail`
- `GET /sys/user/userSelector`
- `GET /biz/user/userSelector`

The script verifies Java-style paging keys, selector aliases, no `PASSWORD` leakage, and frontend-visible `orgName`, `positionName`, and `genderName` fields. `scripts/project-preflight.ps1` now runs this smoke by default unless `-SkipUserDisplay` is passed.

## 2026-06-15 Directory Alias HTTP Smoke

`scripts/directory-alias-http-smoke.ps1` verifies business directory aliases used by copied organization, position, user, and dictionary pages:

- `GET /biz/org/page`
- `GET /biz/org/tree`
- `GET /biz/org/orgTreeSelector`
- `GET /biz/org/userSelector`
- `GET /biz/position/page`
- `GET /biz/position/positionSelector`
- `GET /biz/dict/page`
- `GET /biz/dict/tree`
- `GET /biz/dict/treeAll`

The smoke verifies Java-style paged shapes, tree/selector aliases, and `size` pagination compatibility. `scripts/project-preflight.ps1` runs this smoke by default unless `-SkipDirectoryAlias` is passed.
