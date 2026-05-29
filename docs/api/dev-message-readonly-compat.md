# Dev Message Read-Only Compatibility

## Scope

This slice adds authenticated, read-only compatibility for the Java `DevMessageController` message query endpoints.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevMessageController`
- Service: `DevMessageServiceImpl`
- SQL tables: `dev_message`, `dev_relation`, `sys_user`

## Added Routes

- `GET /dev/message/page`
- `GET /dev/message/detail`

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
- `POST /dev/message/delete` is not implemented.
- Detail reads do not update `dev_relation.EXT_JSON`.
- Detail reads do not send SSE notifications.
- No database schema or Java source files are changed.

## Later Work

Mutation behavior needs a separate write-endpoint plan covering validation, audit logs, receiver relation updates, read-state updates, and frontend compatibility.
