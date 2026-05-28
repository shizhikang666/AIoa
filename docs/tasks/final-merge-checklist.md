# Final Merge Checklist

Last updated: 2026-05-28

## Purpose

This checklist keeps the project focused on one final merged ThinkPHP OA system.

Multiple Agents and worktrees are a development method only. They are not separate products.

Final merged project:

`F:\AI\projects\testJava\OA-ThinkPHP`

Final integration branch:

`refactor/thinkphp-main`

## Pre-Merge Requirements For Every Agent

Before a branch can be merged, the Agent must provide:

- clean `git status --short --branch`
- committed work
- changed file list
- test command list and results
- known risk list
- no unauthorized Java source modifications
- no unauthorized locked public file modifications
- no unrelated module changes

## Required Merge Order

Run merges from:

`F:\AI\projects\testJava\OA-ThinkPHP`

on branch:

`refactor/thinkphp-main`

Merge order:

1. `refactor/db`
2. `refactor/auth`
3. `refactor/user`
4. `refactor/workflow`
5. `refactor/api`
6. `refactor/test`
7. `refactor/docs`

## Merge Commands

Generate commands only until the user explicitly starts merge work:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
git status --short --branch
git fetch origin
git switch refactor/thinkphp-main
git pull --ff-only origin refactor/thinkphp-main
git merge --no-ff refactor/db
git merge --no-ff refactor/auth
git merge --no-ff refactor/user
git merge --no-ff refactor/workflow
git merge --no-ff refactor/api
git merge --no-ff refactor/test
git merge --no-ff refactor/docs
```

## Checks After Each Merge

Run at least:

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

If tests exist:

```powershell
php think test
```

## Final Acceptance

The project is ready only when:

- all module branches are merged into `refactor/thinkphp-main`
- `composer install` works
- `composer dump-autoload` works
- `php think` works
- `php think route:list` works
- PHP lint passes for `app`, `config`, and `route`
- database structure and mapping are documented
- auth, user, workflow, API, frontend, test, and docs scopes are integrated
- final project runs from `F:\AI\projects\testJava\OA-ThinkPHP`

## Merge Agent Boundary

merge-agent may:

- merge branches
- resolve conflicts
- fix syntax, route, namespace, composer, and relation integration issues
- run tests

merge-agent must not:

- develop new business features
- modify Java source project
- delete database fields
- turn worktrees into separate final projects
