# Frontend Joint Test Workflow

Date: 2026-06-01

Agent: merge-agent / main control agent

## Purpose

From this point forward, frontend adaptation is part of the refactor workflow. Backend API slices are still developed in small steps, but each completed backend slice must be considered against the Vue frontend and, once the frontend is imported into the final repository, tested with the backend service running.

Future new Codex conversations should use real multi-Agent mode by default. The main conversation is the merge/coordinator session. It assigns scoped work to role-specific workers such as `frontend-agent`, `api-agent`, `test-agent`, `docs-agent`, and other module Agents, then integrates and commits the final result. Worker Agents must only handle the explicitly assigned slice and must not broaden into merge ownership or unrelated modules.

The final delivery is still one complete project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

The worktrees remain temporary parallel workspaces and are not final standalone projects.

## Current Frontend Discovery

| Item | Current State |
| --- | --- |
| Original frontend source | `F:\AI\projects\testJava\OA\snowy-admin-web` |
| Original frontend write policy | Read-only, do not edit |
| Target repository frontend path | `F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web` |
| frontend-agent worktree | `F:\AI\projects\testJava\OA-frontend`, currently still contains the ThinkPHP backend tree only |
| Frontend dev script | `npm run dev` or `npm run serve` |
| Frontend default dev port | `83` from `.env.development` |
| Frontend default backend target | `http://localhost:82` from `.env.development` |

## Frontend Baseline Import

The frontend baseline has been copied into the final target repository.

Target:

`F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web`

Import notes:

- Copied from `F:\AI\projects\testJava\OA\snowy-admin-web` in read-only mode.
- Copied files: 908.
- Excluded files/directories: `.git`, `.idea`, `.vite`, `node_modules`, `dist`, `coverage`, `log`, `logs`, `stats.html`, `*.log`, and `vite.config.mjs.timestamp-*.mjs`.
- The first frontend import is an approved baseline exception to the normal small-commit size because it brings the existing Vue app under the target repository.
- After the baseline import, all adaptation commits should return to small commits.
- Do not commit `node_modules`, `dist`, local logs, local secrets, or runtime cache.

## Joint Startup Order

Use this order for future integrated testing.

### 1. Start MySQL And Redis

```powershell
Start-Process -FilePath "F:\project\socket\AI\testPhp\files\startServer1.bat" -WorkingDirectory "F:\project\socket\AI\testPhp\files" -WindowStyle Hidden
```

Then verify services before running application tests.

### 2. Start ThinkPHP Backend

```powershell
cd F:\AI\projects\testJava\OA-ThinkPHP
php think run --host 127.0.0.1 --port 82
```

Backend port `82` matches the current original frontend development environment.

### 3. Start Vue Frontend

After frontend import and dependency installation:

```powershell
cd F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web
npm install
npm run dev
```

Expected browser URL:

`http://127.0.0.1:83`

## Frontend Adaptation Items

frontend-agent owns these items after the baseline import:

- request base URL and Vite proxy alignment
- token header alignment
- login flow compatibility
- menu and button permission rendering
- API wrapper cleanup for endpoints already migrated to ThinkPHP
- read-only page smoke checks against current backend routes
- build and browser smoke documentation

Backend agents own these items:

- missing ThinkPHP routes
- controller/service behavior
- response shape compatibility
- permission middleware behavior
- write-flow implementation

Do not let frontend-agent modify backend business code unless a separate public-file or module request is approved.

## Token Compatibility Note

Project backend convention:

`Authorization: Bearer <token>`

Original frontend convention observed:

`token: <token>`

Future adaptation should make the frontend send the backend convention. For transition safety, backend may also accept the legacy `token` header if a compatibility slice explicitly documents and tests it.

## Login Compatibility Note

The original frontend may encrypt the login password with SM2 when a public key is configured. The backend already isolates password verification logic, but browser login must be tested end to end after frontend import.

No SM2 key, password, Redis credential, database password, or token may be committed to the repository.

For local login smoke tests, read the superadmin credentials from the ignored project `.env`:

- `LOCAL_SUPER_ADMIN_ACCOUNT`
- `LOCAL_SUPER_ADMIN_PASSWORD`

Do not place plaintext local account names, passwords, tokens, or generated credential values in this document, screenshots, task notes, commits, or final reports.

## Joint Smoke Checklist

Run this checklist after frontend import and after every backend route slice that affects visible pages.

| Step | Expected Result |
| --- | --- |
| Backend `composer dump-autoload` | Pass |
| Backend `php think` | Pass |
| Backend `php think route:list` | Pass |
| Backend PHP lint | Pass |
| Frontend `npm install` if dependencies missing | Pass |
| Frontend `npm run dev` | Starts on port `83` |
| Login as superadmin test account | Browser reaches main layout |
| Current user and menu load | No fatal error, menu renders |
| User/org/position read pages | Page loads read data or records missing endpoint |
| Business read pages | Product, supplier, warehouse, inventory, finance, team project, and return order reads load where implemented |
| Write buttons and mutation flows | Either hidden by permission/deferred state or fail safely until implemented |
| Browser console | No blocking runtime error on tested pages |

## Browser Upload Smoke, 2026-06-08

Coordinator plus sub-agent mode was used. Popper performed read-only frontend path discovery while the main session ran browser automation against the live local services.

Runtime:

- Backend: `http://127.0.0.1:82`
- Frontend: `http://127.0.0.1:83`
- Login credentials came from the ignored project `.env`.

Verified:

- Login reaches the main layout and menu.
- Dev file page path is `/dev/file/index`; local upload from the visible "文件上传" button calls `POST /api/dev/file/uploadLocalReturnUrl`, returns `code=200`, refreshes `/api/dev/file/page`, and shows the temporary uploaded file.
- Product page path is `/biz/bizproduct`; the "新增单品产品" form's `XnUpload` cover-image control calls `POST /api/dev/file/uploadDynamicReturnUrl`, returns `code=200`, and the image preview download calls `/api/dev/file/download?id=<id>`.
- Business attachment smoke path is `/biz/saleproject/dealProjectList`; opening the first deal project, selecting the "项目附件" tab, and uploading a file calls `POST /api/dev/file/uploadLocalReturnFile`, then `POST /api/biz/bizfilerelation/add`, then refreshes `GET /api/biz/bizfilerelation/list?objectId=<projectId>&category=SALE_PROJECT`. The uploaded attachment appears in the tab list.

Cleanup:

- Temporary `CODEX_UI_*` `dev_file` rows were deleted.
- The temporary business file relation row was deleted.
- Temporary uploaded physical files under `runtime/upload/dev_file` were deleted.

Follow-up:

- Rich-text image upload was covered later through the visible email send form; see `Browser Rich-Text Upload Smoke, 2026-06-11`.

## Browser Rich-Text Upload Smoke, 2026-06-11

Coordinator plus sub-agent mode was used. Helmholtz performed read-only TinyMCE/upload entry discovery while the main session ran browser automation against the live local services.

Runtime:

- Backend: `http://127.0.0.1:82`
- Frontend: `http://127.0.0.1:83`
- Login credentials came from the ignored project `.env`.

Verified:

- Page path: `/dev/email/index`.
- Because the current local admin menu did not include `/dev/email/index`, the smoke used a minimal temporary browser `MENU` cache so the copied Vue dynamic route could load directly.
- The visible "发送邮件" form opened through `snowy-admin-web/src/views/dev/email/form.vue`.
- The local email tab was switched from plain text to `HTML`, mounting the copied TinyMCE wrapper from `snowy-admin-web/src/components/Editor/index.vue`.
- The email send component passes `fileUploadFunction`, so TinyMCE image upload called `POST /api/dev/file/uploadDynamicReturnUrl`.
- The upload endpoint returned `code=200` and a Java-compatible `/api/dev/file/download?id=<id>` URL.
- Browser console had no blocking error during the smoke.
- Screenshot artifact for local inspection: `runtime/codex-smoke/dev-email-richtext-upload.png`.

Cleanup:

- The uploaded `dev_file` row was deleted after first passing through the API delete path.
- The temporary physical file under `runtime/upload/dev_file` was removed.
- A database back-check confirmed no `CODEX_RICHTEXT_*` `dev_file` rows remained.

Follow-up coverage:

- The old `components/Editor` default upload fallback was covered on 2026-06-12; see `Browser Old Editor Fallback Smoke, 2026-06-12`.

## Browser Old Editor Fallback Smoke, 2026-06-12

Coordinator plus sub-agent mode was used. Einstein checked the copied TinyMCE wrappers and fallback behavior, Bernoulli checked old `components/Editor` consumers and route exposure, and the main session implemented the compatibility change plus browser verification.

Runtime:

- Backend: `http://127.0.0.1:82`
- Frontend: `http://127.0.0.1:83`
- Login credentials came from the ignored project `.env`.

Verified:

- Page path: `/exm/editor`.
- The smoke used a minimal temporary browser `MENU` cache because the sample editor page is not exposed by the imported local admin menu.
- The temporary dynamic route loaded `snowy-admin-web/src/views/exm/editor/index.vue`, which mounts the old wrapper from `snowy-admin-web/src/components/Editor/index.vue` without passing `fileUploadFunction`.
- TinyMCE image upload fell back to `POST /api/dev/file/uploadDynamicReturnUrl`.
- The upload handler returned a Java-compatible `/api/dev/file/download?id=<id>` URL.
- Browser console had no blocking error during the smoke.

Cleanup:

- The uploaded row was first deleted through `POST /dev/file/delete`.
- The temporary `dev_file` row was hard-deleted after the smoke back-check.
- The temporary physical file under `runtime/upload/dev_file` was removed.

## Browser Workflow Read-Only Smoke, 2026-06-15

Coordinator plus sub-agent mode was used. Mencius reviewed the workflow diff as a read-only sidecar check while the main session fixed task/process row shape and ran API plus browser verification.

Runtime:

- Backend: `http://127.0.0.1:82`
- Frontend: `http://127.0.0.1:83`
- Login credentials came from the ignored project `.env`.

Verified:

- API shape check covered `/biz/task/page`, `/biz/task/history/page`, `/biz/process/page`, `/biz/process/all/page`, and `/biz/ccrecords/page`; every call returned HTTP 200 with `code=200`.
- Task page rows preserve task ids as `id`/`taskId`, while process instance ids are exposed through `instanceId` and `processInstanceId`.
- Process page rows keep `id` as the process instance id.
- A temporary browser `MENU` cache loaded copied workflow routes directly through Vue `createWebHistory` paths:
  - `/biz/biztask`
  - `/biz/biztask/historyTask`
  - `/biz/biztask/mystarttask`
  - `/biz/biztask/allprocess`
  - `/biz/biztask/copytask`
- Each page rendered an Ant table or empty state and hit its corresponding read endpoint.
- Browser console had no blocking errors.
- The smoke observed no approve, reject, cancel, start, edit, CC delete, or task SSE write requests.

Not verified:

- Workflow start, approve, reject, cancel, edit, SSE, and business side-effect hooks remain intentionally deferred.
- `useProcessParam` is guarded for missing `SYS_CONFIG`, but it is mainly exercised by workflow start/edit forms, not by this read-only list smoke.

## Team Project Base HTTP Smoke, 2026-06-08

Verified after `/biz/bizteamproject/add`, `/edit`, and `/delete` compatibility was added:

- Backend: `http://127.0.0.1:82`
- Command: `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke`
- Add posted JSON to `/biz/bizteamproject/add`, returned `code=200`, and created a temporary project.
- Database back-check confirmed the current token user became `LEADER` in `biz_team_project_user`.
- Database back-check confirmed `biz_relation.CATEGORY = TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` includes `delProject`.
- Member edit posted JSON to `/biz/bizteamprojectuser/edit`, returned `code=200`, refreshed member audit fields, and did not change role or permission relation JSON.
- Edit posted JSON to `/biz/bizteamproject/edit`, returned `code=200`, updated description/status, and incremented `VERSION`.
- Delete posted Java-style `[{ id }]` JSON to `/biz/bizteamproject/delete`, returned `code=200`, and marked project/member rows `DELETED`.

Cleanup:

- Temporary `biz_team_project_user`, `biz_relation`, and `biz_team_project` rows were deleted.

Not verified:

- The visible Vue team-project page button flow was not browser-smoked in this slice; HTTP smoke covered the backend endpoints used by the copied page.

## Sys Field Write HTTP Smoke, 2026-06-10

Verified after `/sys/field/add`, `/edit`, and `/delete` compatibility was added:

- Backend: `http://127.0.0.1:82`
- Command: `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysFieldHttpSmoke`
- Add posted JSON to `/sys/field/add`, returned `code=200`, and created a temporary `sys_resource.CATEGORY = FIELD` row under a temporary menu.
- Page lookup through `/sys/field/page?parentId=<menuId>&searchKey=<prefix>` returned the created field.
- Detail lookup through `/sys/field/detail?id=<fieldId>` returned the created field.
- Duplicate sibling `code` was rejected.
- Edit posted JSON to `/sys/field/edit`, returned `code=200`, and updated title/code/sort order.
- Delete posted Java-style `[{ id }, { id: "missing" }]` JSON to `/sys/field/delete`, returned `code=200`, marked the target field `DELETED`, preserved the sibling field, and removed a direct `SYS_ROLE_HAS_RESOURCE` relation targeting the field.

Cleanup:

- Temporary `sys_relation` and `sys_resource` rows were deleted.

Browser smoke on 2026-06-11:

- Real dynamic routes from `sys_resource`: `/sys/module` and `/sys/menu`.
- Copied menu page loaded through `snowy-admin-web/src/views/sys/resource/menu/index.vue`.
- Copied frontend API wrappers loaded: `menuApi.js`, `buttonApi.js`, and `fieldApi.js`.
- The current local admin menu data did not include `/sys/module` or `/sys/menu`, so the smoke inserted temporary `SYS_USER_HAS_RESOURCE` rows in `sys_relation`, logged in again, verified the page, and deleted the temporary relation rows afterward.
- `/sys/menu` loaded and called `GET /api/sys/menu/moduleSelector` plus `GET /api/sys/menu/tree`.
- After expanding a catalog row, a `MENU` row's `更多` dropdown exposed both `按钮权限` and `字段权限`.
- Clicking `字段权限` opened the field drawer and called `GET /api/sys/field/page`.
- Screenshot artifact for local inspection: `runtime/codex-smoke/sys-menu-field-drawer-expanded.png`.

## Browser Dev File Delete Smoke, 2026-06-08

Verified after `/dev/file/delete` compatibility was added:

- Page path: `/dev/file/index`
- The visible file upload control posts to `POST /api/dev/file/uploadLocalReturnUrl`.
- Row delete posts to `POST /api/dev/file/delete`.
- The temporary `dev_file` row is marked `DELETE_FLAG = DELETED`.
- The filtered table refreshes through `GET /api/dev/file/page` and the temporary row disappears.

Cleanup:

- Temporary `CODEX_UI_DELETE_*` `dev_file` rows were removed.
- Temporary uploaded physical files under `runtime/upload/dev_file` were removed.

## Browser Dev Email/SMS Delete Smoke, 2026-06-08

Verified after `/dev/email/delete` and `/dev/sms/delete` compatibility was added:

- Page paths: `/dev/email/index` and `/dev/sms/index`
- Test setup used a short-lived local token and a minimal browser menu cache so the copied Vue routes could load directly.
- Row delete on the email page posts to `POST /api/dev/email/delete`.
- Row delete on the SMS page posts to `POST /api/dev/sms/delete`.
- The temporary `dev_email` and `dev_sms` rows are marked `DELETE_FLAG = DELETED`.

Cleanup:

- Temporary `CODEX_UI_NOTIFY_*` `dev_email` and `dev_sms` rows were removed.

## Browser Dev Config Other Config Smoke, 2026-06-08

Verified after `/dev/config/add`, `/dev/config/edit`, and `/dev/config/delete` compatibility was added:

- Page path: `/dev/config`
- Test setup used a short-lived local token and a minimal browser menu cache so the copied Vue route could load directly.
- The "其他配置" tab loaded the copied `otherConfig` table through `GET /api/dev/config/page`.
- The visible add drawer posted to `POST /api/dev/config/add`, returned `code=200` with `data = null`, refreshed the table, and showed the temporary `CODEX_UI_CONFIG_*` row.
- The row edit drawer posted to `POST /api/dev/config/edit`, returned `code=200` with `data = null`, and the database value changed from `value-a` to `value-b`.
- The row delete action posted to `POST /api/dev/config/delete`, returned `code=200` with `data = null`, and the temporary row reached `DELETE_FLAG = DELETED`.

Cleanup:

- Temporary `CODEX_UI_CONFIG_*` `dev_config` rows were removed after verification.

## Dev Log Delete HTTP Smoke, 2026-06-08

Verified after `/dev/log/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/dev/logApi.js`
- Target copied frontend page: not present in `snowy-admin-web/src/views/dev/log` at the time of this slice, so no real page-button browser smoke was possible.
- Authenticated HTTP smoke inserted temporary `dev_log` rows, posted `{ "category": "CODEX_HTTP_LOG_*" }` to `POST /dev/log/delete`, and verified the response returned `code=200` with `data = null`.
- Database verification confirmed the target category row was physically deleted and a different temporary category remained until cleanup.

Cleanup:

- Temporary `CODEX_HTTP_LOG_*` `dev_log` rows were removed.

## Browser Dev Job Delete Smoke, 2026-06-08

Verified after `/dev/job/delete` compatibility was added:

- Page path: `/dev/job/index`
- Test setup inserted a temporary `CODEX_UI_JOB_*` `dev_job` row, then used a short-lived local token and minimal browser menu cache to load the copied Vue page directly.
- The table loaded through `GET /api/dev/job/page` and showed the temporary job row.
- Row delete posted to `POST /api/dev/job/delete`.
- The table refreshed through `GET /api/dev/job/page`.
- Database verification confirmed the temporary row reached `DELETE_FLAG = DELETED`.

Cleanup:

- Temporary `CODEX_UI_JOB_*` `dev_job` rows were removed.

## Gen Config EditBatch HTTP Smoke, 2026-06-08

Verified after `/gen/config/editBatch` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/gen/genConfigApi.js`
- Copied frontend component: `snowy-admin-web/src/views/gen/config.vue`
- Target copied frontend component is nested inside generator metadata editing rather than a stable standalone route, so this slice used authenticated HTTP smoke instead of browser button automation.
- Authenticated HTTP smoke inserted temporary `gen_config` rows, posted a malformed mixed array to `POST /gen/config/editBatch`, and verified the valid row was not partially updated.
- Authenticated HTTP smoke posted a valid Java-style array to `POST /gen/config/editBatch`, verified the response returned `code=200` with `data = null`, and confirmed both temporary rows were updated while `DELETE_FLAG` was not client-overwritten.

Cleanup:

- Temporary `CODEX_HTTP_GENCFG_*` `gen_config` rows were removed.

## Sale Project Invoicing Complete HTTP Smoke, 2026-06-08

Verified after `/biz/saleprojectinvoicing/complete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/biz/bizSaleProjectInvoicingApi.js`
- Copied frontend page: `snowy-admin-web/src/views/biz/saleprojectinvoicing/index.vue`
- Authenticated HTTP smoke inserted temporary `biz_sale_project` and `biz_sale_project_invoicing` rows.
- A cross-tenant complete request to `POST /biz/saleprojectinvoicing/complete` returned a business error and did not update the row.
- A valid complete request returned `code=200` with `data = null` and updated the target row to `INVOICING_STATE_COMPLETE`.

Cleanup:

- Temporary `CODEX_HTTP_INVOICING_*` project and invoicing rows were removed.

## Sys Button Write HTTP Smoke, 2026-06-09

Verified after `/sys/button/add`, `/sys/button/edit`, and `/sys/button/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/sys/resource/buttonApi.js`
- Copied frontend pages: `snowy-admin-web/src/views/sys/resource/button/index.vue` and `snowy-admin-web/src/views/sys/resource/button/form.vue`
- Authenticated HTTP smoke creates a temporary button under an existing menu resource, calls `POST /sys/button/add`, and verifies the button appears in `GET /sys/button/page`.
- The same smoke verifies duplicate `code` rejection, calls `POST /sys/button/edit`, prepares a temporary `SYS_ROLE_HAS_RESOURCE` relation, calls `POST /sys/button/delete`, and confirms the row reaches `DELETE_FLAG = DELETED`.
- Database verification confirms the deleted button id is removed from `sys_relation.EXT_JSON.buttonInfo` while unrelated button ids remain.

Cleanup:

- Temporary `CODEX_HTTP_BUTTON_*` button rows and temporary smoke relation rows are removed.

## Sys Module Write HTTP Smoke, 2026-06-09

Verified after `/sys/module/add`, `/sys/module/edit`, and `/sys/module/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/sys/resource/moduleApi.js`
- Copied frontend pages: `snowy-admin-web/src/views/sys/resource/module/index.vue` and `snowy-admin-web/src/views/sys/resource/module/form.vue`
- Authenticated HTTP smoke creates a temporary module, calls `POST /sys/module/add`, and verifies the module appears in `GET /sys/module/page`.
- The same smoke verifies duplicate `title` rejection, calls `POST /sys/module/edit`, prepares a temporary child menu and `SYS_ROLE_HAS_RESOURCE` relation, calls `POST /sys/module/delete`, and confirms the module and child menu reach `DELETE_FLAG = DELETED`.
- Database verification confirms the temporary role-resource relation is removed.

Cleanup:

- Temporary `CODEX_HTTP_MODULE_*` module/menu rows and temporary smoke relation rows are removed.

## Sys Menu Write HTTP Smoke, 2026-06-10

Verified after `/sys/menu/add`, `/sys/menu/edit`, `/sys/menu/changeModule`, and `/sys/menu/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/sys/resource/menuApi.js`
- Copied frontend pages: `snowy-admin-web/src/views/sys/resource/menu/index.vue`, `form.vue`, and `changeModuleForm.vue`
- Authenticated HTTP smoke creates temporary system modules, calls `POST /sys/menu/add`, and verifies the created menu appears in `GET /sys/menu/tree`.
- The same smoke verifies duplicate sibling-title rejection, child parent/module mismatch rejection, `POST /sys/menu/edit`, `IFRAME` field normalization, self/descendant parent rejection, child `changeModule` rejection, and root `POST /sys/menu/changeModule` propagation to child menu rows.
- The same smoke prepares a temporary system button and `SYS_ROLE_HAS_RESOURCE` relation, calls `POST /sys/menu/delete` with a mixed existing and missing id payload, confirms the menu/button tree reaches `DELETE_FLAG = DELETED`, and confirms the relation row is removed.

Cleanup:

- Temporary `CODEX_HTTP_SYS_MENU_*` module/menu/button rows and temporary smoke relation rows are removed.

Not verified:

- The visible Vue system menu page button flow was not browser-smoked in this slice; HTTP smoke covered the backend endpoints used by the copied page.

## Mobile Button Write HTTP Smoke, 2026-06-09

Verified after `/mobile/button/add`, `/mobile/button/edit`, and `/mobile/button/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/mobile/resource/buttonApi.js`
- Copied frontend pages: `snowy-admin-web/src/views/mobile/resource/button/index.vue` and `snowy-admin-web/src/views/mobile/resource/button/form.vue`
- Authenticated HTTP smoke creates a temporary button under an existing mobile menu resource, calls `POST /mobile/button/add`, and verifies the button appears in `GET /mobile/button/page`.
- The same smoke verifies duplicate `code` rejection, calls `POST /mobile/button/edit`, prepares a temporary `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `POST /mobile/button/delete` with a mixed existing and missing id payload, and confirms the row reaches `DELETE_FLAG = DELETED`.
- Database verification confirms the deleted button id is removed from `sys_relation.EXT_JSON.buttonInfo` while unrelated button ids remain.

Cleanup:

- Temporary `CODEX_HTTP_MOBILE_BUTTON_*` button rows, temporary mobile menu rows created only if needed, and temporary smoke relation rows are removed.

Browser note:

- The standalone mobile button drawer is browser-smoked through the mobile menu page in the 2026-06-11 mobile resource browser smoke below.

## Mobile Module Write HTTP Smoke, 2026-06-09

Verified after `/mobile/module/add`, `/mobile/module/edit`, and `/mobile/module/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/mobile/resource/moduleApi.js`
- Copied frontend pages: `snowy-admin-web/src/views/mobile/resource/module/index.vue` and `snowy-admin-web/src/views/mobile/resource/module/form.vue`
- Authenticated HTTP smoke creates a temporary module, calls `POST /mobile/module/add`, and verifies the module appears in `GET /mobile/module/page`.
- The same smoke verifies duplicate `title` rejection, calls `POST /mobile/module/edit`, prepares temporary child mobile menu rows and a `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `POST /mobile/module/delete` with a mixed existing and missing id payload, and confirms module/menu rows reach `DELETE_FLAG = DELETED`.
- Database verification confirms the temporary mobile-menu relation row is removed.

Cleanup:

- Temporary `CODEX_HTTP_MOBILE_MODULE_*` module/menu rows and temporary smoke relation rows are removed.

Browser note:

- The visible Vue mobile module page is browser-smoked in the 2026-06-11 mobile resource browser smoke below.

## Mobile Menu Write HTTP Smoke, 2026-06-09

Verified after `/mobile/menu/add`, `/mobile/menu/edit`, `/mobile/menu/changeModule`, and `/mobile/menu/delete` compatibility was added:

- Copied frontend API wrapper: `snowy-admin-web/src/api/mobile/resource/menuApi.js`
- Copied frontend pages: `snowy-admin-web/src/views/mobile/resource/menu/index.vue` and `snowy-admin-web/src/views/mobile/resource/menu/form.vue`
- Authenticated HTTP smoke creates temporary mobile modules, calls `POST /mobile/menu/add`, and verifies the created menu appears in `GET /mobile/menu/tree`.
- The same smoke verifies duplicate sibling-title rejection, child parent/module mismatch rejection, `POST /mobile/menu/edit`, child `changeModule` rejection, and root `POST /mobile/menu/changeModule` propagation to child menu rows.
- The same smoke prepares a temporary mobile button and `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `POST /mobile/menu/delete` with a mixed existing and missing id payload, confirms the menu tree reaches `DELETE_FLAG = DELETED`, confirms the relation row is removed, and confirms the button row is preserved.

Cleanup:

- Temporary `CODEX_HTTP_MOBILE_MENU_*` module/menu/button rows and temporary smoke relation rows are removed.

Browser smoke on 2026-06-11:

- Real copied frontend routes used for smoke: `/mobile/module` and `/mobile/menu`.
- The imported database did not include dynamic `sys_resource` menu rows for those copied mobile resource pages, so the smoke inserted temporary marked `sys_resource` menu rows and `SYS_USER_HAS_RESOURCE` relations, then deleted them after verification.
- Temporary marked `mobile_resource` module/menu/button rows were inserted for a deterministic page target and deleted after verification.
- `/mobile/module` loaded the copied module page and called `GET /api/mobile/module/page`.
- `/mobile/menu` loaded the copied menu page and called `GET /api/mobile/menu/moduleSelector` plus `GET /api/mobile/menu/tree`.
- After selecting the temporary mobile module radio, the temporary root and child mobile menu rows were visible.
- Opening the child menu row's more dropdown exposed the mobile button permission drawer entry.
- Opening the button permission drawer called `GET /api/mobile/button/page` and displayed the temporary button row.
- Screenshot artifact for local inspection: `runtime/codex-smoke/mobile-resource-button-drawer-selected.png`.

Cleanup:

- Temporary `sys_relation`, `sys_resource`, and `mobile_resource` rows with the browser-smoke markers were deleted; remaining counts were verified as zero.

## Gap Recording Rule

Any frontend call that fails because the backend route is missing must be recorded in:

`docs/tasks/api-gap-map.md`

If that file does not exist yet, create it before the first full browser test.

## Definition Of Done For Frontend Phase

frontend-agent is not complete until:

- frontend source exists in the target repository
- local dev server starts
- production build succeeds or known build blockers are documented
- login works against ThinkPHP
- menu and button permissions render from ThinkPHP data
- main migrated read-only pages are browser-smoked
- missing write-flow calls are recorded and assigned
- no secrets or generated dependency directories are committed
