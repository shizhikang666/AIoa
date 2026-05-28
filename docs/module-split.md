# 多 Agent 模块拆分

## 1. auth-agent

- 范围：登录、Token、Redis 会话、权限、菜单、角色。
- 分支：`refactor/auth`
- Worktree：`F:\AI\projects\testJava\OA-auth`

## 2. user-agent

- 范围：用户、部门、岗位、组织架构。
- 分支：`refactor/user`
- Worktree：`F:\AI\projects\testJava\OA-user`

## 3. workflow-agent

- 范围：审批流、流程定义、流程实例、审批记录。
- 分支：`refactor/workflow`
- Worktree：`F:\AI\projects\testJava\OA-workflow`

## 4. db-agent

- 范围：数据库表结构、字段映射、索引、ThinkPHP Model 设计。
- 分支：`refactor/db`
- Worktree：`F:\AI\projects\testJava\OA-db`

## 5. api-agent

- 范围：Java Controller 到 ThinkPHP Controller 的接口映射。
- 分支：`refactor/api`
- Worktree：`F:\AI\projects\testJava\OA-api`

## 6. frontend-agent

- 范围：前端接口适配、Token 处理、菜单和按钮权限适配。
- 分支：`refactor/frontend`
- Worktree：`F:\AI\projects\testJava\OA-frontend`

## 7. test-agent

- 范围：测试、语法检查、接口冒烟、错误修复。
- 分支：`refactor/test`
- Worktree：`F:\AI\projects\testJava\OA-test`

## 8. docs-agent

- 范围：文档、接口说明、部署说明、协作记录。
- 分支：`refactor/docs`
- Worktree：`F:\AI\projects\testJava\OA-docs`

## 9. merge-agent

- 范围：最终合并、冲突处理、集成 review、syntax、route、namespace、composer、relation、测试修复。
- 集成分支：`refactor/thinkphp-main`
- 集成目录：`F:\AI\projects\testJava\OA-ThinkPHP`
- 说明：merge-agent 不开发业务功能，只负责把各 Agent 分支按顺序合并成一个完整 ThinkPHP OA 系统。

## 最终合并顺序

1. `refactor/db`
2. `refactor/auth`
3. `refactor/user`
4. `refactor/workflow`
5. `refactor/api`
6. `refactor/test`
7. `refactor/docs`
