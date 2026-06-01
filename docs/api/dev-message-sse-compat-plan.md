# Dev Message SSE Compatibility Plan

Date: 2026-06-01

Agent: api-agent / frontend-agent coordination

## Scope

This document records the compatibility plan and first implementation slice for the frontend SSE endpoint:

- `GET /dev/message/createSseConnect`

The first implementation slice is intentionally minimal. It registers the route and returns a short compatible `text/event-stream` response. It does not implement broadcast, workflow push side effects, database mutation, frontend changes, Redis pub/sub, or Java source changes.

## Java Source Reference

Read-only Java files reviewed:

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-sys\src\main\java\vip\xiaonuo\sys\modular\index\controller\SysIndexController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-sys\src\main\java\vip\xiaonuo\sys\modular\index\service\impl\SysIndexServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-dev\src\main\java\vip\xiaonuo\dev\modular\sse\service\impl\DevSseEmitterServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-dev\src\main\java\vip\xiaonuo\dev\modular\sse\enums\NoticeEnum.java`

## Frontend Callers

The copied Vue frontend opens an EventSource connection from:

- `snowy-admin-web/src/layout/components/message.vue`
- `snowy-admin-web/src/layout/components/panel-message/index.vue`

Both callers send:

- query: `clientId`
- header: `Authorization: Bearer <token>`
- expected stream event name: default `message`

Expected event payload examples:

```json
{"code":0,"data":"new-client-id"}
{"code":200,"data":"FlushProcessNotice"}
{"code":200,"data":"FlushMessageNotice"}
```

## Java Behavior Summary

Java exposes `GET /dev/message/createSseConnect` in `SysIndexController`.

The controller delegates to `SysIndexServiceImpl#createSseConnect`, which delegates to `DevSseApi#createSseConnect(clientId, true, true, null)`.

`DevSseEmitterServiceImpl` then:

- reads the current login id from the auth context;
- reuses an existing valid client connection when possible;
- creates a new generated client id when needed;
- sends the generated client id with `code = 0`;
- keeps a heartbeat task alive;
- sends later refresh notices such as `FlushProcessNotice` and `FlushMessageNotice`.

## ThinkPHP Current State

Current ThinkPHP routes expose read-only message APIs:

- `GET /dev/message/page`
- `GET /dev/message/detail`
- `GET /dev/message/createSseConnect`

The SSE route was added after the public-file request was continued by the user:

- `route/app.php`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageSseService.php`

## Recommended Implementation Slice

The minimal SSE compatibility endpoint now uses:

- Controller method: `app\controller\dev\MessageController::createSseConnect`
- Service/helper: a small `MessageSseService` or a method on `MessageService`
- Route: `GET /dev/message/createSseConnect`
- Middleware: `AuthMiddleware`

Initial behavior is conservative:

- authenticate by existing bearer-token middleware;
- accept optional `clientId`;
- return `text/event-stream`;
- send one initial event with `code = 0` and the effective `clientId`;
- send a lightweight compatible message event for `FlushMessageNotice`;
- send a heartbeat comment;
- avoid mutation of message records;
- avoid implementing broadcast/push writes until workflow/message mutation modules are ready.

The response is short-lived by design. A persistent PHP SSE loop could block the local `php think run` development server, so full long-lived realtime behavior is deferred to a later runtime design.

## Deferred Behavior

Do not implement in the first SSE compatibility slice:

- message broadcast route;
- manual send-message route;
- workflow task push side effects;
- mark-read mutation;
- cross-process worker queue;
- Redis pub/sub fanout;
- online production realtime-data synchronization.

## Public File Rule

Adding the route requires editing locked file:

- `route/app.php`

The route change was recorded in:

- `docs/tasks/public-file-change-request.md`

The first route implementation is applied. Further realtime behavior still requires a separate public-file/runtime design if it touches routes, queues, Redis pub/sub, or workflow write side effects.
