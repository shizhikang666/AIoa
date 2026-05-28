# Merge Plan

## Purpose

This project uses multiple worktrees for parallel development, but the final deliverable is one complete ThinkPHP OA system.

Final integration branch:

`refactor/thinkphp-main`

Final project path:

`F:\AI\projects\testJava\OA-ThinkPHP`

## Merge Agent Scope

The Merge Agent is responsible only for integration work:

- merge branches
- resolve conflicts
- review integration risks
- check syntax
- check routes
- check namespaces
- check Composer dependencies
- check cross-module relations
- fix integration test failures

The Merge Agent must not implement new business features.

## Pre-Merge Checks

Before merging, each module Agent must:

- commit all completed work
- push its branch
- report changed files
- report test commands and results
- report known risks

The Merge Agent must run:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
git status --short --branch
git fetch origin
git switch refactor/thinkphp-main
git pull --ff-only origin refactor/thinkphp-main
```

## Required Merge Order

```powershell
git merge --no-ff refactor/db
git merge --no-ff refactor/auth
git merge --no-ff refactor/user
git merge --no-ff refactor/workflow
git merge --no-ff refactor/api
git merge --no-ff refactor/test
git merge --no-ff refactor/docs
```

## Why This Order

1. `refactor/db`: database schema and Model foundations must land first.
2. `refactor/auth`: authentication and permission boundaries are shared by later modules.
3. `refactor/user`: users, departments, positions, and organization data support workflow and permissions.
4. `refactor/workflow`: workflow depends on users and organization structure.
5. `refactor/api`: API standardization should wire finalized module surfaces.
6. `refactor/test`: test fixes should validate the integrated project.
7. `refactor/docs`: final documentation should describe the merged system.

## Post-Merge Checks

Run these checks after each merge when possible, and definitely after the final merge:

```powershell
composer validate
composer install
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

## Final Push

After all checks pass:

```powershell
git status --short --branch
git push origin refactor/thinkphp-main
```

## Non-Negotiable Rule

Do not treat Agent worktrees as final projects.

All Agent output must merge back into:

`F:\AI\projects\testJava\OA-ThinkPHP`
