# Deployment and provider setup

Current application: [Portmyfolio production](https://portmyfolio.laravel.cloud/) · [GitHub repository](https://github.com/camomilesson/port-my-folio). Recorded deployment: Laravel Cloud, Ireland, PHP 8.5 / Node 26, Supabase PostgreSQL and two private object buckets. Main uses push-to-deploy; merge/push is a deliberate release action after review. Historical implementation records remain in [PLAN.md](../PLAN.md).

Stages 1–7 were user-verified live. On 26 November 2026 the assistant also observed the current landing and `/up` pages in the browser. This does not independently verify current worker/scheduler settings or a complete authenticated journey; see [release review](RELEASE.md).

The shorter `portmyfolio.laravel.cloud` hostname was verified serving the landing page on 26 November. Its production environment needs `APP_URL=https://portmyfolio.laravel.cloud` and `GOOGLE_REDIRECT_URI=https://portmyfolio.laravel.cloud/auth/google/callback`, with that exact callback registered in Google OAuth. Sign-in after the hostname change still needs a user check.

## Hosted environment

Set values in Laravel Cloud's environment settings. Never commit or paste secret values into chat. Use the exact connection details supplied by each dashboard.

```dotenv
APP_NAME=Portmyfolio
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-HOST
APP_KEY=YOUR-EXISTING-UNIQUE-KEY
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=YOUR-SUPABASE-HOST
DB_PORT=YOUR-CONNECTION-PORT
DB_DATABASE=postgres
DB_USERNAME=YOUR-CONNECTION-USERNAME
DB_PASSWORD=SET-IN-DASHBOARD
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
DEMO_LOGIN_ENABLED=false

GOOGLE_CLIENT_ID=SET-IN-DASHBOARD
GOOGLE_CLIENT_SECRET=SET-IN-DASHBOARD
GOOGLE_REDIRECT_URI=https://YOUR-HOST/auth/google/callback

CV_FILESYSTEM_DISK=s3
IMAGE_FILESYSTEM_DISK=images_s3
AWS_ACCESS_KEY_ID=SET-IN-DASHBOARD
AWS_SECRET_ACCESS_KEY=SET-IN-DASHBOARD
AWS_DEFAULT_REGION=YOUR-SUPABASE-REGION
AWS_BUCKET=portmyfolio-cvs
AWS_IMAGE_BUCKET=portmyfolio-images
AWS_ENDPOINT=https://YOUR-PROJECT.storage.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true

GEMINI_API_KEY=SET-IN-DASHBOARD
GEMINI_MODEL=gemini-3.5-flash-lite
```

Preserve the existing `APP_KEY`; changing it is not a normal release step. Do not set secrets under `VITE_*`. The verified database connection is Supabase direct PostgreSQL with TLS; use its documented session-pooler details if direct connectivity is unavailable. Laravel uses a server-side database connection; keep application tables inaccessible through unneeded unauthenticated Data API access. Supabase Auth is not used.

Both buckets stay **private**. CVs allow PDF only, up to 10 MB; images allow JPEG/PNG/WebP, up to 10 MB. `AWS_BUCKET` and `AWS_IMAGE_BUCKET` must be different. The server-side S3 credentials are distinct from Supabase publishable/anon keys. Publication-authorized Laravel routes stream files; a public bucket policy or `storage:link` is not required.

## Build, deploy and processes

Use the working pinned build commands; Corepack was unavailable in the hosted build that was diagnosed during Stage 5:

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npx --yes pnpm@12.16.1 install --frozen-lockfile
npx --yes pnpm@12.16.1 run build
```

The initial build downloads declared font assets; runtime serves them locally. Deploy with `php artisan migrate --force --no-interaction` and Cloud's normal optimization steps. Never run `migrate:fresh` or demo seeding in production. Stages 6.2–8 add no migration. Confirm workers use the deployed release/configuration.

Allow Cloud to inject the **managed queue connection**; remove a custom `QUEUE_CONNECTION=database` override in production. Keep `database` locally. Normal generation must use an asynchronous connection, not `sync`.

The code uses a 90-second provider timeout, a 120-second job timeout, a 180-second lease and a maximum 900-second dispatched delay. Local database `retry_after` defaults to 180 seconds. Verify the selected hosted worker supports 120-second jobs and has retry/visibility strictly above 120 seconds. The historical setup was Standard/Flex, default queue, 256 MiB, 0–1 workers; these dashboard values have not been independently re-read. If the selected runtime imposes a shorter maximum, resolve that mismatch before calling timeout/recovery verification complete.

Enable Cloud's scheduler so `schedule:run` executes every minute, including when the web process is idle. `generation:recover` is registered in `routes/console.php` with overlap protection. Confirm actual scheduler executions, not just `schedule:list`. Recovery terminalizes exhausted/interrupted runs; use the application's **Retry remaining steps** for a failed initial attempt rather than blindly retrying old queue payloads. Cleanup jobs also need functioning workers.

Check GD with WebP and EXIF, at least 256 MB PHP memory, `upload_max_filesize=10M`, `post_max_size=128M`, `max_file_uploads` at least 10, `max_input_vars` at least 2,000, and a matching proxy body limit. These admit the maximum image batch plus overhead; application validation still bounds each file, dimensions/pixels and gallery size. Test a representative demo image on the actual runtime.

## Google sign-in

1. Create/use the dedicated Portmyfolio Web application OAuth client in Google Cloud / Google Auth Platform.
2. Configure branding/audience and any testing-mode test users. Laravel Socialite handles sign-in; no Drive/Gmail scopes or Supabase Google provider are needed.
3. Register exact callbacks: `http://127.0.0.1:8000/auth/google/callback` locally and `https://YOUR-HOST/auth/google/callback` hosted.
4. Store the client ID/secret and matching `GOOGLE_REDIRECT_URI` in the appropriate local/hosted environment. Use one hostname consistently.
5. Clear/rebuild configuration and restart workers as appropriate. Check sign-in, workspace, logout and protection after logout. Accounts are identified by Google ID; matching email does not link accounts automatically.

## Gemini in Google AI Studio

The user verified the following setup with `gemini-3.5-flash-lite` on 25 November 2026. Model/project access and dashboard wording can change; use the official [API key guide](https://aistudio.google.com/docs/api-key) if the controls differ.

1. Open [AI Studio API keys](https://aistudio.google.com/apikey) with your Google account.
2. Import/select the dedicated Portmyfolio Google Cloud project under Projects. The OAuth project can also hold Gemini access; use a dedicated key for this app.
3. Choose Create API key, select that project and name the key. Save it directly in the ignored local `.env` as `GEMINI_API_KEY`; set `GEMINI_MODEL=gemini-3.5-flash-lite`.
4. Check model access, usage limits and any required verification/billing in AI Studio. Review displayed prices before enabling paid use.
5. Run `php artisan config:clear --no-interaction`, then one explicit `php artisan gemini:check --no-interaction`. It sends the bundled fictional PDF, prints validated fictional fields/usage and changes no portfolio. This is a potentially billable live request; routine tests use fakes.
6. The optional `php artisan gemini:check --scanned --no-interaction` sends the fictional image-only CV. That controlled extraction also passed on 25 November; it does not guarantee every scanned CV's OCR quality.
7. Add a separate production key/model to Laravel Cloud, reload configuration and workers, then verify one controlled real generation. A missing hosted key previously produced the safe provider-configuration message even though deployment was green.

The adapter uses Laravel HTTP, native inline PDF and JSON Schema output, followed by application validation. No AI SDK or additional service is required. Current prompt contract: `portfolio-v4`; input text is untrusted evidence, missing facts stay empty, dates retain their precision, and project descriptions do not invent personal contributions. Technical details must be supported by the README. Home/CV wording uses extracted facts and completed project summaries. Editing, appearance and publication make no AI calls.

## Infrastructure checks

Run from the intended environment, never by copying production credentials into local test profiles:

```sh
php artisan infrastructure:check --no-interaction
php artisan infrastructure:check --disk=s3 --no-interaction
php artisan infrastructure:queue --no-interaction
php artisan schedule:list --no-interaction
```

The queue command prints a token and its exact completion-check command. The `s3` storage probe writes/reads/deletes a disposable PDF. **Do not use that PDF probe against `images_s3`**; its image-only bucket should reject it. Exercise images through the app with a synthetic JPEG/PNG/WebP instead. These checks complement the authenticated [release checklist](RELEASE.md#hosted-release-checklist).

## Service references

- [Supabase Laravel connection](https://supabase.com/docs/guides/getting-started/quickstarts/laravel) and [S3 credentials](https://supabase.com/docs/guides/storage/s3/authentication)
- [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect)
- [Laravel queue timeout/visibility guidance](https://laravel.com/framework/docs/queues#job-expirations-and-timeouts)
- [Laravel Cloud](https://cloud.laravel.com/) for current queue/scheduler settings
- Backup, restore, costs and shutdown: [release runbook](RELEASE.md#backup-and-restore)
