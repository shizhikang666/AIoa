# Biz Workflow Read-Only Compatibility

Date: 2026-06-02

Agent: workflow-agent / api-agent

## Scope

This slice adds protected read-only ThinkPHP endpoints used by the copied Vue workflow pages. It maps Java workflow query routes to the existing ThinkPHP Camunda-table read layer.

The Java project remains read-only. This slice does not implement approval, rejection, process start, process cancel, task SSE, workflow writes, or Java delegate side effects.

## Java Reference

- `BizProcessController.java`
- `BizProcessProjectController.java`
- `BizTaskController.java`
- `BizProcessServiceImpl.java`
- `BizProjectProcessServiceImpl.java`
- `BizTaskServiceImpl.java`

## Routes

| Method | Route | ThinkPHP handler |
| --- | --- | --- |
| GET | `/biz/process/all/page` | `biz.ProcessController/allPage` |
| GET | `/biz/process/query` | `biz.ProcessController/query` |
| POST | `/biz/process/query/list` | `biz.ProcessController/queryList` |
| GET | `/biz/process/project/runtime/query/list` | `biz.ProcessController/projectRuntimeQueryList` |
| POST | `/biz/process/fileList` | `biz.ProcessController/fileList` |
| GET | `/biz/task/runtime/activity/detail` | `biz.TaskController/runtimeActivityDetail` |

All routes are protected by `AuthMiddleware`.

## Response Notes

- Process rows include raw Camunda fields plus frontend-friendly aliases:
  - `id`
  - `instanceId`
  - `processInstanceId`
  - `category`
  - `processKey`
  - `title`
  - `status`
  - `remark`
  - `amount`
  - `createTime`
  - `startTime`
  - `endTime`
  - `variable`
- `query` returns Java-compatible entries with `variable`, `processIdList`, and `variableMap`.
- `query/list` filters historic processes by process keys and variable attributes when provided.
- `project/runtime/query/list` returns runtime process rows matching `projectId`.
- `fileList` reads attachment rows through existing `biz_file_relation` and `dev_file` read logic.
- `runtime/activity/detail` returns `category`, `variables`, `taskId`, `processKey`, `processInstanceId`, and `processDefinitionId`.
- Existing `detail` and `variable` reads now accept either `processInstanceId` or the Java/frontend `id` parameter.
- `detail` also returns the old frontend detail shape: `userProcess`, `startUser`, `startOrgTree`, `userActivityList`, and `ccUser`.

## Deferred

- `POST /biz/task/approve`
- `POST /biz/task/reject`
- `GET /biz/task/sse/stream`
- `POST /biz/process/cancel`
- All process start/edit routes
- Java delegate side effects
- Long-lived task SSE or Redis workflow push
