# Biz History Excel API

Updated: 2026-06-05

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
- `POST /biz/bizhistoryexcel/add`
- `POST /biz/bizhistoryexcel/edit`
- `POST /biz/bizhistoryexcel/delete`

## Behavior

- `page` reads `biz_history_excel` rows with Java-compatible pagination.
- `detail` reads one row by `id`.
- `add` creates one history Excel row with `name` and submitted `extJson`.
- `edit` requires `id` and updates submitted `extJson`, matching Java `BizHistoryExcelEditParam`.
- `delete` accepts Java-style array bodies, `idList`, `ids`, or a single `id` and sets `DELETE_FLAG = DELETED`.
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

- Excel import/export parsing changes
- Spreadsheet storage writes
- `biz_history_excel_row` row-table parsing/writes
- frontend Excel parser changes

## Verification

Run:

```powershell
php think route:list
```

Expected:

- `biz/bizhistoryexcel/page`
- `biz/bizhistoryexcel/detail`
- `biz/bizhistoryexcel/add`
- `biz/bizhistoryexcel/edit`
- `biz/bizhistoryexcel/delete`

Requests without a bearer token should return `code = 401`.
