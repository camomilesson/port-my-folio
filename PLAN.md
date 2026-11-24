# Portmyfolio — Development Plan

Status: proposed execution sequence for the agreed MVP, 23 November 2026.

Product scope: [VISION.md](VISION.md). Agreed architecture: [DATA_MODEL.md](DATA_MODEL.md).

Work through stages in order. Each stage ends with a working demonstration, appropriate verification, updated documentation, and a review point with the user before starting the next stage. This document authorizes planning; it does not mean implementation has already started. Avoid building later-stage features early except for the explicit infrastructure checks in Stage 1.

## Working approach

- Build one Laravel application with Blade/Tailwind, Google authentication, Supabase PostgreSQL/storage, and Laravel Cloud hosting.
- Use Pest for tests and Spatie Media Library for project galleries. Treat class examples as references, including teaching simplifications, not a blueprint. Do not add relationships just to mirror the class project.
- Pin compatible framework/runtime/package versions during setup after checking current official documentation; do not assume the newest release is required by the course.
- Keep credentials out of source control; commit .env.example with descriptions and safe placeholders.
- Prefer small, coherent commits and ordinary Laravel conventions. Add abstractions for actual boundaries, especially AI providers, rather than a general CMS/agent framework.
- Use meaningful feature/integration tests for authorization, publication, quota, and job behavior. Verify layouts visually; do not duplicate trivial implementation logic in tests.
- Every normal development/test command must target a disposable local/test database. Never run migrate:fresh against the hosted application's database.
- Resolve product decisions at their listed stage before dependent implementation. Record agreed changes in the vision/model documents.

## Stage 1 — Foundation and infrastructure proof

### Work

- Confirm course-compatible Laravel/PHP versions and available local tools; scaffold the app, Blade/Tailwind assets, formatting, and basic test setup.
- Set up local PostgreSQL and local file disks. Establish migrations and configuration independent of production credentials.
- Create a relevant minimal welcome page and health check.
- Prove Laravel Cloud deployment from the repository with Supabase database connectivity.
- Prove private file upload/read/delete through the Supabase S3 endpoint, using a disposable non-personal fixture. Verify public-image delivery strategy too.
- Dispatch and complete a harmless queue job in the hosted environment; confirm supported queue driver/configuration, retries, and job timeout settings.
- Implement a minimal Google Socialite sign-in/logout and verify local and hosted callbacks, then carry it into Stage 2.
- Document setup, required service configuration, approximate resource costs, and how to shut down paid resources. Service signup, billing, secrets, and any necessary account approvals are user setup steps, not assumed completed.

### Completion criteria

- A fresh local checkout can boot and run a test without production services.
- Hosted welcome page responds; database, object storage, queue, and Google callback have each been exercised successfully.
- No secrets are committed or printed in diagnostic output.
- README contains actual commands and distinguishes local, test, and production environments.

Review: confirm the foundation and deployment fit before investing in product features. If external account setup is pending, report the precise unverified checks and continue only independent local work.

## Stage 2 — Schema, account onboarding, and ownership

### Work

- Implement five application model migrations, constraints, relationships, indexes, and factories plus Spatie’s media schema from DATA_MODEL.md, plus necessary framework tables.
- Finish Google identity handling using google_id, session management, username onboarding, and reserved-name validation.
- Add an owner dashboard listing multiple portfolios; implement basic title/slug/focus draft creation and deletion.
- Add ownership policies and properly scoped nested routes. Separate owner controllers/views under Userzone; retain distinct public rendering controllers.
- Seed realistic users, portfolios, CV evidence, projects, images, and generation states without external calls.
- Provide a documented local/testing-only demo sign-in path for assessment; it must be unavailable in production. Seed the requested admin@example.com demo identity without introducing a production admin role.

### Completion criteria

- migrate:fresh --seed works on a disposable local database and produces a useful demo.
- Two owners cannot read/edit each other's private resources, including guessed nested IDs.
- Username/slug uniqueness and deletion/default foreign-key behavior work.
- Production cannot use the assessment authentication shortcut.

Review: inspect the schema and account/dashboard behavior.

## Stage 3 — Manual draft editor and galleries

### Decisions before implementation

- Finalize home/CV JSON fields, date semantics, required fields, text limits, PDF/image limits, image processing, and allowed URL schemes.
- Resolve whether users can add a repository-backed project after generation using manual content only. No extra AI generation is implied.
- Define repository identity/role editability after generation and which source inputs become read-only.

### Work

- Build owner forms for home content and all CV entries, with validation feedback and explicit save behavior.
- Build repository-backed project forms for title, summary, role, overview, technologies, and live URL; enforce a five-project maximum.
- Show every project on the home page in chosen order. Implement ordering with buttons/select controls, not drag-and-drop.
- Build gallery uploads, captions, alt text, delete/reorder, and first-image-as-cover behavior through Spatie Media Library. Do not add a parallel ProjectImage model.
- Validate and privately store the source PDF; direct AI extraction comes later.
- Build one shared preset and owner-only preview routes using draft content.
- Establish immutable file paths and safe cleanup boundaries before introducing publishing. Use media tombstones and retained/soft-deleted Project owners so package deletion hooks cannot break published snapshots.

### Completion criteria

- Seeded/manual drafts can be edited and previewed on all three page types.
- CV repeated entries can be added, edited, reordered, and removed.
- Gallery actions and maximum-project checks work, including concurrent additions.
- Invalid inputs produce useful form feedback; uploaded originals are not publicly readable.
- Manual development tools do not create a public bypass of final generation requirements.

Review: try the complete editing experience before adding publication.

## Stage 4 — Publishing and default portfolio routing

### Decisions before implementation

- Choose username/slug change rules. Prefer stable addresses for the MVP; do not build redirect history without need.
- Confirm the no-default public response. Proposed fallback: a generic no-published-portfolio page, with no draft details exposed.

### Work

- Implement consistent, validated publication snapshots and publish/unpublish actions.
- Serve public home/CV/project pages solely from the current published snapshot.
- Implement canonical /username/slug routes and /username redirect to the default published portfolio.
- Implement explicit original-PDF download permission from the published version.
- Preserve published images and project pages when their draft counterparts are edited/deleted; clean files only after references disappear.
- Make publication/default changes transactional and invalidate relevant caches after successful commits.
- Keep unpublished/draft content inaccessible to visitors. Define the publishing eligibility check so Stage 6 can enforce successful initial generation in the finished MVP.

### Completion criteria

- Draft edits do not alter public content; Publish changes updates text, appearance, projects, and gallery together.
- A project removed from the draft remains public until republishing; its old image still loads.
- Default selection rejects another user's or unpublished portfolio. Unpublish/delete clears the default appropriately.
- Original PDFs cannot be downloaded without published permission.
- Public pages render efficiently without N+1 query behavior.

Review: demonstrate two independent portfolios, switching the default, and draft/published isolation.

## Stage 5 — Input collection and provider contracts

### Decisions before implementation

- Set required/optional portfolio-focus behavior and limits.
- Define deterministic README sufficiency checks, size limits, supported PDF limits, retention policy, and actionable failure messages.
- Select the initial Gemini model and SDK after verifying PDF, structured-output, timeout, and usage reporting support.

### Work

- Build the creation/input flow: required PDF, portfolio focus, one to five public repositories, and a required role per repository.
- Normalize/validate GitHub URLs and fetch only README plus necessary metadata through fixed GitHub API endpoints; no arbitrary URL fetching, code inspection, or private access.
- Add bounded fetches, caching, missing README feedback, and rate-limit-aware delayed retries.
- Define application-owned CV extraction and content-generation contracts with validated DTOs/schemas and deterministic fake implementations.
- Implement native PDF extraction through the initial Gemini adapter and schema validation. Keep original input/evidence separate from editable content.
- Preflight repositories before paid AI processing so invalid READMEs don't trigger avoidable charges.

### Completion criteria

- A valid fixture CV and public README can pass collection/extraction; bad PDFs, private/missing repositories, and insufficient READMEs receive clear feedback.
- HTTP/AI fakes cover routine verification; one controlled live integration check confirms actual provider compatibility.
- Rate-limited imports are delayed rather than repeatedly retried immediately.
- CV/repository text cannot override application instructions or inject rendered HTML.
- No automatic portfolio regeneration endpoints have been introduced.

Review: inspect actual extracted content and agree that evidence handling is sufficient.

## Stage 6 — Durable initial generation and quota

### Decisions before implementation

- Define bounded automatic retries, stale reservation recovery, and handling of permanently failed/partially completed attempts.
- Decide how a user resumes a failed initial attempt without enabling regeneration of a completed portfolio.
- Define cost/input ceilings and user-facing progress/error labels.

### Work

- Implement StartPortfolioGeneration with user-row locking, quota reservation, frozen inputs, and durable dispatch after transaction commit.
- Orchestrate collection/extraction, independent project drafts, and focus-aware home/CV content. Reuse validated completed checkpoints on retry.
- Assemble the initial editable draft consistently, preventing concurrent edits during generation and duplicate completion writes.
- Record provider/model/prompt version, usage, progress, and safe errors.
- Poll progress from owner-authorized endpoints; show completion and recovery states.
- Enforce three successful generations per rolling seven days, count active reservations, release permanent failures, and calculate the next slot time.
- Preserve usage on portfolio deletion while cancelling its jobs and redacting deleted source payloads.
- Require successful initial generation for normal first publication in the completed product; demo fixtures satisfy the same state invariants.

### Completion criteria

- The full input → queued generation → editable draft → publication journey works.
- Concurrent starts cannot exceed quota; deletion doesn't refund successful usage.
- Retries don't duplicate projects or reprocess completed steps unnecessarily. A worker crash cannot leave a reservation indefinitely stuck.
- Failures preserve recoverable progress; deleted targets cannot be recreated by late jobs.
- Existing portfolios remain editable at quota exhaustion.
- Completed portfolios have no user-triggered regeneration flow.

Review: try both the successful journey and deliberately injected failures.

## Stage 7 — Three presets and product polish

### Work

- Finalize Minimal, Material-inspired, and Editorial directions/names, each with fixed fonts and background styling.
- Add suggested accent swatches, hex entry, and color picker. Validate syntax only; no automatic contrast correction.
- Preview and publish appearance through the existing snapshot mechanism.
- Complete responsive layouts, keyboard-accessible controls, empty states, long-content behavior, and useful welcome-page copy.
- Add the real quota contact email and experimental-hosting wording.

### Completion criteria

- All page types work in every preset at mobile and desktop widths with realistic content and empty galleries.
- Every project appears on the homepage, in order.
- Color controls stay in sync and persist without changing fixed fonts/backgrounds.
- Owner editing controls never appear for public visitors; validation and progress states remain understandable.

Review: visual walkthrough of all three presets and the complete journey.

## Stage 8 — Release, assessment, and portfolio evidence

### Work

- Run the appropriate automated suite and build; perform an end-to-end hosted smoke check of login, generation, uploads, editing, publishing, default redirect, and unpublishing.
- Verify query efficiency, private file access, quota behavior, cleanup, resource limits, and recovery configuration in the deployed environment.
- Finish README setup/deployment instructions, ER diagram, seed/demo guidance, provider configuration, and documented deviations from assessment guidelines.
- Verify a clean local installation without live AI credentials using fixtures and the local-only demo login.
- Capture screenshots and a short demo; prepare a concise presentation emphasizing the data model, backend logic, tradeoffs, and next steps.
- Document database/storage backup and restore basics, budget monitoring, and a practical sunset checklist covering user notice, data handling, and shutting down all billable resources.

### Completion criteria

- A fresh local setup can run migrate:fresh --seed and demonstrate the product offline from external AI/Google services.
- The deployed application passes the real end-to-end smoke check and known limitations are documented.
- Repository history, documentation, screenshots, and presentation materials are ready for assessment and personal portfolio use.
- No unresolved data-exposure, ownership, publication-isolation, or quota failures remain.

Review: decide whether to open the experimental public demo and what hosting lifetime/budget to use.

## Progress

- [x] Freeze product vision.
- [x] Agree initial data architecture; remove featured-project selection.
- [x] Write staged development plan.
- [ ] Stage 1 — Foundation and infrastructure proof.
- [ ] Stage 2 — Schema, account onboarding, and ownership.
- [ ] Stage 3 — Manual draft editor and galleries.
- [ ] Stage 4 — Publishing and default portfolio routing.
- [ ] Stage 5 — Input collection and provider contracts.
- [ ] Stage 6 — Durable initial generation and quota.
- [ ] Stage 7 — Three presets and product polish.
- [ ] Stage 8 — Release, assessment, and portfolio evidence.

## Stage 1 execution notes — 23 November 2026

- Laravel 13 scaffold installed with Herd PHP 8.5; Socialite and S3 adapter installed.
- Local welcome page, protected placeholder workspace, Google callback/logout, and CLI infrastructure probes implemented.
- Dedicated service projects are not created yet; hosted checks remain pending in docs/DEPLOYMENT.md.
- No local PostgreSQL server was found. SQLite is used for initial smoke checks and isolated tests; local PostgreSQL setup remains pending.
- Stage 1 is in progress, not complete. Do not advance to Stage 2 until the review and remaining infrastructure checks are addressed.

Local verification: 13 tests / 47 assertions pass; Pint and Vite build pass; Composer manifest/lock validate; local storage round-trip and a real local database-queue probe pass; landing page visually checked in-browser. No hosted checks have been claimed complete.

## Agreed refinements — 24 November 2026

Adopt Pest, separate owner controllers/views under Userzone, and use Spatie Media Library instead of ProjectImage. Keep CvDocument and publication snapshots. These choices follow product needs rather than reproducing the class blog. Stage 1 hosted verification remains pending.

## Local PostgreSQL branch — 24 November 2026

Branch: `chore/local-postgres`, kept separate from main until review. Local PostgreSQL 17.12 installed and started through Homebrew. Dedicated portmyfolio and portmyfolio_test databases created with a non-superuser application role. The ignored local .env holds its generated password; existing SQLite data was left unchanged.

PostgreSQL is now the example/default development configuration. SQLite remains the default isolated test backend, with a separate fixed-loopback PostgreSQL test profile. Both suites pass (14 tests, 51 assertions each). Migrations, local file round-trip, and PostgreSQL-backed development queue processing pass. Supabase, real OAuth, hosted storage/queue, and Laravel Cloud checks remain pending; Stage 1 is not complete.

Subsequent significant changes use branches and focused commits. Preserve atomic commits when merging after validation; do not squash them. The cloud-deployment branch starts after the local PostgreSQL branch is reviewed/merged.
