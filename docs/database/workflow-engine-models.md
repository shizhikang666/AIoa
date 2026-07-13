# Workflow Engine Models

Primary SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`

## Purpose

Add passive ThinkPHP model coverage for Camunda-style workflow tables so workflow-agent can later implement read-only process/task queries without creating database schema changes.

## Added Base Model

- `ActBaseModel`

The base model uses `ID_` as the primary key because Camunda tables use underscore-suffixed column names.

## Added Table Models

| Model | Table | Main Use |
| --- | --- | --- |
| `ActGeBytearray` | `act_ge_bytearray` | BPMN resources and serialized variable values |
| `ActReDeployment` | `act_re_deployment` | Deployment metadata |
| `ActReProcdef` | `act_re_procdef` | Process definitions |
| `ActRuExecution` | `act_ru_execution` | Runtime process execution tree |
| `ActRuTask` | `act_ru_task` | Pending user tasks |
| `ActRuVariable` | `act_ru_variable` | Runtime process variables |
| `ActRuIdentitylink` | `act_ru_identitylink` | Runtime identity links |
| `ActHiProcinst` | `act_hi_procinst` | Historic process instances |
| `ActHiTaskinst` | `act_hi_taskinst` | Historic task instances |
| `ActHiVarinst` | `act_hi_varinst` | Historic process variables |
| `ActHiActinst` | `act_hi_actinst` | Historic activity timeline |
| `ActHiComment` | `act_hi_comment` | Historic comments/opinions |
| `ActHiIdentitylink` | `act_hi_identitylink` | Historic identity links |

## Boundary

These models do not implement workflow runtime behavior. workflow-agent owns query services, approval behavior, process start behavior, and Java delegate replacement planning.

## Data Sync Reminder

The final online real-time data synchronization plan must include both runtime `act_ru_*` tables and history `act_hi_*` tables, otherwise active workflow state and audit history may diverge.
