# Remaining Table Audit

Agent: db-agent

Source:

- Java entities under `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz`
- SQL dump `F:\AI\projects\testJava\OA\oa2026.sql`
- Current ThinkPHP Models under `F:\AI\projects\testJava\OA-db\app\model`

## Result

The db-agent foundation is sufficient for the next staged agent, auth-agent.

The current Model set covers:

- Auth and RBAC foundations: users, roles, resources, relations, tenant/client/mobile auth helpers.
- Organization foundations: users, departments/orgs, positions.
- Core business foundations: customers, suppliers, warehouses, inventory, purchase, sales project, leave, payment, expenditure, file relation.
- Sales support: product, delivery, invoicing, follow-up, return, field change, and rating tables.
- Finance settlement: collection receipt, debit note, settlement account, account statement.
- Team collaboration support: comments, replies, task comments, categories, project users, task users.

## Remaining Java Biz Entity Tables

These tables are still not modeled as dedicated ThinkPHP Models:

| Java entity | Table | Audit decision |
| --- | --- | --- |
| `BizDraft` | `biz_draft` | Low-priority draft storage. Defer until api-agent needs draft endpoints. |
| `BizHistoryExcel` | `biz_history_excel` | Export/import history. Defer until frontend/api export work. |
| `BizPayroll` | `biz_payroll` | Payroll module. Defer because it is outside current auth/user/workflow startup path. |
| `BizUserVacation` | `biz_user_vacation` | Vacation balance/support table. Defer until user/workflow requirements are explicit. |
| `BizRelation` | `BIZ_RELATION` | Generic business relation table. Defer until a concrete relation category is needed. |
| `BizFile` | `DEV_FILE` | Shared dev file table. Defer to api-agent or a dev-shared db slice. |
| `BizDict` | `DEV_DICT` | Shared dictionary table. Defer to api-agent/frontend-agent if dict endpoints are migrated. |

## Handoff Decision

db-agent should pause here and hand over to auth-agent.

Reasoning:

- The original staged order requires auth-agent after db-agent.
- Auth-agent has the required foundational tables already modeled: `sys_user`, `sys_role`, `sys_resource`, `sys_relation`, `tenant`, `auth_third_user`, `client_user`, `client_relation`, and `mobile_resource`.
- Remaining unmapped tables are not required to start login, Token, RBAC, menu, or permission migration.
- Adding more low-priority Models now would slow the staged workflow without reducing auth-agent risk.

## Follow-Up

If api-agent or workflow-agent later hits an unmapped table, create a small db-agent follow-up branch/commit for that specific dependency.

Production online realtime data synchronization remains a final-stage requirement and must not start until the final merged ThinkPHP OA project is complete and a confirmed sync plan exists.
