# Stage 1 — Hosted setup and verification

Status: **Stage 1 verified on 24 November 2026**, except hosted image delivery, explicitly deferred until gallery implementation. Dedicated Portmyfolio Supabase, Google OAuth, and Laravel Cloud resources are configured. The steps below remain setup/reference instructions; see the verification record for actual results.

## Resources to create

1. The Git repository already exists: https://github.com/camomilesson/port-my-folio. Secrets, dependencies, built assets, and local databases are excluded.
2. A dedicated Supabase project. Keep the application tables accessible through Laravel's server-side database connection, not an unauthenticated browser data API. Restrict/disable unneeded Data API exposure.
3. A private CV bucket and an image bucket with an explicit public delivery policy. Configure S3 credentials server-side; these are not browser API keys. This Stage 1 scaffold has one S3 disk, so verify the CV bucket first and document the image bucket setup before gallery implementation.
4. A Google OAuth client with local and hosted callback URLs. Configure the consent screen and test-user access as needed. Reusing the Google account is fine; create dedicated client credentials for this app.
5. A Laravel Cloud application/environment connected to the repository, with a managed queue. Review the resource estimate and spending controls before activating paid resources.

Use nearby regions for Laravel Cloud and Supabase. Laravel Cloud hosting does not require using its own database or file storage.

## Configuration

Set secrets through the hosting dashboard, never committed files or chat messages. Use the framework's PostgreSQL connection settings and Supabase's documented connection details. The direct connection is verified for this deployment. Session pooling is the fallback if direct IPv6 connectivity becomes unavailable. Require TLS (`DB_SSLMODE=require`) and verify the connection from the actual hosted environment.

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

Verified runtime: PHP 8.5 and Node 26. Configured build commands:

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm install -g pnpm@12.16.1
pnpm install --frozen-lockfile
pnpm run build
```

Confirm pnpm is available in the Cloud build environment and provision the pinned version if necessary. Use `php artisan migrate --force` as a controlled deployment command against the dedicated application's database; never run `migrate:fresh` there. Apply the platform's recommended Laravel optimization commands after environment variables are set. Ensure the queue uses the new release/configuration after deploys.

The hosted deployment completed successfully, including migrations. Push-to-deploy was disabled during setup. Keep the Cloud Git branch on main after validation/merge; deploy deliberately when automatic deployment is disabled.

## Verification record

Results below combine user-performed dashboard/terminal checks and the assistant’s explicit hosted health request.

- [x] User confirmed the styled homepage; assistant independently checked `/up`: HTTP 200.
- [x] Hosted infrastructure command reports Database connection: OK using Supabase direct PostgreSQL.
- [x] User confirmed PDF probe write/read/cleanup all OK. Probe uses a real PDF and application/pdf, matching the CV bucket restriction.
- [x] User tested the uploaded fixture’s unsigned public URL: 404 NoSuchBucket. This verifies that public download path is blocked; signed URLs intentionally behave differently.
- [ ] Deferred by agreement: no image bucket created yet. Verify hosted image delivery before galleries ship in Stages 3–4.
- [x] User configured Cloud’s managed worker and confirmed Queue probe completed using the printed status command.
- [x] User confirmed real hosted Google sign-in, workspace access, logout, and renewed authentication requirement.
- [x] Hosted callback registered in Google; production environment configured with APP_DEBUG=false and secure database-backed sessions. Secret values are not reproduced in this record.

Deployment: https://portmyfolio-production-ksrkiz.laravel.cloud/ · verified 24 November 2026 · Ireland · PHP 8.5 / Node 26. Supabase direct PostgreSQL with DB_SSLMODE=require; database-backed sessions/cache; private portmyfolio-cvs bucket (PDF only, 10 MB maximum). Queue setup followed Cloud managed-queue instructions: Standard/Flex, default queue, 256 MiB, 0–1 workers. Worker completion is user-reported; settings were not independently read through the Cloud API.

Remove the custom QUEUE_CONNECTION=database override in the hosted environment when using managed queues; allow Cloud to inject its managed connection. Do not change the local development queue configuration.

Managed Flex job-duration and message-delay limits must be accounted for when designing the AI pipeline and GitHub rate-limit retries in Stages 5–6; do not assume that a single hour-long delayed job or unbounded AI job is supported.

Automated fakes do not count as hosted verification. The broad final release/security and restore checks remain in Stage 8.

## Costs and shutdown

Laravel Cloud's base plan and resource usage, Supabase database/storage allowances, and future AI usage must be budgeted independently. Review current pricing before enabling resources; the earlier $5 Cloud plan discussion is not a total deployment estimate.

To sunset later: notify demo users, capture screenshots/video, export needed database/files, stop workers, remove the app and all separately billable resources/subscriptions, review Supabase resources, and revoke dedicated OAuth/storage credentials. Merely deleting a domain or disconnecting Git does not stop every billable resource. A detailed release/sunset runbook is completed in Stage 8.

## Your setup checklist — 24 November 2026

Local PostgreSQL is already configured and verified on this machine. The remaining work is dedicated hosted resources and real OAuth credentials. Reuse your existing provider accounts; create separate Portmyfolio resources inside them.

### 1. Supabase

- Create a project named Portmyfolio in a European region close to the eventual Laravel Cloud region. Save the database password in a password manager.
- From Connect, retain the session-pooler host, port, database, and username. Copy exact dashboard values rather than guessing the endpoint. These go into Laravel Cloud, not the local development database settings.
- In Storage, create `portmyfolio-cvs` as a private bucket. You may also create `portmyfolio-images` as private for now; its eventual public-delivery configuration belongs to the gallery/cloud work, not account creation.
- Under Storage S3 configuration, generate server-side S3 access credentials and save the endpoint and region. They are different from Supabase publishable/anon keys. Do not enable Supabase Auth's Google provider; Laravel Socialite handles Google sign-in.
- Keep the database password and S3 secret out of chat/Git. Do not put production DB values in the local `.env`; we will enter hosted secrets in Laravel Cloud.

### 2. Google

- In Google Cloud Console, create a dedicated Portmyfolio project (or at least a separate Web application OAuth client in an existing project).
- Configure Google Auth Platform branding/audience for Portmyfolio. If keeping the app in testing mode, add your Google account as a test user where the dashboard requires it.
- Create an OAuth client of type Web application. Add the exact local redirect URI: `http://127.0.0.1:8000/auth/google/callback`.
- Save the client ID and secret in a password manager. Enter them in the ignored local `.env` as GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET, then run `php artisan config:clear`. Do not paste them into chat.
- Once Laravel Cloud assigns a hostname, add `https://YOUR-HOST/auth/google/callback` as another authorized redirect and use that URI in the hosted environment. This is a server-side sign-in flow; no Drive/Gmail scopes are needed.

### 3. Laravel Cloud

- Create/sign into a Laravel Cloud account and connect GitHub with access to `camomilesson/port-my-folio`.
- Create a Portmyfolio application in a nearby European region. Keep the production branch on main; use the later cloud-deployment branch/environment for setup validation rather than auto-publishing unreviewed work.
- Review billing and resource estimates. We already use Supabase, so do not provision another database or object store just to satisfy a default wizard. A queue resource will be configured for the hosted checks.
- Pause before the first deliberate deployment so the environment can receive database, app key, OAuth, storage, and queue configuration. An initial automatic build may fail until that configuration is present.
- Report the app's public hostname and which resources are ready. No secrets are needed in your reply. We will wire the settings, run the hosted verification checklist above, and review the deployment branch before merging.

### Sources checked

- Supabase Laravel connection guide: https://supabase.com/docs/guides/getting-started/quickstarts/laravel
- Supabase S3 credentials: https://supabase.com/docs/guides/storage/s3/authentication
- Google sign-in configuration: https://developers.google.com/identity/openid-connect/openid-connect
- Laravel Cloud deployment: https://laravel.com/cloud/deploy-a-laravel-app
