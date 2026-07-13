# Biz Directory Alias Compatibility API

Date: 2026-06-02

Agent: user-agent

## Scope

This document records the safe compatibility aliases for legacy frontend `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` requests.

The implementation reuses existing ThinkPHP services for system organization, user, position, role selector, and dictionary data. Write behavior is added only in narrow slices documented below.

## Implemented Routes

| Method | Path | Target |
| --- | --- | --- |
| GET | `/biz/org/page` | `sys.OrgController/page` |
| GET | `/biz/org/list` | `sys.OrgController/list` |
| GET | `/biz/org/tree` | `sys.OrgController/tree` |
| GET | `/biz/org/detail` | `sys.OrgController/detail` |
| GET | `/biz/org/orgTreeSelector` | `sys.OrgController/treeSelector` |
| GET | `/biz/org/userSelector` | `sys.OrgController/userSelector` |
| POST | `/biz/org/add` | `sys.OrgController/bizAdd` |
| POST | `/biz/org/edit` | `sys.OrgController/bizEdit` |
| POST | `/biz/org/delete` | `sys.OrgController/bizDelete` |
| GET | `/biz/user/page` | `sys.UserController/page` |
| GET | `/biz/user/list/detail` | `sys.UserController/listDetail` |
| GET | `/biz/user/detail` | `sys.UserController/detail` |
| POST | `/biz/user/add` | `sys.UserController/bizAdd` |
| POST | `/biz/user/edit` | `sys.UserController/bizEdit` |
| GET | `/biz/user/ownRole` | `sys.UserController/ownRole` |
| POST | `/biz/user/delete` | `sys.UserController/bizDelete` |
| POST | `/biz/user/disableUser` | `sys.UserController/bizDisableUser` |
| POST | `/biz/user/enableUser` | `sys.UserController/bizEnableUser` |
| POST | `/biz/user/resetPassword` | `sys.UserController/bizResetPassword` |
| GET | `/biz/user/export` | `sys.UserController/bizExport` |
| GET | `/biz/user/exportUserInfo` | `sys.UserController/bizExportUserInfo` |
| GET | `/biz/user/orgTreeSelector` | `sys.UserController/orgTreeSelector` |
| GET | `/biz/user/positionSelector` | `sys.UserController/positionSelector` |
| GET | `/biz/user/roleSelector` | `sys.UserController/roleSelector` |
| GET | `/biz/user/userSelector` | `sys.UserController/userSelector` |
| GET | `/biz/position/page` | `sys.PositionController/page` |
| GET | `/biz/position/list` | `sys.PositionController/list` |
| GET | `/biz/position/detail` | `sys.PositionController/detail` |
| GET | `/biz/position/orgTreeSelector` | `sys.PositionController/orgTreeSelector` |
| GET | `/biz/position/positionSelector` | `sys.PositionController/selector` |
| POST | `/biz/position/add` | `sys.PositionController/bizAdd` |
| POST | `/biz/position/edit` | `sys.PositionController/bizEdit` |
| POST | `/biz/position/delete` | `sys.PositionController/bizDelete` |
| GET | `/biz/dict/page` | `dev.DictController/page` |
| GET | `/biz/dict/tree` | `dev.DictController/tree` |
| GET | `/biz/dict/treeAll` | `dev.DictController/treeAll` |
| POST | `/biz/dict/edit` | `dev.DictController/edit` |

All routes are protected by `AuthMiddleware`.

## Compatibility Notes

- `/biz/user/list/detail` returns sanitized user rows and never returns the `PASSWORD` field.
- `/biz/org/add`, `/biz/org/edit`, and `/biz/org/delete` write base `sys_org` rows with conservative organization data-scope checks and dependency-protected logical delete.
- `/biz/position/add`, `/biz/position/edit`, and `/biz/position/delete` write base `sys_position` rows with conservative organization data-scope checks and user-reference-protected logical delete.
- `/biz/user/ownRole` reads role IDs from `sys_relation` where `CATEGORY = SYS_USER_HAS_ROLE`.
- `/biz/user/add` and `/biz/user/edit` write base `sys_user` profile fields with conservative organization data-scope or current-user edit fallback.
- `/biz/user/delete` logically deletes users and clears affected director references with conservative organization data-scope or current-user fallback.
- `/biz/user/disableUser` and `/biz/user/enableUser` update only `sys_user.USER_STATUS` with conservative organization data-scope or current-user fallback.
- `/biz/user/resetPassword` updates only `sys_user.PASSWORD` to the configured default password hash with conservative organization data-scope or current-user fallback.
- `/biz/user/export` and `/biz/user/exportUserInfo` return sanitized download blobs with conservative organization data-scope or current-user fallback.
- `/biz/dict/treeAll` returns the dictionary tree without tenant-specific filtering for frontend compatibility.
- `/biz/dict/edit` updates only active business dictionary rows where `CATEGORY = BIZ`.
- Selector responses keep existing `id`, `value`, `label`, `title`, and display-name aliases.

## Deferred

- Business user import, `.xls` parsing, and real `.docx` rendering
- General organization-wide profile edit beyond `/biz/user/center/edit`
- Dictionary add/delete
- Java source changes
- Database schema changes
- Frontend code changes

## 2026-06-06 Self Profile Write Alias

`POST /biz/user/center/edit` is now routed for the copied user-center "more info" form.

The route delegates to the user-center self-profile writer and mirrors Java `BizUserController.editUser` behavior by forcing the target user id to the current token user. It is not a general user-management edit route.

Still deferred:

- import/export and organization-wide side effects beyond base profile writes

## 2026-06-06 User Add Edit Alias

`POST /biz/user/add` and `POST /biz/user/edit` are now routed for the copied business user management form.

The routes delegate to the shared user directory writer and mirror Java `BizUserServiceImpl` behavior for base profile fields, default password/status on add, uniqueness checks, organization/position validation, and organization data-scope guarding.

Still deferred:

- import/export
- token/session invalidation
- Java data-change event publishing
- full SM4 encrypted-field migration

## 2026-06-06 Organization Add Edit Delete Alias

`POST /biz/org/add`, `POST /biz/org/edit`, and `POST /biz/org/delete` are now routed for the copied business organization management page.

The routes delegate to the shared organization service and mirror Java `BizOrgServiceImpl` behavior for base organization fields, category validation, same-level name uniqueness, parent cycle checks, dependency-protected delete, and organization data-scope guarding.

The ThinkPHP implementation uses logical delete on `sys_org.DELETE_FLAG` during this staged refactor instead of Java's physical row removal.

Still deferred:

- Java data-change event publishing
- route-permission middleware

## 2026-06-06 Position Add Edit Delete Alias

`POST /biz/position/add`, `POST /biz/position/edit`, and `POST /biz/position/delete` are now routed for the copied business position management page.

The routes delegate to the shared position service and mirror Java `BizPositionServiceImpl` behavior for base position fields, category validation, same-organization name uniqueness, user-reference-protected delete, and organization data-scope guarding.

The ThinkPHP implementation uses logical delete on `sys_position.DELETE_FLAG` during this staged refactor instead of Java's physical row removal.

Still deferred:

- business user import, `.xls` parsing, and real `.docx` rendering
- Java data-change event publishing
- route-permission middleware

## 2026-06-06 User Export Download Alias

`GET /biz/user/export` and `GET /biz/user/exportUserInfo` are now routed for copied business user download actions.

The routes delegate to the shared user directory export service and return sanitized CSV/plain-text blobs without adding Composer dependencies. Business downloads enforce conservative organization data-scope; when no organization scope exists, ordinary users may download only their own profile data.

Still deferred:

- `POST /sys/user/import`
- real `.xlsx` generation
- real `.docx` template rendering
- file upload/storage behavior
- route-permission middleware

## 2026-06-06 Business Dictionary Edit Alias

`POST /biz/dict/edit` is now routed for the copied business dictionary maintenance page.

The route delegates to the shared dictionary service and updates only active `dev_dict` rows where `CATEGORY = BIZ`. It validates required `id`, `dictLabel`, and numeric `sortCode`, supports optional `extJson`, blocks duplicate business dictionary labels for the same tenant, and preserves parent, dictionary value, category, tenant, and create metadata.

## 2026-06-08 Dev BIZ Dictionary Writes

The legacy maintenance page that exposes BIZ dictionary add/delete actions posts through `/dev/dict/add`, `/dev/dict/edit`, and `/dev/dict/delete`, not `/biz/dict/add` or `/biz/dict/delete`. Those `/dev/dict` write routes now support `CATEGORY = BIZ` rows only:

- `POST /dev/dict/add`
- `POST /dev/dict/edit`
- `POST /dev/dict/delete`

Deletes are soft deletes and include active BIZ descendants. `/biz/dict/add` and `/biz/dict/delete` remain unregistered because the Java business dictionary controller does not expose them.

Still deferred:

- `/biz/dict/add`
- `/biz/dict/delete`
- FRM/system dictionary writes under `/dev/dict`
- dictionary cache invalidation parity with Java

## 2026-06-15 Directory Alias HTTP Smoke

`scripts/directory-alias-http-smoke.ps1` verifies the copied business directory alias payloads against the local authenticated backend.

Covered read-only checks:

- `GET /biz/org/page`
- `GET /biz/org/tree`
- `GET /biz/org/orgTreeSelector`
- `GET /biz/org/userSelector`
- `GET /biz/position/page`
- `GET /biz/position/positionSelector`
- `GET /biz/dict/page`
- `GET /biz/dict/tree`
- `GET /biz/dict/treeAll`

The smoke checks Java-style paging keys where applicable, first-row or first-node display aliases when data exists, no `PASSWORD` leakage from user selectors, and `current=1&size=1` compatibility. `OrgService::pagination()` now accepts copied frontend `size` pagination in addition to `limit` and `pageSize`.

This smoke is read-only. It does not call organization, position, user, or dictionary add/edit/delete routes.
