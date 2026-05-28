# Worktree Status

Last updated: 2026-05-28

## Main Repository

- Path: `F:\AI\projects\testJava\OA-ThinkPHP`
- Branch: `main`
- Remote: `origin/main`
- Purpose: baseline ThinkPHP project and coordination documents

## Coordination Branch

- Branch: `refactor/thinkphp-main`
- Remote: `origin/refactor/thinkphp-main`
- Purpose: shared base branch for all refactor agents

## Agent Worktrees

| Agent | Branch | Worktree |
| --- | --- | --- |
| auth-agent | `refactor/auth` | `F:\AI\projects\testJava\OA-auth` |
| user-agent | `refactor/user` | `F:\AI\projects\testJava\OA-user` |
| workflow-agent | `refactor/workflow` | `F:\AI\projects\testJava\OA-workflow` |
| db-agent | `refactor/db` | `F:\AI\projects\testJava\OA-db` |
| api-agent | `refactor/api` | `F:\AI\projects\testJava\OA-api` |
| frontend-agent | `refactor/frontend` | `F:\AI\projects\testJava\OA-frontend` |
| test-agent | `refactor/test` | `F:\AI\projects\testJava\OA-test` |
| docs-agent | `refactor/docs` | `F:\AI\projects\testJava\OA-docs` |

## Preserved Backup

The previous non-Git `OA-auth` directory was preserved here:

`F:\AI\projects\testJava\OA-auth-backup-20260528-143431`

Do not delete it until the user confirms it is no longer needed.

## Safety Notes

- The Java source project `F:\AI\projects\testJava\OA` remains read-only.
- No business migration has started.
- Each Agent must check `git status --short --branch` in its own worktree before making changes.
