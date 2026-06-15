# New Conversation Bootstrap

Use this note when starting a future Codex conversation for the ThinkPHP OA refactor.

## New Chat Starter Prompt

Paste this into a new Codex conversation when the current thread is too long:

```text
Continue the ThinkPHP OA refactor in F:\AI\projects\testJava\OA-ThinkPHP. Do not rely on prior chat history. Use real multi-Agent mode by default: the main conversation coordinates, assigns bounded sub-Agent work when available, reviews, verifies, and updates docs. Do not commit unless the current user explicitly asks for a commit or the main merge/coordinator explicitly approves committing the completed slice. If sub-Agent tools or quota are unavailable, use the documented single-conversation fallback.

First run Set-Location F:\AI\projects\testJava\OA-ThinkPHP and .\scripts\project-progress.ps1 -Lean. If local MySQL, Redis, PHP FastCGI, ThinkPHP backend, and Vue frontend are expected to be running, run .\scripts\project-preflight.ps1 next; otherwise use the relevant skip switches. Treat F:\AI\projects\testJava\OA as read-only Java reference only. Do not print or commit secrets; read local database, Redis, and login smoke values only from the ignored .env. Continue with the next smallest safe slice from docs\tasks\refactor-progress-dashboard.md and STATUS.md. Record recurring problems and mitigations in docs\tasks\problem-optimization-log.md. If the current context is too large for precise work, ask the user to open a new conversation using docs\tasks\context-handoff.md.
```

## Required Startup Reads

Before continuing a normal task, use the lean startup packet instead of loading every long log file end to end:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
.\scripts\project-progress.ps1 -Lean
```

Run `.\scripts\project-preflight.ps1` immediately after the startup packet when local services are expected to be available. Use skip switches such as `-SkipWeb` or `-SkipRoleSelector` when a layer is intentionally offline.

Use the manual packet below only when the script is unavailable or a tool cannot run PowerShell:

```powershell
git status --short --branch
Get-Content -Raw AGENTS.md
Get-Content -Raw docs\tasks\new-conversation-bootstrap.md
Get-Content -Raw docs\tasks\lean-continuation-workflow.md
Get-Content docs\tasks\refactor-progress-dashboard.md -TotalCount 90
Get-Content STATUS.md -Tail 140
```

Then use targeted `rg` / `Select-String -Context` searches for the active module in `PLANS.md`, `IMPLEMENT.md`, `STATUS.md`, `docs`, `app`, `route`, and `snowy-admin-web\src`.

Read full `PLANS.md`, `IMPLEMENT.md`, or `STATUS.md` only for cross-module audits, release/merge work, or when targeted search cannot answer the current task.

Treat existing local changes as another Agent's work unless the user explicitly says otherwise. Do not revert unrelated changes.

Detailed low-token continuation rules are in:

`docs/tasks/lean-continuation-workflow.md`

Parallel conversation and sub-agent coordination rules are in:

`docs/tasks/parallel-execution-plan.md`

Recurring problems and workflow optimizations are tracked in:

`docs/tasks/problem-optimization-log.md`

Long-context handoff rules and the new conversation starter are tracked in:

`docs/tasks/context-handoff.md`

## Default Agent Mode

Default to real multi-Agent mode for this project.

- The main conversation is the merge/coordinator session.
- Worker Agents such as `frontend-agent`, `api-agent`, `test-agent`, `docs-agent`, and other scoped module Agents execute explicitly assigned slices.
- The main merge/coordinator assigns scope, reviews worker output, performs final acceptance, integrates changes, and commits only when the user explicitly asks or the coordinator explicitly approves it.
- Worker Agents do not broaden scope, take over merge coordination, or edit unrelated modules.
- Multiple worktrees are temporary parallel workspaces. The final deliverable remains one merged ThinkPHP project at `F:\AI\projects\testJava\OA-ThinkPHP`.

If a new conversation does not have all tools, connectors, or worker-thread capabilities available, it must state what is missing and continue with the closest available safe workflow. For example, emulate worker roles in one conversation, keep scopes explicit, and record any limitations in the final report.

For speed and token control, use sub-Agents only for bounded, non-overlapping work:

- explorer Agents answer Java/frontend/current-PHP behavior questions and do not edit files.
- worker Agents edit only assigned files or modules.
- the main merge/coordinator reviews, runs acceptance checks, updates docs, and commits only when explicitly approved.
- if sub-Agent quota is unavailable, continue with the same explorer/implementation/test/docs passes inside the main conversation.
- `docs/tasks/parallel-execution-plan.md` defines which tracks may run in parallel and which shared files or side-effect-heavy modules must remain serial under the coordinator.

## Runtime Services

For database-backed or Redis-backed checks, start the local runtime described in:

`docs/tasks/local-runtime-services.md`

Expected runtime service targets are:

- MySQL: `127.0.0.1:3306`, database `phpoa20026`
- Redis: `127.0.0.1:6379`
- PHP FastCGI: `127.0.0.1:9000`

Read database and Redis credentials only from the ignored local `.env`. Do not print, commit, or document secret values.

## Local Login Smoke

Browser or authenticated HTTP smoke tests must read local login smoke values from the ignored local `.env`:

- `LOCAL_SUPER_ADMIN_ACCOUNT`
- `LOCAL_SUPER_ADMIN_PASSWORD`

Do not write plaintext login accounts, passwords, tokens, database passwords, Redis passwords, or API keys into tracked files, logs, commits, task notes, or final reports.

## Common Test Scripts

Use the focused smoke scripts when relevant:

```powershell
.\scripts\project-progress.ps1
.\scripts\project-progress.ps1 -Lean
.\scripts\project-preflight.ps1
.\scripts\runtime-ready.ps1
.\scripts\web-ready.ps1
.\scripts\role-selector-http-smoke.ps1
.\scripts\user-display-http-smoke.ps1
.\scripts\business-read-http-smoke.ps1
.\scripts\directory-alias-http-smoke.ps1
.\scripts\test-agent-smoke.ps1
.\scripts\test-agent-smoke.ps1 -SkipComposer
.\scripts\test-agent-db-smoke.ps1
```

`scripts/project-progress.ps1` prints the current branch/status, dashboard head, next execution order, recent problem rows, context handoff pointer, and commit guardrail without reading secrets; use `-Lean` for the shortest normal startup snapshot. `scripts/project-preflight.ps1` runs the repeatable local preflight bundle: Git status, runtime readiness, web readiness, role-selector HTTP smoke, user-display HTTP smoke, business-read HTTP smoke, directory-alias HTTP smoke, and `git diff --check`, with skip switches for unavailable layers. `scripts/runtime-ready.ps1` checks local MySQL, Redis, and PHP FastCGI ports without credentials. `scripts\web-ready.ps1` checks the local ThinkPHP backend on port `82` and Vue frontend on port `83` before browser or authenticated HTTP smoke tests. `scripts\role-selector-http-smoke.ps1`, `scripts\user-display-http-smoke.ps1`, `scripts\business-read-http-smoke.ps1`, and `scripts\directory-alias-http-smoke.ps1` create short-lived local tokens from the ignored `.env` account and verify copied frontend payloads without printing credentials or tokens. `scripts/test-agent-smoke.ps1` covers the repeatable ThinkPHP baseline checks. `scripts/test-agent-db-smoke.ps1` expects the local runtime and ignored `.env` credentials, then checks MySQL, Redis, and current DB-backed export smoke coverage without printing secrets.

When a JSON response may contain case-variant duplicate aliases such as `ID` and `id`, avoid PowerShell 5.1 `ConvertFrom-Json`. Use the case-sensitive Node helper instead:

```powershell
curl.exe -sS <url> | node .\scripts\json-read.js data.records.0.id
```

The helper exits with code `2` when the requested path is not present, so smoke scripts can distinguish missing fields from empty string values.

Current focused DB-backed coverage also includes sys process-config detail/edit behavior with admin-compatible write rejection checks, settlement-account base add/edit/status behavior without balance or statement side effects, collection-receipt mark-success behavior with version increment and no account/statement/payment/expenditure side effects, debit-note mark-success behavior with version increment and no account/statement/payment/expenditure side effects, payroll edit/batch-edit/delete behavior with non-edit field preservation and missing-id rollback, payroll import-template service/HTTP download with original Java template SHA verification, leave-application edit/delete behavior with Java edit-field-only updates, nested delete payload support, missing-id rollback, non-admin rejection, deleted-detail hiding, and no payroll/vacation side effects, sale-project draft save behavior with create/update by `TARGET_ID`, raw `EXT_JSON` preservation, validation failure, and no `biz_sale_project` side effects, gen-basic preview behavior with Java-compatible buckets, missing-id 404, no DB writes, and no runtime file creation, gen-basic ZIP download behavior that reuses preview buckets and writes no project files, dev-file local upload/delete behavior, dev email/SMS metadata logical delete behavior, dev-log category delete behavior, dev-job logical delete behavior, gen-config `editBatch` metadata saves, sale-project invoicing complete, business file-relation maintenance, sys module add/edit/delete maintenance with child-resource and role-resource cleanup, sys menu add/edit/changeModule/delete maintenance with menu/button-tree relation cleanup, sys button add/edit/delete maintenance with role-resource `buttonInfo` cleanup, sys field add/edit/delete maintenance with menu-parent validation and direct relation cleanup, mobile module add/edit/delete maintenance with role mobile-menu relation cleanup, mobile menu add/edit/changeModule/delete maintenance with menu-tree relation cleanup and button preservation, mobile button add/edit/delete maintenance with role mobile-menu `buttonInfo` cleanup, team-project base add/edit/delete maintenance, Java-compatible team-project member edit audit refresh, and `DevConfigService` `BIZ_DEFINE` add/edit/delete with sensitive-value preservation and logical delete checks.

Current browser-side focused coverage additionally includes dev-file upload/delete, product `XnUpload`, sale-project attachment relation binding, email TinyMCE rich-text upload, old `components/Editor` fallback upload through a temporary `/exm/editor` route, and workflow read-only pages `/biz/biztask`, `/biz/biztask/historyTask`, `/biz/biztask/mystarttask`, `/biz/biztask/allprocess`, and `/biz/biztask/copytask`.

When the backend server is already running, use focused authenticated HTTP smokes as needed:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevConfigHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevLogHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevJobHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -GenConfigHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SaleProjectInvoicingHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysModuleHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysMenuHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysButtonHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysFieldHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileModuleHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileMenuHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileButtonHttpSmoke
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke
```

## Java Source Boundary

The Java source project is read-only:

`F:\AI\projects\testJava\OA`

No Agent may add, edit, delete, rename, format, or otherwise modify files under the Java source project. Use Java files only as compatibility and migration references.

## Reporting

Each continuation should report:

- active Agent role and scope
- files changed
- tests or smoke scripts run
- new or updated problem-log rows, when applicable
- current blockers or missing tools
- next recommended slice

Do not commit unless the current user request explicitly asks for a commit or the main merge/coordinator explicitly approves committing the completed slice.
