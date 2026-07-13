# Dev Monitor Read-Only Compatibility

Date: 2026-06-04

Agent: api-agent / frontend-agent

## Scope

This slice adds read-only ThinkPHP compatibility for dev monitor network information.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Route

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/dev/monitor/networkInfo` | Read current server network upload/download rates. |

The route is protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `DevMonitorController.networkInfo` returns a `DevMonitorServerResult` containing `devMonitorNetworkInfo`.
- Java `DevMonitorNetworkInfo` exposes `upLinkRate` and `downLinkRate`.
- The ThinkPHP implementation samples OS network counters twice and returns formatted per-second rates.
- If OS counters are unavailable, the endpoint returns `0 B/s` rates instead of failing.

## Deferred

The following remain intentionally deferred:

- monitor write or control actions
- long-running metric storage
- server process management
- Java source changes
- database schema changes

## 2026-06-15 HTTP Smoke Coverage

`scripts/dev-read-http-smoke.ps1` now covers authenticated monitor reads for:

- `GET /dev/monitor/serverInfo`
- `GET /dev/monitor/networkInfo`

The smoke asserts CPU, memory, storage, server, JVM/PHP, and network rate sections. It intentionally does not call monitor writes, server control, metric persistence, provider, or external services.
