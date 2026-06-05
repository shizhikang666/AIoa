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
| GET | `/biz/bizteamprojectcommentreply/page` | `biz.TeamProjectCommentReplyController/page` |
| GET | `/biz/bizteamprojectcommentreply/detail` | `biz.TeamProjectCommentReplyController/detail` |
| POST | `/biz/bizteamprojectcommentreply/add` | `biz.TeamProjectCommentReplyController/add` |

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
- Project comment add requires `teamProjectId`, `status`, `statusColor`, `contentText`, and `mentionableUsers`.
- Project comment add stores `mentionableUsers` in `EXT_JSON` as `{"mentionableUsers":[...]}`.
- Project comment reply add requires `targetId` and `contentText`.
- Project comment and reply writes keep the same project-member boundary as the read routes.

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

- `/biz/bizteamprojectcomment/delete`
- `/biz/bizteamprojectcommentreply/edit`
- `/biz/bizteamprojectcommentreply/delete`
- Notifications and data-change events
- Team-project, task, existing-comment, or existing-reply mutations beyond add
