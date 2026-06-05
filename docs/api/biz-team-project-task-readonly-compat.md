# Biz Team Project Task And Comment Compatibility

Status: implemented and expanded on `refactor/thinkphp-main` by `merge-agent` and `api-agent`.

## Java Inputs

- `BizTeamProjectTaskCategoryController`
- `BizTeamProjectTaskCategoryServiceImpl`
- `BizTeamProjectTaskController`
- `BizTeamProjectTaskServiceImpl`
- `BizTeamProjectCommentController`
- `BizTeamProjectCommentServiceImpl`
- `BizTeamProjectTaskCommentController`
- `BizTeamProjectTaskCommentServiceImpl`
- `BizTeamProjectCommentReplyServiceImpl`
- `biz_team_project_task_category`
- `biz_team_project_task`
- `biz_team_project_task_user`
- `biz_team_project_comment`
- `biz_team_project_comment_reply`
- `biz_team_project_task_comment`

## Implemented Routes

All routes are protected by `AuthMiddleware`.

| Method | Route | Notes |
| --- | --- | --- |
| GET | `/biz/bizteamprojecttaskcategory/page` | Category page for one project. |
| GET | `/biz/bizteamprojecttaskcategory/list` | Category list for one project, ordered by `sortCode`. |
| GET | `/biz/bizteamprojecttaskcategory/detail` | Category detail by id. |
| GET | `/biz/bizteamprojecttask/page` | Task page scoped to projects visible to the current user. |
| GET | `/biz/bizteamprojecttask/list` | Task list for the project kanban view. |
| GET | `/biz/bizteamprojecttask/detail` | Task detail by id with `users`. |
| POST | `/biz/bizteamprojecttask/user/edit` | Sync task assignees from the task detail drawer. |
| GET | `/biz/bizteamprojectcomment/page` | Project timeline comment page. |
| GET | `/biz/bizteamprojectcomment/list` | Project timeline comment list with nested replies. |
| POST | `/biz/bizteamprojectcomment/add` | Project timeline comment add with member guard. |
| POST | `/biz/bizteamprojectcomment/delete` | Project timeline comment logical delete with `delComment` resource permission guard. |
| POST | `/biz/bizteamprojectcommentreply/add` | Project timeline comment reply add with member guard. |
| POST | `/biz/bizteamprojectcommentreply/edit` | Project timeline comment reply content/target edit with maintainer guard. |
| POST | `/biz/bizteamprojectcommentreply/delete` | Project timeline comment reply logical delete with maintainer guard. |
| GET | `/biz/bizteamprojecttaskcomment/page` | Task comment/log page. |
| GET | `/biz/bizteamprojecttaskcomment/list` | Task comment/log list for the task detail drawer. |
| POST | `/biz/bizteamprojecttaskcomment/add` | Add a user comment to an existing team-project task. |
| POST | `/biz/bizteamprojecttaskcomment/edit` | Edit a user task comment. |
| POST | `/biz/bizteamprojecttaskcomment/delete` | Logically delete user task comments. |
| GET | `/biz/bizteamprojecttaskcomment/detail` | Task comment/log detail by id. |

## Access Rules

- Reads are limited to projects where the current authenticated user has a `biz_team_project_user` row.
- Soft-deleted project, project-member, task, category, comment, reply, and task-comment rows are excluded.
- Task detail and task-comment detail resolve access through the task/project relationship, so a direct id cannot bypass project membership.
- Comment and reply writes are limited to non-deleted members of the owning team project.
- Comment add stores `mentionableUsers` in `EXT_JSON`; notification push and data-change events remain deferred.
- Comment delete requires the current user to have imported project resource permission `delComment`.
- Reply edit/delete allows the reply creator or a project user with imported `delComment` resource permission.
- Task assignee sync requires the current user to be a non-deleted member of the owning team project and to have imported `addUser` project permission or task-level `MANAGE` role.
- Task assignee sync only accepts users who are already non-deleted members of the same team project. Removed assignees are logically deleted to preserve imported data during refactor testing.
- Task comment add requires current-user membership of the owning team project and stores submitted files under `EXT_JSON` as `{"file":[...]}` for the copied task detail parser.
- Task comment edit/delete only applies to user comments with `CATEGORY = COMMENT`; generated `LOG` rows remain read-only.
- Task comment maintenance is allowed for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.

## Deferred Routes

- `/biz/bizteamprojecttask/add`
- `/biz/bizteamprojecttask/edit`
- `/biz/bizteamprojecttask/delete`
- `/biz/bizteamprojecttaskcategory/add`
- `/biz/bizteamprojecttaskcategory/edit`
- `/biz/bizteamprojecttaskcategory/sort/edit`
- `/biz/bizteamprojecttaskcategory/delete`
These routes mutate task state, category order, memberships, and data-change events. They need a later write-flow design.

## Verification Scope

- Syntax checks for the new service and controllers.
- Baseline `composer dump-autoload`.
- Baseline `php think`.
- `php think route:list` route registration.
- Runtime smoke tests for representative category, task, project-comment, task-comment reads, task comment add, task assignee sync, and project-comment/reply base writes.
- No-token check for a protected route.
