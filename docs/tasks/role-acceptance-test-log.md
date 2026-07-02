# 角色验收测试记录

日期：2026-06-30
环境：https://oa.fucity.cn
测试范围：机构“演示账号”

## 测试基准

- 演示账号 orgId：`2018244380591632386`
- 演示账号 tenantId：`2018244380532912130`
- 当前线上版本：`20260630-110410`
- 记录策略：先记录问题，不在本轮边测边改；遇到阻塞流程先跳过，最终统一复核和修改。

## 已确认通过

- `superAdmin / 123456 / 租户 1` 可登录前端。
- 前端人员管理页可打开，并能看到“演示账号 / superAdminTwo”。
- 前端工资单管理页可打开，当前测试后显示暂无数据。
- 演示账号下薪资接口闭环已通过：
  - 岗位查询
  - 人员查询
  - 生成工资单
  - 工资单详情
  - 工资单编辑
  - 按机构、月份、用户筛选
  - 导出 CSV
  - 删除并验证测试工资单已清理

## 角色账号矩阵

| 账号 | 租户 | 机构/部门 | 登录接口 | 前端菜单 | 权限/角色情况 |
| --- | --- | --- | --- | --- | --- |
| `superAdmin` | `1` | 系统超管 | 通过 | 66 个菜单 | `superAdmin`，204 个权限 |
| `superAdminTwo` | `2018244380532912130` | 演示账号 | 通过 | 空 | 绑定“总经理”角色，但角色 0 菜单、0 权限 |
| `csyw001` | `2018244380532912130` | 演示账号/业务部 | 通过 | 空 | 未绑定角色，0 权限 |
| `cscw001` | `2018244380532912130` | 演示账号/财务部 | 通过 | 空 | 未绑定角色，0 权限 |
| `cszjb001` | `2018244380532912130` | 演示账号/总经办 | 通过 | 空 | 未绑定角色，0 权限 |
| `csjs001` | `2018244380532912130` | 演示账号/技术部 | 通过 | 空 | 未绑定角色，0 权限 |

## 阻塞问题

### P0：演示账号角色无法进入前端系统

现象：
- `superAdminTwo`、`csyw001`、`cscw001`、`cszjb001`、`csjs001` 的 `doLogin` 均返回成功。
- `getLoginUser` 可返回用户信息。
- `/sys/userCenter/loginMenu` 返回空数组。
- 前端登录后会清除 token 并停留在登录页，无法按角色继续页面验收。

影响：
- 无法真实按业务、财务、总经办、技术等角色验收前端流程。
- 用户看到的是“账号密码没问题，但进不了系统”的体验。

初步原因：
- `superAdminTwo` 绑定的“总经理”角色没有菜单资源和接口权限。
- 其他演示账号角色账号未绑定任何角色。

待复核修改：
- 先确认演示账号租户应有的角色模板和菜单模板。
- 给演示账号下的角色补齐菜单、按钮、接口权限和数据范围。
- 给测试账号绑定对应角色。

### P0：无菜单/无权限账号仍可直接调用部分业务读接口

现象：
- 上述无菜单账号直接调用部分业务读接口仍返回 `code=200`。
- 例如：
  - `/biz/user/page`
  - `/biz/position/page`
  - `/biz/bizpayroll/page`
  - `/biz/bizleaveapplication/page`
  - `/biz/task/page`
  - `/biz/process/all/page`
  - `/biz/saleproject/page`
  - `/biz/bizcollectionreceipt/page`

影响：
- 前端菜单为空挡住了用户，但后端接口没有一致拒绝。
- 权限模型存在“前端不可见、接口可读”的风险。

待复核修改：
- 统一检查 `AuthMiddleware`、RBAC 权限校验、各业务 service 的权限入口。
- 明确接口权限策略：没有接口权限时应返回 403，还是只依赖数据范围。
- 如果使用数据范围，需确认无角色账号为什么仍获得演示账号组织树/数据范围。

## 暂不修改项

- 未直接修改角色、菜单、权限数据。
- 未新增或保留测试工资单。
- 未执行生产数据变更。

## 待合并

- 独立验收智能体 `Volta` 已返回，结论已合并。

## 独立验收智能体补充

### 账号与前端结果

| 账号 | 租户 | 角色/身份 | 登录 | 菜单 | 前端结果 |
| --- | --- | --- | --- | --- | --- |
| `superAdmin` | `1` | 超管 | 成功 | 约 66 个菜单 | 可进入系统 |
| `superAdminTwo` | `2018244380532912130` | 演示账号超管 | 成功 | 空 | 前端清 token，停在登录页 |
| `csyw001` | `2018244380532912130` | 测试业务001 | 成功 | 空 | 前端清 token，停在登录页 |
| `cscw001` | `2018244380532912130` | 测试财务001 | 成功 | 空 | 前端清 token，停在登录页 |
| `cszjb001` | `2018244380532912130` | 测试总经办001 | 成功 | 空 | 前端清 token，停在登录页 |
| `csjs001` | `2018244380532912130` | 测试技术001 | 成功 | 空 | 前端清 token，停在登录页 |

### 已确认页面

`superAdmin / 租户 1` 可打开：

- 组织管理：`/sys/org`
- 人员管理：`/biz/user`
- 工资单管理：`/biz/bizpayroll`
- 我的任务：`/biz/biztask`
- 收入记录：`/biz/paymentrecord`
- 销售项目：`/biz/saleproject`

### 独立验收智能体发现

- 演示租户角色菜单为空，是继续按角色验收 HR/薪资、人事、审批、财务、业务项目流程的 P0 阻塞。
- 无菜单账号拿到 token 后仍可直接调用部分业务读接口，存在权限/数据隔离风险。
- 演示租户缺少验收业务数据，薪资、请假、流程、收入、支出、项目、客户、采购按演示租户筛选基本为空，暂时只能验证接口通不通。

## 统一修改清单

### P0：权限初始化

- 为演示账号租户建立可验收角色：
  - 演示超管/总经理
  - 业务
  - 财务
  - 总经办
  - 技术
- 为上述角色分配菜单、按钮、接口权限和数据范围。
- 将 `superAdminTwo`、`csyw001`、`cscw001`、`cszjb001`、`csjs001` 分别绑定到对应角色。

### P0：后端权限一致性

- 复核无菜单/无接口权限 token 仍可访问业务读接口的问题。
- 对人员、组织、薪资、请假、流程、项目、财务等接口统一权限策略：
  - 无接口权限应返回 403；或
  - 若允许基础读接口，必须严格按租户和数据范围过滤。
- 优先复核人员接口无筛选返回 `total=125` 的数据隔离风险。

### P1：演示数据

- 建立“演示账号”专用验收数据：
  - 员工/岗位
  - 客户
  - 销售项目
  - 收入/支出
  - 请假
  - 薪资
- 数据需带明确演示/测试标识，后续验收可重复使用。

### P1：角色流程验收

- 权限初始化后重跑：
  - 业务：客户、项目、发起流程
  - 财务：收支、工资、审批处理
  - 总经办：审批、报表
  - 技术：任务、项目相关入口
  - 演示超管：组织、人员、岗位、薪资维护

### P2：体验优化

- 登录页“租户id”改为租户选择，或提供默认演示入口。
- 工资单空表列很多，空状态体验需要优化。
- 菜单文案拼写统一，例如 `EXCLE数据`。

## 权限初始化 Dry Run

脚本：[scripts/demo-tenant-permission-init.php](../scripts/demo-tenant-permission-init.php)

执行方式：

```bash
php scripts/demo-tenant-permission-init.php
```

默认是 dry-run，只输出计划，不写数据库。显式传入 `--apply` 才会写入权限关系。

生产写入时必须指定备份目录：

```bash
php scripts/demo-tenant-permission-init.php --apply --backup-dir=/www/wwwroot/oa.fucity.cn/runtime/permission-init-YYYYMMDD-HHMMSS
```

备份目录会生成：

- `before-snapshot.json`：写入前相关角色、用户、关系、资源快照。
- `apply-summary.json`：本次写入汇总和新增 ID。
- `rollback-inserted.sql`：按本次新增 ID 删除的回滚 SQL。

线上 dry-run 结果：

| 角色模板 | 复用角色 | 绑定账号 | 菜单数 | 接口权限数 | 将新增用户角色绑定 | 将新增菜单关系 | 将新增接口权限 |
| --- | --- | --- | ---: | ---: | ---: | ---: | ---: |
| demoAdmin | 总经理 | `superAdminTwo` | 66 | 414 | 0 | 66 | 414 |
| hr | 行政人事 | `cszjb001` | 13 | 104 | 1 | 13 | 104 |
| sales | 销售总监 | `csyw001` | 24 | 88 | 1 | 24 | 88 |
| finance | 财务经理 | `cscw001` | 18 | 118 | 1 | 18 | 118 |
| tech | PHP工程师 | `csjs001` | 23 | 97 | 1 | 23 | 97 |

汇总：

- 新建角色：0
- 新增用户-角色关系：4
- 新增角色-菜单关系：144
- 新增角色-接口权限关系：821

脚本当前只负责权限初始化，不处理后端接口统一鉴权逻辑。执行 `--apply` 前建议先备份：

- `sys_role`
- `sys_relation`
- `sys_user`
- `sys_resource`

2026-06-30 线上 dry-run 复核：

- 脚本语法检查通过：`No syntax errors detected`
- dry-run 备份目录：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-dryrun-20260630-115922`
- dry-run 仍为 0 个新角色、4 条用户-角色关系、144 条角色-菜单关系、821 条角色-接口权限关系。

2026-06-30 线上执行结果：

- 权限初始化已执行：`php scripts/demo-tenant-permission-init.php --apply --backup-dir=/www/wwwroot/oa.fucity.cn/runtime/permission-init-apply-20260630-120025`
- 写入结果：0 个新角色、4 条用户-角色关系、144 条角色-菜单关系、821 条角色-接口权限关系。
- 回滚 SQL：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-apply-20260630-120025/rollback-inserted.sql`
- 执行后重跑 dry-run：0 个新角色、0 条用户-角色关系、0 条角色-菜单关系、0 条角色-接口权限关系。

线上账号复核：

| 账号 | 登录 | 当前用户 | 菜单 | 菜单/权限数量 |
| --- | --- | --- | --- | --- |
| `superAdminTwo` | 200 | 200 | 200 | 66 菜单 / 418 接口权限 |
| `csyw001` | 200 | 200 | 200 | 24 菜单 / 93 接口权限 |
| `cscw001` | 200 | 200 | 200 | 18 菜单 / 123 接口权限 |
| `cszjb001` | 200 | 200 | 200 | 13 菜单 / 108 接口权限 |
| `csjs001` | 200 | 200 | 200 | 23 菜单 / 104 接口权限 |

## 后端接口权限一致性

改动文件：[app/middleware/AuthMiddleware.php](../../app/middleware/AuthMiddleware.php)

线上部署备份：

- 原文件备份：`/www/wwwroot/oa.fucity.cn/runtime/code-backup-authmiddleware-20260630-120442/AuthMiddleware.php.before`

实现策略：

- token 校验通过后，继续用请求路径匹配 token 内的 `permission_codes`。
- `superAdmin` / `tenantAdmin` 内置角色放行。
- 兼容 `/backend`、`/api`、`/index.php` 前缀，统一转小写并去掉尾部 `/`。

线上抽样复核：

| 账号 | 接口 | 预期 | 实际 |
| --- | --- | ---: | ---: |
| `csyw001` | `/biz/customer/page` | 200 | 200 |
| `csyw001` | `/biz/bizpayroll/page` | 403 | 403 |
| `cscw001` | `/biz/bizpayroll/page` | 200 | 200 |
| `cscw001` | `/biz/customer/page` | 403 | 403 |
| `csjs001` | `/biz/task/page` | 200 | 200 |
| `csjs001` | `/biz/saleproject/page` | 200 | 200 |
| `csjs001` | `/biz/saleprojectproductinfo/page` | 200 | 200 |
| `superAdmin` | `/sys/role/page` | 200 | 200 |

补充记录：

- `csjs001` 调用 `/biz/inventory/page` 曾返回 400，不是 403；复核后确认为库存分页需要 `warehousesId`，前端会先调用 `/biz/warehouses/list` 取第一个仓库。
- 已通过演示数据初始化补入 1 个演示仓库、2 个产品、2 条库存，并给技术角色补入 `/biz/warehouses/list` 接口权限。复测 `/biz/warehouses/list` 返回 1 个仓库，`/biz/inventory/page?warehousesId=2026063000100000001` 返回 2 条库存。

## 角色只读 Smoke

2026-06-30 在 `https://oa.fucity.cn/backend` 以“演示账号”租户复核：

| 角色账号 | 已通过入口 | 当前数据 |
| --- | --- | --- |
| `superAdminTwo` | `/sys/org/page`、`/sys/user/page`、`/sys/position/page`、`/sys/role/page`、`/biz/bizpayroll/page`、`/biz/bizleaveapplication/page`、`/biz/task/page` | 系统管理有数据；演示业务数据已补入 |
| `cszjb001` | `/biz/bizpayroll/page`、`/biz/bizleaveapplication/page`、`/biz/task/page` | 工资单 1、请假 1 |
| `csyw001` | `/biz/customer/page`、`/biz/saleproject/page`、`/biz/task/page`、`/biz/ccrecords/page` | 客户 2、销售项目 1 |
| `cscw001` | `/biz/settlementaccount/page`、`/biz/bizcollectionreceipt/page`、`/biz/bizdebitnote/page`、`/biz/bizpaymentrecord/page`、`/biz/bizexpenditurerecord/page`、`/biz/bizpayroll/page`、`/biz/task/page` | 结算账户 2、收款单/借款单/付款/支出/工资单各 1 |
| `csjs001` | `/biz/task/page`、`/biz/saleproject/page`、`/biz/saleprojectproductinfo/page`、`/biz/bizproduct/page`、`/biz/warehouses/list`、`/biz/inventory/page` | 产品 2、仓库 1、库存 2 |

路径修正：

- 抄送任务接口路径是 `/biz/ccrecords/page`，不是 `/biz/copytask/page`。
- 付款记录接口路径是 `/biz/bizpaymentrecord/page`，不是 `/biz/paymentrecord/page`。

## 演示数据初始化

脚本：[scripts/demo-tenant-data-init.php](../../scripts/demo-tenant-data-init.php)

2026-06-30 线上执行结果：

- 执行目录：`/www/wwwroot/oa.fucity.cn`
- 备份目录：`/www/wwwroot/oa.fucity.cn/runtime/demo-data-init-20260630-135300`
- 回滚 SQL：`/www/wwwroot/oa.fucity.cn/runtime/demo-data-init-20260630-135300/rollback-inserted.sql`
- 写入结果：17 行演示数据，全部带 `CODEX_DEMO_20260630` 标识。

写入范围：

| 表 | 写入数量 | 用途 |
| --- | ---: | --- |
| `warehouses` | 1 | 技术/库存页面仓库选择 |
| `biz_product` | 2 | 技术产品、库存、项目数据 |
| `inventory` | 2 | 技术库存分页 |
| `customer` | 2 | 业务客户列表 |
| `biz_sale_project` | 1 | 业务/技术项目列表 |
| `settlement_account` | 2 | 财务账户列表 |
| `biz_payment_record` | 1 | 财务付款/收入记录 |
| `biz_expenditure_record` | 1 | 财务支出记录 |
| `biz_collection_receipt` | 1 | 财务代收款单 |
| `biz_debit_note` | 1 | 财务借款单 |
| `biz_payroll` | 2 | 财务/总经办工资单 |
| `biz_leave_application` | 1 | 总经办请假列表 |

执行后重跑 dry-run：0 行待写入、17 行已存在。

## 技术角色仓库列表权限补丁

脚本：[scripts/demo-tenant-permission-init.php](../../scripts/demo-tenant-permission-init.php)

2026-06-30 线上执行结果：

- 补丁目标：给技术角色 `csjs001` 增加 `/biz/warehouses/list` 接口权限，用于库存页前置加载仓库列表。
- dry-run 结果：仅新增 1 条 `sys_relation` 权限关系。
- 备份目录：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-warehouse-list-20260630-135959`
- 回滚 SQL：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-warehouse-list-20260630-135959/rollback-inserted.sql`
- 执行后重跑 dry-run：0 条待写入。

补丁后线上 smoke：

| 账号 | 接口 | 结果 |
| --- | --- | --- |
| `csjs001` | `/biz/warehouses/list` | 200，1 个仓库，首个仓库 `2026063000100000001` |
| `csjs001` | `/biz/inventory/page?warehousesId=2026063000100000001` | 200，total=2 |

## 公共启动权限补丁

脚本：[scripts/demo-tenant-permission-init.php](../../scripts/demo-tenant-permission-init.php)

2026-06-30 浏览器级验收前发现：演示角色登录后菜单可用，但真实前端启动依赖的公共接口仍被后端 RBAC 拦截。

补入公共接口：

- `/sys/sysConfig/detail`：登录后加载系统/流程配置。
- `/dev/dict/tree`：登录后加载字典缓存。
- `/dev/message/createSseConnect`：布局消息/任务刷新 SSE。
- `/biz/user/orgTreeSelector`：业务页面组织树筛选。
- `/sys/index/message/list`：布局消息列表。

线上执行记录：

- 第一批公共启动补丁：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-bootstrap-public-20260630-142630/rollback-inserted.sql`，新增 15 条 `sys_relation`。
- 技术软件打包页只读依赖补丁：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-tech-saleprojectproductinfo-20260630-144002/rollback-inserted.sql`，新增 1 条 `sys_relation`，补 `/biz/bizdatareport/saleProjectList/details`。
- 组织树/消息列表公共补丁：`/www/wwwroot/oa.fucity.cn/runtime/permission-init-common-org-message-20260630-144507/rollback-inserted.sql`，新增 8 条 `sys_relation`。
- 最终重跑 dry-run：0 条待写入。

启动接口复核：

| 账号 | getLoginUser | loginMenu | sysConfig | processConfig | dictTree |
| --- | ---: | ---: | ---: | ---: | ---: |
| `superAdminTwo` | 200 | 200 | 200 | 200 | 200 |
| `csyw001` | 200 | 200 | 200 | 200 | 200 |
| `cscw001` | 200 | 200 | 200 | 200 | 200 |
| `cszjb001` | 200 | 200 | 200 | 200 | 200 |
| `csjs001` | 200 | 200 | 200 | 200 | 200 |

## 浏览器级角色页面 Smoke

脚本：[scripts/online-role-browser-smoke.ps1](../../scripts/online-role-browser-smoke.ps1)

执行方式：线上登录 `https://oa.fucity.cn`，使用演示机构 `tenantId=2018244380532912130`，无痕 Chrome 打开目标菜单页，检查：

- 未跳转登录页、未渲染 404。
- HTTP 无 4xx/5xx。
- 后端 JSON `code` 无 403/500 等错误。
- 控制台无错误。
- 只读页面未误触发 add/edit/delete/start/approve 等写接口。

2026-06-30 全量结果：16/16 通过。

| 角色 | 账号 | 页面 | 标题 | 行数 | 结果 |
| --- | --- | --- | --- | ---: | --- |
| 业务 | `csyw001` | `/biz/customer` | 客户管理 | 3 | 通过 |
| 业务 | `csyw001` | `/biz/saleproject` | 销售项目管理 | 2 | 通过 |
| 财务 | `cscw001` | `/biz/settlementaccount` | 结算账户表管理 | 2 | 通过 |
| 财务 | `cscw001` | `/biz/paymentrecord` | 收入记录管理 | 1 | 通过 |
| 财务 | `cscw001` | `/biz/bizexpenditurerecord` | 支出记录表管理 | 1 | 通过 |
| 财务 | `cscw001` | `/biz/bizcollectionreceipt` | 代收款单管理 | 1 | 通过 |
| 财务 | `cscw001` | `/biz/bizdebitnote` | 借款/代付单管理 | 1 | 通过 |
| 总经办 | `cszjb001` | `/biz/bizpayroll` | 工资单管理 | 2 | 通过 |
| 总经办 | `cszjb001` | `/biz/bizleaveapplication` | 请假记录表管理 | 1 | 通过 |
| 技术 | `csjs001` | `/biz/bizproduct` | 产品管理 | 2 | 通过 |
| 技术 | `csjs001` | `/biz/inventory` | 仓库库存管理 | 2 | 通过 |
| 技术 | `csjs001` | `/biz/saleprojectproductinfo` | 软件打包表管理 | 1 | 通过 |
| 演示超管 | `superAdminTwo` | `/sys/org` | 组织管理 | 10 | 通过 |
| 演示超管 | `superAdminTwo` | `/sys/user` | 用户管理 | 10 | 通过 |
| 演示超管 | `superAdminTwo` | `/sys/position` | 职位管理 | 10 | 通过 |
| 演示超管 | `superAdminTwo` | `/sys/role` | 角色权限 | 10 | 通过 |

脚本稳定性补充：`scripts/online-role-browser-smoke.ps1` 已改为由系统分配空闲 Chrome CDP 端口，并在 Chrome 启动失败时保留 stderr，避免随机端口/启动时序造成误报。

## 受控写入 Smoke

脚本：[scripts/online-controlled-write-smoke.ps1](../../scripts/online-controlled-write-smoke.ps1)

执行方式：线上登录 `https://oa.fucity.cn/backend`，使用演示机构 `tenantId=2018244380532912130`，每项执行“新增 -> 详情/列表验证 -> 删除 -> marker 查 0”，并在异常时用 `finally` 清理已创建记录。

2026-06-30 线上结果：3/3 通过，临时数据已通过业务删除接口清理。

marker：`CODEX_WRITE_SMOKE_20260630150732`

| 范围 | 账号 | 接口闭环 | 临时 ID | 验证结果 |
| --- | --- | --- | --- | --- |
| 业务 | `csyw001` | `/biz/customerfollowup/add` -> `/detail` -> `/delete` | `1782803255112552998` | 跟进内容一致，删除后 marker 查询 0 条 |
| 总经办 | `cszjb001` | `/biz/bizleaveapplication/add` -> `/detail` -> `/delete` | `1782803257270121538` | 请假备注一致，删除后 marker 查询 0 条 |
| 技术 | `csjs001` | `/biz/saleprojectproductinfo/add` -> `/detail` -> `/delete` | `1782803259352567943` | 软件打包内容一致，删除后 marker 查询 0 条 |

写入清理后页面复查：

| 账号 | 页面 | 行数 | 结果 |
| --- | --- | ---: | --- |
| `csyw001` | `/biz/customer` | 3 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `cszjb001` | `/biz/bizleaveapplication` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `csjs001` | `/biz/saleprojectproductinfo` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |

## 财务受控写入 Smoke

脚本：[scripts/online-finance-controlled-write-smoke.ps1](../../scripts/online-finance-controlled-write-smoke.ps1)

执行方式：线上登录 `https://oa.fucity.cn/backend`，使用演示机构 `tenantId=2018244380532912130` 和财务账号 `cscw001`。脚本创建临时结算账户，并在该账户上执行“收入 -> 代收款单 -> 支出 -> 借款单 -> 依赖倒序删除 -> 账户余额恢复校验 -> 删除临时账户”。

2026-07-01 线上结果：5/5 写入闭环通过，临时数据已清理。

marker：`CODEX_FINANCE_SMOKE_20260701084523`

| 范围 | 账号 | 接口闭环 | 临时 ID | 验证结果 |
| --- | --- | --- | --- | --- |
| 财务 | `cscw001` | `/biz/settlementaccount/add` -> `/detail` -> `/delete` | `1782866724801240390` | 临时账户初始/当前金额 `1000.00`，最终删除成功 |
| 财务 | `cscw001` | `/biz/settlementaccount/payment/add` -> `/biz/bizpaymentrecord/detail` -> `/delete` | `1782866725188861267` | 收入 `120.50`，账户金额 `1000.00 -> 1120.50`，删除后金额回滚 |
| 财务 | `cscw001` | `/biz/bizcollectionreceipt/add` -> `/detail` -> `/delete` | `1782866725660795458` | 代收款单 `50.00`，未结算，删除成功 |
| 财务 | `cscw001` | `/biz/settlementaccount/expenses/add` -> `/biz/bizexpenditurerecord/detail` -> `/delete` | `1782866726018469667` | 支出 `70.25`，账户金额 `1120.50 -> 1050.25`，删除后金额回滚 |
| 财务 | `cscw001` | `/biz/bizdebitnote/add` -> `/detail` -> `/delete` | `1782866726583304394` | 借款/代付单 `20.00`，未结算，删除成功 |

清理复核：

- 删除收入、支出记录后，临时账户当前金额恢复到 `1000.00`。
- 删除临时账户后，按 marker 查询活跃记录：`settlement_account`、`biz_payment_record`、`biz_expenditure_record`、`biz_collection_receipt`、`biz_debit_note`、`settlement_account_statement` 均为 0。

写入清理后页面复查：

| 账号 | 页面 | 行数 | 结果 |
| --- | --- | ---: | --- |
| `cscw001` | `/biz/settlementaccount` | 2 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `cscw001` | `/biz/paymentrecord` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `cscw001` | `/biz/bizexpenditurerecord` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `cscw001` | `/biz/bizcollectionreceipt` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `cscw001` | `/biz/bizdebitnote` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |

## 销售项目立项流程 Smoke

脚本：[scripts/online-project-init-workflow-smoke.ps1](../../scripts/online-project-init-workflow-smoke.ps1)

执行方式：线上登录 `https://oa.fucity.cn/backend`，使用演示机构 `tenantId=2018244380532912130` 和业务账号 `csyw001`。脚本远端创建临时客户、产品、结算账户、3 个销售项目和附件，分别执行“发起后撤回”、“发起后驳回”、“发起后审批通过”，然后按流程实例和业务 ID 硬清理临时数据。

2026-07-01 线上结果：3/3 流程闭环通过，临时数据已清理。

marker：`CODEX_PROJECT_INIT_SMOKE_20260701095344`

| 范围 | 账号 | 流程闭环 | 临时项目 ID | 流程实例 ID | 验证结果 |
| --- | --- | --- | --- | --- | --- |
| 业务 | `csyw001` | `/biz/process/project/init/start` -> `/biz/process/cancel` | `XP0701095344` | `0baa4faf-3b5f-4955-9ab7-1985f2e9e843` | 项目状态回到 `FOLLOW` |
| 业务 | `csyw001` | `/biz/process/project/init/start` -> `/biz/task/reject` | `RP0701095344` | `8598afd8-a50a-45e9-9cbe-0203cdc3f6a6` | 项目状态回到 `FOLLOW` |
| 业务 | `csyw001` | `/biz/process/project/init/start` -> `/biz/task/approve` | `VP0701095344` | `2c914761-5ac9-48b4-bd99-81165d66d651` | 项目状态 `WAIT_DELIVER`，产品明细 1 条，销售项目附件关联 1 条，开票记录 1 条，客户成交数增量为 `1.00` |

清理复核：

- 审批通过后校验历史流程状态为 `COMPLETED`，运行时任务、变量、执行记录均为 0。
- 清理后按临时 ID 查询：`customer`、`biz_product`、`settlement_account`、`biz_sale_project`、`dev_file` 均为 0。
- 脚本清理逻辑已补强：`act_ru_execution` 按子执行到父执行顺序逐条删除，避免失败路径下外键约束中断清理。

流程后页面复查：

| 账号 | 页面 | 行数 | 结果 |
| --- | --- | ---: | --- |
| `csyw001` | `/biz/saleproject` | 2 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |
| `csyw001` | `/biz/biztask` | 1 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |

## 薪资直接维护/生成 Smoke

脚本：[scripts/online-payroll-controlled-smoke.ps1](../../scripts/online-payroll-controlled-smoke.ps1)

执行方式：线上登录 `https://oa.fucity.cn/backend`，使用演示机构 `tenantId=2018244380532912130` 和总经办账号 `cszjb001`。脚本远端创建 3 个临时用户、1 个临时客户、2 个销售项目、2 条收款记录和 2 条事假记录，然后通过真实接口执行薪资直接维护和工资生成，最后按精确 ID 硬清理临时数据。

2026-07-01 线上结果：2/2 薪资闭环通过，临时数据已清理。

marker：`CODEX_PAYROLL_SMOKE_20260701100907`

| 范围 | 账号 | 接口闭环 | 临时 ID | 验证结果 |
| --- | --- | --- | --- | --- |
| 总经办/薪资 | `cszjb001` | `/biz/bizpayroll/add` -> `/detail` -> `/edit` -> `/bath/edit` -> `/delete` | `1782871750679469405` | 手工工资单新增成功；编辑后 `actualAmount=3710.00`；批量编辑后 `actualAmount=3720.00`、`rateCommission=5.00`；删除后详情不可见 |
| 总经办/薪资 | `cszjb001` | `/biz/bizpayroll/generate/add` -> `/delete` | `1782871752224126399`、`1782871752224509612` | 生成 2 条工资单；用户 A 统计当月成交 `1000.00`、当月收款 `900.00`、历史项目本月收款 `650.00`、事假 `1.50`、实发 `2126.55`；用户 B 跨月事假折算 `1.50`、实发 `1001.55` |

清理复核：

- 通过业务 `/biz/bizpayroll/delete` 逻辑删除 3 条临时工资单后，活跃薪资残留为 0。
- 最终硬清理后，按精确 ID 查询：`biz_payroll`、`sys_user`、`biz_sale_project`、`biz_payment_record`、`biz_leave_application`、`customer` 均为 0。
- 本轮修复并已同步线上：`app/service/auth/TokenService.php` 现在会把 `RbacService` 生成的 `data_scopes` 写入 token payload；线上原文件备份在 `/www/wwwroot/oa.fucity.cn/runtime/tokenservice-backup-20260701-100743/app/service/auth/TokenService.php`。

薪资后页面复查：

| 账号 | 页面 | 行数 | 结果 |
| --- | --- | ---: | --- |
| `cszjb001` | `/biz/bizpayroll` | 2 | 通过，无 4xx/5xx、无后端 JSON 错误、无控制台错误 |

本轮发现：

- `/biz/settlementaccount/payment/add` 和 `/biz/settlementaccount/expenses/add` 对 `objectId` 没有接口层长度校验；线上字段 `OBJECT_ID` 为 `varchar(20)`，传入过长值时会直接触发数据库异常并返回 `code=500`。本轮脚本已改用 20 字符以内对象编号继续验收，后续建议在 service 层返回 400 级参数错误。
- `/biz/task/approve` 在销售项目立项审批通过时，服务层允许 `consignee` 最长 80 字符，但线上 `biz_sale_project.CONSIGNEE` 为 `varchar(40)`；传入 40 字符以上值会触发数据库异常并被控制器包装成 `code=500`。本轮脚本已改用短表单值继续验收，后续建议把服务层长度校验与数据库字段长度对齐，并记录真实异常日志。
- 已修复：`TokenService::create()` 未写入 `data_scopes`，导致普通角色虽然有接口权限，但服务层无法读取组织数据范围，`cszjb001` 对同组织临时用户薪资写入返回 403。修复后新登录 token 可携带 108 条数据范围，薪资 smoke 已通过。

下一轮优先事项：

- 已完成客户跟进、请假记录、软件打包记录的最小新增/详情/删除闭环。
- 已完成财务结算账户、收入、支出、代收款单、借款/代付单的最小新增/详情/删除闭环。
- 已完成销售项目立项流程发起、撤回、驳回、审批通过闭环。
- 已完成薪资直接维护和工资生成闭环。
- 继续高风险流程验收：更多审批任务通过/驳回/撤回组合，以及销售交付/收款/退货/补发等项目后续流程。
- 每个写入项先准备回滚或测试数据清理策略，再执行线上 smoke。
