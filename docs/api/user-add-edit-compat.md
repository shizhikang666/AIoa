# User Add Edit Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business user add/edit forms:

- `snowy-admin-web/src/api/sys/userApi.js`
- `snowy-admin-web/src/api/biz/bizUserApi.js`
- `snowy-admin-web/src/views/sys/user/form.vue`
- `snowy-admin-web/src/views/biz/user/form.vue`

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/sys/user/add` | Add a system user profile row. |
| POST | `/sys/user/edit` | Edit a system user profile row. |
| POST | `/biz/user/add` | Add a business user profile row with organization scope checks. |
| POST | `/biz/user/edit` | Edit a business user profile row with organization scope or self-edit fallback. |

All routes are protected by `AuthMiddleware`.

## Compatibility Notes

- Required fields match Java form behavior: `account`, `name`, `orgId`, and `positionId`.
- Add validates active organization, active position, optional supervisor, account/phone/email uniqueness, tenant compatibility, and non-negative `basicSalary`.
- Add sets the default password from system configuration through the existing SM3 compatibility hasher.
- Add sets `USER_STATUS = ENABLE`, `DELETE_FLAG = NOT_DELETE`, tenant id from the selected organization, empty bank defaults, a simple SVG avatar when no avatar is submitted, and a Java-style company employee id.
- Edit validates the same profile references and uniqueness while preserving password, status, delete flag, tenant id, and create metadata.
- Built-in/admin-compatible accounts cannot have their account name changed.
- JSON string fields submitted by the copied frontend are stored as-is; array payloads are encoded to JSON for compatibility.
- Detail/page rows now expose camelCase aliases for the additional profile fields used by the copied forms.

## Deferred

- Import/export
- Route-permission middleware
- Java data-change event publishing
- Token/session invalidation after profile edits
- Full SM4 encrypted-field migration
- Organization and position add/edit/delete
- Java source changes
- Database schema changes
- Frontend source changes
