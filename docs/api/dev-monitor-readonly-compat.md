# Dev Monitor Read-Only Compatibility

## Scope

This slice adds authenticated, read-only compatibility for the Java server monitor information endpoint.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevMonitorController`
- Service: `DevMonitorServiceImpl`
- Result class: `DevMonitorServerResult`
- Frontend API: `snowy-admin-web/src/api/dev/monitorApi.js`

## Added Route

Protected route:

- `GET /dev/monitor/serverInfo`

## Response Shape

The endpoint returns the Java monitor result group names used by the old frontend:

- `devMonitorCpuInfo`
- `devMonitorMemoryInfo`
- `devMonitorStorageInfo`
- `devMonitorServerInfo`
- `devMonitorJvmInfo`

The PHP implementation maps JVM-shaped fields to PHP runtime data where possible:

- `jvmName`: `PHP`
- `jvmVersion`: current PHP version
- `javaVersion`: current PHP version
- `javaPath`: PHP binary path

Unavailable OSHI/JVM metrics are returned as safe placeholder values instead of running system commands.

## Safety Notes

- The route is protected by `AuthMiddleware`.
- The service only uses safe PHP runtime, disk, and host built-ins.
- No shell command, `netstat`, `ifconfig`, or long-running sampling call is executed.
- No database table is read or mutated.

## Deliberate Exclusions

- No `/dev/monitor/networkInfo` route is implemented.
- No Druid monitoring proxy route is implemented.
- No Knife4j/OpenAPI document route is implemented.
- No Java source files are changed.
- No database schema, `.env`, Composer, or public config files are changed.

## Later Work

`/dev/monitor/networkInfo` should remain deferred until there is an approved cross-platform implementation plan with clear permission, latency, and information-exposure boundaries.
