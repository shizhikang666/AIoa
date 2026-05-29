# Backend Contract Map

## Purpose

Track backend contracts that the frontend will need once the ThinkPHP integration branch is merged and routes are registered.

## Auth Contracts

| Endpoint | Method | Frontend Concern |
| --- | --- | --- |
| `/auth/b/getPicCaptcha` | GET | login captcha |
| `/auth/b/doLogin` | POST | login and token creation |
| `/auth/b/doLogout` | GET | logout |
| `/auth/b/getLoginUser` | GET | current user info |
| `/auth/b/safe/password` | POST | secondary password window |
| `/sys/userCenter/loginMenu` | GET | dynamic menu and button permission data |

Response shape:

```json
{
  "code": 200,
  "message": "ok",
  "msg": "ok",
  "data": {}
}
```

## User Directory Contracts

Prepared by api-agent as thin adapters and user-agent as read-only services. Route registration is still pending.

| Endpoint | Method | Frontend Usage |
| --- | --- | --- |
| `/sys/org/tree` | GET | organization tree |
| `/sys/org/orgTreeSelector` | GET | organization selector |
| `/sys/org/detail` | GET | organization detail |
| `/sys/position/page` | GET | position table page |
| `/sys/position/detail` | GET | position detail |
| `/sys/position/positionSelector` | GET | position selector |
| `/sys/user/page` | GET | user table page |
| `/sys/user/detail` | GET | user detail |
| `/sys/userCenter/loginOrgTree` | GET | current user's org tree |
| `/sys/userCenter/loginPositionInfo` | GET | current user's position data |
| `/sys/userCenter/getUserListByIdList` | POST | user selector echo |
| `/sys/userCenter/getPositionListByIdList` | POST | position selector echo |

## Workflow Read-Only Contracts

Prepared by api-agent as thin adapters and workflow-agent as read-only services. Route registration is still pending.

| Endpoint | Method | Frontend Usage |
| --- | --- | --- |
| `/biz/task/count` | GET | pending task badge/count |
| `/biz/task/list` | GET | pending task dropdown/list |
| `/biz/task/page` | GET | pending task page |
| `/biz/task/history/page` | GET | completed task page |
| `/biz/process/page` | GET | my started process page |
| `/biz/process/detail` | GET | process timeline/detail |
| `/biz/process/variable` | POST | process variables |

## Explicitly Deferred

- `/biz/task/approve`
- `/biz/task/reject`
- `/biz/process/cancel`
- all `/biz/process/*/start`
- upload/download
- SSE and web push
- import/export
- production realtime data sync

## Token Header Decision

Current frontend reads local key `TOKEN` and sends configured header `token` with no prefix.

Current backend target is `Authorization: Bearer <token>`.

Recommended transition:

1. Keep backend auth middleware tolerant of both header styles if needed during integration.
2. When frontend becomes editable, switch config to `TOKEN_NAME: 'Authorization'` and `TOKEN_PREFIX: 'Bearer '`.
3. Remove legacy `token` header support after the migrated frontend is verified.
