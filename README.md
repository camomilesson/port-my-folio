# Portmyfolio

A Laravel demo that turns a PDF CV and public GitHub READMEs into an editable portfolio. Upload a CV, add repository links, and watch the private portfolio fill as generation progresses. Edit text with pencils, add photos, choose Minimal, Material or Editorial, then explicitly publish a saved version.

Stages 1–7 are implemented and user-verified live. Stage 8 release evidence and operational checks are tracked in [the release review](docs/RELEASE.md). This is a controlled demonstration; there is no generation quota or public-service availability commitment.

- [Product scope](VISION.md) · [Data model and ER diagram](DATA_MODEL.md) · [Development history](PLAN.md)
- [Deployment and provider setup](docs/DEPLOYMENT.md) · [Release checks and operations](docs/RELEASE.md)
- [Professor inspection flow](docs/INSPECTION.md) — hosted access, local setup, credentials and the exact journey to try

## Requirements

- PHP **8.5** and Composer 2 for the verified lockfile; PDO SQLite for the offline demo, PDO PostgreSQL for the deployed database. Image processing needs GD with WebP support and EXIF; allow at least 256 MB PHP memory.
- Node.js **22.12+** and pnpm **12.16.1**; development/deployment currently use Node 26.
- PostgreSQL 17 for normal development and concurrency tests. The assessment demo and default test suite also run on SQLite.

The lockfiles pin Laravel 13, Pest 5, Spatie Media Library 11, Socialite 5, Vite 8 and Tailwind 4. Installing dependencies and the first font build require internet access; the prepared fixture demo needs no Google, GitHub or Gemini connection. Fonts are served locally after building.

## Fresh assessment demo

Use a **new checkout** for these steps. They do not need production credentials.

```sh
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate --no-interaction
pnpm install --frozen-lockfile
```

Change these entries in the new checkout's ignored `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DEMO_LOGIN_ENABLED=true
```

Keep the example's local storage, database session/cache/queue settings and empty Google/Gemini/S3 keys. Then:

```sh
touch database/database.sqlite
php artisan migrate --seed --no-interaction
pnpm run build
php artisan serve --host=127.0.0.1 --port=8000 --no-interaction
```

Open [the local app](http://127.0.0.1:8000), choose **Explore the local demo**, then **Backend developer**. This signs in fictional Sam Example (`admin@example.com`, username `sam-example`); it is not an admin role. The seed includes two independent owners, editable projects with synthetic images and CVs, a blank draft, and a published default at `/sam-example/published-example`.

Edit the seeded portfolio, switch presets, browse Home/CV/projects, and try publication without external services. **New generation requires GitHub and Gemini**; the offline browser demo uses already completed fixture generations. Automated pipeline tests use deterministic HTTP/provider fakes. Demo sign-in and seeding are rejected in production even if the flag is enabled.

For a deliberate reset of this disposable demo only, use `php artisan migrate:fresh --seed --no-interaction`. Never use that command on a normal development or hosted database. Seeding is intended for an empty database, not repeated imports into existing accounts.

## Normal local development

The example configuration uses local PostgreSQL databases `portmyfolio` and `portmyfolio_test`, with an application role named `portmyfolio`. On macOS:

```sh
brew install postgresql@17
brew services start postgresql@17
export PATH="$(brew --prefix postgresql@17)/bin:$PATH"
createuser --login --pwprompt portmyfolio
createdb --owner=portmyfolio portmyfolio
createdb --owner=portmyfolio portmyfolio_test
```

Use the local role's password in `.env`; keep hosted database credentials out of it. Run `php artisan migrate --no-interaction`, install/build assets as above, and start the app. Use `pnpm dev` for frontend work. For generation and deferred file cleanup, keep these in separate terminals:

```sh
php artisan queue:work --tries=1 --timeout=120 --no-interaction
php artisan schedule:work --no-interaction
```

The scheduler runs recovery every minute. Restart long-running workers after code/config changes (`php artisan queue:restart`); manually managed workers must be started again after they exit. `composer run dev` is the project's alternative combined development entry point.

If PHP's upload defaults are too small, stop the Artisan server and use this process-local alternative from the repository root:

```sh
(cd public && php -d upload_max_filesize=10M -d post_max_size=128M -d max_file_uploads=10 -d max_input_vars=2000 -d memory_limit=256M -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php)
```

CVs live on the private `local` disk; processed photos/galleries use `images_local`. No public storage symlink is needed. Normal local Google sign-in needs the three `GOOGLE_*` values and the exact callback `http://127.0.0.1:8000/auth/google/callback`; consistently use the same hostname. See [provider setup](docs/DEPLOYMENT.md) for Gemini and hosted configuration. After `.env` changes run `php artisan config:clear --no-interaction` and restart workers.

## Inputs and editing

- One immutable PDF CV, up to 10 MB and 1–20 pages. Encrypted/malformed files are rejected. Gemini reads the PDF directly; local PDF parsing validates structure and page count.
- One to five public GitHub repositories. Complete README evidence must exceed 200 meaningful characters and stay **strictly below 100,000 bytes**. The importer reads metadata/README through fixed GitHub API endpoints, with bounded responses, one-hour caching and rate-limit delays. It does not inspect source code or private repositories.
- Optional focus up to 5,000 characters changes emphasis. Project roles have been removed.
- Home/CV/project text is edited in place after generation; technologies/skill items use comma-separated inputs. Fixed section headers and ordering have no pencils. Supporting forms remain under Portfolio settings.
- Photo and gallery uploads save immediately after file selection. JPEG/PNG/WebP only, up to 10 MB each and ten active images per project. Processing strips metadata, bounds dimensions/pixels, and stores resized WebP. New gallery images default to decorative with no caption or description. The carousel rotates using its left/right halves or arrow keys.
- Appearance autosaves to the draft. Public pages change only after **Publish changes**.

## Generation and publication

Each portfolio gets one initial generation; failed attempts can resume completed checkpoints, successful ones cannot regenerate. Jobs check evidence, extract CV facts, write projects independently (including required Technical details), write home/CV, and assemble the final draft atomically. Owner-only progress survives refresh/revisit. Each step has at most three automatic attempts per run, with provider-aware delays and a 24-hour recovery deadline.

The provider request timeout is 90 seconds, job timeout 120 seconds, processing lease 180 seconds, and local database queue visibility 180 seconds. Hosted worker limits must support these values; a green build alone does not verify recovery. Late/duplicate workers need the current lease and run version before committing. Deletion cancels active work and redacts retained source/checkpoint history; requests already sent may still incur costs.

Publishing validates a complete snapshot of content, appearance, projects and immutable file references. It also makes the **original PDF** publicly downloadable. Draft edits and removed files cannot change the current public version. Unpublish revokes new page/file requests and clears a matching default; downloaded copies cannot be recalled. Private storage buckets are never made public. Cleanup runs through reference-aware queued jobs.

Canonical URLs are `/username/portfolio-slug`, with `/cv` and `/projects/project-slug` subpages. `/username` redirects to that account's selected published default. There is no global `/user` public portfolio. Usernames and slugs remain fixed.

## Verification

```sh
composer test -- --compact
composer test:postgres -- --compact
pnpm run build
composer validate --strict
```

The default test profile uses isolated in-memory SQLite and fake local storage. The PostgreSQL profile fixes host/database/user to loopback `portmyfolio_test` and uses the local `.env` password; it never uses a production DB URL. Run the two suites **sequentially**, since they share fake-storage directories. After PHP changes run `vendor/bin/pint --dirty --format agent`.

Tests cover ownership/nested scoping, validation, provider contracts, retry/recovery fencing, progressive rendering, inline editing, image failure handling, snapshot isolation, file revocation, cleanup and PostgreSQL races. [Release evidence](docs/RELEASE.md) records actual results and remaining hosted checks. Live `gemini:check` is an explicit potentially billable probe, not part of routine verification. GitHub Actions remains deferred by agreement.
