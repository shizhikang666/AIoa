# User Delete Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business user table row-delete and batch-delete actions:

- `snowy-admin-web/src/api/sys/userApi.js`
- `snowy-admin-web/src/api/biz/bizUserApi.js`
- `snowy-admin-web/src/views/sys/user/index.vue`
- `snowy-admin-web/src/views/biz/user/index.vue`

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/sys/user/delete` | Logically delete system users and clear director references. |
| POST | `/biz/user/delete` | Logically delete business users with conservative organization data-scope checks. |

Both routes are protected by `AuthMiddleware`.

## Compatibility Notes

- Requests accept copied frontend array payloads such as `[{ id }]`.
- Requests also accept common `id`, `ids`, `idList`, and `userIds` forms.
- Deletion sets `sys_user.DELETE_FLAG = DELETED`; it does not physically remove user rows.
- Built-in/admin-compatible accounts are rejected.
- Java-compatible cleanup clears:
  - `sys_user.DIRECTOR_ID` where the deleted users were direct supervisors.
  - `directorId` entries inside `sys_user.POSITION_JSON`.
  - `sys_org.DIRECTOR_ID` where the deleted users were organization supervisors.
- Business delete keeps a conservative organization data-scope or current-user fallback before saving.
- No frontend source change is required for this compatibility slice.

## Deferred

- User add/edit
- Import/export
- Token/session invalidation after delete
- Java data-change event publishing
- Fine-grained route-permission middleware
- Java source changes
- Database schema changes
- Frontend source changes
