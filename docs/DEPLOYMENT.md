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
IMAGE_FILESYSTEM_DISK=images_s3
AWS_ACCESS_KEY_ID=SET-IN-DASHBOARD
AWS_SECRET_ACCESS_KEY=SET-IN-DASHBOARD
AWS_DEFAULT_REGION=YOUR-SUPABASE-REGION
AWS_BUCKET=YOUR-PRIVATE-CV-BUCKET
AWS_IMAGE_BUCKET=YOUR-PRIVATE-IMAGE-BUCKET
AWS_ENDPOINT=https://YOUR-PROJECT.storage.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true
```

`IMAGE_FILESYSTEM_DISK=images_s3` selects the separate private hosted gallery bucket. Set `AWS_IMAGE_BUCKET` alongside the CV bucket setting; both use the server-side S3 endpoint and credentials. Public file delivery goes through publication-authorized Laravel routes.

Configure `QUEUE_CONNECTION` and any queue environment variables using the current Laravel Cloud managed-queue instructions. Do not assume the local database queue is the managed-queue configuration. Verify the configured job timeout is shorter than the queue's retry/visibility interval.

## Build and deployment

Verified runtime: PHP 8.5 and Node 26. Configured build commands:

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npx --yes pnpm@12.16.1 install --frozen-lockfile
npx --yes pnpm@12.16.1 run build
```

Confirm pnpm is available in the Cloud build environment and provision the pinned version if necessary. Use `php artisan migrate --force` as a controlled deployment command against the dedicated application's database; never run `migrate:fresh` there. Apply the platform's recommended Laravel optimization commands after environment variables are set. Ensure the queue uses the new release/configuration after deploys.

The hosted deployment completed successfully, including migrations. Push-to-deploy was disabled during setup. Keep the Cloud Git branch on main after validation/merge; deploy deliberately when automatic deployment is disabled.

## Verification record

Results below combine user-performed dashboard/terminal checks and the assistant’s explicit hosted health request.

- [x] User confirmed the styled homepage; assistant independently checked `/up`: HTTP 200.
- [x] Hosted infrastructure command reports Database connection: OK using Supabase direct PostgreSQL.
- [x] User confirmed PDF probe write/read/cleanup all OK. Probe uses a real PDF and application/pdf, matching the CV bucket restriction.
- [x] User tested the uploaded fixture’s unsigned public URL: 404 NoSuchBucket. This verifies that public download path is blocked; signed URLs intentionally behave differently.
- [x] Image verification was deferred from Stage 1 and completed in Stage 3: user confirmed hosted gallery upload/delete and PDF upload/download on 25 November 2026.
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


## Stage 3 gallery deployment (pending review)

The user created private portmyfolio-images with ten MB limit and explicit image/jpeg, image/png and image/webp MIME restrictions on 24 November 2026. This is bucket creation only; hosted app uploads/delivery remain unverified.

After Stage 3 review, before deployment, set IMAGE_FILESYSTEM_DISK=images_s3 and AWS_IMAGE_BUCKET=portmyfolio-images in Laravel Cloud. Preserve AWS_BUCKET=portmyfolio-cvs and all existing S3 credentials/endpoint/region. The new disk is independent of the CV disk. Both buckets remain private; no public policies or browser credentials are needed. images_local and the old public local disk are not durable hosted gallery storage.

Deploy main only after merge approval; push-to-deploy is now enabled. Deploy command remains php artisan migrate --force (nullable CV disk column, no destructive reset). Verify GD with WebP support and EXIF, PHP memory of at least 256 MB, and request/upload limits that admit ten MB plus multipart overhead. For the maximum CV form set max_input_vars to at least 2,000; a final form marker detects truncated submissions. Keep managed workers active for cleanup jobs.

Hosted smoke checks: save home/CV and shared identity; create/reorder/remove manual projects; upload JPEG/PNG/WebP and verify processed image plus cover/caption/alt behavior; verify a second account and signed-out visitor cannot read preview/media/PDF routes; upload one PDF and verify replacement rejection; verify cleanup completes for an unpublished draft. Do not use infrastructure:check --disk=images_s3: that probe intentionally uploads a PDF and would be rejected by the image bucket. Use the app gallery with a non-personal test image.

Publication and original-PDF public downloads are implemented in Stage 4; hosted first publication still requires genuine generation. The private bucket must stay private when published-image delivery is implemented.


## Stage 3 hosted closure — 25 November 2026

User completed local review, approved merge, and confirmed the live version including image upload/delete and PDF upload/download working. Stage 3 was merged as 7012a3f and deployed through main push-to-deploy. This supersedes the earlier pending gallery checks above. GitHub Actions remains deferred; perform local checks before merging.

## Stage 4 deployment/review (deployed; smoke checks passed)

No new resource, schema or credential configuration is needed. Keep both storage buckets private and preserve the Stage 3 disk configuration. Keep normal migration deploy command and managed cleanup workers. Public files are streamed through routes that authorize against the current publication snapshot; direct bucket access is not enabled.

Until Stage 6 provides real generation, ordinary hosted portfolios cannot pass initial publication eligibility. Local/testing fixture generation can demonstrate publication; fixture-provider success is rejected outside those environments. Do not insert fabricated production successes or add a bypass flag merely to test hosted publishing. Complete publication/file lifecycle review locally now, and verify the real hosted generated-to-published journey when generation exists.

After Stage 4 merge/deploy, verify normal login, draft editing/preview, absence of the PDF-permission checkbox, publication confirmation warning, friendly missing-public/default routes, and rejection of publication for an ungenerated portfolio. Full live publish/republish/unpublish/default/file revocation checks require genuine successful generation. Never publish personal PDFs accidentally: publishing intentionally exposes the original download as well as the web pages; unpublish cannot recall already downloaded copies.


## Stage 5 Google AI Studio setup

1. Open https://aistudio.google.com/apikey using your Google account.
2. In Dashboard → Projects, import the existing dedicated Portmyfolio Google Cloud project (the OAuth project can also hold Gemini access). Create a dedicated project if you prefer separate billing/quota. Do not reuse another application's key.
3. Open API Keys, choose Create API key, select that project and name the key Portmyfolio development. Current AI Studio creates authorization keys restricted to the Gemini API. If project/key creation is unavailable, check project permissions in Google's setup guide.
4. Copy the key directly into the ignored local .env as GEMINI_API_KEY. Set GEMINI_MODEL=gemini-3.5-flash-lite. Do not send it through chat, commit it, or prefix it with VITE_.
5. Confirm model access and quota in AI Studio. If the project requires verification or billing, complete that setup in Google; review the displayed prices before enabling paid use. Use fictional fixtures for the initial check.
6. Run php artisan config:clear, then php artisan gemini:check --no-interaction once. Expect validated fictional CV fields and token usage. This command makes one real provider request but writes no portfolio/generation record.
7. Review the extracted facts. Then perform the separate controlled scanned-CV check before claiming scanned-PDF compatibility. Routine tests use fakes.
8. Add a separate production key to Laravel Cloud only when the generation deployment is ready. Never expose keys to browser JavaScript. Worker timeout and retry visibility must be configured for the adapter's 90-second request timeout during Stage 6.

Official references checked 25 November 2026: https://aistudio.google.com/docs/api-key, https://aistudio.google.com/docs/models/gemini-3.5-flash-lite, https://aistudio.google.com/docs/document-processing?generate-content=true, https://aistudio.google.com/docs/structured-output?generate-content=true. The user verified text-based fictional-CV extraction with gemini-3.5-flash-lite. Scanned-CV quality and hosted integration remain pending.


Stage 5 promotion approved by the user on 25 November 2026 after the local input-form checklist and live fictional-CV extraction passed. Configure GEMINI_API_KEY and GEMINI_MODEL=gemini-3.5-flash-lite in Laravel Cloud before a hosted CLI probe; normal input collection makes no AI calls. No new migration is required. Verify hosted PDF/repository collection after deployment. Scanned-CV extraction remains a tracked follow-up before end-to-end generation acceptance.


## Stage 6 deployment requirements (promotion approved; hosted checks pending)

Stage 5 deployed successfully after replacing unavailable Corepack with pinned npx pnpm invocations above. Keep those build commands.

Stage 6 requires the additive generation-lease migration (`php artisan migrate --force`), server-side GEMINI_API_KEY and GEMINI_MODEL=gemini-3.5-flash-lite, a real asynchronous managed queue, and an active Laravel scheduler. The scheduler must execute schedule:run every minute (or Cloud's supported scheduler equivalent); generation:recover is registered in routes/console.php. Do not use the sync queue driver for normal generation.

Configure managed worker timeout to 120 seconds and retry/visibility strictly above 120 seconds (180 seconds recommended); provider HTTP timeout is 90 seconds and each processing lease lasts 180 seconds. Recovery must keep running when the web process scales down. Restart workers on deployment so they load current code. Review the Cloud queue/scheduler configuration before production rollout; the local database queue round-trip does not verify hosted managed-queue behavior.

Before promotion, the user reviews local generation, edits, publication and failure recovery. After deployment, verify one genuine generation → editable draft → explicit publication, plus return-to-progress, quota display and unpublish. Scanned fictional-PDF native extraction has passed locally; it does not prove every scanned CV will extract correctly.


User approved Stage 6 promotion after local end-to-end checks on 25 November 2026. Retain migration deployment, the working pinned npx pnpm commands and the queue/scheduler requirements above. A green build/deployment alone does not verify production Gemini credentials or scheduled recovery.
