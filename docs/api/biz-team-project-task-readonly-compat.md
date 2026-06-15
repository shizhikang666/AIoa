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
| POST | `/biz/bizteamprojecttaskcategory/add` | Add a task category to an existing team project. |
| POST | `/biz/bizteamprojecttaskcategory/edit` | Edit task category title, `extJson`, or `sortCode`. |
| POST | `/biz/bizteamprojecttaskcategory/sort/edit` | Reorder categories for one team project. |
| POST | `/biz/bizteamprojecttaskcategory/delete` | Logically delete empty task categories. |
| GET | `/biz/bizteamprojecttask/page` | Task page scoped to projects visible to the current user. |
| GET | `/biz/bizteamprojecttask/list` | Task list for the project kanban view. |
| POST | `/biz/bizteamprojecttask/add` | Add a base task row and initial task-user rows. |
| POST | `/biz/bizteamprojecttask/edit` | Edit base task fields used by the copied kanban and task detail drawer. |
| POST | `/biz/bizteamprojecttask/delete` | Logically delete tasks and active task-user rows. |
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
- Task category add/edit/sort/delete requires a project maintainer boundary: team-project `LEADER`, team-project `MANAGE`, or imported `addUser` project resource permission.
- Task category delete rejects categories that still contain active tasks, and uses `DELETE_FLAG = DELETED` instead of physical removal.
- Task add requires current-user membership of the owning team project, validates the selected category belongs to the same project, creates the current user as task `MANAGE`, and creates submitted project members as task `MEMBER`.
- Task edit is limited to the task creator, a task-level `MANAGE` user, or a project maintainer. It only updates submitted base task fields and increments `VERSION`.
- Task delete is limited to the task creator, a task-level `MANAGE` user, or a project maintainer. It logically deletes the task and active task-user rows.
- Task comment add requires current-user membership of the owning team project and stores submitted files under `EXT_JSON` as `{"file":[...]}` for the copied task detail parser.
- Task comment edit/delete only applies to user comments with `CATEGORY = COMMENT`; generated `LOG` rows remain read-only.
- Task comment maintenance is allowed for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.

## Deferred Behavior

- Java data-change events and generated `CATEGORY = LOG` task comments.
- Notification push and realtime message fan-out.
- Full drag ordering beyond category reassignment.
These behaviors need a later write-flow design.

## Verification Scope

- Syntax checks for the new service and controllers.
- Baseline `composer dump-autoload`.
- Baseline `php think`.
- `php think route:list` route registration.
- Runtime smoke tests for representative category, task, project-comment, task-comment reads, task base maintenance, category maintenance, task comment add, task assignee sync, and project-comment/reply base writes.
- No-token check for a protected route.

## 2026-06-15 HTTP Smoke Coverage

`scripts/team-project-read-http-smoke.ps1` now covers authenticated team-project task reads for:

- `GET /biz/bizteamprojecttaskcategory/page`
- `GET /biz/bizteamprojecttaskcategory/list`
- `GET /biz/bizteamprojecttaskcategory/detail` when a visible category exists
- `GET /biz/bizteamprojecttask/page`
- `GET /biz/bizteamprojecttask/list`
- `GET /biz/bizteamprojecttask/detail` when a visible task exists
- `GET /biz/bizteamprojecttaskcomment/page`
- `GET /biz/bizteamprojecttaskcomment/list`
- `GET /biz/bizteamprojecttaskcomment/detail` when a visible task comment exists

The smoke asserts paging/list wrappers, task/category/comment display fields, and task-detail `users`. It intentionally does not call category add/edit/sort/delete, task add/edit/delete/user-edit, task-comment add/edit/delete, notification, realtime, workflow, or data-change behavior.
