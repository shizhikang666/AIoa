# Team Project Comment Read-Only Compatibility

Date: 2026-06-05

Agent: api-agent / frontend-agent

## Scope

This slice supports the copied team-project comment and comment-reply API wrappers:

- `snowy-admin-web/src/api/biz/bizTeamProjectCommentApi.js`
- `snowy-admin-web/src/api/biz/bizTeamProjectCommentReplyApi.js`

The Java source remains read-only under `F:\AI\projects\testJava\OA`.

## Routes

| Method | Path | Controller |
| --- | --- | --- |
| GET | `/biz/bizteamprojectcomment/detail` | `biz.TeamProjectCommentController/detail` |
| GET | `/biz/bizteamprojectcommentreply/page` | `biz.TeamProjectCommentReplyController/page` |
| GET | `/biz/bizteamprojectcommentreply/detail` | `biz.TeamProjectCommentReplyController/detail` |

Existing covered routes remain:

- `GET /biz/bizteamprojectcomment/page`
- `GET /biz/bizteamprojectcomment/list`
- `GET /biz/bizteamprojecttaskcomment/page`
- `GET /biz/bizteamprojecttaskcomment/list`
- `GET /biz/bizteamprojecttaskcomment/detail`

## Behavior

- Project comment detail reads one visible non-deleted project timeline comment by `id`.
- Project comment detail includes nested `bizTeamProjectCommentReplies`.
- Comment-reply page reads visible non-deleted replies with pagination.
- Comment-reply detail reads one visible non-deleted reply by `id`.
- Standalone reply reads keep the same team-project membership boundary by joining the reply target comment, owning team project, and current-user project membership.
- Sorting uses a whitelist and defaults to `ID` ascending.

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

- `/biz/bizteamprojectcomment/add`
- `/biz/bizteamprojectcomment/delete`
- `/biz/bizteamprojectcommentreply/add`
- `/biz/bizteamprojectcommentreply/edit`
- `/biz/bizteamprojectcommentreply/delete`
- Notifications and data-change events
- Team-project, task, comment, or reply mutations
