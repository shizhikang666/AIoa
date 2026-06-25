# workflow-agent Java Map

## Source Scope

Java project path, read-only:

`F:\AI\projects\testJava\OA`

Workflow module source roots:

- `bpmn`
- `snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizprocess`
- `snowy-plugin\snowy-plugin-sys\src\main\java\vip\xiaonuo\sys\modular\userprocessconfig`
- `snowy-plugin-api\snowy-plugin-biz-api\src\main\java\vip\xiaonuo\biz\api\ProcessApi.java`

## Java Runtime Engine

The Java workflow module uses Camunda engine APIs:

- `RuntimeService`
- `HistoryService`
- `TaskService`
- `FormService`
- `RepositoryService`
- `IdentityService`

The database contains Camunda-style `act_*` tables. ThinkPHP cannot directly execute Java delegates, so a later phase must replace delegate behavior with PHP services or choose a compatible workflow engine strategy.

## Main Controllers

### `BizProcessController.java`

Main process routes:

| Method | Path | Meaning | Notes |
| --- | --- | --- | --- |
| GET | `/biz/process/page` | Current user's started processes | Uses historic process query by starter |
| GET | `/biz/process/all/page` | Managed/all process page | Permission `/biz/process/all/page` |
| POST | `/biz/process/query/list` | Query process list by variable attributes | Uses historic variables |
| POST | `/biz/process/variable` | Read process variables | Uses form metadata plus historic variables |
| POST | `/biz/process/fileList` | Read process files | Uses variable `fileIdList` |
| GET | `/biz/process/detail` | Process detail | Includes activity participants |
| GET | `/biz/process/query` | Runtime process query by variables | Uses runtime variables |
| POST | `/biz/process/cancel` | Cancel process | Permission `/biz/process/cancel` |
| POST | `/biz/process/procure/start` | Start purchase process | Process `Process_procure` |
| POST | `/biz/process/procure/warehouse/start` | Start purchase warehouse process | Process `Process_procure_in_warehouse` |
| POST | `/biz/process/reimbursement/start` | Start reimbursement process | Process `Process_reimbursement` |
| POST | `/biz/process/makePayment/start` | Start payment-out process | Process `Process_make_payment` |
| POST | `/biz/process/payment/start` | Start payment-in process | Process `Process_payment` |
| POST | `/biz/process/leave/start` | Start leave/travel process | Process `Process_ask_leave` |
| POST | `/biz/process/leave/edit` | Edit editable leave process variables | Only when `isEdit` is true |

ThinkPHP coverage as of 2026-06-22:

- `leave/start`, `leave/edit`, leave approve/reject, and initial leave cancel are covered through bounded PHP runtime writes.
- `payment/start`, `reimbursement/start`, `makePayment/start`, `procure/start`, and `procure/warehouse/start` are covered as first-step runtime/history starts plus initial cancel.
- `Process_procure` approve now replaces `BizProcureApproveDelegate` through staged procurement confirmation, optional general-office approval, and PHP purchase-order creation.
- `Process_procure_in_warehouse` approve now replaces `BizProcureInWareHouseJavaDelegate` through the PHP purchase-order warehouse-in service.
- `Process_sale_project_init` start/approve now replaces the project-init state delegate for the bounded initial-order path; cancel/reject rolls the sale project back to `FOLLOW`.
- `Process_sale_project_play` start/approve now replaces the project collection delegate for the bounded collection path; first approval advances to `Activity_payment_approval`, finance approval writes collection statement/payment rows, and cancel/reject close without finance side effects.
- Project delivery/reissue/return approve/reject completion and remaining Java delegate side effects remain deferred.

### `BizProcessProjectController.java`

Project-related process routes:

| Method | Path | Meaning | Process |
| --- | --- | --- | --- |
| GET | `biz/process/project/runtime/query/list` | Runtime project process list | Variable `projectId` |
| POST | `/biz/process/project/play/start` | Start project collection progress | `Process_sale_project_play` |
| POST | `/biz/process/project/init/start` | Start project initialization approval | `Process_sale_project_init` |
| POST | `/biz/process/project/delivery/start` | Start project delivery approval | `Process_sale_project_delivery` |
| POST | `/biz/process/project/reissue/start` | Start project reissue approval | `Process_project_reissue_product` |
| POST | `/biz/process/project/return/start` | Start project return approval | `Process_sale_project_product_return` |

### `BizTaskController.java`

Task routes:

| Method | Path | Meaning | Notes |
| --- | --- | --- | --- |
| GET | `/biz/task/page` | Current user's pending task page | Assignee is login user |
| GET | `/biz/task/list` | Current user's pending task list | No paging in service behavior |
| GET | `/biz/task/count` | Current user's pending task count | Assignee is login user |
| GET | `/biz/task/history/page` | Current user's completed task page | Uses historic task query |
| POST | `/biz/task/reject` | Reject approval task | Requires login |
| POST | `/biz/task/approve` | Approve task | Permission `/biz/task/approve` |
| GET | `/biz/task/runtime/activity/detail` | Runtime task form detail | Reads task form fields and runtime variables |

### `SysUserProcessConfigController.java`

Workflow user config routes:

| Method | Path | Meaning |
| --- | --- | --- |
| POST | `/biz/userprocessconfig/save` | Save current user's default process approver/copy config |
| GET | `/biz/userprocessconfig/detail` | Read current user's workflow config |

## Core Services

### `BizBaseProcessServiceImpl`

Starts a process by key. Adds common variables:

- `tenantId`
- `approval`
- `org`

It also sets the authenticated start user through Camunda `IdentityService`.

### `BizProcessServiceImpl`

Handles non-project process listing, detail, variables, cancel, and start actions.

Important variable names:

- `title`
- `status`
- `remark`
- `amount`
- `isEdit`
- `copyUserIdList`
- `fileIdList`
- `tenantId`
- `org`
- `initiator`
- `approveUserIdList`
- `treasurer`
- `procure`

### `BizProjectProcessServiceImpl`

Handles sale project process starts and runtime project process list.

Business side effects:

- Project init changes sale project status before starting workflow.
- Delivery validates warehouse and product item amounts.
- Project payment/reissue/return processes call business delegates after approval.

ThinkPHP now covers project init start/approval/cancel/reject through `Process_sale_project_init`, project delivery start/approval/cancel/reject through `Process_sale_project_delivery`, and project play start/approval/cancel/reject through `Process_sale_project_play`. Project reissue and return starts remain controlled-deferred until their Java state changes and delegates are replaced explicitly.

### `BizTaskServiceImpl`

Handles task query and approval/reject.

Important behavior:

- Pending tasks are filtered by `taskAssignee(loginUserId)`.
- Approve submits task form with `state=AGREE`.
- Reject submits task form with `approval=false`, `state=REJECT`, and `comment`.
- Date task form fields are parsed before submission.
- Required start form fields are validated before approval.
- SSE notices are sent to approvers, treasurer, or purchaser.

## Process Categories

Mapped from `BizProcessCategoryEnums` and BPMN IDs:

| Process Key | Name From BPMN | Main Domain |
| --- | --- | --- |
| `Process_procure` | Purchase request | Procurement |
| `Process_procure_in_warehouse` | Purchase warehousing | Warehouse |
| `Process_reimbursement` | Reimbursement approval | Finance |
| `Process_make_payment` | Payment request | Finance |
| `Process_payment` | Income/payment-in flow | Finance |
| `Process_sale_project_init` | Order/project initial approval | Sale project |
| `Process_sale_project_play` | Collection progress add | Sale project finance |
| `Process_sale_project_delivery` | Delivery flow | Sale project warehouse |
| `Process_project_reissue_product` | Project reissue flow | Sale project procurement |
| `Process_sale_project_product_return` | Return flow | Sale project after-sales |
| `Process_ask_leave` | Leave process | Personnel |
| `Process_sys` | System process | Enum only; no BPMN file found in this phase |

## BPMN Files

| File | Process Key |
| --- | --- |
| `bpmn/Process_procure.bpmn` | `Process_procure` |
| `bpmn/Process_payment.bpmn` | `Process_payment` |
| `bpmn/Process_sale_project_product_return.bpmn` | `Process_sale_project_product_return` |
| `bpmn/sale_project_play.bpmn` | `Process_sale_project_play` |
| `bpmn/Process_procure_in_warehouse.bpmn` | `Process_procure_in_warehouse` |
| `bpmn/sale_project_init.bpmn` | `Process_sale_project_init` |
| `bpmn/Process_make_payment.bpmn` | `Process_make_payment` |
| `bpmn/Process_sale_project_delivery.bpmn` | `Process_sale_project_delivery` |
| `bpmn/Process_project_reissue_product.bpmn` | `Process_project_reissue_product` |
| `bpmn/Process_reimbursement.bpmn` | `Process_reimbursement` |
| `bpmn/personnel/Process_ask_leave.bpmn` | `Process_ask_leave` |

## Java Delegates To Replace Later

| Delegate | BPMN Usage | PHP Replacement Direction |
| --- | --- | --- |
| `CopyUserDelegate` | Copy-to participant task | Write `biz_cc_records` |
| `BizProcureApproveDelegate` | Purchase approval result | Create purchase order and related data |
| `BizProcureInWareHouseJavaDelegate` | Purchase warehousing result | Covered: update purchase order and warehouse records |
| `BizReimbursementApproveDelegate` | Reimbursement/payment-out result | Covered: two-step payment-out approval creates expenditure record and settlement-account serial flow |
| `BizPaymentApproveDelegate` | Payment-in result | Covered: create payment record and settlement-account serial flow |
| `BizSaleProjectInitStateApproveDelegate` | Project init result | Update sale project state |
| `BizSaleProjectPlayStateApproveDelegate` | Project collection result | Covered: create sale project payment record and recalculate payment status |
| `BizSaleProjectDeliveryApproveDelegate` | Delivery result | Update delivery/warehouse/product state |
| `BizSaleProjectReissueProductApproveDelegate` | Reissue result | Create reissue order |
| `BizSaleProjectReturnProductApproveDelegate` | Return result | Create return-related records |
| `LeaveApproveDelegate` | Leave process result | Create leave application |

## User Process Config

`sys_user_process_config.CONFIG_JSON` stores per-user process settings:

```json
{
  "config": [
    {
      "processName": "Process_reimbursement",
      "approveUserIdList": [],
      "copyUserIdList": [],
      "treasurer": "",
      "procure": ""
    }
  ]
}
```

Fields:

- `processName`: process key
- `approveUserIdList`: default approvers
- `copyUserIdList`: default copy-to users
- `treasurer`: finance approver for finance flows
- `procure`: purchase user for procurement flows

## Workflow Agent Boundary

This phase only documents the workflow map. Later implementation must coordinate with:

- auth-agent for current login user, permissions, tenant, org, data scope
- user-agent for user/org lookup
- db-agent for model/table compatibility
- api-agent for route standardization
- frontend-agent for workflow form payload compatibility
