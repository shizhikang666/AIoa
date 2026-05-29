# Team Collaboration Table Map

Agent: db-agent

Source SQL:

- `F:\AI\projects\testJava\OA\oa2026.sql`

Java source scope:

- `snowy-plugin-biz` team project comment, task comment, category, and user relation entity/mapper packages.

## Scope

This phase adds passive database coverage for team project collaboration tables. It documents table shape and relation hints only. It does not implement team collaboration services, routes, permissions, workflow behavior, or frontend behavior.

## Tables

| Table | Java entity | Purpose |
| --- | --- | --- |
| `biz_team_project_comment` | `BizTeamProjectComment` | Timeline/comment entry for a team project. |
| `biz_team_project_comment_reply` | `BizTeamProjectCommentReply` | Reply rows linked to project comments. |
| `biz_team_project_task_comment` | `BizTeamProjectTaskComment` | Task comment or log entry. |
| `biz_team_project_task_category` | `BizTeamProjectTaskCategory` | Task category/column under a team project. |
| `biz_team_project_user` | `BizTeamProjectUser` | Team project member relation and role marker. |
| `biz_team_project_task_user` | `BizTeamProjectTaskUser` | Task member relation and role marker. |

## Relation Notes

- `TEAM_PROJECT_ID` points to `biz_team_project.ID`.
- `TEAM_PROJECT_TASK_ID` points to `biz_team_project_task.ID`.
- `biz_team_project_comment_reply.TARGET_ID` points to `biz_team_project_comment.ID`.
- `USER_ID` points to `sys_user.ID`.
- `ROLE_TYPE` stores a role marker; its business meaning belongs to later business agents.
- Java display fields such as user names, avatars, reply lists, and permission code lists are not physical columns when annotated with `@TableField(exist = false)`.

## Deferred Work

- Collaboration APIs belong to api-agent.
- Team project business behavior belongs to workflow-agent or a later team-project business slice.
- Permission interpretation belongs to auth-agent or api-agent after the database layer is merged.
