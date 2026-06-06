# User Export Download Compatibility

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business user download buttons:

- `GET /sys/user/downloadImportUserTemplate`
- `GET /sys/user/export`
- `GET /sys/user/exportUserInfo`
- `GET /biz/user/export`
- `GET /biz/user/exportUserInfo`

## Java References

- `snowy-plugin-sys/.../SysUserController.java`
- `snowy-plugin-sys/.../SysUserServiceImpl.java`
- `snowy-plugin-biz/.../BizUserController.java`
- `snowy-plugin-biz/.../BizUserServiceImpl.java`
- `snowy-admin-web/src/api/sys/userApi.js`
- `snowy-admin-web/src/api/biz/bizUserApi.js`

## ThinkPHP Behavior

- All five routes are protected by `AuthMiddleware`.
- The import template route returns a CSV template blob without adding Composer dependencies.
- Export routes return sanitized CSV blobs.
- User-info routes return a sanitized plain-text profile blob.
- Export rows do not include `PASSWORD`, token data, or secrets.
- System export requires admin-compatible payloads or matching system user export permission codes.
- Business export requires admin-compatible payloads or matching business user export permission codes and applies conservative organization data-scope. If no organization scope is present, it falls back to the current token user only.

## Supported Filters

- `userIds`, `ids`, or `idList`
- `searchKey`
- `userStatus`
- Existing user directory filters such as `account`, `name`, `phone`, `orgId`, `positionId`, and `tenantId`

## Deferred

- `POST /sys/user/import`
- Real `.xlsx` generation
- Real `.docx` template rendering
- File upload/storage behavior
- Java data-change events
- Route-permission middleware
- Java source changes
- Database schema changes
- Composer changes
- Frontend source changes
