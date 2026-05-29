# Frontend Adaptation Map

Frontend source, read-only: `F:\AI\projects\testJava\OA\snowy-admin-web`

## Project Shape

- Vue 3
- Vite
- Ant Design Vue
- Axios request wrapper
- Pinia and local storage utility usage
- `package.json` scripts include `dev`, `serve`, `build`, `preview`, and `prod`

## Key Files

- `src/config/index.js`
- `src/utils/request.js`
- `src/api/sys/userCenterApi.js`
- `src/utils/permission/index.js`
- `src/layout/index.vue`
- `src/utils/routerUtil.js`
- `src/components/XnUpload/index.vue`

## Token Contract

Current frontend behavior:

- token is read from local storage key `TOKEN`
- request header name comes from `sysConfig.TOKEN_NAME`
- request header prefix comes from `sysConfig.TOKEN_PREFIX`
- current default appears to be `TOKEN_NAME: 'token'` and `TOKEN_PREFIX: ''`

ThinkPHP backend target:

- `Authorization: Bearer <token>`
- Redis-backed token state under `oa:auth:`

Compatibility decision needed:

1. Change frontend config later to `Authorization` and `Bearer `.
2. Or keep Java-compatible `token` header support in the backend middleware during transition.

## Response Contract

Current frontend behavior:

- success requires `data.code === 200`
- success data is returned from `data.data`
- error message uses `data.msg`
- login expiration codes include `401`, `1011007`, and `1011008`
- secondary password verification uses code `408`

Backend planning:

- standard response is `code`, `message`, and `data`
- auth-agent now also returns `msg` for Java frontend compatibility

Compatibility decision needed:

1. Keep backend dual fields `message` and `msg` during the transition.
2. Later decide whether the editable frontend should switch to `message`.

## Menu And Permission Contract

The frontend depends on:

- `/sys/userCenter/loginMenu`
- local storage key `MENU`
- local storage key `USER_INFO`
- `USER_INFO.buttonCodeList`
- `hasPerm()` checks in views

auth-agent owns the backend login menu and permission data. frontend-agent must not duplicate that logic.

## Upload, Download, And Streaming

The frontend request wrapper has special behavior for:

- `responseType === 'blob'`
- `text/event-stream`
- upload token handling in `XnUpload`
- web push and SSE utilities

These should be deferred until file, message, and realtime modules are planned.

## Read-Only Rule

Do not edit `F:\AI\projects\testJava\OA\snowy-admin-web` directly. If frontend source must be changed later, first decide a managed editable frontend target path.

## Prepared Backend Contracts

The backend branches now have prepared contracts for:

- login, token, current user, and login menu from auth-agent
- user, organization, position, and user-center directory read APIs from user-agent and api-agent
- workflow task/process read APIs from workflow-agent and api-agent

See `docs/frontend/backend-contract-map.md` for the current endpoint list.
