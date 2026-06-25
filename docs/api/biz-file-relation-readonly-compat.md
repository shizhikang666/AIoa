# Biz File Relation Compatibility

## Scope

This slice documents the ThinkPHP compatibility endpoints for the old Java file-relation APIs used by the Vue OA frontend. It now covers reads plus the low-risk relation writes needed after local file upload.

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
| GET | `/biz/bizfilerelation/detail` | Detail lookup. |
| POST | `/biz/bizfilerelation/add` | Bind an uploaded `dev_file` row to a business object. |
| POST | `/biz/bizfilerelation/edit` | Frontend compatibility route for editing a relation row. |
| POST | `/biz/bizfilerelation/delete` | Frontend compatibility route for logical relation delete. |
| GET | `/biz/bizfilerelation/projectCase/del` | Java project-case delete route; logical relation delete only. |

## Write Compatibility

`POST /biz/bizfilerelation/add` accepts JSON:

- `objectId`
- `targetId`
- `category`

The category must match the Java enum values:

- `SALE_PROJECT`
- `Process_reimbursement`
- `SALE_PROJECT_CASE`

The service writes:

- `OBJECT_ID` from `objectId`
- `TARGET_ID` from `targetId`
- `CATEGORY` from `category`
- `FILE_NAME` from linked `dev_file.NAME`
- `DELETE_FLAG=NOT_DELETE`
- `CREATE_TIME`, `CREATE_USER`, `TENANT_ID`
- `EXT_JSON=null`

The linked file must be an active `dev_file` row in the current token tenant. Relation edit and delete also scope writes to the current token tenant.

Delete routes only mark `biz_file_relation.DELETE_FLAG=DELETED`. They do not delete the linked `dev_file` metadata row and do not delete the physical file, matching the Java `removeByIds` logical-delete behavior.

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

Client-provided `tenantId` is ignored for tenant scoping. Reads and writes use the tenant carried by the authenticated token payload.

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
- Linked local-file rows normalize `downloadPath` to `/api/dev/file/download?id=<targetId>` so copied frontend file links use the current ThinkPHP download route. Non-local file rows keep their stored path.
- Active `POST /biz/process/leave/start` now mirrors Java `CopyUserDelegate` file binding for `Process_ask_leave`: submitted `fileIdList` values create `biz_file_relation` rows with `OBJECT_ID = processInstanceId`, `TARGET_ID = dev_file.ID`, `CATEGORY = Process_ask_leave`, and `FILE_NAME = dev_file.NAME`.
- Workflow-generated `Process_ask_leave` file rows are read through `/biz/process/fileList`; the manual `/biz/bizfilerelation/add` category whitelist is unchanged.
- This slice does not modify Java source, database schema, Composer files, or `.env`.
- Real cloud upload engines, thumbnail generation, and physical file cleanup remain deferred to separate slices. File metadata logical delete is covered by `/dev/file/delete`.
