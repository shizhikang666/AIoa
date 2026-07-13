# User Import And Export Download Compatibility

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business user download buttons:

- `GET /sys/user/downloadImportUserTemplate`
- `POST /sys/user/import`
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

- All six routes are protected by `AuthMiddleware`.
- The import template route returns a Java-compatible `.xlsx` template blob built with PHP `ZipArchive`, without adding Composer dependencies.
- `POST /sys/user/import` accepts multipart field `file` for `.xlsx` files.
- The importer skips the first two worksheet rows and maps columns in the Java `SysUserImportParam` order.
- Required import fields are `account`, `name`, `orgName`, and `positionName`.
- Organization full names use `-` path segments; missing organization nodes are created with `COMPANY` at root and `DEPT` below root.
- Positions are looked up by organization and name; missing positions are created with category `LOW`.
- Existing accounts are updated; missing accounts are added with default avatar, default password hash, status `ENABLE`, sort code `99`, and audit/tenant fields.
- Duplicate imported phone/email values are skipped instead of failing the row, matching the Java import behavior.
- Import returns `totalCount`, `successCount`, `errorCount`, and `errorDetail`.
- Import files are capped at 5 MB, parsed rows are capped at 1,000, and worksheet XML is capped before `SimpleXML` parsing.
- Export routes return sanitized CSV blobs.
- User-info routes return a sanitized plain-text profile blob.
- Export rows do not include `PASSWORD`, token data, or secrets.
- System export requires admin-compatible payloads or matching system user export permission codes.
- System import requires admin-compatible payloads or matching system user import permission codes, and each row still passes the existing user add/edit write permission gate.
- Admin-compatible imports can auto-create missing organizations and positions to match Java behavior.
- Non-admin imports may target existing organizations and positions only, and the target organization must match token data-scope organization ids or the current user's organization.
- Non-admin imports cannot create organization/position rows through import and cannot import over built-in accounts.
- Business export requires admin-compatible payloads or matching business user export permission codes and applies conservative organization data-scope. If no organization scope is present, it falls back to the current token user only.
- `biz/user/import` remains intentionally absent because the Java business user controller and copied business user frontend do not expose that route.

## Supported Filters

- `userIds`, `ids`, or `idList`
- `searchKey`
- `userStatus`
- Existing user directory filters such as `account`, `name`, `phone`, `orgId`, `positionId`, and `tenantId`

## Deferred

- `.xls` binary parsing
- Excel serial date/style conversion for `entryDate` and `birthday`
- Real `.docx` template rendering
- Full SM4 encrypted profile-field migration for `PHONE`, `ID_CARD_NUMBER`, and `EMERGENCY_PHONE`
- File upload/storage behavior
- Java data-change events
- Route-permission middleware
- Java source changes
- Database schema changes
- Composer changes
- Frontend source changes
