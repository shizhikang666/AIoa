# Historical File Migration Runbook

This migration moves historical `LOCAL` attachments into the ThinkPHP file root and rewrites legacy download URLs. It does not delete source files.

## Preconditions

1. Back up the application database.
2. Keep the old Java upload directory mounted/copied to the new server, or confirm the old download endpoint is available.
3. Confirm `.env` points to the migrated OA database.
4. Run the command as the same OS user that owns the OA application files.

Typical paths:

```text
source: /www/wwwroot/oaJar/oajava/upload
target: /www/wwwroot/oa.fucity.cn/public/upload/dev_file
```

## Dry Run

```bash
cd /www/wwwroot/oa.fucity.cn
php scripts/migrate-legacy-files.php \
  --source-root=/www/wwwroot/oaJar/oajava/upload \
  --target-root=/www/wwwroot/oa.fucity.cn/public/upload/dev_file
```

The command writes a JSONL manifest under `runtime/backup/`. Resolve every `missing`, `conflict`, or `error` entry before applying. A conflict means the target path already contains different bytes and is never overwritten.

If the old directory cannot be mounted, use the old download endpoint. The `{id}` placeholder is mandatory and credentials are rejected in the URL. Remote files are downloaded atomically into a verified cache; even dry-run mode populates this cache, but it does not update business data or the target file tree.

```bash
php scripts/migrate-legacy-files.php \
  --source-download-url='https://oa.xzx8.com/backend/dev/file/download?id={id}' \
  --source-cache-root=/www/wwwroot/oa.fucity.cn/runtime/legacy-file-source-cache \
  --target-root=/www/wwwroot/oa.fucity.cn/public/upload/dev_file \
  --limit=100
```

Both source options can be supplied together. Existing files from `--source-root` are preferred and the download endpoint is only used for unresolved rows.

For a staged rehearsal:

```bash
php scripts/migrate-legacy-files.php \
  --source-root=/www/wwwroot/oaJar/oajava/upload \
  --target-root=/www/wwwroot/oa.fucity.cn/public/upload/dev_file \
  --limit=100
```

Use `--file-id=ID` for a single-record download rehearsal or retry. Runs using `--limit`, `--file-id`, or `--tenant-id` intentionally skip global business URL and file-root configuration updates. A full unfiltered apply performs those updates only after every file row has been resolved.

## Apply

```bash
php scripts/migrate-legacy-files.php \
  --source-root=/www/wwwroot/oaJar/oajava/upload \
  --target-root=/www/wwwroot/oa.fucity.cn/public/upload/dev_file \
  --apply
```

Apply mode copies through a temporary file, verifies SHA-256, then updates `dev_file`. It also normalizes attachment URLs in project drafts, product images, product relations, project follow-ups, and project ratings.

Do not run full `--apply` until a complete dry run has zero unresolved entries and the source cache has enough free disk space for all historical attachments.

## Verification

1. Confirm the manifest has no `missing`, `conflict`, or `error` entries.
2. Open customer licenses, project attachments, follow-up files, case images, and rating images.
3. Upload and download one new file.
4. Run a normal deployment and repeat one historical and one new file download. `public/upload` is preserved by the deployment script.
5. Retain the old upload directory and migration manifest until business acceptance is complete.
