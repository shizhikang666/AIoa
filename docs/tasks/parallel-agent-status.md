# Parallel Agent Status

Last updated: 2026-05-28

## Non-Negotiable Rule

This is one refactor project, not multiple final projects.

The worktrees are temporary parallel workspaces. Every Agent branch must eventually merge into:

`refactor/thinkphp-main`

The final deliverable must be:

`F:\AI\projects\testJava\OA-ThinkPHP`

## Source And Target

- Java source project, read-only: `F:\AI\projects\testJava\OA`
- Updated SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`
- Final ThinkPHP target project: `F:\AI\projects\testJava\OA-ThinkPHP`
- Integration branch: `refactor/thinkphp-main`

## Current Parallel Strategy

Run several Agents in parallel only when their file scopes do not conflict.

Recommended active lanes:

| Agent | Branch | Worktree | Current Role | Parallel Status |
| --- | --- | --- | --- | --- |
| db-agent | `refactor/db` | `F:\AI\projects\testJava\OA-db` | Database maps, Models, relations | Foundation completed; can support other Agents |
| auth-agent | `refactor/auth` | `F:\AI\projects\testJava\OA-auth` | Login, Token, RBAC, menu, permissions | Active; continue menu compatibility after confirmation |
| user-agent | `refactor/user` | `F:\AI\projects\testJava\OA-user` | Users, departments, positions, organization | Safe to start in parallel |
| docs-agent | `refactor/docs` | `F:\AI\projects\testJava\OA-docs` | Coordination docs and status | Active in this phase |
| test-agent | `refactor/test` | `F:\AI\projects\testJava\OA-test` | Syntax, route, namespace, composer checks | Safe to start after auth/user checkpoints |
| api-agent | `refactor/api` | `F:\AI\projects\testJava\OA-api` | Controller mapping and API standardization | Analysis can start; implementation should wait for module surfaces |
| workflow-agent | `refactor/workflow` | `F:\AI\projects\testJava\OA-workflow` | Workflow definition and runtime | Analysis can start; code should wait for user/auth foundations |
| frontend-agent | `refactor/frontend` | `F:\AI\projects\testJava\OA-frontend` | Frontend API adaptation | Analysis can start; adaptation should wait for stable APIs |

## File Ownership Rules

- Each Agent modifies only its own worktree.
- Each Agent modifies only files required by its module.
- Java source project is read-only.
- Business code must not be changed by docs-agent.
- Public locked files require a change request before modification.

## Locked Public Files

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If any Agent must change one of these files, it must write:

`docs/tasks/public-file-change-request.md`

and wait for confirmation.

## Required Agent Completion Report

Each Agent must report:

- completed content
- changed files
- test commands and results
- known risks
- next plan
- commit hash

## Commit Rule

Every completed phase must be committed before merge.

Commit message format must include the Agent name, for example:

```powershell
git commit -m "docs-agent: update parallel refactor docs"
```
