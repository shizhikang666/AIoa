# Biz Team Project Compatibility

## Scope

This document tracks ThinkPHP compatibility endpoints for the old Java team-project APIs used by the Vue OA frontend. It started as a read-only slice and now also records isolated member/comment/task maintenance routes that have been opened in later small slices.

Java source analyzed:

- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamproject/controller/BizTeamProjectController.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamproject/service/impl/BizTeamProjectServiceImpl.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamproject/param/BizTeamProjectPageParam.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamproject/result/BizTeamProjectDetail.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamprojectuser/controller/BizTeamProjectUserController.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamprojectuser/service/impl/BizTeamProjectUserServiceImpl.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizteamprojectuser/enums/BizTeamProjectUserRoleEnum.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

Frontend source analyzed:

- `snowy-admin-web/src/api/biz/bizTeamProjectApi.js`
- `snowy-admin-web/src/api/biz/bizTeamProjectUserApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/index.vue`
- `snowy-admin-web/src/views/biz/bizteamproject/composables/index.js`
- `snowy-admin-web/src/views/biz/bizteamproject/details/index.vue`

## Added Routes

All routes are protected by `AuthMiddleware`.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/biz/bizteamproject/page` | Paginated current-member project cards. |
| POST | `/biz/bizteamproject/add` | Create a project, set current user as owner, create `LEADER` member, and sync project permission relation. |
| POST | `/biz/bizteamproject/edit` | Update base project fields after `delProject` permission check. |
| POST | `/biz/bizteamproject/delete` | Logically delete projects and active member rows after `delProject` permission check. |
| GET | `/biz/bizteamproject/detail` | Project detail plus current member role. |
| GET | `/biz/bizteamprojectuser/page` | Paginated team-member list. |
| GET | `/biz/bizteamprojectuser/list` | Non-paginated members for a project. |
| POST | `/biz/bizteamprojectuser/add` | Add normal project members after `addUser` resource permission check. |
| POST | `/biz/bizteamprojectuser/manage/add` | Add project managers after `addManage` resource permission check. |
| POST | `/biz/bizteamprojectuser/edit` | Java-compatible member edit stub: validates active member id and refreshes audit fields only. |
| POST | `/biz/bizteamprojectuser/delete` | Logically remove non-leader, non-current-user project members. |
| GET | `/biz/bizteamprojectuser/detail` | Read-only member detail lookup. |

## Explicitly Deferred Routes

No team-project controller route is deferred in this compatibility document. Role-changing member edit behavior remains deferred because Java's `BizTeamProjectUserEditParam` only contains `id`.

## Query Compatibility

Supported project query parameters:

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `name`
- `projectStatus`
- `user`, `userId`
- `org`, `orgId`
- `startCompletionTime`, `endCompletionTime`
- `startCreateTime`, `endCreateTime`
- `searchKey`
- `tenantId`

Supported member query parameters:

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `teamProjectId`, `projectId`
- `userId`
- `roleType`
- `searchKey`
- `tenantId`

The service reads and enriches:

- `biz_team_project`
- `biz_team_project_user`
- `sys_user` for names and avatars
- `sys_org` for organization display

## Response Shape

Project page rows return frontend-friendly camelCase fields:

- `id`, `name`, `description`, `projectStatus`, `completionTime`
- `user`, `userId`, `headName`, `avatar`
- `org`, `orgName`
- `version`, `deleteFlag`, `createTime`, `createUser`, `createUserName`, `updateTime`, `updateUser`, `tenantId`
- `currentMemberId`, `currentRoleType`

Project detail returns the Java-compatible wrapper:

- `project`
- `user`

Member rows return:

- `id`, `teamProjectId`, `projectName`
- `userId`, `headName`, `avatar`
- `roleType`, `roleName`, `permissionCode`
- `deleteFlag`, `createTime`, `createUser`, `updateTime`, `updateUser`, `tenantId`

## Write Compatibility

Project add accepts:

- `name` (required)
- `description`

It mirrors Java's `BIZ_TEAM_PROJECT` add event by creating a `biz_team_project_user` row for the current user with `ROLE_TYPE = LEADER`, then syncing `biz_relation.CATEGORY = TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` with the Java leader permission codes. The frontend `users` selector is currently commented out, and the Java add service does not persist submitted `users` directly; adding other members remains covered through `/biz/bizteamprojectuser/add` and `/manage/add`.

Project edit accepts:

- `id` (required)
- `name`
- `description`
- `projectStatus`
- `completionTime`

Project delete accepts Java-style `[{ id }]` arrays plus existing compatible `idList`, `ids`, or single `id` payloads.

Member edit accepts:

- `id` (required)

The Java edit parameter class only contains `id`; Java then calls `updateById`, which refreshes audit fields through MyBatis fill. The ThinkPHP route mirrors that narrow behavior by validating an active member row and writing `UPDATE_TIME` and `UPDATE_USER` only. Submitted `roleType`, `userId`, `teamProjectId`, or permission fields are ignored and do not update `biz_relation`.

## Notes

- Java project page joins `biz_team_project_user` and only lists projects that include the current login user. This ThinkPHP query preserves that gate.
- Java project detail loads the current user's `BizTeamProjectUser` record and returns it as `user`; this slice mirrors that shape.
- Role permission codes are mapped from Java `BizTeamProjectUserRoleEnum`.
- Project add/edit/delete are opened for copied frontend project-card and project-detail maintenance flows.
- Project base maintenance uses existing `delProject` resource permission semantics for edit/delete.
- Member add/manage-add writes `biz_team_project_user` rows and keeps `biz_relation.CATEGORY = TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` JSON compatible with Java role defaults.
- Member edit refreshes audit fields only and intentionally does not change role or permission JSON.
- Member delete uses logical deletion through `DELETE_FLAG = DELETED` instead of Java's physical remove, and it rejects leader/current-user removal.
- Project delete also uses logical deletion and marks active project members deleted; the permission relation row is retained until cleanup or later relation-maintenance work.
- This slice does not modify Java source, database schema, Composer files, `.env`, notification push, data-change events, or frontend source.
