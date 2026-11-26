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

- Agreed: username and portfolio/project slugs are fixed; no redirect history.
- Agreed: missing default/public portfolio uses the friendly could-not-find page, revealing no draft details.

### Work

- Implement consistent, validated publication snapshots and publish/unpublish actions.
- Serve public home/CV/project pages solely from the current published snapshot.
- Implement canonical /username/slug routes and /username redirect to the default published portfolio; automatically select the first publication when no default exists, with Make default in other published tiles/editors.
- Always expose the original PDF through a publication-authorized download route; remove the optional-download checkbox.
- Preserve published images and project pages when their draft counterparts are edited/deleted; clean files only after references disappear.
- Make publication/default changes transactional and invalidate relevant caches after successful commits.
- Keep unpublished/draft content inaccessible to visitors. Define the publishing eligibility check so Stage 6 can enforce successful initial generation in the finished MVP.

### Completion criteria

- Draft edits do not alter public content; Publish changes updates text, appearance, projects, and gallery together.
- A project removed from the draft remains public until republishing; its old image still loads.
- Default selection rejects another user's or unpublished portfolio. Unpublish/delete clears the default appropriately.
- Original PDFs are publicly downloadable only while referenced by a currently published portfolio.
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

## Stage 6 — Durable initial generation and recovery

### Decisions before implementation

- Define bounded automatic retries, stale reservation recovery, and handling of permanently failed/partially completed attempts.
- Decide how a user resumes a failed initial attempt without enabling regeneration of a completed portfolio.
- Define cost/input ceilings and user-facing progress/error labels.

### Work

- Implement StartPortfolioGeneration with user/portfolio-row locking, frozen inputs, and durable dispatch after transaction commit.
- Orchestrate collection/extraction, independent project drafts, and focus-aware home/CV content. Reuse validated completed checkpoints on retry.
- Assemble the initial editable draft consistently, preventing concurrent edits during generation and duplicate completion writes.
- Record provider/model/prompt version, usage, progress, and safe errors.
- Poll progress from owner-authorized endpoints; show completion and recovery states.
- Preserve redacted processing history on portfolio deletion while cancelling its jobs and redacting deleted source payloads.
- Require successful initial generation for normal first publication in the completed product; demo fixtures satisfy the same state invariants.

### Completion criteria

- The full input → queued generation → editable draft → publication journey works.
- Concurrent starts for the same portfolio cannot create duplicate attempts.
- Retries don't duplicate projects or reprocess completed steps unnecessarily. A worker crash cannot leave a reservation indefinitely stuck.
- Failures preserve recoverable progress; deleted targets cannot be recreated by late jobs.
- Completed portfolios have no user-triggered regeneration flow.

Review: try both the successful journey and deliberately injected failures.

## Stage 6.1 — Simple inputs and a portfolio that fills during generation

### Agreed direction

- Prioritize a convincing course demo with a preselected CV and repositories over a comprehensive portfolio-management product.
- Keep the current home and CV content/layout. Add a required Technical details section to project pages, below the functionality-focused overview.
- Reuse Stage 6's durable generation, checkpoints, quota, authorization and publication boundaries. Three-preset work waits until Stages 6.1 and 6.2 have been reviewed.

### Work

- Replace the main creation journey with one minimal PDF/repository input screen and a Generate action. Suggest/create the portfolio title and address automatically; keep optional focus out of the primary path.
- Supply the agreed author-role context for the preselected demo repositories without extra form clutter. Do not infer personal contributions from repository ownership or functionality.
- Take the user directly into the owner-only portfolio layout with placeholders, rather than a separate editor overview or generation-status page.
- Reveal CV information after extraction and project content as individual checkpoints finish; apply final home/CV wording when profile generation completes. Use saved checkpoints and polling, not token-by-token streaming.
- Keep a small progress indicator in the portfolio. Reloading or returning must reconstruct the same partial preview from saved state. Show recoverable failure/retry controls in that experience.
- Extend the existing project provider call, schema, validation, persistence and rendering with required technical_details. Explain actual workings supported by the full README, distinct from the overview and technology tags. Use demo repositories with sufficient technical evidence; no optional/hidden-section branch and no additional AI call.
- Keep progressive content read-only until generation completes. This is a private checkpoint presentation; final draft assembly remains atomic and public pages continue reading published snapshots only.

### Completion criteria

- The selected demo inputs lead straight to an initially empty portfolio that visibly fills as generation advances.
- CV extraction, completed projects, and final profile wording appear in the correct places without inventing placeholder facts.
- Every generated demo project has a substantive Technical details section grounded in its README.
- Refresh/revisit preserves progress; existing retry, quota and draft/public isolation guarantees remain intact.
- Run affected regression tests and visually review the chosen demo journey at desktop and mobile widths.

Review: user walkthrough of the minimal input screen and progressively filled portfolio before starting inline editing.

## Stage 6.2 — Pencil editing and publication on the portfolio

### Scope

- Let the owner refine existing content on the rendered portfolio itself. Avoid a general document editor or a comprehensive set of content-management controls.
- Preserve the current home/CV structure and Stage 6.1's required project Technical details section.
- Adding, deleting and reordering structured CV entries or projects is outside this pass; existing supporting screens can remain during the transition.

### Work

- Add owner-only pencil buttons beside editable text or grouped sections across home, CV and project pages, including Technical details.
- Open a suitable input, textarea or compact section editor in place, with explicit Save/Cancel and validation feedback. Reuse existing save actions and validation where possible.
- Enable editing after successful generation; keep inputs locked while generation is active so checkpoint updates cannot overwrite edits.
- Update the rendered private draft after saving without navigating to a separate editor page. Preserve shared name/headline consistency between home and CV.
- Present a clear Publish / Publish changes action within the portfolio experience, retaining the original-PDF publication warning and explicit snapshot semantics.
- Simplify owner navigation and move separate editor screens out of the primary journey after their inline replacements work. Keep workspace access for selecting portfolios.

### Completion criteria

- The demo journey is inputs → progressively generated portfolio → pencil refinements → explicit publication.
- Owners can edit the agreed existing fields, save/cancel on the same screen and receive useful validation feedback; no owner controls appear publicly.
- Saved draft edits remain private until Publish changes; public pages, default routing and original-PDF access continue to work.
- Complete keyboard/focus and responsive checks for inline controls, affected regression tests and the selected end-to-end demo walkthrough.

Review: user approves the complete demo flow before resuming Stage 7's visual presets and broader polish.

## Stage 7 — Three presets and product polish

Starts after Stages 6.1 and 6.2 are reviewed; apply presets to the redesigned portfolio experience.

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
- [x] Stage 1 — Foundation and infrastructure proof (completed 24 November 2026; hosted image delivery explicitly deferred).
- [x] Stage 2 — Schema, account onboarding, and ownership (reviewed, merged, and verified live).
- [x] Stage 3 — Manual draft editor and galleries (local review, merge, and hosted image/PDF checks complete).
- [x] Stage 4 — Publishing and default portfolio routing (local lifecycle review and hosted smoke checks passed; full hosted generation-to-publication check deferred to Stage 6).
- [x] Stage 5 — Input collection and provider contracts (deployed and verified).
- [x] Stage 6 — Durable initial generation and quota (local walkthrough and hosted generation/publication verified by user).
- [x] Stage 6.1 — Simple inputs and a portfolio that fills during generation (user reviewed locally, approved promotion and confirmed it works live).
- [x] Stage 6.2 — Pencil editing and publication on the portfolio (user reviewed and approved promotion; hosted checks pending).
- [ ] Stage 7 — Three presets and product polish.
- [ ] Stage 8 — Release, assessment, and portfolio evidence.

## Stage 1 execution notes — 23 November 2026 (historical; superseded below)

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

## Stage 1 completion — 24 November 2026

Foundation and infrastructure proof are complete. The historical pending notes above record earlier states, not outstanding blockers.

- Local PostgreSQL 17.12 and isolated development/test databases verified; chore/local-postgres merged into main with its atomic commits preserved.
- Pest suite passes on SQLite and PostgreSQL after merge: 15 tests / 56 assertions on each. Composer validation passes.
- Laravel Cloud production deployed with PHP 8.5 / Node 26 in Ireland, using Supabase's direct PostgreSQL connection with TLS. User confirmed styled homepage, real Google login/logout, and protected dashboard access. Hosted /up independently returns HTTP 200.
- CV bucket is private, PDF-only, maximum 10 MB. User confirmed the known fixture's public download URL returns 404 NoSuchBucket; this is an anonymous public-path check, not a comprehensive storage policy audit.
- PDF storage probe successfully writes, reads, and deletes its fixture in the hosted bucket. The text-fixture mismatch was fixed and regression-tested on fix/pdf-storage-probe, validated in Cloud, then merged into main.
- User configured the hosted managed worker and confirmed Queue probe completed. Local database queues remain separate from Cloud's managed queue configuration.
- Image bucket setup and public-image delivery checks are deliberately deferred to Stages 3–4, before galleries ship.
- Branch workflow is active: significant changes on branches, atomic type/emoji commits, validation before merge. Production uses main; push-to-deploy was disabled during setup, so release changes require a deliberate deploy.

Next review boundary: begin Stage 2 on a new feature branch when requested. No Stage 2 implementation is included in this closure.


## Stage 2 implementation — 24 November 2026 (historical; closure below)

Branch: `feat/portfolio-models`. Five application models/migrations, relationship constraints, factories, and offline fixtures are implemented. Username onboarding normalizes input and enforces reserved names and uniqueness. The owner workspace lists multiple portfolios and supports private title/slug/focus draft creation, viewing, and deletion. Ownership policies deny foreign resources with 404; future nested editor routes remain in Stage 3.

Draft deletion preserves generation usage history while redacting input/progress payloads, clears foreign-key pointers, and queues file cleanup after commit. Published and active-generation records are intentionally rejected by this draft-only action; publication-aware deletion follows in Stages 4–6. Project soft deletion preserves gallery media for future snapshots.

Local migrations and seeding completed without resetting existing development accounts. Local-only demo login, dashboard layout, and private draft creation were checked in-browser. Full suite: 70 tests / 273 assertions on both SQLite and PostgreSQL; production frontend build passes. Tests cover identity onboarding, owner isolation, constraints, deletion/file cleanup, and production rejection of the demo login. No hosted Stage 2 deployment is claimed. Stage 2 stays unchecked until user review; do not start Stage 3 automatically.


## Stage 2 closure and subsequent fix — 24 November 2026

User approved Stage 2, it was merged with atomic commits preserved, and the user reported all hosted smoke checks passing. Push-to-deploy on main is now enabled by agreement. The later portfolio-not-found change was also merged and user-verified live: signed-out owner overview requests show a 404 with a sign-in suggestion instead of redirecting immediately to Google. These updates supersede the earlier awaiting-review notes.

## Stage 3 agreed decisions and implementation — 24 November 2026 (historical; closure below)

Branch: `codex/stage-3-editor`. Home personal information is name/headline/introduction with a prominent web-CV link; all projects remain visible. CV owns detailed background and contacts. Shared identity stays synchronized within the portfolio; home introduction and CV summary remain separate. Dates permit year or year-month, unknown dates, and explicit current entries. Generous bounded text lengths and repeated-entry limits are recorded in DATA_MODEL.md.

JPEG/PNG/WebP uploads: ten images per project, ten MB each, 12,000 pixels per side and 24 megapixels; no animation. Decode/reorient/resize within 2,400 pixels and keep only the metadata-free WebP. Decorative checkbox means empty alt text; otherwise require a description. First active image is cover. Processed galleries use private storage and authorized delivery. User confirmed creation of the separate private portmyfolio-images bucket; Cloud gallery configuration and live upload/delivery checks are still pending.

One immutable source PDF per portfolio, ten MB, private by default, with no replacement. Public-download preference is part of draft CV content and only becomes effective through later publication snapshots. Manual repository-backed project additions are allowed within the five-project cap, with no AI calls; existing repository identity stays fixed and generation evidence is retained separately. Contacts use optional email/phone plus labeled HTTP(S) links.

Implemented owner forms, nested authorization, CV repeaters/order controls, project CRUD/order, gallery upload/metadata/order/tombstones, private PDF upload/download, and a shared minimal home/CV/project preview. No publishing or provider integration is added. Mutations lock owner/portfolio and reject active generation; real PostgreSQL multi-process tests cover simultaneous additions at both caps. Cleanup retries preserve records on storage failure and conservatively retain removed media whenever a published snapshot exists; Stage 4 must implement reference-aware cleanup after publication changes.

Validation: PostgreSQL 124 tests / 534 assertions; SQLite 122 passing / 524 assertions plus two PostgreSQL-only concurrency cases skipped. Pint, Composer validation, and Vite build pass. Local additive migration applied without resetting accounts. Browser checks confirmed shared-identity saving, add/reorder/save CV entries, project creation, and home/project preview layout. Browser upload automation stalled at the file picker; user will perform the remaining browser walkthrough, including uploads, CV preview and responsive layout. Backend upload/file-error tests pass. No hosted Stage 3 deployment is claimed; do not merge/deploy or advance to Stage 4 before review.


## Stage 3 closure — 25 November 2026

User completed the manual browser checklist, approved merge, and confirmed hosted image upload/delete and PDF upload/download working. Stage 3 merged as 7012a3f; the earlier awaiting-review notes are historical. GitHub Actions was discussed and deferred by user preference; keep local validation before merges.

## Stage 4 decisions and implementation boundary — 25 November 2026

Freeze the decisions in VISION.md and DATA_MODEL.md before coding. First publication while no default exists automatically selects it; mark Default in the list and provide Make default on other published tiles and in their editors. Unpublish/delete clears that pointer; no silent substitute. Explicit Publish / Publish changes captures saved content together and shows last publication time/public URL. Confirmation warns that the portfolio and original CV PDF become public, including original details absent from the web CV.

Do not impose manual completion requirements for optional prose, contact fields, images, or CV sections. Require successful initial generation, one source PDF and at least one active repository-backed project, with internal safety/structure validation. Use successful local fixture evidence to demonstrate publication until generation is built; never add a production bypass. Keep portfolio focus optional until Stage 5 decides its input behavior. Remove the legacy allow_original_download control and omit it from new CV/snapshot data; published PDF access is unconditional on that old flag.

Proceed on codex/stage-4-publishing, validate publication/default/file-reference isolation and cleanup, then return for review before merging/deploying or starting Stage 5. User will handle browser checks.


## Stage 4 implementation — 25 November 2026 (historical; closure below)

Decisions committed first as f5137ef. Implemented consistent snapshots, publication confirmation, first-default assignment and explicit switching, unpublishing, public home/CV/project/image/PDF routes, and snapshot-only public rendering shared with private previews. All saved publication content and immutable file references are captured together under the same owner/portfolio locks as draft edits. Public file access checks the current snapshot; no storage bucket is made public. No shared public cache is introduced; responses use no-store to avoid stale access after unpublish. Original PDF download is mandatory while published; the old flag is ignored and omitted from new content/snapshots, and its editor control is removed.

Eligibility checks completed generation, source PDF and an active repository-backed project; optional sections/prose/images/contacts may be empty. Fixture-provider generation is eligible only in local/testing, never hosted production/staging. First publication without a default selects it; subsequent publication preserves an existing selection. Default switching/unpublish races are serialized. Unpublishing remains available even if generation is active. Deleting a published portfolio revokes access immediately, clears the default through its foreign key, retains redacted usage history and queues file cleanup; active-generation deletion remains a Stage 6 recovery/cancellation task.

Reference-aware cleanup checks the current snapshot again under a lock, keeps published project/image references across draft removals, and removes obsolete files/soft-deleted project owners after republish or unpublish. Public content rendering uses one snapshot-content query rather than querying draft projects/media, and public markup hides owner controls and flash messages. Source PDF and image downloads stop resolving after unpublish/delete.

Verification: PostgreSQL 146 tests / 699 assertions; SQLite 142 passing / 676 assertions plus four PostgreSQL-specific concurrency cases skipped. Pint, Composer manifest validation, Blade compilation and Vite build pass. Tests exercise generation eligibility, confirmation, default selection, simultaneous publication/default-unpublish requests, snapshot isolation, safe/escaped output, anonymous file access and revocation, cleanup retries, reference preservation, and query efficiency. No new dependencies or migrations. User handles browser review; no hosted Stage 4 check or deployment is claimed. Keep this stage unchecked until review, merge and appropriate hosted verification; do not advance to Stage 5 automatically.


## Stage 4 closure and Stage 5 start — 25 November 2026

User approved local publication lifecycle checks; staging was promoted to main as 351f35b, and hosted smoke checks passed. Earlier awaiting-review notes are historical. Full hosted generation-to-publication verification remains pending real generation in Stage 6. Cloud build commands now use corepack pnpm, avoiding global executable conflicts.

Stage 5 is in progress on codex/stage-5-inputs. Input and evidence decisions are frozen in VISION.md. Implement collection/preflight and validated provider boundaries; keep the generation button/pipeline/quota for Stage 6. Do not mark Stage 5 complete before the user reviews inputs/extracted evidence and the controlled live integration check passes.


## Stage 5 implementation review — 25 November 2026

Implemented one-screen input collection, strict PDF page/size validation with approved smalot/pdfparser 2.13.1, normalized public GitHub README preflight and one-hour evidence cache. Streams are bounded below 100,000 bytes even without a size header; no redirects/arbitrary URLs or repository code inspection. GitHub rate limits return a retry time and suppress outbound requests until reset; durable automatic delayed retries will be wired by Stage 6. Successful evidence remains cached when another input fails. Collection creates PDF/project evidence atomically without AI requests, publication, or quota history.

Defined application-owned provider/result/error contracts, strict CV/portfolio validation, deterministic fake and Gemini REST adapter with native PDF/JSON Schema support. Added gemini:check for one explicit fictional-CV extraction. Provider model/key are server-side configuration; no generation web endpoint is added. Extraction persistence, retries/progress and assembled draft completion remain Stage 6 work.

Verification: PostgreSQL 185 tests / 805 assertions; SQLite 181 passing / 780 assertions with four PostgreSQL-only cases skipped. Pint, Composer validation, frontend build and diff checks passed. Browser review confirmed rendered creation form and add/remove repository controls; no local review portfolio was created by the assistant. A subsequent rollback-cleanup refinement passed affected input tests. User browser walkthrough and controlled live Gemini/text/scanned fixture checks remain pending AI Studio setup. No merge/deployment; Stage 5 remains unchecked.


## Stage 5 promotion approval — 25 November 2026

User confirmed the local input-form checklist passed and explicitly requested promotion. The live gemini:check fixture extraction passed with gemini-3.5-flash-lite (666 prompt tokens, 327 output tokens, 993 total); default model configuration and examples now match that verified choice. Scanned-CV extraction is not verified and remains a follow-up before end-to-end generation acceptance. Hosted collection smoke checks remain pending deployment. This approval supersedes the earlier awaiting-user-review status; preserve atomic feature commits during promotion.


## Stage 6 decisions agreed — 25 November 2026

Explicit Generate freezes source PDF, focus, normalized repositories/roles and evidence. Conflicting edits are blocked while active. Each technical step gets at most three automatic attempts; honor rate-limit times and retain completed checkpoints. User retry resumes the same failed initial attempt with unchanged inputs, rechecks/reserves quota, and never regenerates a successful portfolio. Changed inputs require a new portfolio.

Independent project checkpoints remain private until all work can be assembled atomically. Three successful generations in rolling seven days, plus active reservations, exhaust quota. Permanent failure releases a reservation; deleting success never refunds it. Processing leases fence duplicate/late workers, with scheduled recovery of stale work. Active deletion terminalizes work before detaching/redacting source payloads; late responses are discarded. Already-sent requests may incur costs.

Progress is saved and polled: Checking inputs, Reading CV, Writing projects (completed/total), Assembling portfolio, Ready. Scanned fictional-CV quality verification remains pending before end-to-end acceptance. Stage 6 implementation proceeds on codex/stage-6-generation; no merge/deployment is implied.


## Stage 6 implementation review — 25 November 2026

Implemented explicit input confirmation and quota reservation under a user-row lock; immutable attempt snapshots; one durable job per input-check/extraction/project/profile/assembly step; private checkpoints; three technical attempts per step; provider-aware delayed retries; lease/version fencing; scheduled recovery and a 24-hour run deadline. Failed initial attempts resume with existing checkpoints after a fresh quota check. Draft assembly is transactional. Successful attempts cannot restart. Active deletion cancels/detaches/redacts work and retains usage history without refunding successful quota.

Added owner-only progress polling, quota/next-slot display, retry and review controls. New jobs contain only IDs/version. Provider contracts now separately generate projects and home/CV, retaining author-role context without rewriting completed projects. One additive lease migration applied only to local development; no dependencies changed.

Controlled scanned-PDF check used an image-only rendering of the bundled fictional CV. An initial valid response misclassified the demo notice as headline; a general prompt clarification corrected that on recheck. Flash-Lite returned the professional title, contacts, partial dates, experience, skills and education matching the fixture (681 input / 317 output / 998 total tokens). This proves baseline image-only extraction, not universal OCR quality. User portfolios were not generated by the assistant.

Verification: PostgreSQL 202 tests / 900 assertions, including separate-process simultaneous starts competing for the last quota slot; actual database-worker round-trip; expired/deleted late-result rejection; quota rollover/deletion; bounded technical/crash retries; invalid preflight before paid work; provider contracts, isolation and safe polling. Full SQLite and formatting/build checks also pass (see final check results). Browser verified confirmation/input summary/consent controls. Local migration and recovery schedule registration pass. A pair of initial parallel suite runs conflicted over shared fake-storage paths; final suites ran sequentially.

Stage 5 hosted deployment was confirmed green by the user before Stage 6 began. Stage 6 remains awaiting the user's end-to-end generation/content/publication review. Configure local worker/scheduler and hosted timeouts/visibility/scheduler per README/DEPLOYMENT before production rollout. No Stage 6 merge or deployment is authorized by this implementation milestone.


## Stage 6 promotion approval — 25 November 2026

User completed the local end-to-end walkthrough, including real generation, content review, editing, publication and default routing under the sam-example demo account, and confirmed that everything appears to work. User explicitly approved promotion to main and requested checking the resulting deployment. Earlier awaiting-review/no-promotion notes are historical. Preserve the atomic implementation commits; hosted deployment, generation and recovery configuration still require verification.

## Stage 6 closure and demo-flow revision — 25 November 2026

Stage 6 was promoted as b4b10c8. After adding the production Gemini environment variables, the user confirmed hosted generation and the preview/publication/public-PDF journey work. This closes the earlier pending functional verification; it does not independently certify every hosted recovery setting. The user then agreed to prioritize a simpler demo flow and required project Technical details, scheduled above as Stages 6.1 and 6.2 before visual-preset work. These sections supersede the earlier separate-editor main journey for the redesign; implementation has not started.


## Stage 6.1 implementation review — 25 November 2026

Implemented the primary PDF/repository form with generated title/address, collapsed optional focus and automatic generation start. It stores inputs and reserves quota transactionally; quota rejection removes the newly stored PDF and records. GitHub fetching/preflight runs in the queued Checking inputs step, before paid processing, so submission opens the private portfolio promptly. Existing explicit-title/role draft endpoints remain supporting paths.

Owner previews now project completed extraction/project/profile checkpoints into the existing home/CV/project templates without changing the saved draft before atomic assembly. Pending content uses placeholders. Browser polling replaces the portfolio content as checkpoint revisions change; reload/revisit reconstructs the saved state. Failed attempts retain completed sections and offer retry within the portfolio. Publication remains explicit and snapshot-only. Pencil editing is not included; it remains Stage 6.2.

Added required technical_details to new provider output/schema, project storage, existing editor validation, publication snapshots and rendering. Prompt version is portfolio-v3. The additive local migration is applied; no dependencies changed. Older finished portfolios and public snapshots remain readable without invented technical descriptions or regeneration. The selected demo repository roles reuse the user's previous Solo developer input; unknown roles remain unspecified.

Verification: PostgreSQL 208 tests / 965 assertions; SQLite 203 passing / 936 assertions with five PostgreSQL-only cases skipped. Pint, Composer validation, Blade compilation, Vite build and diff checks pass. Tests cover the primary queued workflow, quota rollback/file cleanup, checkpoint reveal/revisit, failure/retry, private/public isolation, required technical content and existing editor persistence/validation. Browser checked the minimal form and repository controls at desktop and mobile viewport; full live generation through the new screen remains for user review. No personal PDF or live Gemini request was sent during this implementation.

Implemented on codex/stage-6-1-demo-flow. Do not mark 6.1 complete, promote it or start 6.2 until the user reviews the selected demo journey. Restart the local queue worker before reviewing so it loads the new output schema.


## Demo simplification: quota removed — 25 November 2026

Before promoting Stage 6.1, the user explicitly removed the generation quota altogether because this is a controlled demo, not a public product. Remove account slot checks, rolling-window counting, slot UI and the temporary camomilesson exemption. Keep processing history/redaction, per-portfolio one-time generation and concurrent-start protection, bounded retries/leases and input limits. This supersedes all earlier quota/reservation requirements and quota-specific completion criteria; no generation-table migration is needed. Promotion remains paused until this change is verified.


## Stage 6.1 promotion approval — 25 November 2026

User confirmed the local flow works, removed all account generation quotas, and explicitly resumed promotion to main. Final checks: 208 PostgreSQL tests / 963 assertions; SQLite 203 passing with five PostgreSQL-only skips; frontend build, formatting and diff checks pass. Promote the reviewed branch with atomic commits preserved. Hosted migration, worker restart and the redesigned generation journey still require verification after deployment.


## Stage 6.2 inline-edit scope agreed — 25 November 2026

Owner-only pencils edit existing content on the rendered portfolio after successful generation. Home: shared name/headline and introduction. Projects: title, summary, overview, required technical details and comma-separated technology tags. CV: existing content fields, with fixed section headings; entry identities, array structure and ordering cannot be edited. Use appropriate short/paragraph/date/link/boolean inputs with in-place Save/Cancel and validation. Shared name/headline remain synchronized between home and CV. Address/repository identity and source evidence stay fixed.

Project galleries gain Add images with multiple selection, preserving the ten-image cap and existing processed-only private storage. Validate the complete batch first and append atomically; preserve per-image captions, descriptions and decorative choices, editable on the project page. No gallery reorder controls, no CV/project add/remove/reorder controls, no AI calls from editing. Public pages contain no owner controls, and saved edits stay private until Publish changes.


## Stage 6.2 implementation review — 25 November 2026

Implemented the agreed existing-field pencils on the private home, CV and project pages. Editors support in-place Save/Cancel, server validation, keyboard focus/Escape cancellation, unsaved-navigation protection and refresh without losing other open edits. Shared name/headline stay synchronized; edits merge into the latest locked draft by stable CV entry ID. CV section headings, structure/order, project role and repository/source identities are not editable here. Generation must have succeeded; queued/running/failed pages expose no pencils and reject inline writes. No AI calls are made by editing.

Project Add images supports multiple selection with individual descriptions/decorative choices and optional captions. The whole batch is validated and processed before appending under the portfolio lock, retaining the ten-image cap, private processed WebP storage and existing file handling. Storage failure rolls back every new record and cleans partial files while preserving existing gallery media. Existing image metadata can be edited without replacing bytes; saving a description explicitly marks an image non-decorative.

Workspace links open generated portfolios directly. Separate forms remain supporting settings. Publish / Publish changes keeps explicit original-PDF disclosure and snapshot publication, returns to the portfolio and offers View published. Public rendering contains no owner controls; saving never updates the published snapshot. No migrations, dependencies, provider/prompt changes or new environment variables. README includes combined upload requirements and a local server command because the installed CLI PHP defaults to 2 MB uploads; global PHP configuration was not changed.

Verification: PostgreSQL 240 tests / 1,165 assertions; SQLite 235 passing / 1,134 assertions with five PostgreSQL-only cases skipped. Pint, Composer validation, Blade compilation, Vite build and diff checks pass. Browser checked real field save/identity refresh, validation retention, independent open editors, Escape cancellation, mobile overflow, multi-select details and a successful two-image upload preserving an unsaved caption. The upload added two labelled synthetic review images to the local seeded Backend developer draft only. Its name was restored after testing, unsaved text was cancelled and nothing was published. Workspace navigation, publication disclosure/back link and public absence of pencils were verified. No personal CV or live provider call was sent.

Implemented on codex/stage-6-2-inline-editing, ready for the user's selected-demo walkthrough. Keep Stage 6.2 unchecked until review. Do not promote/push or begin Stage 7 until the user approves.


## Stage 6.2 pre-review simplifications — 25 November 2026

At the user's request, remove project-role inputs, display, validation requirements, demo defaults and role context from generation. New snapshots and frozen generation inputs omit project roles. Keep the legacy database column for compatibility, blank on new projects, with old submitted role values ignored. Prompt version is portfolio-v4; project generation describes repository functionality without assigning the owner a personal role or contribution. Restart the worker before generating with the updated prompts. This supersedes earlier role requirements.

The portfolio home has an owner-only Add photo / Change photo control and a photo on the right at desktop widths, stacked on mobile. Uploads reuse still-image validation, bounded decoding, metadata removal and WebP processing on the private image disk. Each replacement has an immutable opaque file identity; publication keeps the previous public photo until changes are published. Current and published files are protected from cleanup; replacement, republishing, unpublishing and portfolio deletion schedule reference-aware cleanup. Supporting home edits preserve the photo. Old snapshots without photos remain readable. No dependencies, migrations or new environment variables are required.

The project gallery now follows the name, summary and technology list, above Overview and Technical details. It shows one image at a time with left/right side buttons, wraparound and a counter. There is no automatic rotation. Per-image editors remain associated with their slides; upload refresh preserves the selected slide and open edits.

Verification: PostgreSQL 251 tests / 1,250 assertions; SQLite 246 passing / 1,219 assertions with five PostgreSQL-only skips. Affected endpoint/provider/storage tests, Pint, Blade compilation, Vite build and diff checks pass. Browser verified an actual photo upload/right-hand layout, mobile photo controls and overflow, unpublished photo isolation, carousel first/last wrap and keyboard activation. Added one synthetic fixture photo to the existing local seeded Backend developer draft for this check; no real personal photo was uploaded or published. Stage 6.2 still awaits the user's inspection on codex/stage-6-2-inline-editing; no promotion or Stage 7 work is authorized yet.


## Stage 6.2 gallery refinement before inspection — 26 November 2026

User requested one-step uploads with no caption/description/decorative controls. Add images now opens the native picker directly; a non-empty selection immediately submits the atomic batch, with disabled controls and a status message while processing. No Add selected images confirmation remains. New images always store caption=null, alt_text="", decorative=true; supplied metadata cannot override the batch defaults. The supporting single-image upload uses the same defaults and immediate selection behavior. Legacy metadata endpoints/data remain compatible, and existing captions are displayed without editing controls. A failed request leaves existing gallery media unchanged and restores the picker for another selection; files are reset so selecting the same files again works. Successful upload selects the first new slide and preserves unrelated open text edits.

Reviewed the read-only reference at /Users/camomilesson/Documents/Portfolio/src/components/ProjectGallery.astro. Matched its padded, bounded image area, transparent 50% left/right click targets and circular arrow controls. Kept wraparound, added ArrowLeft/ArrowRight handling and retained explicit publication. No automatic carousel movement, migrations, dependency or environment changes.

Verification: PostgreSQL 249 tests / 1,260 assertions; SQLite 244 passing / 1,229 assertions and five PostgreSQL-only skips. Metadata-required cases were superseded by default/override and file-only upload coverage. Affected tests, Pint, Blade compilation, frontend build and diff checks pass. Browser verified Add images opening the multi-picker, immediate saving of two selected fixture files with no extra confirmation, first-new-image selection, preservation/cancellation of an unrelated unsaved overview, wraparound/keyboard navigation and mobile no-overflow. Measured click regions as exactly half the image stage each. Two synthetic images were added to the local seeded Backend developer draft only; nothing was published. Browser used local demo sign-in after automatic approval review blocked an expired-session redirect to Google; no Google account access was needed. Stage 6.2 remains awaiting inspection on the feature branch, with no push/promotion or Stage 7 work.


## Stage 6.2 immediate home photo upload — 26 November 2026

At the user's request, Add photo / Change photo now directly opens the native picker and submits a non-empty selection immediately, sharing the gallery upload flow. Removed the details form and Save photo / Cancel confirmation. Upload status, duplicate-submit protection, error feedback, reset-for-retry and focus restoration apply to the photo as well. Cancelling the picker makes no request. Existing draft/publication behavior and processed private storage remain intact.

Verification: 20 affected photo/gallery tests / 171 assertions pass; Pint, JavaScript syntax check, frontend build and diff checks pass. Browser verified selecting a synthetic fixture through Change photo immediately saves a different photo version, refreshes the rendered image and restores button focus without a Save confirmation; no console errors. No backend, dependency, migration or environment change. The local seeded Backend developer photo was replaced for QA; nothing was published. Final stage inspection/promotion remains with the user.


## Stage 6.2 promotion approval — 26 November 2026

User confirmed the final no-confirmation home-photo flow and explicitly approved pushing the reviewed stage to main. This supersedes earlier awaiting-inspection/no-promotion notes. Promote all Stage 6.2 implementation/refinement commits with their history preserved. Final checks: PostgreSQL 249 tests / 1,262 assertions; SQLite 244 passing / 1,233 assertions and five PostgreSQL-only skips; frontend build, Composer validation and diff checks pass. No migration, new dependency or environment variable is required. Hosted deployment and image/photo/upload/publication smoke checks remain for verification after promotion. Stage 7 is not started by this approval.
