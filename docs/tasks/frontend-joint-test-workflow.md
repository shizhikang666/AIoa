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

Not verified:

- Rich-text image upload was not browser-smoked because the currently tested visible pages do not expose a TinyMCE image-upload control. When a rich-text page is exposed, smoke the TinyMCE image button against `/dev/file/uploadDynamicReturnUrl`.

## Team Project Base HTTP Smoke, 2026-06-08

Verified after `/biz/bizteamproject/add`, `/edit`, and `/delete` compatibility was added:

- Backend: `http://127.0.0.1:82`
- Command: `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke`
- Add posted JSON to `/biz/bizteamproject/add`, returned `code=200`, and created a temporary project.
- Database back-check confirmed the current token user became `LEADER` in `biz_team_project_user`.
- Database back-check confirmed `biz_relation.CATEGORY = TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` includes `delProject`.
- Edit posted JSON to `/biz/bizteamproject/edit`, returned `code=200`, updated description/status, and incremented `VERSION`.
- Delete posted Java-style `[{ id }]` JSON to `/biz/bizteamproject/delete`, returned `code=200`, and marked project/member rows `DELETED`.

Cleanup:

- Temporary `biz_team_project_user`, `biz_relation`, and `biz_team_project` rows were deleted.

Not verified:

- The visible Vue team-project page button flow was not browser-smoked in this slice; HTTP smoke covered the backend endpoints used by the copied page.

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
