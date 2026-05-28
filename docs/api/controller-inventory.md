# Java Controller Inventory

Source project, read-only: `F:\AI\projects\testJava\OA`

Discovery command:

```powershell
rg --files -g "*Controller.java" "F:\AI\projects\testJava\OA"
```

## Controller Groups

### Auth

- `AuthController`
- `AuthClientController`
- `AuthThirdController`
- `AuthSessionController`

Owner: auth-agent.

api-agent must not duplicate login, token, session, RBAC, or menu behavior.

### System

- `SysUserController`
- `SysUserCenterController`
- `SysOrgController`
- `SysPositionController`
- `SysRoleController`
- `SysModuleController`
- `SysMenuController`
- `SysButtonController`
- `SysConfigController`
- `SysIndexController`
- `SysUserProcessConfigController`

Owner split:

- auth-agent: role/resource/menu/button permission behavior, login menu compatibility.
- user-agent: users, organization tree, positions, user center profile behavior.
- workflow-agent: user process configuration only when tied to approval flow behavior.
- api-agent: controller adapter mapping and route standardization after service owners are ready.

### Business

Examples include sales projects, product, customer, supplier, inventory, purchase order, return order, invoices, settlement account, payroll, leave/vacation, data report, team project, comments, tasks, and file relation controllers.

Owner split:

- db-agent: table/model mapping.
- workflow-agent: approval/process endpoints.
- future business module agents: actual domain services.
- api-agent: controller mapping and response compatibility only.

### Development Support

- file, message, log, monitor, job, dict, email, sms, SSE, web push.

These endpoints need explicit implementation decisions because they may require storage, background jobs, external services, or streaming behavior.

### Mobile, Tenant, Client, Generator

- mobile resource controllers
- tenant and config controllers
- client user controller
- generator config/basic controllers

These should be deferred until the main B-side OA APIs are stable.

## Route Integration Boundary

`route/app.php` is locked. api-agent should not edit it directly in Phase 1.

Recommended later approach:

1. Prepare controller group mapping docs.
2. Write `docs/tasks/public-file-change-request.md` for route registration.
3. Wait for confirmation or leave route registration to merge-agent.
4. Keep controller adapter code narrow and module-owned service calls explicit.
