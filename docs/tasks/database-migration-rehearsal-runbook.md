# Java OA Database Migration Rehearsal Runbook

## Purpose

`scripts/run-database-migration-rehearsal.ps1` is the only supported entry
point. It creates private temporary client files, initializes and verifies a
current-user-only ACL under `runtime/backup`, and then invokes the internal PHP
migrator. The migration builds a new database from the current ThinkPHP schema
and imports only the old Java rows. It refuses to reuse or overwrite a target.

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
- Never reuse the target, quarantine, or manifest name from a failed attempt;
  every retry receives a fresh suffix.

## MySQL connection files

Give the wrapper the old Java connection text file and the new PHP `.env`.
The wrapper creates separate untracked client option files in a private random
temporary directory and removes them before exit. Never put passwords in a
command, Git, a task note, or the generated report.

The source account should be read-only. The target account must be scoped to
the database server used for the rehearsal. The tool never executes DDL or DML
through the source connection.

## First dry-run

Run this while Java is still online. Omit `--apply` and the freeze token.

```powershell
.\scripts\run-database-migration-rehearsal.ps1 `
  -JavaConnectionFile C:\secure\old-java-connection.txt `
  -PhpEnvFile .env `
  -SourceDatabase oa2026 `
  -TemplateDatabase oa2026 `
  -TargetDatabase oa2026_migrated `
  -QuarantineDatabase oa2026_quarantine_20260717 `
  -ManifestDirectory runtime\backup\database-migration-reviewed-dry-run
```

The dry-run writes manifests below `runtime/backup/database-migration-*`. That
directory is ignored by Git. It contains:

- source table/column/index/foreign-key/row-count manifest;
- ThinkPHP template schema manifest;
- source/template comparison;
- detected orphan task candidates;
- the frozen classification of removed-engine byte arrays, including candidate
  counts, distinct-root counts, business-link counts, consumer-table coverage,
  and private identity/content digests;
- the frozen classification of the reviewed detached legacy operation-log row,
  including only aggregate counts in public preflight output and private
  identity/content/structure evidence in the manifest;
- count of workflow variables waiting for the external converter;
- each variable-to-byte-array mapping plus the original byte-array SHA-256;
- a plan SHA-256 over both schemas, all source row counts, the orphan set, the
  removed-engine byte-array and detached operation-log classifications, and the complete workflow
  variable/byte-array candidate set;
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
.\scripts\run-database-migration-rehearsal.ps1 <same-reviewed-options> `
  -KnownOrphans C:\secure\known-orphans-20260717.json
```

Do not apply unless `readyForApply` is true. A configured external converter is
mandatory whenever Java-serialized runtime/history variables remain. The
migration command only calls that reviewed converter and verifies its output;
it does not decode Java objects itself.

## Apply to a new rehearsal database

Copy the exact `confirmToken` printed by the frozen dry-run:

```powershell
.\scripts\run-database-migration-rehearsal.ps1 <same-reviewed-options> `
  -KnownOrphans C:\secure\known-orphans-20260717.json `
  -ManifestDirectory runtime\backup\database-migration-reviewed-apply `
  -Apply -SourceFrozen -ConfirmToken <exact-dry-run-token>
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
   Before isolation, every orphan root byte array must have no reference from
   any reviewed workflow payload column (`BYTEARRAY_ID_`, attachment content,
   external-task error details, or job exception stack columns), and every
   candidate must still have no runtime variable, no
   history row, no business-table `PROCESS_ID` reference, and no restored
   same-tenant assignee; otherwise migration stops for manual review.
9. Separately copies the one frozen detached legacy operation-log row into
   `act_hi_op_log_detached`, verifies full row and table-structure identity,
   preserved user/tenant and process-definition/deployment support, no task,
   execution, case, job, workflow-engine, business-process, inbound-FK, or
   operation-group sibling evidence, then deletes only the binary-matched row
   from the new target. Copy, audit, delete, and residual checks run in one
   serializable InnoDB transaction; a forced failure rolls all three writes
   back while leaving the failed attempt available for diagnosis.
10. Separately copies the frozen removed-engine byte-array set into
   `act_ge_bytearray_detached` in the quarantine schema, verifies exact row and
   content identity, then deletes the target copies through a primary-key
   equality join with an additional binary-identity guard against the already
   copied IDs. The reviewed local snapshot contains 96,340 such
   rows: 96,188 are fully detached and 152 retain 19 completed reissue-order
   process-number links. All have a removal timestamp, no deployment binding,
   no runtime/history engine evidence, and no reviewed workflow payload
   consumer. The
   business rows themselves remain in the normal target. Any new consumer,
   runtime/history evidence, deployment binding, business table, tenant
   conflict, identity drift, or count drift aborts before deletion.
11. Calls the external workflow-variable converter when needed, then requires
   string JSON, no variable byte-array reference, a 4,000-byte maximum, and
   matching runtime/history JSON for paired process/name values. The imported
   candidate mapping and byte-array hashes must first equal the frozen-source
   plan, and the original byte-array rows/content must remain unchanged after
   conversion for single-row forensics or reversal.
12. Repairs exactly two blank `Process_procure / Activity_approval_procure`
    assignees only when one unique string `user` variable matches an existing,
    non-deleted user in the same tenant and one matching history task exists.
    Any other blank-assignee shape fails.
13. Runs the travel-days, delivery-plan, and after-sales installers twice and
    requires the second apply to be a no-op.
14. Writes final schema/row/quarantine/repair/installer manifests, requires the
    final target schema to equal the ThinkPHP template, verifies the final
    `act_ge_bytearray` total equals source minus the two exact byte-array
    isolation sets, verifies `act_hi_op_log` equals source minus the orphan and
    detached-operation-log sets,
    and removes successful SQL staging files while retaining their size/hash
    audit. A failed run keeps its private staging evidence and never writes
    `completed.json`.

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
assignee repair, all ten reviewed byte-array payload consumers, unreviewed
foreign-key consumer rejection, orphan and detached-isolation rollback,
removed-engine byte-array classification/quarantine, plan-drift rejection,
detached operation-log structure/support/reference/sibling gates, scoped
workflow ignores, deployment/reference guards, null primary/secondary process references, and
installer connection pinning, then drops only those random temporary schemas in
a guarded `finally` block.

```powershell
& 'E:\project\socket\AI\testPhp\files\tools\php\php.exe' `
  scripts\migration-database-local-smoke.php `
  --env=E:\AI\projects\testJava\OA-ThinkPHP\.env `
  --host=127.0.0.1
```

## Isolated approval-continuation validation

Do not repeatedly clone the migrated rehearsal database. The validation flow
has one read-only preflight and, after it passes, one full final clone:

1. Run `create-isolated-validation-clone.php` with `--preflight-only=1` and a
   reserved target name. Preflight validates all table DDL, foreign keys, and
   the absence of unhandled views, triggers, routines, and events, but it does
   not create a database.
2. Run the same command once without `--preflight-only`. The source is
   fingerprinted before copying and rechecked afterward. The final clone must
   contain 124 tables and 42 foreign keys, match every source row count and
   table checksum, match the full structure fingerprint, and contain no
   unhandled non-table objects.
3. Run `prepare-r10-isolated-validation.php`. It accepts exactly one completed
   final clone, recomputes the current source and clone structure/content, and
   creates a private pointer plus an isolated runtime directory. It never
   modifies either database.
4. Run `run-r10-isolated-approval-validation.ps1`. The runner exclusively owns
   its loopback PHP process and client process, proves the listener PID and the
   actual `SELECT DATABASE()` value, executes authenticated task count/list/page
   reads, performs one approval continuation, verifies the next task through
   the same APIs, checks all 124 tables before and after, and performs exact
   row-level assertions for the six permitted workflow tables.
5. The runner completes server identity, database binding, structure/content,
   and authenticated read checks before any non-reuse marker is created. A
   read-only preflight failure before that marker triggers an independent full
   structure and 124-table content recheck. It may be retried only when that
   recheck proves equality with both the clone evidence and canonical baseline.
   Proven drift creates `validation-invalid.json` and permanently consumes the
   clone; an unavailable or inconclusive recheck reports the reuse state as
   unknown and forbids reuse until an independent verification succeeds. The
   recheck is never attempted until the owned client, server, and listener are
   all proven stopped; cleanup uncertainty also leaves reuse status unknown.
6. Immediately before the approval POST, the client atomically creates
   `validation-mutation-started.json`. Once that marker exists, a completed,
   interrupted, timed-out, or failed clone is permanently non-reusable. The
   runner writes `validation-failed.json` only when mutation had started (or a
   legacy started marker already exists). The client timeout is configurable
   and should retain ample headroom for the approval and all post-write checks.
   Completion evidence is also written through the same failure state machine;
   a create or durable-flush failure after approval leaves the clone explicitly
   failed and permanently non-reusable.

The posthoc provenance audit is a database-state audit for an older validation
that predates the owned-process and mutation-marker controls. It can prove that
the current clone equals the R10 baseline plus exactly one expected approval
transition, with all other rows unchanged. It cannot retroactively prove the
historical HTTP listener identity or create a missing start marker. Record that
limitation explicitly; do not describe the posthoc result as a complete
historical execution chain.

For the production window, the validation sequence is repeated against the
new formal target using the hardened runner. The old Java service is stopped
only after the candidate package, database dry-run, converter, file inventory,
SM4 gate, and rollback materials are ready.

## Acceptance and rollback boundary

- Do not point the PHP `.env` at the migrated database until database, workflow,
  file, SM4, login, and representative approve/reject rehearsals all pass.
- Perform business workflow tests on a separate copy of the completed migrated
  database, not on the final cutover candidate.
- Keep the frozen Java database or separately controlled source backup, the
  quarantine database, and all manifests until acceptance and the agreed
  retention window are complete. Successful rehearsal SQL staging copies are
  removed after their import and final validation.
- Before application cutover, rollback is simply leaving PHP unchanged and
  restarting Java if necessary. After cutover, use the separately approved
  application/database rollback runbook; this tool never switches services.
