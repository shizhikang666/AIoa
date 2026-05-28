# Phase 2 Multi-Agent Execution Plan

## Purpose

This plan prepares the next stage of the Java OA to ThinkPHP refactor.

The next stage should begin only after user confirmation.

Final integration target:

- Branch: `refactor/thinkphp-main`
- Project path: `F:\AI\projects\testJava\OA-ThinkPHP`

The worktrees are parallel workspaces, not final projects.

## Shared Rules For All Agents

- Read `AGENTS.md` first.
- Read `docs/refactor-plan.md`, `docs/module-split.md`, `docs/codex-tasks.md`, and `docs/tasks/merge-plan.md`.
- Work only inside the assigned worktree.
- Do not modify `F:\AI\projects\testJava\OA`.
- Do not delete database fields.
- Do not change unrelated modules.
- Do not make broad unrelated refactors.
- Check `git status --short --branch` before editing and before committing.
- Commit completed work locally.
- Report changed files and test results.
- Wait for Merge Agent to integrate branches into `refactor/thinkphp-main`.

## auth-agent

负责范围：

- 登录
- Token
- Redis 会话
- RBAC
- 菜单
- 权限

禁止修改范围：

- Java 原项目
- 用户组织业务实现
- 工作流业务实现
- 前端页面
- 数据库字段删除

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-auth`
- Branch: `refactor/auth`

测试命令：

```powershell
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Git commit 要求：

- Commit format: `refactor(auth): <summary>`
- Commit only auth-related files.

最终 merge 要求：

- Merge into `refactor/thinkphp-main` after `refactor/db`.

## user-agent

负责范围：

- 用户
- 部门
- 岗位
- 组织架构

禁止修改范围：

- Java 原项目
- 认证 Token 核心逻辑
- 工作流业务实现
- 前端页面
- 数据库字段删除

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-user`
- Branch: `refactor/user`

测试命令：

```powershell
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Git commit 要求：

- Commit format: `refactor(user): <summary>`
- Commit only user/organization-related files.

最终 merge 要求：

- Merge into `refactor/thinkphp-main` after `refactor/auth`.

## workflow-agent

负责范围：

- 审批流
- 流程定义
- 流程实例
- 审批记录

禁止修改范围：

- Java 原项目
- 认证核心逻辑
- 用户组织基础结构
- 前端页面
- 数据库字段删除

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-workflow`
- Branch: `refactor/workflow`

测试命令：

```powershell
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Git commit 要求：

- Commit format: `refactor(workflow): <summary>`
- Commit only workflow-related files.

最终 merge 要求：

- Merge into `refactor/thinkphp-main` after `refactor/user`.

## db-agent

负责范围：

- 数据库结构
- 字段映射
- 索引
- ThinkPHP Model 设计

禁止修改范围：

- Java 原项目
- Controller 业务实现
- Service 业务实现
- 前端页面
- 生产数据库变更

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-db`
- Branch: `refactor/db`

测试命令：

```powershell
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Git commit 要求：

- Commit format: `refactor(db): <summary>`
- Commit only database mapping, schema, and Model design files.

最终 merge 要求：

- Merge into `refactor/thinkphp-main` first.

## api-agent

负责范围：

- Java Controller 分析
- ThinkPHP Controller 映射
- API 标准化
- 参数和返回格式契约

禁止修改范围：

- Java 原项目
- 具体业务 Service 实现
- Model 字段定义
- 前端页面
- 数据库字段删除

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-api`
- Branch: `refactor/api`

测试命令：

```powershell
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Git commit 要求：

- Commit format: `refactor(api): <summary>`
- Commit only API mapping and API layer files.

最终 merge 要求：

- Merge into `refactor/thinkphp-main` after workflow integration.

## frontend-agent

负责范围：

- 前端接口适配
- Token 请求头适配
- 菜单和按钮权限适配
- 前端接口差异文档

禁止修改范围：

- Java 原项目
- 后端业务逻辑
- UI 大范围重写
- 数据库字段

输入路径：

- Java frontend source: `F:\AI\projects\testJava\OA\snowy-admin-web`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-frontend`
- Branch: `refactor/frontend`

测试命令：

```powershell
npm --version
pnpm --version
php think route:list
```

Git commit 要求：

- Commit format: `refactor(frontend): <summary>`
- Commit only frontend adaptation files and related docs.

最终 merge 要求：

- Merge through the normal integration flow after API contracts stabilize.

## test-agent

负责范围：

- PHP syntax
- route checks
- namespace checks
- Composer checks
- integration smoke checks
- low-risk test fixes after approval

禁止修改范围：

- Java 原项目
- 新业务功能开发
- 大范围业务重构
- 数据库字段删除

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-test`
- Branch: `refactor/test`

测试命令：

```powershell
composer validate
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Git commit 要求：

- Commit format: `test(refactor): <summary>`
- Commit only test scripts, test docs, and approved low-risk fixes.

最终 merge 要求：

- Merge after API and module branches.

## docs-agent

负责范围：

- 文档
- 接口说明
- 部署说明
- 协作记录

禁止修改范围：

- Java 原项目
- 业务代码
- 数据库字段
- 前端页面逻辑

输入路径：

- Java: `F:\AI\projects\testJava\OA`
- ThinkPHP baseline: `F:\AI\projects\testJava\OA-ThinkPHP`

输出路径：

- Worktree: `F:\AI\projects\testJava\OA-docs`
- Branch: `refactor/docs`

测试命令：

```powershell
git status --short --branch
php think route:list
```

Git commit 要求：

- Commit format: `docs(refactor): <summary>`
- Commit only documentation.

最终 merge 要求：

- Merge last so final documentation matches integrated behavior.

## Merge Agent Reminder

The Merge Agent starts only after the module Agents have committed their work and the user confirms integration.

Mandatory merge order:

1. `refactor/db`
2. `refactor/auth`
3. `refactor/user`
4. `refactor/workflow`
5. `refactor/api`
6. `refactor/test`
7. `refactor/docs`

Post-merge checks:

```powershell
composer validate
composer install
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```
