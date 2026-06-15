# Context Handoff

Purpose: keep long ThinkPHP OA refactor work continuable when the current Codex conversation becomes too large or starts losing useful focus.

## When To Ask For A New Conversation

Ask the user to open a new conversation when any of these are true:

- The conversation has accumulated several completed slices and the next slice needs broad Java/frontend/backend inspection.
- Tool outputs or status logs are repeatedly being summarized instead of read precisely because the context is too large.
- The assistant starts needing to re-open the same orientation files multiple times in one turn.
- A planned task is side-effect-heavy, risky, or cross-module and deserves a clean context.
- The next step is no longer a tiny follow-up and would benefit from the lean startup packet.

Do not ask for a new conversation in the middle of an edit or verification unless the current turn is blocked. Finish the current coherent slice first when practical.

## Before Ending The Old Conversation

Before asking for a new conversation, update the minimal handoff state:

1. `STATUS.md`: latest completed work, tests, known issues, and next plan.
2. `docs/tasks/problem-optimization-log.md`: recurring problems, blockers, slow commands, avoidable mistakes, or improved mitigations.
3. `docs/tasks/refactor-progress-dashboard.md`: only if capability, counts, or next actions changed.
4. `docs/tasks/api-gap-map.md`: only if route/API coverage changed.
5. `PLANS.md` and `IMPLEMENT.md`: only for completed implementation/process slices.

Keep the handoff concise. Do not paste long command output or secrets.

## New Conversation Starter

Use this exact starter when opening a new conversation:

```text
Continue the ThinkPHP OA refactor in F:\AI\projects\testJava\OA-ThinkPHP. Do not rely on prior chat history. Start with Set-Location F:\AI\projects\testJava\OA-ThinkPHP, then run .\scripts\project-progress.ps1 -Lean. If local MySQL, Redis, PHP FastCGI, ThinkPHP backend, and Vue frontend are expected to be running, run .\scripts\project-preflight.ps1 next; otherwise use the relevant skip switches. Read docs\tasks\context-handoff.md and docs\tasks\problem-optimization-log.md only as needed. Treat F:\AI\projects\testJava\OA as read-only Java reference. Do not print or commit secrets; read local database, Redis, and login smoke values only from the ignored .env. Do not commit unless the current user explicitly asks for a commit or the main merge/coordinator explicitly approves committing the completed slice. Continue with the next smallest safe slice from the progress dashboard, STATUS.md, and the problem table.
```

## First Commands In The New Conversation

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
.\scripts\project-progress.ps1 -Lean
```

For a longer startup snapshot without the full status tail:

```powershell
.\scripts\project-progress.ps1 -SkipStatusTail
```

For the full status tail:

```powershell
.\scripts\project-progress.ps1
```

When local services are expected to be running:

```powershell
.\scripts\project-preflight.ps1
```

When some layers are intentionally offline:

```powershell
.\scripts\project-preflight.ps1 -SkipWeb -SkipRoleSelector
```

For a stale-worktree audit:

```powershell
.\scripts\project-progress.ps1 -IncludeWorktreeSummary
```

Before continuing browser or authenticated HTTP smoke work:

```powershell
.\scripts\project-preflight.ps1
```

## Continuation Rules

- Treat existing local changes as another Agent's work unless the user says otherwise.
- Start from `OA-ThinkPHP`, not the parent directory.
- Use targeted `rg` searches for the active module instead of reading long logs.
- Update the problem table whenever the same problem repeats or a better mitigation is found.
- Continue small, coherent slices and verify each slice before reporting completion.
