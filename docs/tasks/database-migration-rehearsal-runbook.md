# Java OA Database Migration Rehearsal Runbook

## Purpose

`scripts/migrate-legacy-database.php` builds a new migrated database from the
current ThinkPHP schema and imports only the old Java rows. It is designed for
repeatable rehearsals and refuses to reuse or overwrite an existing target.

The command does not stop Java, does not change the Java source database, does
not switch the application database, and does not implement SM4 compatibility
or Java object deserialization. Those remain separate release gates.

## Non-negotiable safety rules

- Default mode is read-only dry-run.
- Apply requires `--apply`, the exact dry-run confirmation token, and
  `--source-freeze-token=JAVA_STOPPED_AND_SOURCE_FROZEN`.
- The source and target endpoints/schemas must differ.
- The target name must end in `_migrated`, must appear in an explicit
  `--allow-target` entry, and must not already exist.
- The quarantine name must end in `_quarantine_YYYYMMDD` and must not exist.
- The current ThinkPHP database is a read-only schema template. The migration
  creates a fresh target from its schema-only dump.
- Old Java data is dumped with `--no-create-info --complete-insert`; the dump is
  scanned before import and rejected if it contains CREATE, DROP, ALTER,
  TRUNCATE, RENAME, USE, GRANT, or REVOKE statements.
- No cleanup or `DROP DATABASE` is automatic. A failed rehearsal remains
  isolated for diagnosis until a human explicitly decides what to remove.

## MySQL connection files

Use separate untracked MySQL client option files. Restrict them to the operator
account (`chmod 600`) and never put passwords in a command, Git, a task note, or
the generated report.

```ini
[client]
host=127.0.0.1
port=3306
user=migration_user
password=<set-in-this-untracked-file>
default-character-set=utf8mb4
```

The source account should be read-only. The target account must be scoped to
the database server used for the rehearsal. The tool never executes DDL or DML
through the source connection.

## First dry-run

Run this while Java is still online. Omit `--apply` and the freeze token.

```powershell
$php = 'E:\project\socket\AI\testPhp\files\tools\php\php.exe'
& $php scripts\migrate-legacy-database.php `
  --source-defaults=C:\secure\old-java-readonly.cnf `
  --source-db=oa2026 `
  --target-defaults=C:\secure\new-php-migrator.cnf `
  --template-db=oa2026 `
  --target-db=oa2026_migrated `
  --quarantine-db=oa2026_quarantine_20260717 `
  --allow-target=oa2026_migrated `
  --workflow-converter=C:\reviewed\convert-workflow-variables.php
```

The dry-run writes manifests below `runtime/backup/database-migration-*`. That
directory is ignored by Git. It contains:

- source table/column/index/foreign-key/row-count manifest;
- ThinkPHP template schema manifest;
- source/template comparison;
- detected orphan task candidates;
- count of workflow variables waiting for the external converter;
- each variable-to-byte-array mapping plus the original byte-array SHA-256;
- a plan SHA-256 over both schemas, all source row counts, the orphan set, and
  the complete workflow variable/byte-array candidate set;
- the exact apply confirmation token bound to that plan SHA-256.

The reviewed orphan candidate JSON can be supplied as `--known-orphans` on the
next dry-run. It must contain exactly the 20 audited task IDs. If the frozen
source later produces an unknown twenty-first task or loses an expected task,
apply fails before target creation.

## Final frozen-source dry-run

After the operator stops the running Java service and confirms users cannot
write to it, repeat the dry-run against the frozen source and pass the reviewed
orphan manifest:

```powershell
& $php scripts\migrate-legacy-database.php <same-options> `
  --known-orphans=C:\secure\known-orphans-20260717.json
```

Do not apply unless `readyForApply` is true. A configured external converter is
mandatory whenever Java-serialized runtime/history variables remain. The
migration command only calls that reviewed converter and verifies its output;
it does not decode Java objects itself.

## Apply to a new rehearsal database

Copy the exact `confirmToken` printed by the frozen dry-run:

```powershell
& $php scripts\migrate-legacy-database.php <same-reviewed-options> `
  --known-orphans=C:\secure\known-orphans-20260717.json `
  --apply `
  --confirm-token=<exact-dry-run-token> `
  --source-freeze-token=JAVA_STOPPED_AND_SOURCE_FROZEN
```

Apply performs these gated stages:

1. Revalidates the audited 121-table/1,836-column old structure against the
   124-table/1,882-column ThinkPHP template.
2. Requires the only template additions to be the three reviewed feature
   tables and `biz_sale_project.TRAVEL_DAYS`.
3. Produces and validates a schema-only template dump and a data-only old dump.
4. Rechecks frozen source row counts, all 121 table checksums, and orphan IDs
   before target creation; any same-count update detected by a checksum aborts.
5. Creates a new `_migrated` schema and imports the ThinkPHP schema.
6. Imports old rows using explicit INSERT column lists and checks every source
   table row count before any normalization.
7. Confirms the three new tables remain empty and `TRAVEL_DAYS` remains at its
   new-schema default for imported projects.
8. Copies the 20 reviewed orphan tasks, related runtime/history rows, original
   byte arrays, and referenced process/deployment resources into the separate
   quarantine schema with row counts and SHA-256 digests. Only orphan runtime
   rows are removed from the new normal runtime; shared deployments remain.
   Before isolation, every candidate must still have no runtime variable, no
   history row, no business-table `PROCESS_ID` reference, and no restored
   same-tenant assignee; otherwise migration stops for manual review.
9. Calls the external workflow-variable converter when needed, then requires
   string JSON, no variable byte-array reference, a 4,000-byte maximum, and
   matching runtime/history JSON for paired process/name values. The imported
   candidate mapping and byte-array hashes must first equal the frozen-source
   plan, and the original byte-array rows/content must remain unchanged after
   conversion for single-row forensics or reversal.
10. Repairs exactly two blank `Process_procure / Activity_approval_procure`
    assignees only when one unique string `user` variable matches an existing,
    non-deleted user in the same tenant and one matching history task exists.
    Any other blank-assignee shape fails.
11. Runs the travel-days, delivery-plan, and after-sales installers twice and
    requires the second apply to be a no-op.
12. Writes final schema/row/quarantine/repair/installer manifests and requires
    the final target schema to equal the ThinkPHP template.

## External workflow converter contract

The converter is a separate reviewed script. A `.php` converter is invoked with
the configured `--php-bin`; another executable is invoked directly. The
orchestrator reads the target option file in memory and places its password in
a randomly named environment variable that exists only in the child process.
The password is never a command argument or audit value. The converter receives:

```text
--apply
--database=<new migrated database>
--confirm-target=<the same new migrated database>
--host=<target host>
--port=<target port>
--user=<target user>
--password-env=<random child-only environment variable name>
```

For a non-loopback target the orchestration command additionally requires
`--allow-remote-workflow-converter` and then passes the converter's own
`--allow-remote-target` gate. A local target receives neither remote override.

It must exit non-zero on any unknown Java class or conversion mismatch. The
orchestrator subsequently verifies all converted rows itself. SM4 encrypted
business fields are outside this contract and outside this tool.

## Offline smoke

The smoke has no network connection and performs no database writes. It covers
unsafe target rejection, allowlisting, audited 121/124 schema differences,
display-width normalization, the twenty-first-orphan failure, explicit-column
data dumps, old-DDL rejection, schema-dump DROP rejection, and installer target
pinning.

```powershell
& 'E:\project\socket\AI\testPhp\files\tools\php\php.exe' `
  scripts\migration-database-offline-smoke.php
```

## Optional loopback temporary-database smoke

This smoke requires an explicitly supplied local `.env`. It refuses every
non-loopback MySQL host, creates two random `oa_migration_smoke_*` schemas,
tests raw-row quarantine, shared deployment preservation, the exact two-task
assignee repair, and installer connection pinning, then drops only those random
temporary schemas in a guarded `finally` block.

```powershell
& 'E:\project\socket\AI\testPhp\files\tools\php\php.exe' `
  scripts\migration-database-local-smoke.php `
  --env=E:\AI\projects\testJava\OA-ThinkPHP\.env `
  --host=127.0.0.1
```

## Acceptance and rollback boundary

- Do not point the PHP `.env` at the migrated database until database, workflow,
  file, SM4, login, and representative approve/reject rehearsals all pass.
- Perform business workflow tests on a separate copy of the completed migrated
  database, not on the final cutover candidate.
- Keep the frozen Java database, its full dump, the quarantine database, and all
  manifests until acceptance and the agreed retention window are complete.
- Before application cutover, rollback is simply leaving PHP unchanged and
  restarting Java if necessary. After cutover, use the separately approved
  application/database rollback runbook; this tool never switches services.
