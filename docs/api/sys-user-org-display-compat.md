# Sys User And Org Display Compatibility

Date: 2026-06-01

Agent: user-agent / frontend compatibility support

## Scope

This slice aligns existing read-only system user, organization, and position responses with the copied Vue frontend.

No route, Controller, database schema, write endpoint, or Java source file is changed.

## Reason

The Java/Snowy frontend expects camelCase response fields such as `id`, `name`, `parentId`, `sortCode`, `orgName`, `positionName`, and `userStatus`.

The initial ThinkPHP read services returned many raw SQL fields such as `ID`, `NAME`, `PARENT_ID`, `ORG_ID`, and `USER_STATUS`. Those uppercase fields are still useful for backend compatibility, so this slice keeps them and adds camelCase aliases instead of replacing the payload.

## Affected Endpoints

| Endpoint | Compatibility Added |
| --- | --- |
| `GET /sys/org/page` | `records[]` now includes `id`, `parentId`, `name`, `category`, `sortCode`, audit aliases, and pagination aliases |
| `GET /sys/org/list` | Rows now include org camelCase aliases |
| `GET /sys/org/tree` | Tree nodes now include org camelCase aliases plus normalized `children` |
| `GET /sys/org/detail` | Detail row now includes org camelCase aliases |
| `GET /sys/position/page` | `records[]` now includes `id`, `orgId`, `name`, `category`, `sortCode`, audit aliases, and pagination aliases |
| `GET /sys/position/list` | Rows now include position camelCase aliases |
| `GET /sys/position/detail` | Detail row now includes position camelCase aliases |
| `GET /sys/user/page` | `records[]` now includes visible user fields and batched `orgName`/`positionName` enrichment |
| `GET /sys/user/detail` | Detail row now includes visible user fields and name enrichment |
| `GET /sys/user/userSelector` | Selector rows include `orgName` and `positionName` in addition to id/label/title fields |
| `POST /sys/userCenter/getOrgListByIdList` | Rows now include basic org camelCase aliases |
| `POST /sys/userCenter/getPositionListByIdList` | Rows now include basic position camelCase aliases |

## User Row Aliases

User read rows now include:

- `id`
- `avatar`
- `signature`
- `account`
- `name`
- `nickname`
- `gender`
- `genderName`
- `age`
- `birthday`
- `phone`
- `email`
- `empNo`
- `entryDate`
- `orgId`
- `orgName`
- `positionId`
- `positionName`
- `positionLevel`
- `directorId`
- `positionJson`
- `userStatus`
- `sortCode`
- `extJson`
- `deleteFlag`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`
- `tenantId`

## Notes

- `orgName` and `positionName` are resolved in batch to avoid N+1 queries.
- `genderName` uses `dev_dict` label data when available and falls back to the raw gender value.
- Uppercase SQL fields remain in the payload for existing backend consumers.
- User and organization writes remain deferred.
- Dictionary loading is still handled by the copied frontend login flow through `/dev/dict/tree`.

## 2026-06-15 HTTP Smoke Coverage

`scripts/user-display-http-smoke.ps1` verifies the frontend-visible user display aliases against the local authenticated backend without requiring browser automation.

Covered read-only checks:

- `GET /sys/user/page`
- `GET /biz/user/page`
- `GET /sys/user/detail`
- `GET /sys/user/list/detail`
- `GET /sys/user/userSelector`
- `GET /biz/user/userSelector`

The smoke checks that paged responses keep Java-style `records`, `total`, `current`, `size`, and `pages`, user rows do not expose `PASSWORD`, and visible frontend fields include `id`, `account`, `name`, `avatar`, `gender`, `genderName`, `phone`, `orgId`, `orgName`, `positionId`, `positionName`, `userStatus`, and `sortCode`.

Because these responses intentionally preserve both uppercase SQL keys and camelCase aliases, the smoke reads values through `scripts/json-read.js` instead of Windows PowerShell `ConvertFrom-Json`, which cannot handle case-only duplicate keys such as `ID` and `id`.
