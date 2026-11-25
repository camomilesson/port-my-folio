# Portmyfolio

A Laravel portfolio builder that turns a PDF CV and up to five public GitHub READMEs into an editable personal website. **Stages 1–6 are deployed and verified. Stage 6.1 passed local review and is approved for production promotion:** minimal inputs lead straight into an owner-only portfolio that fills from generation checkpoints, with required Technical details on new projects. Inline pencil editing follows in Stage 6.2.

- [Product vision](VISION.md)
- [Data model](DATA_MODEL.md)
- [Development plan](PLAN.md)
- [Hosted setup and verification](docs/DEPLOYMENT.md)

## Requirements

- PHP 8.5 with Composer 2 for the currently verified lockfile; development uses Herd PHP 8.5. Gallery processing requires GD with WebP support and EXIF. Allow at least 256 MB PHP memory for bounded image decoding.
- Node.js 22.12+ (development currently uses Node 26) and pnpm 12.16.1.
- PostgreSQL 17 for local development and database integration tests. SQLite remains the fast, isolated default test database.

Composer and pnpm lockfiles pin actual dependency versions. The application currently uses Laravel 13 and Tailwind 4.

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create the local database role and databases using the PostgreSQL instructions below. Put the role password in your ignored `.env`, then:

```sh
php artisan migrate
pnpm install --frozen-lockfile
pnpm build
php artisan serve --host=127.0.0.1 --port=8000
```

The stock Artisan server uses your PHP upload limits. For image-batch review when those defaults are too small, stop that server and run this project-local alternative from the repository root. It uses Laravel's existing development router and changes limits only for this process:

```sh
(cd public && php -d upload_max_filesize=10M -d post_max_size=128M -d max_input_vars=2000 -d memory_limit=256M -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php)
```

Open http://127.0.0.1:8000. For frontend changes run `pnpm dev` in another terminal. To process queued work, run:

```sh
php artisan queue:work --tries=1 --timeout=120
```

Local data, sessions, cache, and queue jobs use PostgreSQL. CV files use `storage/app/private`; processed galleries use the separate private `images_local` disk at `storage/app/private/gallery`. No hosted service credentials are required to view the landing page or run automated tests. Google sign-in shows a configuration message until a dedicated OAuth client is added. Owner-authorized routes serve gallery files; no public storage symlink is needed for galleries.

### Google sign-in

Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in your ignored `.env`. Register the exact callback URL `http://127.0.0.1:8000/auth/google/callback` in the Google OAuth client. Use the same hostname consistently for sign-in and callback; switching between localhost and 127.0.0.1 loses the session context. Restart/reload cached configuration after changing settings.

Google-only production sign-in is implemented. There is no password-login endpoint. A separate assessment login is available only locally or in tests when explicitly enabled. Accounts use Google IDs and do not automatically link on matching email addresses.

### Local PostgreSQL

On macOS with Homebrew:

```sh
brew install postgresql@17
brew services start postgresql@17
export PATH="$(brew --prefix postgresql@17)/bin:$PATH"
createuser --login --no-superuser --no-createdb --no-createrole --pwprompt portmyfolio
createdb --owner=portmyfolio portmyfolio
createdb --owner=portmyfolio portmyfolio_test
```

Run role/database creation once; do not overwrite existing resources. The password prompt keeps the password out of shell history. Set that password as `DB_PASSWORD` in `.env`. The application role owns these two databases and is not a PostgreSQL superuser.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=portmyfolio
DB_USERNAME=portmyfolio
DB_PASSWORD=YOUR-LOCAL-ROLE-PASSWORD
DB_SSLMODE=prefer
```

Remove an existing hosted `DB_URL` when switching to local development. Run `php artisan config:clear`, `php artisan migrate`, and `php artisan infrastructure:check`. Homebrew keeps the cluster outside the repository; its service starts again at login. Stop it with `brew services stop postgresql@17` when desired. Homebrew's fresh local cluster uses local trust authentication by default; this is a loopback development service, not a configuration to deploy to a remote server.

Verified locally on 24 November 2026 using PostgreSQL 17.12 and Herd PHP 8.5: migrations, local file round-trip, database queue processing, and the full test suite. Existing SQLite files were left untouched; their contents are not copied into PostgreSQL. The current local `.env` already has the generated role password, so no repeat setup is needed on this machine.

## Verification

```sh
composer test
composer test:postgres
composer format:check
pnpm build
php artisan infrastructure:check --disk=local
php artisan infrastructure:queue
```

The last command prints a probe token. Process it with the worker above, then check the result:

```sh
php artisan infrastructure:queue TOKEN_FROM_PREVIOUS_COMMAND
```

The `/up` endpoint checks application boot; it does not claim to check storage or the database. Infrastructure commands are CLI-only and print no credentials. Storage probes create and delete a disposable PDF object under `infrastructure-probes/`.

`composer test` uses `phpunit.xml` to force in-memory SQLite and clear `DB_URL`. `composer test:postgres` uses `phpunit.postgres.xml` to force PostgreSQL at `127.0.0.1:5432`, the `portmyfolio` role, and the disposable `portmyfolio_test` database, also clearing `DB_URL`. It reads only the role password from your local `.env`; never point the normal development configuration at a hosted database while running local checks. The PostgreSQL suite uses the real schema and may recreate tables inside that dedicated test database. Tests are written in Pest and cover OAuth account creation, stable identity matching, refusal to link by email, invalid OAuth state, logout, protected access, file probe cleanup, and a real database-queue worker round trip. They fake Google responses; they do not establish that a real OAuth client works.

### Offline demo

Set `DEMO_LOGIN_ENABLED=true` in a local `.env`, migrate, then run `php artisan db:seed` once on an unseeded local database. The landing page's **Explore the local demo** button signs in the seeded `admin@example.com` identity (Sam Example). This is a fictional assessment account, with no administrator privileges. The shortcut returns 404 in production even if the flag is enabled.

Fixtures include two owners, multiple portfolios, project records, synthetic images, a fictional PDF CV, and completed generation history. No external requests or AI calls are made. Fresh published fixtures exercise public rendering locally; ordinary hosted drafts require real generation before publication. Start the local queue worker to process draft-file cleanup.

`migrate:fresh --seed` is destructive: use it only against a disposable local database. Do not use it on an existing development or hosted database. The seeder is for a fresh/unseeded local database and refuses production.

## Media Library foundation

Spatie Media Library 11 manages project galleries through its standard `media` table. Its gallery disk follows `IMAGE_FILESYSTEM_DISK` (default `images_local`); source CVs remain on the separate private CV disk. Uploads are decoded and stored as one metadata-free WebP at up to 2,400 pixels; originals are discarded. First active gallery image is the cover. Draft removals create tombstones; cleanup rechecks both draft and published references before deleting removed media or soft-deleted project owners; see DATA_MODEL.md.

## Current limitations

- Private home/CV/project editing, ordering, gallery management, immutable PDF upload/download, and home/CV/project previews are available. Publication, evidence fetching, Gemini extraction, queued initial generation and recovery are implemented. Active deletion cancels work, redacts checkpoints and queues file cleanup. Stage 6 awaits review.
- Hosted Google sign-in/logout, direct Supabase PostgreSQL, private CV storage, managed queue processing, and the health endpoint have been verified. User verified Stage 3 hosted image upload/delete and source PDF upload/download on 25 November 2026. Stage 4 is deployed and hosted smoke checks passed; full hosted publication lifecycle verification follows genuine generation in Stage 6.
- The framework scaffold still contains nullable password infrastructure for compatibility; there is no public password authentication feature. The application-owned schema is now implemented. Ownership policies cover portfolios, projects, CV evidence, generations, and gallery media; nested editor and preview routes apply them and check parent/child identity.
- This is an experimental course/demo project; permanent hosted availability is not promised.


## Stage 3 local review

Use the local demo, open a portfolio, and choose Edit home, Edit CV & PDF, Add project, or Preview draft. Save forms explicitly before previewing. Name/headline are shared within a portfolio; home introduction and CV summary are independent. CV sections have add/remove/reorder controls. The source PDF can be uploaded only once (10 MB), downloaded by its owner while private, and never replaced. Stage 4 removes the old download-permission checkbox: publishing makes the original PDF downloadable to visitors.

Projects require a normalized https://github.com/owner/repository URL and a role, with at most five active projects. Repository identity and slug stay fixed; manual additions do not call AI or consume generation quota. Live GitHub checks follow in Stage 5.

Galleries accept still JPEG/PNG/WebP up to 10 MB, 12,000 pixels per side and 24 megapixels. Maximum ten active images per project. Decorative images have empty alt text; other images require an image description. Only processed files are retained. Existing publication snapshots are never changed by draft actions. Editing is blocked during queued/running generation. Cleanup needs a queue worker.

After review/merge, hosted galleries need `IMAGE_FILESYSTEM_DISK=images_s3` and `AWS_IMAGE_BUCKET=portmyfolio-images`. Keep `AWS_BUCKET` pointing at the private CV bucket. The image disk shares existing server-side S3 credentials/region/endpoint; do not make either bucket public. Apply `php artisan migrate --force` during deployment. Configure HTTP/PHP limits to admit up to ten 10 MB images in one request plus form overhead (for example, `post_max_size=128M`, `upload_max_filesize=10M`, `max_file_uploads` at least 10, and a matching proxy request-body limit). Keep `max_input_vars` at least 2,000 for the supporting CV form. An end-of-form marker rejects truncated CV submissions rather than wiping omitted entries. Test profiles force local storage disks and never upload to Supabase.


## Stage 4 local review

Open the local demo and use the seeded Backend developer portfolio, which has successful fixture generation, projects and a source PDF. Ordinary manually created portfolios have no successful generation and cannot publish yet; no production bypass is provided. Fresh seeds also contain a complete published example. Older pre-Stage-4 synthetic publication snapshots lack file references and return the friendly 404; do not reset your normal database to repair old fixtures. Use Backend developer, publish it, and choose Make default if an old fixture already occupies that slot.

Use Publish portfolio / Publish changes from the overview, preview the saved draft, then explicitly confirm that both the portfolio and original PDF become public. The first publication while no default is selected assigns that default. Other published tiles and editors offer Make default. /username redirects to the owned published default; /username/slug is always canonical. Usernames and slugs stay fixed. Missing/unpublished addresses show the friendly portfolio-not-found page without exposing draft content.

Test public home/CV/project pages, PDF and gallery URLs signed out. Draft edits/removals must leave those public responses unchanged until republishing. After republish, old removed project/image URLs must return 404 and queued cleanup can remove their files. Unpublish immediately disables new public page/media/PDF access and clears a matching default; it does not silently select another existing portfolio. Deleting a published portfolio also revokes access, preserves redacted generation usage history, and queues its files for deletion. Run the worker for cleanup. Already downloaded copies cannot be recalled.

Public pages and previews use the same minimal templates with separate data sources. Public requests read snapshot content/file references only; public markup contains no owner editor controls or session flash. Snapshot validation checks structural limits and safe URLs/dates without requiring optional text, contacts, images or CV sections. Initial publication requires a source PDF, at least one active repository-backed project and completed generation; fixture-provider success is accepted only in local/testing environments. There are no AI calls in publication.

Validation: 146 PostgreSQL tests / 699 assertions; SQLite 142 passing / 676 assertions and four PostgreSQL-only concurrency cases skipped. The publishing/default race tests run separate processes. No new packages, storage resources, or schema migrations are required for Stage 4. Existing private buckets and managed workers remain in use. GitHub Actions remains deferred by user preference.


## Stage 5 inputs and Gemini integration

New portfolios collect one PDF (10 MB, 1–20 pages), optional focus (5,000 characters), and 1–5 public GitHub URLs with roles. Full README evidence must exceed 200 meaningful characters and stay strictly below 100,000 bytes. Badges, comments, markup and link-only content do not count toward the minimum. GitHub preflight uses fixed API endpoints, refuses redirects/private repositories, bounds response sizes, and caches public evidence for one hour. Rate limits return a retry time and suppress requests until then; successfully cached evidence survives a failed submission. Durable automatic delayed retries belong to Stage 6. A failed submission requires choosing the PDF again.

Only validated inputs are persisted; no AI call, quota reservation or successful-generation record is created by collection. smalot/pdfparser 2.13 validates document structure/page count without replacing native Gemini PDF processing. Source PDFs remain immutable. Generation output is validated independently of provider schema promises; rendered text remains escaped.

Configure GEMINI_API_KEY and GEMINI_MODEL=gemini-3.5-flash-lite in the ignored local .env. The application uses Laravel HTTP against Gemini's supported generateContent REST endpoint, native inline PDF input, structured JSON output and safe provider errors. No additional AI SDK is installed. The provider contract and deterministic fake are in app/Actions; tests never require credentials. The adapter has a 90-second timeout; Stage 6 must align worker timeout/visibility settings before queue integration.

Run php artisan config:clear, then php artisan gemini:check --no-interaction for one explicit, potentially billable live extraction of the bundled fictional CV. It prints validated fictional content and usage, never the key; it changes no portfolio or generation record. The user completed this check successfully with gemini-3.5-flash-lite. Do not repeatedly run it as routine testing. The image-only fictional CV check also passed with Flash-Lite after clarifying headline extraction; this is a baseline, not a guarantee of OCR quality for every CV.

User review should exercise the new form, optional focus, add/remove repository controls, invalid README feedback, retry after a failure, and preservation of existing editor/publication behavior. No merge or deployment is implied by implementation.


## Stage 6 generation review

Open a portfolio → Generation & progress, review its PDF/focus/repositories/roles, explicitly consent and choose Generate. The action freezes those inputs. There is no per-account generation quota. Retrying a failed attempt increments its run version and reuses checkpoints. Changed inputs require a new portfolio; successful portfolios cannot be regenerated.

Run the database worker locally with `php artisan queue:work --tries=1 --timeout=120` and recovery scheduling with `php artisan schedule:work`. Each job handles one step; the provider timeout is 90 seconds, job timeout 120 seconds, processing lease 180 seconds, and database retry visibility defaults to 180 seconds. Renew the lease at each step boundary. The recovery command `php artisan generation:recover` runs every minute through the scheduler. It redelivers overdue queued work, recovers expired leases, and terminalizes a step after three interrupted attempts. A run also has a 24-hour recovery deadline; expiry terminalizes the interrupted run and an explicit user retry starts a fresh recovery window. Missing scheduler/worker processes prevent recovery; they must run independently of a browser session.

Processing order: validate frozen evidence, extract CV, generate each project independently, write home/CV from extracted facts and completed summaries, then atomically assemble the editable draft. Progress/checkpoints stay owner-only and are never public content. Each technical step has at most three attempts per run, with delayed retry times. Delay delivery is capped at 900 seconds; the saved next-run time and scheduler handle longer provider waits. Duplicate jobs and expired workers cannot commit results without the matching lease/version. No provider request occurs while holding database row locks.

Deletion terminalizes active work, revokes leases, detaches generation history, redacts inputs/checkpoints and queues files for deletion. An already-sent provider request can still incur cost, but its late result is discarded. Completed-request usage/model/prompt metadata is recorded per checkpoint; a worker crash after a charged request but before checkpoint commit can require repeating that request.

`php artisan gemini:check --scanned --no-interaction` sends the bundled image-only fictional CV. The controlled live check passed on 25 November 2026 with gemini-3.5-flash-lite: professional headline, contact, dates, experience, education and skills matched the fixture. Automated tests continue to use fakes. User completed local generation/content/publication review and approved promotion. Hosted worker/recovery configuration and the production generation journey still need verification.


## Stage 6.1 local review

Keep the app, queue worker and scheduler running as described above. Restart an already-running queue worker after switching to this branch so it loads the new provider contract (`php artisan queue:restart`, then restart a manually managed worker if it exits). The additive technical_details migration is applied locally; no provider credential or dependency change is required.

New portfolio now asks for a PDF and repository URLs; optional focus is collapsed. Generate creates the title/address, stores the PDF/repository identities and queues generation in the same transaction. GitHub fetching and README preflight run in the queued Checking inputs step so the form can open the portfolio promptly. The two predefined demo repositories use the previously confirmed Solo developer roles; other repositories use Contribution not specified. The former explicit-title/role draft endpoint remains supporting functionality, outside the primary input screen.

The owner is redirected to the portfolio's private preview with placeholders. Completed CV extraction and individual projects appear from checkpoints; profile wording follows later. Polling refreshes the portfolio content without navigating away or publishing it. Refresh/revisit reconstructs saved progress. A failed attempt keeps completed sections visible and offers Retry remaining steps. Existing backend edits remain blocked while active, and final draft assembly remains atomic.

New project generation must return technical_details (required plain text, maximum 10,000 characters), covering supported implementation details beyond the overview/tags. It is persisted and included in public snapshots. The existing project editor can edit it; inline editing is Stage 6.2. Legacy finished portfolios/snapshots remain readable with empty technical details and are not regenerated or rewritten.

Review with a fresh portfolio and the selected demo CV/repositories: confirm direct transition, CV/project filling, refresh/revisit, final home/CV wording and substantive Technical details on both project pages. Publish explicitly and confirm that public content stays isolated from draft edits. Full live generation through the redesigned screen remains the user's review checkpoint; automated HTTP/provider fakes prove the step-by-step projection and failure behavior without sending personal PDFs.


Generation quotas have been removed for this controlled demo. All accounts can start new portfolios; duplicate starts and regeneration of an already successful portfolio remain prohibited. There is no account-specific unlimited override or slot UI. Earlier historical quota notes are superseded.


### Stage 6.2: edit on the portfolio

Open a generated portfolio from the workspace. After generation succeeds, use its pencils to edit existing home, CV and project fields with Save/Cancel. Name/title are shared between home and CV; the introduction and CV summary remain separate. Technologies and skill items use comma-separated inputs. Fixed section headers and entry/project ordering have no pencils. The separate editor remains under Portfolio settings for supporting tasks.

On a project, Add images accepts multiple JPEG/PNG/WebP files. Give each image a description or explicitly mark it decorative, with an optional caption. The whole selection is validated before appending; failure leaves the gallery unchanged. The cap is ten active images per project, at 10 MB per uploaded file. Gallery caption, description and decorative choices can be edited in place; saving a description makes the image non-decorative. Images keep the existing private, metadata-stripped WebP processing.

Saved edits update only the private draft. Publish / Publish changes opens the original-PDF disclosure and explicit confirmation, then returns to the portfolio. View published opens the public snapshot, which has no editing controls. Edits do not call Gemini. No new environment variables, migrations or dependencies are needed for this stage; check the combined upload limits above before reviewing a large image batch.


### Stage 6.2 pre-review changes

Project roles have been removed from inputs, settings, presentation and generation context. New project prompts describe repository functionality without claiming personal authorship (prompt version portfolio-v4). Restart the queue worker before generating after this update.

On the ready portfolio home, Add photo uploads one JPEG/PNG/WebP file (10 MB maximum), processed privately like gallery images. It appears on the right at desktop widths and below the introduction on mobile. Change photo replaces the draft version; the public photo changes only when you Publish changes. The original photo upload is not retained. Existing worker processing cleans unused replaced photos, while preserving current/published references.

Project galleries now appear after the title, summary and technologies, before Overview and Technical details. Click the left/right side controls to move through images; navigation wraps, with no automatic rotation. The existing multi-upload and image-detail pencils remain available. No migration or new environment variable is needed.
