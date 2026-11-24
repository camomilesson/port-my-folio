# Portmyfolio

A Laravel portfolio builder that turns a PDF CV and up to five public GitHub READMEs into an editable personal website. Currently at **Stage 1: local foundation**. Generation and portfolio editing are not implemented yet.

- [Product vision](VISION.md)
- [Data model](DATA_MODEL.md)
- [Development plan](PLAN.md)
- [Hosted setup and verification](docs/DEPLOYMENT.md)

## Requirements

- PHP 8.5 with Composer 2 for the currently verified lockfile; development uses Herd PHP 8.5.
- Node.js 22.12+ (development currently uses Node 26) and pnpm 12.16.1.
- SQLite for the initial local scaffold. PostgreSQL is the target development/production database; it has not yet been verified locally.

Composer and pnpm lockfiles pin actual dependency versions. The application currently uses Laravel 13 and Tailwind 4.

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create the SQLite file if absent:

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
pnpm install --frozen-lockfile
pnpm build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. For frontend changes run `pnpm dev` in another terminal. To process queued work, run:

```sh
php artisan queue:work --tries=2 --timeout=60
```

The local defaults use SQLite for data, sessions, cache, and the queue. CV files use `storage/app/private`; public-image files use `storage/app/public`. No external credentials are required to view the landing page or run automated tests. Google sign-in shows a configuration message until a dedicated OAuth client is added. Local image serving will need `php artisan storage:link` when the gallery is implemented.

### Google sign-in

Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in your ignored `.env`. Register the exact callback URL `http://127.0.0.1:8000/auth/google/callback` in the Google OAuth client. Use the same hostname consistently for sign-in and callback; switching between localhost and 127.0.0.1 loses the session context. Restart/reload cached configuration after changing settings.

Google-only production sign-in is implemented. There is no password-login endpoint or local assessment login yet. Accounts use Google IDs and do not automatically link on matching email addresses.

### Local PostgreSQL (pending)

Install/start a local PostgreSQL service, create a dedicated development database and user, then configure:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=portmyfolio
DB_USERNAME=portmyfolio
DB_PASSWORD=your-local-password
```

Remove an existing `DB_URL` if switching from a hosted connection. Run `php artisan config:clear`, `php artisan migrate`, and `php artisan infrastructure:check`. This setup is not marked verified until a local PostgreSQL server is available. Do not substitute a production database for local/testing work.

## Verification

```sh
composer test
composer format:check
pnpm build
php artisan infrastructure:check --disk=local
php artisan infrastructure:queue
```

The last command prints a probe token. Process it with the worker above, then check the result:

```sh
php artisan infrastructure:queue TOKEN_FROM_PREVIOUS_COMMAND
```

The `/up` endpoint checks application boot; it does not claim to check storage or the database. Infrastructure commands are CLI-only and print no credentials. Storage probes create and delete a disposable text object under `infrastructure-probes/`.

Pest uses the PHPUnit configuration to force an in-memory SQLite database and clears `DB_URL`, keeping tests away from hosted databases. Tests are written in Pest and cover OAuth account creation, stable identity matching, refusal to link by email, invalid OAuth state, logout, protected access, file probe cleanup, and a real database-queue worker round trip. They fake Google responses; they do not establish that a real OAuth client works.

`migrate:fresh --seed` is destructive: use it only against a disposable local database. Rich application factories/fixtures and the assessment-only sign-in mechanism arrive in Stage 2.

## Media Library foundation

Spatie Media Library 11 manages future project galleries through its standard `media` table. Its default disk follows `IMAGE_FILESYSTEM_DISK`; original CV files remain on the separate private CV disk. The package and migration are installed, but the Project model, upload forms, conversions, and publication-aware cleanup are not implemented yet. Gallery removal must preserve media referenced by the published snapshot; see DATA_MODEL.md.

## Current limitations

- No portfolio schema/editor, publishing, PDF parsing, GitHub import, AI generation, or usage limits yet.
- Supabase, real Google sign-in, local PostgreSQL, and Laravel Cloud deployment have not been verified.
- The framework scaffold still contains nullable password infrastructure for compatibility; there is no public password authentication feature. The application-owned schema is introduced in Stage 2.
- This is an experimental course/demo project; permanent hosted availability is not promised.
