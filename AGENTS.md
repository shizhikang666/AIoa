# AGENTS.md

## 项目目标

将原 Java OA 系统逐步重构为 ThinkPHP 版本。当前阶段只做工程化准备、规范沉淀、任务拆分和多 Agent 协作配置，不开始业务迁移。

## 项目路径

- Java 原项目路径：`F:\AI\projects\testJava\OA`
- ThinkPHP 目标项目路径：`F:\AI\projects\testJava\OA-ThinkPHP`

## 多 Agent 协作规则

- 所有 Agent 必须先阅读本文件和 `docs/refactor-plan.md`。
- 每个 Agent 只在自己的 worktree 和分支中工作。
- 每个 Agent 只能修改自己负责模块相关文件。
- 跨模块依赖必须先写入文档说明，再由主协调 Agent 合并决策。
- 修改前先检查 `git status`，不得覆盖其他 Agent 或用户已有改动。
- 每次提交必须保持小粒度，说明变更范围、测试结果和未完成事项。

## 禁止事项

- 禁止直接修改 Java 原项目 `F:\AI\projects\testJava\OA`。
- 禁止删除任何目录或文件，除非用户明确批准。
- 禁止在准备阶段迁移业务功能。
- 禁止重构 Controller、Service、Model、Mapper、前端页面业务逻辑。
- 禁止写入 API Key、密码、Token 或其他密钥。
- 禁止绕过 Git 状态检查强行覆盖文件。
- 禁止未经确认引入大型第三方依赖。

## 测试命令

准备阶段可使用：

```powershell
php -v
composer --version
git --version
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

业务迁移阶段由各 Agent 在自己的任务文档中补充模块测试命令。

## Git 提交规范

- 分支命名：`refactor/<module>`
- 提交信息格式：`refactor(<module>): <summary>`
- 示例：`refactor(auth): add token design document`
- 提交前必须执行基础检查并在提交说明中记录结果。
- 不允许把无关格式化、缓存、运行时文件混入业务提交。

## 目录规范

- `docs/java-analysis`：Java 原项目分析记录。
- `docs/thinkphp-design`：ThinkPHP 设计方案。
- `docs/database`：数据库表结构、字段映射、索引设计。
- `docs/api`：接口映射和返回格式说明。
- `docs/tasks`：多 Agent 任务、worktree 命令和执行记录。
- `.codex`：项目级 Codex 配置，不存放密钥。

## API 返回格式

统一返回 JSON：

```json
{
  "code": 200,
  "message": "ok",
  "data": {}
}
```

约定：

- `code = 200` 表示成功。
- `code = 400` 表示参数错误。
- `code = 401` 表示未登录或 Token 无效。
- `code = 403` 表示无权限。
- `code = 500` 表示服务端错误。

## Token + Redis 认证约定

- 登录成功后生成 Access Token 和 Refresh Token。
- Token 存储在 Redis，不在服务端本地文件保存会话。
- 请求头使用：`Authorization: Bearer <token>`。
- Redis Key 前缀建议：`oa:auth:`。
- Token 内只保存必要用户身份、角色码、权限码和租户信息。
- 禁止在 Token 或 Redis 中保存明文密码、密钥、身份证等敏感信息。

## Java 原项目只读约定

`F:\AI\projects\testJava\OA` 仅用于分析 Java 代码、SQL、接口和业务流程。任何 Agent 都不得在该目录中新增、修改或删除文件。
