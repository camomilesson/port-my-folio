# Stage 1 — Hosted setup and verification

Status: prepared, **not deployed or verified**. Dedicated Portmyfolio resources do not exist yet. Existing projects must not be repurposed or altered implicitly.

## Resources to create

1. A Git repository containing this application, with `.env`, vendor, node_modules, built assets, and local databases excluded.
2. A dedicated Supabase project. Keep the application tables accessible through Laravel's server-side database connection, not an unauthenticated browser data API. Restrict/disable unneeded Data API exposure.
3. A private CV bucket and an image bucket with an explicit public delivery policy. Configure S3 credentials server-side; these are not browser API keys. This Stage 1 scaffold has one S3 disk, so verify the CV bucket first and document the image bucket setup before gallery implementation.
4. A Google OAuth client with local and hosted callback URLs. Configure the consent screen and test-user access as needed. Reusing the Google account is fine; create dedicated client credentials for this app.
5. A Laravel Cloud application/environment connected to the repository, with a managed queue. Review the resource estimate and spending controls before activating paid resources.

Use nearby regions for Laravel Cloud and Supabase. Laravel Cloud hosting does not require using its own database or file storage.

## Configuration

Set secrets through the hosting dashboard, never committed files or chat messages. Use the framework's PostgreSQL connection settings and Supabase's documented connection details. A session-pooler connection is a practical starting point where direct IPv6 connectivity is unavailable. Require TLS (`DB_SSLMODE=require`) and verify the connection from the actual hosted environment.

```dotenv
APP_NAME=Portmyfolio
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-HOST
APP_KEY=GENERATE-A-UNIQUE-KEY
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

GOOGLE_CLIENT_ID=SET-IN-DASHBOARD
GOOGLE_CLIENT_SECRET=SET-IN-DASHBOARD
GOOGLE_REDIRECT_URI=https://YOUR-HOST/auth/google/callback

CV_FILESYSTEM_DISK=s3
IMAGE_FILESYSTEM_DISK=public
AWS_ACCESS_KEY_ID=SET-IN-DASHBOARD
AWS_SECRET_ACCESS_KEY=SET-IN-DASHBOARD
AWS_DEFAULT_REGION=YOUR-SUPABASE-REGION
AWS_BUCKET=YOUR-PRIVATE-CV-BUCKET
AWS_ENDPOINT=https://YOUR-PROJECT.storage.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true
```

`IMAGE_FILESYSTEM_DISK=public` is only the scaffold's local image configuration, not a production gallery solution. Configure the separate hosted image disk/delivery URL before Stage 3's hosted gallery checks. Never expose the CV bucket to make images work.

Configure `QUEUE_CONNECTION` and any queue environment variables using the current Laravel Cloud managed-queue instructions. Do not assume the local database queue is the managed-queue configuration. Verify the configured job timeout is shorter than the queue's retry/visibility interval.

## Build and deployment

Use PHP 8.5 or a compatible supported PHP version and a supported Node version. Install Composer production dependencies from the lockfile, then:

```sh
pnpm install --frozen-lockfile
pnpm build
```

Confirm pnpm is available in the Cloud build environment and provision the pinned version if necessary. Use `php artisan migrate --force` as a controlled deployment command against the dedicated application's database; never run `migrate:fresh` there. Apply the platform's recommended Laravel optimization commands after environment variables are set. Ensure the queue uses the new release/configuration after deploys.

No deploy commands have been executed against a hosted environment yet.

## Verification record

Run these only after resource setup:

- [ ] `/` renders Portmyfolio with compiled assets; `/up` returns success.
- [ ] `php artisan infrastructure:check` succeeds against Supabase PostgreSQL.
- [ ] `php artisan infrastructure:check --disk=s3` writes, reads, and deletes the CV-bucket fixture.
- [ ] A direct unauthenticated request cannot retrieve a private CV object.
- [ ] Image bucket delivery works with a disposable image and its intended public URL; remove the fixture afterward.
- [ ] Dispatch `php artisan infrastructure:queue`, let the managed worker process it, and check the printed token with the same command.
- [ ] Complete actual Google login, reach the protected dashboard, sign out, and confirm dashboard access redirects to login.
- [ ] Confirm callback URLs, secure sessions, APP_DEBUG=false, and absence of secrets from application output.

Record deployment URL, date, runtime versions, queue configuration, and actual results here after verification. Automated tests with faked Google/storage services do not count as hosted verification.

## Costs and shutdown

Laravel Cloud's base plan and resource usage, Supabase database/storage allowances, and future AI usage must be budgeted independently. Review current pricing before enabling resources; the earlier $5 Cloud plan discussion is not a total deployment estimate.

To sunset later: notify demo users, capture screenshots/video, export needed database/files, stop workers, remove the app and all separately billable resources/subscriptions, review Supabase resources, and revoke dedicated OAuth/storage credentials. Merely deleting a domain or disconnecting Git does not stop every billable resource. A detailed release/sunset runbook is completed in Stage 8.
