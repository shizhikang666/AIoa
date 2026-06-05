# Team Project Comment Compatibility

Date: 2026-06-05

Agent: api-agent / frontend-agent

## Scope

This document tracks the copied team-project comment and comment-reply API wrappers:

- `snowy-admin-web/src/api/biz/bizTeamProjectCommentApi.js`
- `snowy-admin-web/src/api/biz/bizTeamProjectCommentReplyApi.js`

The Java source remains read-only under `F:\AI\projects\testJava\OA`.

## Routes

| Method | Path | Controller |
| --- | --- | --- |
| GET | `/biz/bizteamprojectcomment/detail` | `biz.TeamProjectCommentController/detail` |
| POST | `/biz/bizteamprojectcomment/add` | `biz.TeamProjectCommentController/add` |
| POST | `/biz/bizteamprojectcomment/delete` | `biz.TeamProjectCommentController/delete` |
| GET | `/biz/bizteamprojectcommentreply/page` | `biz.TeamProjectCommentReplyController/page` |
| GET | `/biz/bizteamprojectcommentreply/detail` | `biz.TeamProjectCommentReplyController/detail` |
| POST | `/biz/bizteamprojectcommentreply/add` | `biz.TeamProjectCommentReplyController/add` |
| POST | `/biz/bizteamprojectcommentreply/edit` | `biz.TeamProjectCommentReplyController/edit` |
| POST | `/biz/bizteamprojectcommentreply/delete` | `biz.TeamProjectCommentReplyController/delete` |

Existing covered routes remain:

- `GET /biz/bizteamprojectcomment/page`
- `GET /biz/bizteamprojectcomment/list`
- `GET /biz/bizteamprojecttaskcomment/page`
- `GET /biz/bizteamprojecttaskcomment/list`
- `POST /biz/bizteamprojecttaskcomment/add`
- `POST /biz/bizteamprojecttaskcomment/edit`
- `POST /biz/bizteamprojecttaskcomment/delete`
- `GET /biz/bizteamprojecttaskcomment/detail`

## Behavior

- Project comment detail reads one visible non-deleted project timeline comment by `id`.
- Project comment detail includes nested `bizTeamProjectCommentReplies`.
- Comment-reply page reads visible non-deleted replies with pagination.
- Comment-reply detail reads one visible non-deleted reply by `id`.
- Standalone reply reads keep the same team-project membership boundary by joining the reply target comment, owning team project, and current-user project membership.
- Sorting uses a whitelist and defaults to `ID` ascending.
- Project comment add requires `teamProjectId`, `status`, `statusColor`, `contentText`, and `mentionableUsers`.
- Project comment add stores `mentionableUsers` in `EXT_JSON` as `{"mentionableUsers":[...]}`.
- Project comment reply add requires `targetId` and `contentText`.
- Project comment and reply writes keep the same project-member boundary as the read routes.
- Project comment delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, requires imported project resource permission `delComment`, and sets `DELETE_FLAG = DELETED`.
- Project comment reply edit requires `id`, `targetId`, and `contentText`; it validates both the existing reply and requested target comment through the project-member boundary.
- Project comment reply delete accepts Java-style array bodies, `idList`, `ids`, or single `id` and sets `DELETE_FLAG = DELETED`.
- Reply edit/delete is allowed for the reply creator or a project user with imported `delComment` resource permission.
- Task comment add requires `teamProjectTaskId`, keeps the same project-member boundary as task-comment reads, writes `CATEGORY = COMMENT`, and stores submitted `files` in `EXT_JSON` as `{"file":[...]}`.
- Task comment edit/delete only maintains `CATEGORY = COMMENT` rows. `CATEGORY = LOG` rows remain read-only.
- Task comment maintenance is allowed for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.

## Response Fields

Comment rows include:

- `id`
- `teamProjectId`
- `status`
- `statusColor`
- `contentText`
- `deleteFlag`
- `extJson`
- `createTime`
- `createUser`
- `createUserName`
- `avatar`
- `updateTime`
- `updateUser`
- `tenantId`
- `bizTeamProjectCommentReplies`

Reply rows include:

- `id`
- `targetId`
- `contentText`
- `deleteFlag`
- `extJson`
- `createTime`
- `createUser`
- `createUserName`
- `avatar`
- `updateTime`
- `updateUser`
- `tenantId`

## Deferred

- Notifications and data-change events
- Team-project, task, category, and task-user mutations
