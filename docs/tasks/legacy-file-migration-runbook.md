# Historical File Migration Runbook

This one-time migration moves historical `LOCAL` attachments into the ThinkPHP shared file root and normalizes legacy download URLs. It never deletes legacy source files.

## Execution boundary

Run this tool only from an independently checked-out, clean, access-restricted migration worktree or migration toolkit pinned to the approved commit/tag.

- Do **not** run it from the active Web document root.
- Do **not** include this runbook, its manifests, database evidence, or the offline smoke in a normal Web release package.
- Do **not** use a release directory as `--target-root`; use the persistent shared `dev_file` root retained across atomic releases.
- The OS account running the tool must own the shared target and private evidence directories.

Example preparation, with environment-specific paths substituted by the operator:

```bash
cd /private/oa-migration-toolkit
test -z "$(git status --porcelain)"
chmod 0700 runtime/backup
php scripts/migrate-legacy-files-offline-smoke.php
```

The offline smoke performs no network or database access.

## Required evidence and bindings

Set these values in the private shell session. The database name must end in `_migrated`.

```bash
TARGET_DATABASE='REPLACE_WITH_MIGRATED_DATABASE_migrated'
DATABASE_COMPLETED='/private/database-migration-manifest/completed.json'
TARGET_FILE_ROOT='/replace/with/shared/dev_file'
SOURCE_ROOT='/replace/with/legacy/upload'
test -d "$TARGET_FILE_ROOT"
chmod 0700 "$(dirname "$DATABASE_COMPLETED")"
chmod 0600 "$DATABASE_COMPLETED"
```

The tool refuses to read business tables until all of these gates pass:

1. `--database` and `--confirm-database` match exactly and end in `_migrated`.
2. `SELECT DATABASE()` returns that exact database.
3. `--database-migration-completed` is a private, non-symlink `completed.json` from the successful database migration. Its `manifestDirectory` must resolve successfully and equal the file's actual parent directory exactly; retain or transfer the whole immutable evidence directory.
4. The database evidence has a successful apply status, valid schema comparison/final schema digest, and the same target database. The tool independently rebuilds the connected database's `information_schema` digest and requires exact equality with `finalSchemaSha256`.
5. `--target-root` and `--confirm-target-root` match exactly. Every existing path component is checked with `lstat`; symlinks, Windows junction/reparse points, and file-versus-directory ancestors are rejected.

The raw database name and complete download URL are not written to the file-migration manifest. The database-migration evidence is bound by SHA-256.

## Full dry-run approval

Create a unique manifest directly under this toolkit's private `runtime/backup` directory. Existing files, symlinks, paths outside that directory, and group/world-accessible evidence are rejected.

```bash
DRY_MANIFEST="$PWD/runtime/backup/legacy-files-full-dry-$(date +%Y%m%d-%H%M%S).jsonl"

php scripts/migrate-legacy-files.php \
  --database="$TARGET_DATABASE" \
  --confirm-database="$TARGET_DATABASE" \
  --database-migration-completed="$DATABASE_COMPLETED" \
  --source-root="$SOURCE_ROOT" \
  --target-root="$TARGET_FILE_ROOT" \
  --confirm-target-root="$TARGET_FILE_ROOT" \
  --manifest="$DRY_MANIFEST"
```

Omitting `--apply` is always dry-run. The database session is set read-only and every database update is blocked by a centralized apply gate.

If the legacy directory is unavailable, a remote source can be used during dry-run:

```bash
SOURCE_CACHE='/private/legacy-file-source-cache'
mkdir -p "$SOURCE_CACHE"
chmod 0700 "$SOURCE_CACHE"

php scripts/migrate-legacy-files.php \
  --database="$TARGET_DATABASE" \
  --confirm-database="$TARGET_DATABASE" \
  --database-migration-completed="$DATABASE_COMPLETED" \
  --source-download-url='https://old.example/backend/dev/file/download?id={id}' \
  --source-cache-root="$SOURCE_CACHE" \
  --target-root="$TARGET_FILE_ROOT" \
  --confirm-target-root="$TARGET_FILE_ROOT" \
  --manifest="$DRY_MANIFEST"
```

The cache and target roots must be strictly separate: neither may equal, contain, or be contained by the other. The cache root is enforced as private `0700`, its descendants are created one component at a time without following links, and cache files use durable no-clobber finalization. Dry-run may populate this cache, but it never changes the target file tree or database. Apply never downloads a missing remote source; every required remote file must already exist in the approved cache.

Before resolving any remote source, the tool computes every selected row's canonical target. Duplicate targets, parent/child target prefixes, existing directory leaves, and existing file ancestors reject the entire plan. No target directory is created during dry-run.

The last JSONL record is authoritative. Approval requires all of the following:

- `type` is `completion`, `status` is `completed`, and `completed` is `true`;
- `mode` is `dry-run` and `scoped` is `false`;
- `missing`, `conflict`, `error`, and aggregate `errors` are all zero;
- `databaseWriteStatements` and `databaseRowsAffected` are zero;
- `planSha256`, database evidence SHA-256, current database schema SHA-256, target root, and full source inventory are present.
- the migration script and its direct file-source helpers are bound into the plan by a code-bundle SHA-256.

A manifest ending in `completed-with-issues` has `completed: false` and cannot authorize apply. A manifest without a final fsynced `completion` record is incomplete.

After independent review, record the exact manifest digest without editing the manifest:

```bash
DRY_MANIFEST_SHA256="$(sha256sum "$DRY_MANIFEST" | awk '{print $1}')"
```

Scoped commands such as `--limit`, `--file-id`, or `--tenant-id` are troubleshooting dry-runs only. They intentionally skip global URL/config planning and can never authorize or run apply.

## Apply with zero-write drift gate

Create a different, unused manifest path for apply:

```bash
APPLY_MANIFEST="$PWD/runtime/backup/legacy-files-apply-$(date +%Y%m%d-%H%M%S).jsonl"

php scripts/migrate-legacy-files.php \
  --database="$TARGET_DATABASE" \
  --confirm-database="$TARGET_DATABASE" \
  --database-migration-completed="$DATABASE_COMPLETED" \
  --source-root="$SOURCE_ROOT" \
  --target-root="$TARGET_FILE_ROOT" \
  --confirm-target-root="$TARGET_FILE_ROOT" \
  --manifest="$APPLY_MANIFEST" \
  --approved-dry-run-manifest="$DRY_MANIFEST" \
  --approved-dry-run-manifest-sha256="$DRY_MANIFEST_SHA256" \
  --apply \
  --confirm-apply
```

Before the first target-file copy or database update, apply:

1. enters database read-only mode;
2. recomputes the complete file, source digest, target state, metadata row, legacy URL, and configuration plan;
3. requires exact equality with the approved dry-run plan, database evidence, target root, and source inventory;
4. performs a second source/target/database stability pass, including target-root identity and nested ancestor checks;
5. re-hashes the database migration `completed.json` and recomputes the live target schema digest;
6. rechecks the target-root identity immediately before every copy, creates missing directories one safe component at a time, and rechecks again before atomic no-clobber finalization;
7. only then changes the session to read-write and starts applying.

Any source, target, database row, URL/config, evidence, or plan drift exits before target-file or database writes. Resolve the drift and produce a new full dry-run manifest; never reuse or edit an old manifest.

## Verification and retention

1. Confirm the apply manifest ends with `type: completion`, `status: completed`, and `completed: true`.
2. Reconcile `filesCopied`, `legacyUrlsUpdated`, database write counters, and source inventory with the reviewed plan.
3. Test representative historical attachments, customer/project images, and one newly uploaded file.
4. Repeat one historical and one new download after the atomic Web release switch.
5. Retain the legacy source, database migration evidence, approved dry-run manifest, its recorded SHA-256, and apply manifest until business acceptance and rollback retention both expire.
