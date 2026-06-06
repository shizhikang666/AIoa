# Biz Directory Alias Read-Only API

Date: 2026-06-02

Agent: user-agent

## Scope

This document records the safe read-only compatibility aliases for legacy frontend `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` requests.

The implementation reuses existing ThinkPHP read services for system organization, user, position, role selector, and dictionary data. It does not add write behavior.

## Implemented Routes

| Method | Path | Target |
| --- | --- | --- |
| GET | `/biz/org/page` | `sys.OrgController/page` |
| GET | `/biz/org/list` | `sys.OrgController/list` |
| GET | `/biz/org/tree` | `sys.OrgController/tree` |
| GET | `/biz/org/detail` | `sys.OrgController/detail` |
| GET | `/biz/org/orgTreeSelector` | `sys.OrgController/treeSelector` |
| GET | `/biz/org/userSelector` | `sys.OrgController/userSelector` |
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
| GET | `/biz/user/orgTreeSelector` | `sys.UserController/orgTreeSelector` |
| GET | `/biz/user/positionSelector` | `sys.UserController/positionSelector` |
| GET | `/biz/user/roleSelector` | `sys.UserController/roleSelector` |
| GET | `/biz/user/userSelector` | `sys.UserController/userSelector` |
| GET | `/biz/position/page` | `sys.PositionController/page` |
| GET | `/biz/position/list` | `sys.PositionController/list` |
| GET | `/biz/position/detail` | `sys.PositionController/detail` |
| GET | `/biz/position/orgTreeSelector` | `sys.PositionController/orgTreeSelector` |
| GET | `/biz/position/positionSelector` | `sys.PositionController/selector` |
| GET | `/biz/dict/page` | `dev.DictController/page` |
| GET | `/biz/dict/tree` | `dev.DictController/tree` |
| GET | `/biz/dict/treeAll` | `dev.DictController/treeAll` |

All routes are protected by `AuthMiddleware`.

## Compatibility Notes

- `/biz/user/list/detail` returns sanitized user rows and never returns the `PASSWORD` field.
- `/biz/user/ownRole` reads role IDs from `sys_relation` where `CATEGORY = SYS_USER_HAS_ROLE`.
- `/biz/user/add` and `/biz/user/edit` write base `sys_user` profile fields with conservative organization data-scope or current-user edit fallback.
- `/biz/user/delete` logically deletes users and clears affected director references with conservative organization data-scope or current-user fallback.
- `/biz/user/disableUser` and `/biz/user/enableUser` update only `sys_user.USER_STATUS` with conservative organization data-scope or current-user fallback.
- `/biz/user/resetPassword` updates only `sys_user.PASSWORD` to the configured default password hash with conservative organization data-scope or current-user fallback.
- `/biz/dict/treeAll` returns the dictionary tree without tenant-specific filtering for frontend compatibility.
- Selector responses keep existing `id`, `value`, `label`, `title`, and display-name aliases.

## Deferred

- Organization add/edit/delete
- User import/export
- General organization-wide profile edit beyond `/biz/user/center/edit`
- Position add/edit/delete
- Dictionary edit
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
