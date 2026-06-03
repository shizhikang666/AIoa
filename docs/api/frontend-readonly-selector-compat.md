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
