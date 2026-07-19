# oa.fucity.cn Atomic Deployment

Date: 2026-07-19

## Status

Use `scripts/oa-fucity-atomic-deploy.sh` for release preparation, one-time
baseline initialization, activation, and file-level rollback.

Do not use `scripts/oa-fucity-remote-deploy.sh` for the Java-to-PHP production
cutover. That older script replaces the active tree in place and invokes a
schema installer. It is retained only as historical tooling until it is
separately retired.

The atomic script never invokes MySQL, `mysqldump`, schema installers, database
migrations, queue workers, or schedulers. Database snapshot, migration,
validation, and cutover authorization remain separate gates.

## Verified Initial Server Shape

The pre-cutover server was inspected read-only on 2026-07-19:

- the site root is a physical directory;
- `current`, `releases`, and `shared` do not yet exist;
- the frontend Nginx root points to `<root>/snowy-admin-web/dist`;
- the backend FastCGI entry points to `<root>/public/index.php`;
- FastCGI `DOCUMENT_ROOT` points to `<root>/public`;
- `/storage` aliases the stable `<root>/public/storage/` path;
- `.env`, `.user.ini`, `runtime`, `public/upload`, and `public/storage` exist;
- BaoTa PHP 8.3, its FPM control script, and BaoTa Nginx are present.

Re-inspect those facts immediately before the one-time initialization. The
initializer intentionally fails unless the three old code paths and the stable
storage alias each match exactly once.

## Directory Model

```text
<root>/
├─ current -> releases/<active-release>
├─ releases/
│  ├─ baseline-<id>/
│  └─ <candidate-id>/
├─ shared/
│  └─ env/
│     ├─ baseline-<id>.env
│     └─ <candidate-id>.env
├─ .deploy/
│  ├─ backups/
│  ├─ manifests/
│  ├─ current-release
│  └─ deploy.lock
├─ .env
├─ .user.ini
├─ runtime/
└─ public/
   ├─ upload/
   └─ storage/
```

Existing mutable paths remain in place. This avoids copying migrated files and
preserves absolute `dev_file.STORAGE_PATH` values.

Each prepared release has fixed links:

```text
.env                  -> shared/env/<that-release>.env
public/.user.ini      -> <root>/.user.ini
public/upload         -> <root>/public/upload
public/storage        -> <root>/public/storage
runtime/log           -> <root>/runtime/log
runtime/session       -> <root>/runtime/session
runtime/storage       -> <root>/runtime/storage
runtime/upload        -> <root>/runtime/upload
runtime/backup        -> <root>/runtime/backup
```

`runtime/cache` and `runtime/temp` remain release-local. They are not shared
between old and new code.

Both the stable `runtime` parent and each release's `runtime` parent are
`root:www` mode `0750`. The `www` service account owns only writable leaves,
including release-local `cache`/`temp` and the approved persistent data
directories. Because `www` cannot write either parent, it cannot replace a
persistent link with a different symlink.

The site root containing `current` is `root:www` mode `0750`. This is a
required precondition for the CAS model: `www` can traverse the site but cannot
rename or replace `current` outside the deployment script.

## Environment and DB Binding Isolation

Never link every release to one mutable `shared/.env` file.

The baseline and every candidate receive a separate root-owned environment
file under `shared/env`. A release's `.env` link never changes after prepare.
Consequently:

- the current baseline continues to use its original database binding;
- a candidate can use the migrated target database without changing the old
  release;
- switching `current` changes frontend code, backend code, and the selected
  environment file together;
- compare-and-swap rollback selects the previous release's fixed environment
  file together with its code.

Prepare a candidate environment file outside the web root. Copy the approved
production environment and change only the approved release-specific values,
including `DB_NAME`, without printing secrets. The atomic script checks that
`APP_DEBUG` is false and `DB_NAME` is non-empty, then copies the file to
`shared/env/<release>.env` with mode `0640`. It never edits `DB_NAME` itself.

The environment source must be an absolute, canonical, root-owned regular file
with one hard link and no group/world write bit. The script opens it once,
validates the opened inode, copies only that descriptor into root-only staging,
and never reopens the untrusted source. The staged snapshot is validated and
installed as `root:www` mode `0640`; owner override and no-owner switches do
not exist.

Each release records the environment SHA-256 and a SHA-256 commitment to the
expected `DB_NAME` in both protected release markers and the independent
`.deploy/provenance` record. Candidate prepare requires both values as
`--expected-env-sha256` and `--expected-db-name-sha256`, compares them with the
staged snapshot, and fails before creating the release on either mismatch.
These expected hashes must come from separately reviewed migration-completion
evidence; a hash calculated by the deployment command from the same candidate
environment is not an approval.

Every activation and rollback requires the same two externally approved target
hashes. The script recomputes the installed environment and binding, verifies
the protected markers and provenance, compares the external values again
immediately before the final CAS, and never prints or records the real database
name.

## Package Requirements

Candidate packages may be `.zip`, `.tar.gz`, or `.tgz`.

Every candidate package must contain the build-generated provenance set:

```text
RELEASE-ID
RELEASE-COMMIT
RELEASE-TAGS
RELEASE-SOURCE-DIRTY
RELEASE-DIAGNOSTIC
RELEASE-MANIFEST.json
```

Prepare fails unless all of the following are true:

- `RELEASE-ID`, the manifest `releaseId`, and `--release-id` are identical;
- `RELEASE-COMMIT` is one lowercase 40-character commit and equals manifest
  `gitCommit`;
- both marker and manifest say `sourceDirty=false` and `diagnostic=false`;
- `RELEASE-TAGS` exactly equals manifest `gitTags`;
- at least one tag is `candidate/oa-*` or `release/oa-*`, and manifest
  `releaseTags` is exactly the matching subset;
- every packaged regular file except the manifest appears exactly once in the
  manifest with matching byte length and SHA-256, with no unlisted file;
- the externally supplied archive SHA-256 matches the package bytes.

After extraction, the deployment script records the approved archive SHA-256
and manifest SHA-256 both as root-owned release markers and as a separate
root-owned `.deploy/provenance/<release-id>.txt` record. Activation and
rollback cross-check both copies, revalidate the packaged files and manifest,
and refuse a candidate whose markers or provenance have changed since prepare.
Baseline copies are marked separately as `baseline-copy`; they are not
misrepresented as candidate build artifacts.

Preparation requires an externally approved SHA-256. The input archive must be
an absolute, canonical, root-owned regular file with one hard link and no
group/world write bit. It is opened once and copied from that fixed descriptor
to `.deploy/staging`, whose owner/mode are fixed at `root:root` `0700`. Hashing,
archive listing, and extraction operate only on the staged `0600` copy, which
closes the validation-to-extraction path replacement window.

Before extraction, the script rejects:

- a SHA-256 mismatch;
- absolute or drive-qualified paths;
- `..` path traversal;
- backslash-based archive paths;
- symbolic links, hard links, devices, FIFOs, or other special entries;
- top-level `.env`, `.user.ini`, `.git`, `.deploy`, `releases`, `shared`, or
  `current` entries, or deployment-owned `.release-*` markers.

The extracted tree is checked again before any script-created links are added.
Required backend, Composer, and frontend entries must exist before activation.

Build only from a clean dedicated worktree at the approved commit and tag.
Record the package SHA-256, commit, tag, file manifest, and build evidence in
the release ticket.

The local builder invokes `deployment-readiness.ps1 -ReleasePackageBuild`
against the assembled release tree. That artifact-only gate enforces the
release package policy, validates the built Composer/frontend entries, and
confirms `.env` remains outside the package. It does not replace prepare-time
verification of the protected external environment or post-prepare runtime
readiness on the target server.

The build and deploy contracts use the same 1-80 character ReleaseId rule and
the same non-empty `candidate/oa-*` or `release/oa-*` tag rule. Any build using
`SkipFrontendBuild`, `SkipComposerInstall`, or `SkipReadiness` is marked
`diagnostic=true` and is therefore ineligible for prepare.

## Safe Default: Prepare Only

Omitting an action flag means prepare-only. It cannot change `current`.

```bash
bash scripts/oa-fucity-atomic-deploy.sh \
  --root /www/wwwroot/oa.fucity.cn \
  --release-id <candidate-id> \
  --archive /absolute/protected/path/release.tar.gz \
  --archive-sha256 <approved-sha256> \
  --env-source /absolute/protected/path/candidate.env \
  --expected-env-sha256 <approved-env-sha256> \
  --expected-db-name-sha256 <approved-db-name-sha256>
```

Preparation creates:

```text
<root>/releases/<candidate-id>
<root>/shared/env/<candidate-id>.env
<root>/.deploy/provenance/<candidate-id>.txt
<root>/.deploy/manifests/<timestamp>-prepare-<candidate-id>.txt
```

Confirm that `readlink <root>/current` is unchanged after preparation.

Candidate preflight must run from the candidate directory. Use CLI checks and,
if HTTP behavior must be checked before activation, a loopback-only temporary
process. Do not attach the candidate to the public vhost, start a scheduler, or
start a queue worker.

Run every framework CLI or loopback PHP process as the `www` service account.
Running `think`, an application smoke script, or a loopback server as `root`
can leave root-owned framework cache shards that PHP-FPM cannot update. The
activation gate clears the selected release's local `runtime/cache` and
`runtime/temp` trees and restores `www:www` ownership, but preflight should
still use the production service identity so its behavior matches PHP-FPM.

At minimum:

```bash
cd /www/wwwroot/oa.fucity.cn/releases/<candidate-id>
runuser -u www -- /www/server/php/83/bin/php think route:list

runuser -u www -- bash scripts/deployment-readiness.sh \
  --php-bin /www/server/php/83/bin/php \
  --check-composer-policy \
  --check-runtime-permission-policy \
  --check-database-schema \
  --check-release-package-policy \
  --release-root "$PWD"
```

Only read-only database and HTTP probes are allowed during candidate preflight.

## One-Time Baseline Initialization

Run this once, before candidate activation. It copies the existing live code
into an immutable baseline while excluding `.env`, `.user.ini`, `.deploy`,
runtime data, uploads, storage, releases, shared state, and `current`.

```bash
bash scripts/oa-fucity-atomic-deploy.sh \
  --initialize-baseline \
  --root /www/wwwroot/oa.fucity.cn \
  --release-id baseline-<timestamp> \
  --expected-current absent \
  --confirm-initialize-baseline \
  --health-url https://oa.fucity.cn/
```

Initialization:

1. acquires `<root>/.deploy/deploy.lock` with non-blocking `flock`;
2. copies the active code, rejects links/special files, recursively changes the
   code copy to `root:root`, normalizes directories to `0755`, executable files
   to `0755`, and ordinary files to `0644`, then generates and immediately
   validates a protected full-file content manifest;
3. copies the existing `.env` to a fixed baseline environment file;
4. creates the persistent links;
5. installs `current -> releases/<baseline>`;
6. backs up the BaoTa vhost and rewrite files;
7. changes exactly these three code paths:

```text
<root>/snowy-admin-web/dist  -> <root>/current/snowy-admin-web/dist
<root>/public/index.php      -> <root>/current/public/index.php
<root>/public                -> <root>/current/public  (DOCUMENT_ROOT only)
```

The `/storage` alias remains `<root>/public/storage/`.

The baseline content manifest and its SHA-256 are revalidated before every
future selection. Its owner/mode and the ownership/write bits of every ordinary
baseline code entry are also rechecked; modified or service-writable baseline
code is not eligible for rollback.

The script then runs BaoTa `nginx -t`, reloads Nginx, reloads PHP 8.3 FPM, and
runs every supplied health URL while discarding response bodies. If any step
fails, it restores both Nginx files, removes the new `current` link, and must
successfully reload and health-check the restored service before describing it
as restored. Otherwise it reports an explicit unverified service state.

Do not use `--initialize-baseline` when `current` already exists.

## Activation

Keep the public service in maintenance mode throughout formal migration,
candidate validation, activation, PHP-FPM reload, and acceptance checks.

Read the actual current release from the symlink, not only from the marker:

```bash
readlink /www/wwwroot/oa.fucity.cn/current
```

Activate only after the old Java service is stopped and writes are forbidden:

```bash
bash scripts/oa-fucity-atomic-deploy.sh \
  --activate \
  --root /www/wwwroot/oa.fucity.cn \
  --release-id <candidate-id> \
  --expected-current <baseline-id> \
  --expected-env-sha256 <approved-candidate-env-sha256> \
  --expected-db-name-sha256 <approved-candidate-db-name-sha256> \
  --confirm-activate \
  --health-url https://oa.fucity.cn/
```

`--expected-current` is a compare-and-swap gate. It is checked during preflight
and checked again immediately before the final `current` rename. The deployment
lock serializes all sanctioned switch operations; an external current change
observed by the final check is never overwritten.

Formal initialization, activation, and rollback must all run through this
script and the same `<root>/.deploy/deploy.lock` flock. Do not manually replace
`current`. The site-root parent of `current` must remain `root:www` `0750`;
otherwise the cooperative flock/CAS guarantee is not valid.

Activation uses a temporary relative link followed by `mv -Tf` on the same
filesystem. It reloads PHP 8.3 FPM to clear OPcache and realpath state, then
runs health checks. If reload or health checks fail, it atomically restores the
expected previous release and reloads FPM again where possible.

Before changing `current`, the script writes root-only pending and outcome audit
templates and verifies rename capability in `.deploy/manifests`. A successful
switch is not complete until its prewritten committed audit is atomically
installed. If that final audit rename fails, the code switch is automatically
reversed and revalidated. The pending audit remains evidence if even failure
finalization cannot be written.

Nginx is not edited during routine activation; it permanently follows
`current` after baseline initialization.

## Rollback

Before public writes are enabled, rollback selects the previous fixed code and
environment binding together:

```bash
bash scripts/oa-fucity-atomic-deploy.sh \
  --rollback \
  --root /www/wwwroot/oa.fucity.cn \
  --release-id <baseline-id> \
  --expected-current <candidate-id> \
  --expected-env-sha256 <approved-baseline-env-sha256> \
  --expected-db-name-sha256 <approved-baseline-db-name-sha256> \
  --confirm-rollback \
  --health-url https://oa.fucity.cn/
```

Rollback uses the same CAS, FPM reload, health, and automatic restore behavior
as activation.

A restored symlink alone is not reported as a successful recovery. Failure of
the second FPM reload or restored-release health check produces an explicit
`ROLLBACK_*_FAILED`/unverified state requiring operator intervention.

Once the migrated database has accepted production writes, do not roll back to
a release whose fixed environment points to the old database. At that point,
remain in maintenance mode and use a separately approved code-compatible or
data-recovery plan. This script never restores a database automatically.

## Stop Conditions

Stop without switching when any of these occurs:

- Java writes are not confirmed stopped;
- the package commit, tag, manifest, or SHA-256 is uncertain;
- the candidate environment source is not isolated from the baseline;
- `current` does not match `--expected-current`;
- any persistent link resolves outside its exact approved target;
- Nginx paths do not match the verified pre-initialization/current form exactly;
- `/storage` no longer aliases the stable storage directory;
- PHP is not 8.3;
- FPM reload, Nginx syntax, health, attachment, login, or approval read checks
  fail;
- a migration or schema installer appears in the code activation command.

## Offline Verification

Run locally without a database, network, BaoTa, Nginx, or PHP-FPM:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File `
  .\scripts\oa-fucity-atomic-deploy-offline-smoke.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File `
  .\scripts\deployment-readiness-release-package-offline-smoke.ps1
```

The smokes use temporary trees and stub executables. They cover:

- baseline initialization and the exact three Nginx path changes;
- stable `/storage` alias preservation;
- prepare-only not changing `current`;
- version-fixed environment and persistent links;
- clean/non-diagnostic build provenance and approved release-tag enforcement;
- package SHA-256, manifest SHA-256, manifest file hashes, and `RELEASE-ID`
  cross-checks;
- CAS rejection;
- activation and explicit rollback;
- simulated FPM failure with automatic current restoration;
- persistent FPM failure without a false “restored” claim;
- archive and environment TOCTOU mutation after descriptor snapshot;
- owner/no-owner override rejection and protected runtime parents;
- externally approved env/DB-binding hashes required for prepare, activation,
  and rollback, including mismatch rejection before `current` changes;
- a simulated `www`-owned/group-writable baseline source normalized to
  root-owned, non-group/world-writable code before its manifest is accepted;
- protected ownership/write bits on the site-root parent of `current`;
- an external `current` change rejected by the final CAS;
- audit commit failure with automatic, health-checked rollback;
- baseline content tamper rejection;
- traversal archive rejection;
- archive symlink rejection;
- build/deploy ReleaseId, release-tag, and diagnostic-skip contract checks;
- absence of database commands.
