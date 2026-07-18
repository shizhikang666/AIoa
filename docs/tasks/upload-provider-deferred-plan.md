# Upload, Provider, And File Cleanup Deferred Plan

Date: 2026-06-16

Agent: test-agent / api-agent / provider-agent

## Purpose

This plan fixes the next execution boundary after login-menu read browser smoke coverage. It is not permission to implement cloud storage, real provider sends, scheduler execution, or physical file cleanup.

Use it when a copied frontend page contains upload controls, provider send buttons, or file attachment actions and the next slice needs a concrete smoke pattern before touching the page.

## Current Confirmed State

- LOCAL and dynamic file upload routes are implemented for `SNOWY_SYS_DEFAULT_FILE_ENGINE = LOCAL`.
- Public local download compatibility is implemented through `GET /dev/file/download?id=<id>`.
- `dev_file` delete is logical only and intentionally does not remove local files or cloud objects.
- Business file relation binding and delete are implemented separately from physical file deletion.
- Aliyun, Tencent, and Minio upload routes are registered as unsupported cloud stubs and must return controlled unsupported responses until provider configuration is approved.
- SMS provider send wrapper routes are registered as controlled-deferred responses and must not call provider SDKs.
- Real email provider sending is not implemented.
- Thumbnail generation, historical storage-path whitelist migration, and optional physical cleanup are deferred.

## Stop Conditions

Stop and ask for an explicit plan before doing any of these:

- Changing `.env`, config files, provider credentials, cloud bucket settings, storage roots, Composer packages, Java source, database schema, or production data.
- Switching `SNOWY_SYS_DEFAULT_FILE_ENGINE` away from `LOCAL`.
- Implementing or clicking real cloud upload paths for Aliyun, Tencent, or Minio.
- Implementing or clicking real SMS or email sends.
- Implementing physical file cleanup, orphan scanning, historical file migration, thumbnail generation, or path-root rewrites.
- Running broad browser automation that may click upload, send, delete, complete, approve, reject, start, grant, reset, enable, disable, import, export, save, or scheduler run controls.

## Browser Smoke Rule

For copied pages that only need render or read-detail verification, keep upload and provider actions unclicked and run the browser helper with an explicit forbidden pattern:

```powershell
.\scripts\browser-page-smoke.ps1 -TargetPath "<path>" -MinRows 0 -ForbiddenPathPattern '(/|^)(add|edit|delete|del|complete|upload|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save)(\b|\?|/)'
```

If a page can legitimately render local images or attachments through `GET /api/dev/file/download`, keep `download` out of the forbidden pattern. If a specific page must prove that no binary or attachment read occurs, opt in to the stricter pattern:

```powershell
.\scripts\browser-page-smoke.ps1 -TargetPath "<path>" -MinRows 0 -ForbiddenPathPattern '(/|^)(add|edit|delete|del|complete|download|upload|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save)(\b|\?|/)'
```

Use `-ClickFirstTableLink` only when the first visible table link is known to open a read-only detail drawer. Use `-AllowMissingTableLink` only when list or empty-state rendering is an acceptable pass condition.

For the current default smoke set, use the batch wrapper:

```powershell
.\scripts\browser-upload-provider-guard-smoke.ps1
```

The wrapper runs guarded browser smokes sequentially for `/dev/file/index`, `/biz/bizpayroll`, `/biz/bizproduct`, `/biz/customer`, and `/biz/saleproject/dealProjectList`. It uses a stricter forbidden pattern that also blocks import/export/scheduler-run style requests while still allowing legitimate local image reads through `GET /api/dev/file/download`.

For guarded management-page render checks, pass explicit target paths:

```powershell
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/sys/user','/biz/user','/sys/role','/sys/org','/sys/position'
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/org','/biz/position','/biz/dict/index','/sys/sysConfig/index','/dev/message/index'
```

These commands are render-only checks for pages with visible import/export/grant/reset/send/save-style controls. Keep them list/static-only unless a page has a known read-only detail link.

For guarded finance, purchase, inventory, and business-operation list checks, pass explicit target paths:

```powershell
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/bizpurchaseorder','/biz/returnorder','/biz/bizcollectionreceipt','/biz/bizdebitnote','/biz/inventory'
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/paymentrecord','/biz/bizexpenditurerecord','/biz/settlementaccount','/biz/warehouses','/biz/supplier','/biz/saleprojectinvoicing'
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/saleprojectproductinfo'
```

The final command is intentionally separate because browser CDP can time out on long batches. Rerun a single failed target before treating `Runtime.evaluate timeout` as a page defect.

Do not target raw resource routes such as `/sys/resource`, `/mobile/module`, `/mobile/menu`, or `/gen/basic` unless they are present in the current account's `loginMenu` or a separate temporary-authorization smoke plan is being executed and cleaned up.

For guarded sales, operations, and report list checks, pass explicit target paths:

```powershell
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/saleproject','/biz/saleproject/public/list','/biz/saleproject/dealProjectCaseList','/biz/saleproject/waitShipment','/biz/saleproject/completeProjectList','/biz/saleproject/cancelProjectList','/biz/saleproject/report'
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/bizops/operationCustomerList','/biz/bizops/operationProjectList','/biz/proxyPayment','/biz/ProjectSecurityDeposit','/biz/bizdatareport/index','/biz/bizdatareport/summaryStatistics','/biz/bizdatareport/settlement','/biz/bizdatareport/saleProfit'
```

These pages can trigger report queries and read-only navigation data during render. Do not click statistic cards, workflow buttons, delivery, cancel, export, complete, or settlement actions in this guard pass.

For guarded workflow, home, HR, history, and team-project render checks, pass explicit target paths:

```powershell
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/biz/biztask','/biz/historytask','/biz/biztask/mystarttask','/biz/biztask/allprocess','/biz/copytask','/biz/biztask/processList'
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath '/index','/biz/bizleaveapplication','/biz/bizhistoryexcel','/biz/bizteamproject'
```

Team-project detail requires a project visible to the current smoke account. Do not use an arbitrary `biz_team_project` row, because the detail API correctly rejects projects without a current-member relation. To pick a local member-visible sample:

```powershell
$teamProjectId = php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$env = parse_ini_file(getcwd() . '/.env'); `$account = (string)(`$env['LOCAL_SUPER_ADMIN_ACCOUNT'] ?? ''); `$user = think\facade\Db::name('sys_user')->where('ACCOUNT', `$account)->find(); `$id = ''; if (`$user) { `$id = (string)think\facade\Db::name('biz_team_project_user')->alias('m')->join('biz_team_project p', 'p.ID = m.TEAM_PROJECT_ID', 'INNER')->where('m.USER_ID', (string)`$user['ID'])->where('m.DELETE_FLAG', 'NOT_DELETE')->where('p.DELETE_FLAG', 'NOT_DELETE')->order('p.CREATE_TIME', 'desc')->value('p.ID'); } echo `$id;"
.\scripts\browser-upload-provider-guard-smoke.ps1 -SkipDefaultTargets -TargetPath "/biz/bizteamprojectdetails?id=$teamProjectId"
```

The browser helper expands object-shaped console errors before failing. If a detail page fails with a controlled API error, first confirm that the sample id belongs to the smoke user before treating it as a frontend or route defect.

## Controlled-Deferred HTTP Expectations

These checks are allowed only as targeted API smokes, not as broad UI clicking:

- Cloud file upload stubs should return controlled unsupported responses and must not create `dev_file` rows, write files, read provider credentials, or contact external storage.
- SMS send wrappers should return controlled deferred responses and must not read credentials, load SDKs, call providers, or insert send records.
- Password-recovery, phone-code, WebPush, and OAuth deferred wrappers should return controlled deferred responses and must not send messages, redirect to providers, issue tokens, bind users, or reset passwords.

## Implementation Order

1. Inventory the target frontend page and list every upload, send, delete, import, export, or provider-like control.
2. Decide whether the page can be smoked as render-only, read-detail, or optional-click. Record the selected helper command before running it.
3. For LOCAL upload compatibility, prefer existing DB or HTTP smoke commands that create temporary rows and clean them up. Do not click upload controls through broad browser smoke.
4. For real cloud storage, write a provider plan first: credential source, SDK dependency, network egress, bucket policy, file-size and extension limits, audit fields, error shape, retry behavior, tenant scoping, and rollback behavior.
5. For physical cleanup, write a cleanup plan first: root whitelist, path traversal guard, dry-run output, orphan detection, database transaction boundary, backup and restore path, idempotency, and failure compensation.
6. For real email before SMS, write a provider plan first: secret storage, rate limits, templates, audit records, retries, failure reporting, and safe test recipient handling.

## Verification Commands

Run these before and after a planning or smoke-only slice:

```powershell
.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean
.\scripts\browser-upload-provider-guard-smoke.ps1
.\scripts\project-progress.ps1 -Lean
git diff --check
git status --short --branch
```

If `-CheckWeb` passes but authenticated browser smoke fails while generating a token, rerun `.\scripts\runtime-ready.ps1`; `/think` can respond even when MySQL/Redis are down. Start the local runtime bundle before retrying DB-backed or browser smoke:

```powershell
Start-Process -FilePath "E:\project\socket\AI\testPhp\files\startServer1.bat" -WorkingDirectory "E:\project\socket\AI\testPhp\files" -WindowStyle Hidden
```

Vite cold start can take about 90 seconds in this workspace; wait for `.\scripts\web-ready.ps1` before treating frontend smoke failures as page defects.

Use the targeted optional HTTP smokes only when the slice explicitly covers those behaviors:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevFileHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevEmailSmsHttpSmoke
```

## Decisions Still Required

- Which cloud providers, if any, are production requirements.
- Where provider credentials are stored and how they are rotated.
- Whether `SNOWY_SYS_DEFAULT_FILE_ENGINE` should remain `LOCAL` for this migration.
- Whether thumbnail generation is required for newly uploaded files.
- Whether imported historical storage paths should be migrated or only normalized at response time.
- Whether physical file cleanup should ever go beyond Java-compatible logical deletion.
