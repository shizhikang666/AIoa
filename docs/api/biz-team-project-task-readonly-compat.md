# Biz Team Project Task Read-Only Compatibility

Status: implemented on `refactor/thinkphp-main` by `merge-agent`.

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
| GET | `/biz/bizteamprojectcomment/page` | Project timeline comment page. |
| GET | `/biz/bizteamprojectcomment/list` | Project timeline comment list with nested replies. |
| GET | `/biz/bizteamprojecttaskcomment/page` | Task comment/log page. |
| GET | `/biz/bizteamprojecttaskcomment/list` | Task comment/log list for the task detail drawer. |
| GET | `/biz/bizteamprojecttaskcomment/detail` | Task comment/log detail by id. |

## Access Rules

- Reads are limited to projects where the current authenticated user has a `biz_team_project_user` row.
- Soft-deleted project, project-member, task, category, comment, reply, and task-comment rows are excluded.
- Task detail and task-comment detail resolve access through the task/project relationship, so a direct id cannot bypass project membership.

## Deferred Routes

- `/biz/bizteamprojecttask/add`
- `/biz/bizteamprojecttask/edit`
- `/biz/bizteamprojecttask/delete`
- `/biz/bizteamprojecttask/user/edit`
- `/biz/bizteamprojecttaskcategory/add`
- `/biz/bizteamprojecttaskcategory/edit`
- `/biz/bizteamprojecttaskcategory/sort/edit`
- `/biz/bizteamprojecttaskcategory/delete`
- `/biz/bizteamprojectcomment/add`
- `/biz/bizteamprojectcomment/delete`
- `/biz/bizteamprojectcommentreply/add`
- `/biz/bizteamprojectcommentreply/edit`
- `/biz/bizteamprojectcommentreply/delete`

These routes mutate task state, category order, memberships, comments, replies, and data-change events. They need a later write-flow design.

## Verification Scope

- Syntax checks for the new service and controllers.
- Baseline `composer dump-autoload`.
- Baseline `php think`.
- `php think route:list` route registration.
- Runtime smoke tests for representative category, task, project-comment, and task-comment reads.
- No-token check for a protected route.
