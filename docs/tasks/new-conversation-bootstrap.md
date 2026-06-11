# New Conversation Bootstrap

Use this note when starting a future Codex conversation for the ThinkPHP OA refactor.

## Required Startup Reads

Before continuing any task, read these project files in the target worktree:

```powershell
Get-Content -Raw AGENTS.md
Get-Content -Raw PLANS.md
Get-Content -Raw IMPLEMENT.md
Get-Content -Raw STATUS.md
git status --short --branch
```

Treat existing local changes as another Agent's work unless the user explicitly says otherwise. Do not revert unrelated changes.

## Default Agent Mode

Default to real multi-Agent mode for this project.

- The main conversation is the merge/coordinator session.
- Worker Agents such as `frontend-agent`, `api-agent`, `test-agent`, `docs-agent`, and other scoped module Agents execute explicitly assigned slices.
- The main merge/coordinator assigns scope, reviews worker output, performs final acceptance, integrates changes, and commits only after review.
- Worker Agents do not broaden scope, take over merge coordination, or edit unrelated modules.
- Multiple worktrees are temporary parallel workspaces. The final deliverable remains one merged ThinkPHP project at `F:\AI\projects\testJava\OA-ThinkPHP`.

If a new conversation does not have all tools, connectors, or worker-thread capabilities available, it must state what is missing and continue with the closest available safe workflow. For example, emulate worker roles in one conversation, keep scopes explicit, and record any limitations in the final report.

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
.\scripts\test-agent-smoke.ps1
.\scripts\test-agent-smoke.ps1 -SkipComposer
.\scripts\test-agent-db-smoke.ps1
```

`scripts/test-agent-smoke.ps1` covers the repeatable ThinkPHP baseline checks. `scripts/test-agent-db-smoke.ps1` expects the local runtime and ignored `.env` credentials, then checks MySQL, Redis, and current DB-backed export smoke coverage without printing secrets.

Current focused DB-backed coverage also includes sys process-config detail/edit behavior with admin-compatible write rejection checks, settlement-account base add/edit/status behavior without balance or statement side effects, dev-file local upload/delete behavior, dev email/SMS metadata logical delete behavior, dev-log category delete behavior, dev-job logical delete behavior, gen-config `editBatch` metadata saves, sale-project invoicing complete, business file-relation maintenance, sys module add/edit/delete maintenance with child-resource and role-resource cleanup, sys menu add/edit/changeModule/delete maintenance with menu/button-tree relation cleanup, sys button add/edit/delete maintenance with role-resource `buttonInfo` cleanup, sys field add/edit/delete maintenance with menu-parent validation and direct relation cleanup, mobile module add/edit/delete maintenance with role mobile-menu relation cleanup, mobile menu add/edit/changeModule/delete maintenance with menu-tree relation cleanup and button preservation, mobile button add/edit/delete maintenance with role mobile-menu `buttonInfo` cleanup, team-project base add/edit/delete maintenance, Java-compatible team-project member edit audit refresh, and `DevConfigService` `BIZ_DEFINE` add/edit/delete with sensitive-value preservation and logical delete checks.

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
- current blockers or missing tools
- next recommended slice

Do not commit unless the current user request explicitly asks for a commit or the main merge/coordinator has accepted the completed slice.
