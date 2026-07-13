# Autonomous Execution Rules

## Purpose

Let the main control Agent continue the Java OA to ThinkPHP refactor with fewer interruptions while preserving hard safety boundaries.

## Current Continuation Entry Point

For normal future continuations, use `docs/tasks/lean-continuation-workflow.md` together with `docs/tasks/new-conversation-bootstrap.md`.

This file records autonomy boundaries. The lean workflow records the faster startup packet, targeted search rules, task triage, multi-Agent fallback, documentation scope, and risk-based quality gates.

## User Authorization Template

Copy this into the chat when you want the Agent to proceed autonomously:

```text
我授权 Codex 在本项目中按 AGENTS.md、PLANS.md、IMPLEMENT.md、STATUS.md 和 docs/tasks/autonomous-execution-rules.md 自主推进。

允许自动执行：
1. 在各自 worktree 内创建、修改、提交非破坏性代码和文档。
2. 运行 composer install、composer dump-autoload、php think、php think route:list、PHP lint、git status、git add、git commit、git push。
3. 按已记录的 public-file-change-request 修改 route/app.php，但仅限已申请的路由范围。
4. 在测试通过后，按既定顺序把 refactor/db、refactor/auth、refactor/user、refactor/workflow、refactor/api、refactor/test、refactor/docs merge 到 refactor/thinkphp-main。
5. 解决 merge 冲突，但只允许在冲突文件的相关范围内修改。

必须停止并报告：
1. 需要删除文件或目录。
2. 需要修改 Java 原项目 F:\AI\projects\testJava\OA。
3. 需要修改数据库字段、删除字段、删除表、执行生产数据库写操作。
4. 需要写入 API Key、密码、Token、私钥等秘密。
5. 需要修改 .env、config/database.php、composer.json、composer.lock、app/common.php 等锁定文件且没有变更申请。
6. 测试失败且无法快速定位。
7. merge conflict 涉及多个模块边界或不确定该保留哪一边。
8. 准备处理线上实时数据同步。
```

## What The Agent May Do Automatically

- Read Java source as read-only input.
- Add ThinkPHP services, models, controllers, middleware, validators, docs, and tests inside the correct worktree.
- Run local validation commands.
- Commit small changes with agent-prefixed commit messages.
- Push module branches to GitHub after successful commit.
- Add public file change requests before touching locked files.
- Keep `STATUS.md` updated after each phase.

## What Requires A Stop

- Any destructive file operation.
- Any edit inside `F:\AI\projects\testJava\OA`.
- Any database schema deletion or production database write.
- Any secret or credential handling.
- Any broad unrelated refactor.
- Any uncertain merge conflict.
- Any final online realtime data synchronization action.

## Locked Public File Rule

Locked files remain protected:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

Route changes can be pre-authorized only when a matching `docs/tasks/public-file-change-request.md` exists and the change stays inside that documented route scope.

## Current Practical Recommendation

For this project, the safest autonomy level is:

- allow automatic small module commits and pushes
- allow documented route registration
- allow final merge only after all branch tests pass
- continue to stop for database schema changes, deletion, secrets, and production sync

## Final Reminder

After the ThinkPHP OA system is complete, remind the user to design and implement online real-time production data synchronization. Do not start that work early.
