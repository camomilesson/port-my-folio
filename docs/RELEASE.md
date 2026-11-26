# Release review and operations

Review date: **26 November 2026**. Stage 8 is ready for local review; the final authenticated hosted smoke check, dashboard settings and production restore rehearsal remain unverified in this pass. The user confirmed Stage 7 works live. No Stage 8 deployment is implied by this record.

Assessment handoff: [professor inspection guide](INSPECTION.md). By agreement, an inspection flow replaces a recorded demo and presentation. Only synthetic local fixtures appear in the screenshots below; no personal CV or credential is included.

## Verification record

| Check | Result and boundary |
| --- | --- |
| SQLite automated suite | 260 passed, 1,391 assertions; five PostgreSQL-specific cases skipped |
| PostgreSQL automated suite | 265 passed, 1,422 assertions against the fixed local test database |
| Seed-label change | Existing seeder tests pass after replacing random titles with readable demo labels |
| Frontend build | Passed in working checkout and clean temporary installation |
| Static checks | Pint, Composer manifest/lock validation, JavaScript syntax, Blade compilation and diff whitespace checks passed |
| Fresh dependency installation | Composer lockfile installed separately; pnpm frozen lockfile installed from local package cache; first build downloaded fonts |
| Clean migration/seed | All 13 migrations and seed passed on a new SQLite file, with empty Google/Gemini/S3 keys |
| Browser inspection | Local demo login, input screen, ready fixture Home/CV/projects, three preset saves, paragraph edit, immediate multi-upload, carousel, explicit publication, default redirect and unpublish verified |
| Queue/recovery locally | Fixture cleanup jobs completed through the real database worker; recovery command succeeded; every-minute recovery schedule registered |
| Local backup/restore rehearsal | SQLite backup restored with integrity `ok`; 2 users / 4 portfolios / 5 projects / 3 CVs / 3 generations / 6 media rows; ten private files copied and SHA-256 verified |
| Hosted read-only check | Current landing page and `/up` rendered in the browser; landing has no local demo button |
| Full hosted journey / settings / production restore | Pending the checklist below; earlier user confirmations are not an independent Stage 8 operational audit |

The fresh checkout was isolated under `/private/tmp`; normal development and production data were not reset. Installation requires package/font access, but the seeded browser workflow uses no AI/Google/GitHub calls. The local restore rehearsal does not prove a Supabase restore or object-bucket recovery.

## Code audit

- **Ownership and private files:** policies plus scoped nested routes; public media/PDF/photo requests authorize against current publication references. Tests cover guessed foreign IDs, unpublished files and revoked access. Buckets stay private.
- **Public query behavior:** public page tests assert one content query with no draft project/media/CV-table reads. Draft snapshot construction eager-loads projects/media and CV; the dashboard paginates 12 portfolios with project counts and generation existence in its query.
- **Publication consistency:** locked, validated snapshots include content, appearance and immutable file references. Draft removals do not physically delete published media; cleanup checks references again under locks. Default/unpublish races have PostgreSQL tests.
- **Generation and history:** three attempts per step/run, durable next-run times, lease/run-version fencing, no provider call inside row locks, 24-hour recovery deadline. Active deletion cancels and redacts source/checkpoint payloads while retaining status/usage history. No account generation quota remains.
- **Resource bounds:** one 10 MB / 20-page PDF, five repositories, README below 100,000 bytes, bounded provider request/output, ten gallery images at 10 MB each. Image processing rejects malformed/animated/oversized input, bounds pixels/dimensions, strips metadata and resizes WebP. Actual hosted request, memory and queue settings still need dashboard confirmation.
- **Input/output trust:** PDFs, READMEs and focus are data in the prompt; output is validated again in the application. Text is escaped; URLs are restricted. README evidence does not prove source-code implementation or personal authorship; AI accuracy still needs human review.

No ownership, file-exposure, publication-isolation or recovery defect was reproduced by the local release checks. This is a bounded review of a controlled demo, not a public-service load test or comprehensive security assessment.

## Hosted release checklist

Use the [inspection flow](INSPECTION.md#inspection-flow), with the fictional CV and synthetic images for any new check. Complete this after promotion; avoid publishing a personal CV merely for verification.

- [ ] Confirm intended commit is deployed, migrations are current, and no configuration error appears.
- [ ] Sign in/out with Google; confirm protected owner routes require login and another account cannot open a private portfolio/file.
- [ ] Complete one real input → progressively filled portfolio → Ready journey; refresh/reopen during processing.
- [ ] Save an inline paragraph, upload a photo and multiple gallery images, rotate the carousel, and change appearance across Home/CV/projects.
- [ ] Publish; inspect pages/PDF/images signed out. Verify draft edits stay private until republishing; set a default and follow `/username`.
- [ ] Unpublish; confirm old public page/PDF/image/photo URLs fail and matching default clears. Confirm queued cleanup completes without removing still-referenced files.
- [ ] Read Cloud worker settings: current release, actual connection, job duration/visibility and memory support the application's 90/120/180-second timing. Confirm scheduler executions while web is idle, not only its configured toggle.
- [ ] Confirm `APP_DEBUG=false`, secure session cookies, private durable CV/image disks, and no exposed environment/provider secrets.
- [ ] Record an isolated Supabase database **and object-file** restore rehearsal if retaining the app beyond assessment. Do not restore over the live project to test this.

If a worker dies, the scheduler must recover its expired lease; duplicate/late output must not overwrite a newer attempt. Local tests exercise these cases. A hosted exercise should use a disposable fictional portfolio and a controlled worker interruption, never arbitrary modifications to real generation records.

## Backup and restore

Treat the database, both buckets and deployment configuration as one recoverable set. **Supabase database backups do not include Storage object bytes**, only their metadata. Availability/retention depends on the selected plan; inspect the dashboard rather than assuming backup coverage. [Supabase backup documentation](https://supabase.com/docs/guides/platform/backups).

For the assessment, take a protected export after the content is ready and before removing any resources. Store it outside the application's own project/buckets. Include the Git commit, migration state, disk/bucket mapping and date; keep secrets in a separate password manager. Both exports and logs can contain private CV/contact data.

A logical application backup can use a PostgreSQL client compatible with the server, a named connection service and a password file instead of a password in shell history:

```sh
pg_dump --dbname='service=portmyfolio-backup' --format=custom --no-owner --no-acl --schema=public --file=portmyfolio-public.dump
```

The configured service must point at the intended Supabase connection with TLS. This captures Laravel's `public` schema; it is not a replacement for a whole-platform Supabase backup. Export both buckets separately through the S3 endpoint, preserving object keys. For example, with a privately configured AWS CLI profile:

```sh
aws --profile portmyfolio-backup --endpoint-url https://YOUR-PROJECT.storage.supabase.co/storage/v1/s3 s3 sync s3://portmyfolio-cvs ./backup/portmyfolio-cvs
aws --profile portmyfolio-backup --endpoint-url https://YOUR-PROJECT.storage.supabase.co/storage/v1/s3 s3 sync s3://portmyfolio-images ./backup/portmyfolio-images
```

Do not add `--delete`. Pause writes and generation/cleanup during a coordinated export so database references and object copies describe the same state. Resume normal operation after recording a successful export. Export procedure examples above were not executed against production in this stage.

Restore procedure:

1. Provision an **isolated** compatible database and private buckets. Use the matching application commit; disable external AI access and workers initially. Never point the rehearsal at the live database/buckets.
2. Restore the app schema using the destination's credentials, for example `pg_restore --dbname='service=portmyfolio-restore' --no-owner --no-acl --exit-on-error portmyfolio-public.dump` into an empty destination. Preserve required server extensions/roles according to the destination provider's restore instructions.
3. Copy the archived files back under their original object keys. Configure the existing logical disk names (`s3`, `images_s3`) to those isolated buckets; snapshots store disk names and paths. A SQL restore alone leaves broken PDF/image references.
4. Apply any reviewed newer migrations only if intentionally upgrading. Check users, portfolios, projects, CVs, generation history, media and default pointers. Clear restored sessions/cache before exposing the isolated environment; keep restored queue jobs from dispatching paid requests until intentionally reviewed.
5. Verify public/private pages, original PDF and image bytes, ownership, unpublish/revoke behavior and publication snapshots against the archive. Then enable/review recovery and cleanup against the isolated resources. Record the tested commit/date and any lost interval before considering a real cutover.

Local SQLite fixtures can instead be backed up with SQLite's backup operation plus `storage/app/private`, while writes/workers are stopped. The local rehearsal above verified integrity, rows and file hashes; hosted PostgreSQL/S3 restoration remains a separate check.

## Cost and hosting lifetime

There is no application quota. Review Laravel Cloud compute/queue/storage/egress, Supabase project/storage/egress and Gemini usage independently. Token usage metadata helps diagnose generations; it is not an exact billing ledger, especially if a process dies after a charged request but before checkpoint commit.

Before retaining or opening the demo, the owner still needs to choose a monthly budget and end/review date. Review provider usage after the assessment and configure the available billing alerts in each dashboard. Do not treat alerts as a hard spending cap. Keep concurrency/resource ceilings appropriate to the controlled demonstration; no new public access policy or paid resource was enabled in Stage 8. Current terms: [Laravel Cloud pricing](https://marketing.cloud.laravel.com/pricing), [Supabase billing](https://supabase.com/docs/guides/platform/billing-on-supabase), [Gemini pricing](https://ai.google.dev/gemini-api/docs/pricing).

## Sunset procedure

1. Tell anyone using the demo when hosting will end and how to retain their pages/original inputs if needed.
2. Save wanted assessment evidence; export database and both private buckets if retaining data, and verify the protected copies. Decide a retention/deletion date for those backups too.
3. Stop new use, workers and scheduled work. Review pending/failed jobs and active provider requests before teardown.
4. Remove the Cloud application/environment and every separately billed queue/worker/resource; disconnecting Git or removing a domain alone does not cancel all charges. Check the organization subscription separately.
5. Remove the dedicated Supabase project/buckets and any paid add-ons when their data is no longer needed; verify the final billing state. Deleting the project also removes its hosted backups.
6. Revoke the dedicated Gemini, S3 and OAuth credentials/clients when no longer needed. Keep only deliberate offline evidence, then confirm final invoices/resource lists across providers.

No production export, restore, teardown, billing change or key revocation was performed in this stage.

## Screenshots

Captured from a fresh local installation using only synthetic fixtures. These are inspection evidence, not a recording of live generation.

- [Minimal input screen](evidence/01-inputs.jpg)
- [Inline editing on Home](evidence/02-inline-editing.jpg)
- [Appearance controls](evidence/03-appearance.jpg)
- [Editorial preset](evidence/04-editorial.jpg)
- [Project gallery and Technical details](evidence/05-project.jpg)
- [Published home without owner controls](evidence/06-published.jpg)
- [Published CV](evidence/07-cv.jpg)
