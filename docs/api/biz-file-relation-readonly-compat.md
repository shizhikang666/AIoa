# Biz File Relation Read-Only Compatibility

## Scope

This slice adds read-only ThinkPHP compatibility endpoints for the old Java file-relation APIs used by the Vue OA frontend.

Java source analyzed:

- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizfilerelation/controller/BizFileRelationController.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizfilerelation/service/impl/BizFileRelationServiceImpl.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizfilerelation/param/BizFileRelationPageParam.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizfilerelation/param/BizFileRelationListParam.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizfilerelation/entity/BizFileRelation.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

Frontend source analyzed:

- `snowy-admin-web/src/api/biz/bizFileRelationApi.js`

## Added Routes

All routes are protected by `AuthMiddleware`.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/biz/bizfilerelation/page` | Paginated file-relation list. |
| GET | `/biz/bizfilerelation/list` | Non-paginated file-relation list. |
| GET | `/biz/bizfilerelation/detail` | Read-only detail lookup. |

## Explicitly Deferred Routes

These Java/frontend routes are not implemented in this slice because they mutate attachment links:

- `POST /biz/bizfilerelation/add`
- `POST /biz/bizfilerelation/edit`
- `POST /biz/bizfilerelation/delete`
- `GET /biz/bizfilerelation/projectCase/del`

## Query Compatibility

Supported query parameters:

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `objectId`
- `targetId`
- `category`
- `fileName`
- `name`
- `suffix`
- `createUser`
- `startCreateTime`, `endCreateTime`
- `searchKey`
- `tenantId`

The service reads `biz_file_relation` and enriches rows through:

- `dev_file` by `biz_file_relation.TARGET_ID`
- `sys_user` by `biz_file_relation.CREATE_USER`

## Response Shape

Rows return frontend-friendly camelCase fields:

- `id`
- `objectId`
- `targetId`
- `category`
- `fileName`
- `relationFileName`
- `deleteFlag`
- `createTime`
- `createUser`
- `extJson`
- `tenantId`
- `engine`
- `bucket`
- `name`
- `suffix`
- `sizeKb`
- `sizeInfo`
- `objName`
- `storagePath`
- `downloadPath`
- `thumbnail`
- `fileExtJson`
- `createUserName`
- `avatar`

## Notes

- Java derives relation `fileName` from `dev_file.NAME` during add. The imported SQL often has empty `FILE_NAME`, so this read service falls back to linked `dev_file.NAME` in the returned `fileName`.
- Java `list` requires `objectId` and `category`; this ThinkPHP compatibility query accepts the same filters but does not reject empty reads.
- This slice does not modify Java source, database schema, Composer files, `.env`, or any write endpoint.
