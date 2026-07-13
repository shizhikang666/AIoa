# Regression Checklist (One Page)

Purpose: a single, short list of the basic mistakes that have recurred during this ThinkPHP OA refactor. Run the relevant section **before and after any change** so the same low-level bugs stop coming back. Each item links to its source in `manual-test-issues.md` (MT-xxx) or `problem-optimization-log.md` (P-xxx).

> Rule of thumb: pick the sections that match the layer you touched. You do **not** need every section for every change. When you fix a new recurring class of bug, add one line here.

## 0. Before you edit (always)

- [ ] Work from `F:\AI\projects\testJava\OA-ThinkPHP`, not the parent workspace; the parent is **not** a git repo. (P-001, P-005)
- [ ] Run `git status --short --branch` in `OA-ThinkPHP` first; confirm branch `refactor/thinkphp-main` and a clean/expected tree. (AGENTS.md)
- [ ] Never edit `F:\AI\projects\testJava\OA` (read-only Java source). Never delete DB fields. (AGENTS.md)

## 1. Baseline checks (any code change) — must stay green

One-time per clone/worktree: `git config core.hooksPath .githooks` (enables the pre-commit baseline gate). The `.githooks/pre-commit` hook lints staged PHP and boots the route table before every commit; bypass only in emergencies with `git commit --no-verify`.

- [ ] `composer check` passes — this runs `php scripts/php-lint.php` (syntax over app/config/route) + `php think route:list`.
- [ ] `php -l` on every changed `.php` file — 0 syntax errors (the pre-commit hook does this for staged files automatically).
- [ ] `php think route:list` runs with no error and shows your new/changed route.
- [ ] For a broader pass: `.\scripts\project-preflight.ps1` (skip switches for unavailable layers). (P-012, P-035)
- [ ] `git diff --check` — only the known LF/CRLF warnings.

## 2. Data shape / field mapping — the #1 recurring root cause

The copied Vue frontend expects camelCase keys, not raw DB column names. Any backend read route feeding a table/form/tree-select must return the frontend shape.

- [ ] List/detail/add/edit return rows go through a mapper (e.g. `RoleService::roleRow()`), not raw `UPPER_SNAKE` columns. (MT-002)
- [ ] For plain key-case conversion use the canonical `app\support\RowMapper` (`RowMapper::toCamel($row)` / `toCamelList($rows)` / `camelKey($k)`) instead of hand-rolling a new per-service `camelKey()` — several older services still carry private copies; do not add more. (P-041)
- [ ] Role rows expose `id / name / category / sortCode / orgId`; guard with `scripts/online-role-crud-grant-smoke.ps1` (`Assert-RoleRowShape`). (MT-002)
- [ ] Tree-select nodes expose `name` (Ant Design Vue maps its label to `name`), not only `label/title`. (MT-001, P-022)
- [ ] Long IDs (20-digit tenant/org/user IDs) stay **strings** end to end — never `parseInt`/numeric coercion. (MT-004)

## 3. API response convention

- [ ] Every frontend-facing `message`/`msg` is **Chinese** — no English exception/validation/auth/500 text. Technical detail goes to server logs, not the response. (MT-003, MT-008, AGENTS.md)
- [ ] Error responses use a specific Chinese message, not a generic `请求失败`, when the cause is actionable (permission, validation). (MT-008)
- [ ] Codes follow the convention: `200` success, `400` validation, `401` unauth, `403` denied, `500` server. (AGENTS.md)

## 4. Login / auth (touch login, token, menu, RBAC)

- [ ] Invalid login → `code=401` with Chinese message (e.g. `账号或密码错误`). (MT-003)
- [ ] Login-endpoint `401` shows the backend login message, **not** the global "login expired" relogin modal. (MT-005)
- [ ] Captcha disabled → login payload must not send stale `validCode`/`validCodeReqNo`, and backend skips captcha when `SNOWY_SYS_DEFAULT_CAPTCHA_OPEN` != `true`. (MT-006)
- [ ] Account with no authorized menu → clear no-permission message, not a silent stall on the login page. (MT-007)
- [ ] Token via `Authorization: Bearer <token>`; token payload carries no plaintext password/secret. (AGENTS.md)

## 5. Runtime readiness (DB- or HTTP-backed smoke)

- [ ] Start the local bundle and verify ports before DB/HTTP smoke: `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean` (MySQL 3306, Redis 6379, PHP-FPM 9000; backend/Vite up). (P-008, P-009, P-039)
- [ ] Do not judge DB availability by the Windows `MySQL80` service name; the project uses the local runtime bundle + ignored `.env`. (P-039)

## 6. PowerShell + HTTP smoke scripting gotchas

- [ ] POST JSON bodies: write to a temp file and use `curl.exe --data-binary @file`; strip a leading UTF-8 BOM before `json_decode`. (P-020, P-036)
- [ ] Parse JSON with `scripts/json-read.js` (case-sensitive, BOM-tolerant) — not PowerShell 5.1 `ConvertFrom-Json` for mixed-case alias payloads. (P-011)
- [ ] `php -r` takes code as its argument (via a `$code` variable), not piped STDIN; guard `Invoke-Php` against `$null` output. (P-007, P-037)

## 7. Browser smoke gotchas

- [ ] Use `scripts/browser-page-smoke.ps1` (ASCII-only CDP helper); it runs sequentially via a named mutex — do not run browser smokes in parallel. (P-023, P-025)
- [ ] Target menu paths from the account's `loginMenu` / `sys_resource.PATH` (some include an explicit `/index`), not guessed from component folder names. (P-027, P-028)
- [ ] Known non-blocking Ant Design Vue warnings are allow-listed; legitimate image reads via `/api/dev/file/download` are not "forbidden". (P-024, P-026)

## 8. Scope discipline (avoid unrelated breakage)

- [ ] Stay inside the assigned module/slice; controlled-deferred write wrappers must keep returning authenticated `code=400` without side effects until a real transactional plan lands. (P-032, P-038)
- [ ] No Java source edits, schema changes, `.env`/Composer/npm changes, production data ops, or Git push unless explicitly requested. (AGENTS.md, slice plans)

---

Maintenance: when a genuinely new basic mistake recurs, add one line here **and** a row in `problem-optimization-log.md`. Keep this file to roughly one page — it is a fast pre-flight, not a history log.
