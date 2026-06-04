# Biz History Excel Read-Only API

Date: 2026-06-04

Agent: api-agent / frontend-agent

## Java Source

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizhistoryexcel\controller\BizHistoryExcelController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizhistoryexcel\service\impl\BizHistoryExcelServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizhistoryexcel\entity\BizHistoryExcel.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

The Java project is read-only.

## ThinkPHP Routes

Protected by `AuthMiddleware`:

- `GET /biz/bizhistoryexcel/page`
- `GET /biz/bizhistoryexcel/detail`

## Behavior

- `page` reads `biz_history_excel` rows with Java-compatible pagination.
- `detail` reads one row by `id`.
- Sorting is restricted to a whitelist matching known frontend fields.
- Logical deleted rows are excluded by `DELETE_FLAG IS NULL OR DELETE_FLAG = NOT_DELETE`.
- Response rows keep original database keys and add frontend-friendly camelCase aliases.

## Response Fields

- `id` / `ID`
- `name` / `NAME`
- `remark` / `REMARK`
- `deleteFlag` / `DELETE_FLAG`
- `extJson` / `EXT_JSON`
- `createTime` / `CREATE_TIME`
- `createUser` / `CREATE_USER`
- `updateTime` / `UPDATE_TIME`
- `updateUser` / `UPDATE_USER`
- `tenantId` / `TENANT_ID`

## Deferred

- `/biz/bizhistoryexcel/add`
- `/biz/bizhistoryexcel/edit`
- `/biz/bizhistoryexcel/delete`
- Excel import/export parsing changes
- Spreadsheet storage writes

## Verification

Run:

```powershell
php think route:list
```

Expected:

- `biz/bizhistoryexcel/page`
- `biz/bizhistoryexcel/detail`

Requests without a bearer token should return `code = 401`.
