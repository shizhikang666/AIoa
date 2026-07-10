# Historical File Migration Runbook

This migration moves historical `LOCAL` attachments into the ThinkPHP file root and rewrites legacy download URLs. It does not delete source files.

## Preconditions

1. Back up the application database.
2. Keep the old Java upload directory mounted or copied to the new server.
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

For a staged rehearsal:

```bash
php scripts/migrate-legacy-files.php \
  --source-root=/www/wwwroot/oaJar/oajava/upload \
  --target-root=/www/wwwroot/oa.fucity.cn/public/upload/dev_file \
  --limit=100
```

## Apply

```bash
php scripts/migrate-legacy-files.php \
  --source-root=/www/wwwroot/oaJar/oajava/upload \
  --target-root=/www/wwwroot/oa.fucity.cn/public/upload/dev_file \
  --apply
```

Apply mode copies through a temporary file, verifies SHA-256, then updates `dev_file`. It also normalizes attachment URLs in project drafts, product images, product relations, project follow-ups, and project ratings.

## Verification

1. Confirm the manifest has no `missing`, `conflict`, or `error` entries.
2. Open customer licenses, project attachments, follow-up files, case images, and rating images.
3. Upload and download one new file.
4. Run a normal deployment and repeat one historical and one new file download. `public/upload` is preserved by the deployment script.
5. Retain the old upload directory and migration manifest until business acceptance is complete.
