# Portmyfolio

A Laravel portfolio builder that turns a PDF CV and up to five public GitHub READMEs into an editable personal website. **Stage 1 is complete. Stage 2 is implemented on `feat/portfolio-models`, awaiting review:** schema, username onboarding, owner dashboard, and private draft creation/deletion. Content editing and generation are not implemented yet.

- [Product vision](VISION.md)
- [Data model](DATA_MODEL.md)
- [Development plan](PLAN.md)
- [Hosted setup and verification](docs/DEPLOYMENT.md)

## Requirements

- PHP 8.5 with Composer 2 for the currently verified lockfile; development uses Herd PHP 8.5.
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

Local data, sessions, cache, and queue jobs use PostgreSQL. CV files use `storage/app/private`; public-image files use `storage/app/public`. No hosted service credentials are required to view the landing page or run automated tests. Google sign-in shows a configuration message until a dedicated OAuth client is added. Local image serving will need `php artisan storage:link` when the gallery is implemented.

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

Spatie Media Library 11 manages future project galleries through its standard `media` table. Its default disk follows `IMAGE_FILESYSTEM_DISK`; original CV files remain on the separate private CV disk. The package and migration are installed, but the Project model and its gallery collection exist. Upload forms, conversions, and publication-aware cleanup arrive in later stages. Gallery removal must preserve media referenced by the published snapshot; see DATA_MODEL.md.

## Current limitations

- Private draft creation, viewing, and deletion are available. No content editor, publishing, PDF parsing, GitHub import, AI generation, or usage limits yet. Published or actively generating portfolios cannot be deleted through this stage’s draft-only action.
- Hosted Google sign-in/logout, direct Supabase PostgreSQL, private CV storage, managed queue processing, and the health endpoint have been verified. Image bucket setup/delivery remains deferred to the gallery stage.
- The framework scaffold still contains nullable password infrastructure for compatibility; there is no public password authentication feature. The application-owned schema is now implemented. Ownership policies cover portfolios, projects, CV evidence, generations, and gallery media; future nested editor routes must apply them.
- This is an experimental course/demo project; permanent hosted availability is not promised.
