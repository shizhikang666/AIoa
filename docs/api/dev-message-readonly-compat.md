# Dev Message Compatibility

## Scope

This document tracks authenticated compatibility for Java `DevMessageController` message management endpoints.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevMessageController`
- Service: `DevMessageServiceImpl`
- SQL tables: `dev_message`, `dev_relation`, `sys_user`

## Added Routes

- `GET /dev/message/page`
- `GET /dev/message/detail`
- `POST /dev/message/delete`

All routes are protected by `AuthMiddleware`.

## Response Shape

Page responses return:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

Rows include Java-compatible camel-case fields plus receiver counts:

- `id`
- `category`
- `subject`
- `content`
- `extJson`
- `tenantId`
- `receiveCount`
- `readCount`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

Detail responses also include `receiveInfoList`, with:

- `receiveUserId`
- `receiveUserName`
- `read`

## Supported Filters

- `id`
- `category`
- `searchKey`
- `receiveUserId`
- `receiverId`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

Supported sort fields are:

- `id`
- `category`
- `subject`
- `createTime`
- `updateTime`

## Deliberate Exclusions

- `POST /dev/message/send` is not implemented.
- Detail reads do not update `dev_relation.EXT_JSON`.
- Detail reads do not send SSE notifications.
- Delete does not send SSE/WebPush notifications.
- No database schema or Java source files are changed.

## Delete Behavior

`POST /dev/message/delete` accepts Java-style arrays:

```json
[
  { "id": "2032011542112157698" }
]
```

It also accepts `idList`, `ids`, or a single `id` for frontend compatibility.

The endpoint:

- validates a non-empty id list
- limits rows to the current tenant when the bearer token carries `tenant_id`
- allows admin-compatible accounts or roles to delete tenant messages
- allows ordinary users to delete only messages they created
- deletes `dev_relation` rows with `CATEGORY = MSG_TO_USER`
- deletes the selected `dev_message` rows

## Later Work

Message send behavior still needs a separate write-endpoint plan covering validation, receiver relation creation, SSE/WebPush behavior, and frontend compatibility.
