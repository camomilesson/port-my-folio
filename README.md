# Portmyfolio

A Laravel portfolio builder that turns a PDF CV and up to five public GitHub READMEs into an editable personal website. **Stages 1–2 are complete and verified live. Stage 3 is implemented on `codex/stage-3-editor`, awaiting review:** manual content editing, private draft previews, project galleries, and an immutable source PDF. Publishing and AI generation are not implemented yet.

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

Open http://127.0.0.1:8000. For frontend changes run `pnpm dev` in another terminal. To process queued work, run:

```sh
php artisan queue:work --tries=2 --timeout=60
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

Fixtures include two owners, multiple portfolios, project records, synthetic images, a fictional PDF CV, and completed generation history. No external requests or AI calls are made. The published fixture exercises model/dashboard states; public rendering arrives later. Start the local queue worker to process draft-file cleanup.

`migrate:fresh --seed` is destructive: use it only against a disposable local database. Do not use it on an existing development or hosted database. The seeder is for a fresh/unseeded local database and refuses production.

## Media Library foundation

Spatie Media Library 11 manages project galleries through its standard `media` table. Its gallery disk follows `IMAGE_FILESYSTEM_DISK` (default `images_local`); source CVs remain on the separate private CV disk. Uploads are decoded and stored as one metadata-free WebP at up to 2,400 pixels; originals are discarded. First active gallery image is the cover. Draft removals create tombstones; cleanup retains all removed media while a publication snapshot exists. Stage 4 will complete reference-aware publication cleanup; see DATA_MODEL.md.

## Current limitations

- Private home/CV/project editing, ordering, gallery management, immutable PDF upload/download, and home/CV/project previews are available. Publishing, PDF extraction, GitHub fetching, AI generation, and quota enforcement remain later stages. Published or actively generating portfolios cannot be deleted through the current draft-only action.
- Hosted Google sign-in/logout, direct Supabase PostgreSQL, private CV storage, managed queue processing, and the health endpoint have been verified. The user created the private image bucket for Stage 3; hosted gallery upload/delivery is not yet verified.
- The framework scaffold still contains nullable password infrastructure for compatibility; there is no public password authentication feature. The application-owned schema is now implemented. Ownership policies cover portfolios, projects, CV evidence, generations, and gallery media; nested editor and preview routes apply them and check parent/child identity.
- This is an experimental course/demo project; permanent hosted availability is not promised.


## Stage 3 local review

Use the local demo, open a portfolio, and choose Edit home, Edit CV & PDF, Add project, or Preview draft. Save forms explicitly before previewing. Name/headline are shared within a portfolio; home introduction and CV summary are independent. CV sections have add/remove/reorder controls. The source PDF can be uploaded only once (10 MB), downloaded only by its owner in this stage, and never replaced. The public-download checkbox is a draft preference for Stage 4 publication, not an immediate sharing switch.

Projects require a normalized https://github.com/owner/repository URL and a role, with at most five active projects. Repository identity and slug stay fixed; manual additions do not call AI or consume generation quota. Live GitHub checks follow in Stage 5.

Galleries accept still JPEG/PNG/WebP up to 10 MB, 12,000 pixels per side and 24 megapixels. Maximum ten active images per project. Decorative images have empty alt text; other images require an image description. Only processed files are retained. Existing publication snapshots are never changed by draft actions. Editing is blocked during queued/running generation. Cleanup needs a queue worker.

After review/merge, hosted galleries need `IMAGE_FILESYSTEM_DISK=images_s3` and `AWS_IMAGE_BUCKET=portmyfolio-images`. Keep `AWS_BUCKET` pointing at the private CV bucket. The image disk shares existing server-side S3 credentials/region/endpoint; do not make either bucket public. Apply `php artisan migrate --force` during deployment. Configure HTTP/PHP upload limits to admit a 10 MB file plus form overhead and `max_input_vars` to at least 2,000 for the largest CV form. An end-of-form marker rejects truncated CV submissions rather than wiping omitted entries. Test profiles force local storage disks and never upload to Supabase.
