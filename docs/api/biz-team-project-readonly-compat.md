# Biz Team Project Read-Only Compatibility

## Scope

This slice adds read-only ThinkPHP compatibility endpoints for the old Java team-project APIs used by the Vue OA frontend.

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
| GET | `/biz/bizteamproject/detail` | Project detail plus current member role. |
| GET | `/biz/bizteamprojectuser/page` | Paginated team-member list. |
| GET | `/biz/bizteamprojectuser/list` | Non-paginated members for a project. |
| GET | `/biz/bizteamprojectuser/detail` | Read-only member detail lookup. |

## Explicitly Deferred Routes

These Java/frontend routes are not implemented in this slice because they mutate team projects, team members, role permissions, or data-change events:

- `POST /biz/bizteamproject/add`
- `POST /biz/bizteamproject/edit`
- `POST /biz/bizteamproject/delete`
- `POST /biz/bizteamprojectuser/add`
- `POST /biz/bizteamprojectuser/manage/add`
- `POST /biz/bizteamprojectuser/edit`
- `POST /biz/bizteamprojectuser/delete`

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

## Notes

- Java project page joins `biz_team_project_user` and only lists projects that include the current login user. This ThinkPHP query preserves that gate.
- Java project detail loads the current user's `BizTeamProjectUser` record and returns it as `user`; this slice mirrors that shape.
- Role permission codes are mapped from Java `BizTeamProjectUserRoleEnum`.
- This slice does not modify Java source, database schema, Composer files, `.env`, or any write endpoint.
