# Portmyfolio — Product Vision

Status: agreed MVP direction, initially frozen on 23 November 2026; updated through Stage 5 decisions on 25 November 2026.

This document records the product we intend to build. See [DATA_MODEL.md](DATA_MODEL.md) for the agreed data architecture and [PLAN.md](PLAN.md) for implementation stages. Changes to the agreed scope should be reflected here before they become implementation work.

## Purpose

Portmyfolio turns a PDF CV and selected public GitHub repositories into an editable, hosted personal portfolio. Users can create different portfolios for different industries, roles, or job postings without starting from a blank page.

The application generates structured content once and renders it through predefined templates. Users control the final wording, project images, appearance, and publication.

The class blog is a reference for solutions, not a blueprint; some choices intentionally simplify teaching.

The project is being built for the Modern Web Application course, with Laravel backend development as its technical focus. Assessment criteria are guidelines: requirements can be omitted when they do not fit the product's actual logic. We should be able to explain those decisions rather than invent features to satisfy a checklist.

## Core journey

1. Sign in with Google and choose a username.
2. Create a portfolio with a title and user-chosen URL slug.
3. Upload a required PDF CV.
4. Optionally provide a portfolio focus, such as a target industry, role, or pasted job posting.
5. Add up to five public GitHub repository links and state the author's role for each project.
6. Generate the initial portfolio in the background.
7. Review the generated draft and edit its content through forms.
8. Add and organize project images, choose a preset, and choose an accent color.
9. Preview and publish the portfolio. The first publication becomes the default when none is selected.
10. Return to edit drafts and publish changes.

There is no extra AI context pass after generation. Users correct and expand the generated content themselves.

## Accounts and portfolios

- Google is the only planned sign-in method.
- Each account can own multiple portfolios.
- Portfolios have independent content, selected repositories, appearance, and publication state. Editing one must not silently change another.
- The first portfolio published while no default is selected becomes the account default. Other published portfolios have Make default actions in their editor and dashboard tile.
- Portfolio owners manage their own content. Public visitors can only see published content.
- An administrative area is not part of the agreed product scope.

## Inputs and generation

### CV

- A PDF CV is required for each generation.
- The initial Gemini integration processes the PDF directly and returns structured CV information.
- The application validates extracted data before using it to generate the portfolio.
- Every content field on the resulting CV page is editable through forms, including the ability to manage repeated entries such as experience and education.
- There is no CV regeneration in the MVP.
- One immutable original PDF belongs to each portfolio and cannot be replaced. It stays private while unpublished; publishing the portfolio also makes its original PDF downloadable.
- Every published portfolio includes an original-CV download, through a controlled route backed by private storage. Unpublishing disables new public downloads.

### Portfolio focus

The user may supply the audience or purpose of the portfolio before generation (up to 5,000 characters). Blank focus produces a general professional portfolio. This guides emphasis and wording across the home, CV, and project pages. It must not cause the application to invent experience, skills, or achievements.

### Projects

- Every portfolio project requires a public GitHub repository.
- Maximum five projects per portfolio.
- Repository submission includes a required free-text field describing the user's role, for example, solo developer or backend developer in a three-person team.
- The README is the substantive source of project information. Minimal repository metadata may also be fetched.
- There is no source-code or dependency-file inspection, private repository access, or project creation without a repository.
- Missing or insufficient README content should produce actionable feedback before paid content generation begins. The exact sufficiency check remains to be designed.
- Repository-wide functionality must not automatically be attributed to an individual contributor.

### Generation behavior

- Generate an initial portfolio once, then allow manual editing.
- No user-triggered regeneration of a portfolio, project, section, or CV.
- Automatic retries for technical failures are allowed and resume the same generation attempt.
- Keep the core provider agnostic, with application-owned structured contracts and validation. Gemini is the initial provider; the exact model and SDK are not yet selected.
- Treat CV and repository contents as source material, not instructions to the application.
- Do not fabricate missing facts, outcomes, metrics, or contributions.
- Retain sufficient source and generation metadata for debugging, including provider/model, prompt version, usage, and failures.
- Fetch and cache GitHub evidence. Respect rate-limit reset times and retry instructions, retaining completed work when jobs are delayed.
- Show saved generation progress and useful failure messages to the user.

## Portfolio pages

All portfolios use three predefined page types. AI supplies their content; it does not generate arbitrary page code or layouts.

### Home

- Minimal personal information: shared display name/headline and a brief introduction shaped by the portfolio focus.
- A prominent link to the detailed web CV; detailed background/contact information belongs on that CV page.
- Navigation to the CV and project pages.
- All projects, in the owner's chosen order. There is no featured-project selection.

### CV

- A readable web presentation of the user's background, including experience, education, and skills.
- Editable structured fields and entries.
- Download link to the immutable original PDF whenever the portfolio is published.

The initial content structure is documented in DATA_MODEL.md; editor validation limits are recorded in DATA_MODEL.md; Stage 5 input limits are recorded below.

### Project

1. Header: title, short summary, author's role, and technology tags supported by the supplied material.
2. Overview: what the project does, who it serves, and the problem it addresses, where supported by the README.
3. Gallery: owner-managed images; hidden when empty.
4. Links: required repository link and optional live-demo link.

There is no highlights section and no mandatory metrics, impact, challenges, or lessons section.

## Editing and images

- Owners edit content through forms, accessed from the dashboard or owner controls on a page.
- No click-to-edit document editor or drag-and-drop layout builder.
- Owners can edit generated text, manage project ordering, and remove projects. Every project appears on the homepage.
- Images are entirely user managed: upload, delete, reorder (first image is the cover), and edit captions and alt text. A decorative-image option produces empty alt text; other images require a description.
- Keep only processed, resized, metadata-free gallery images; discard original image uploads.
- AI does not select or generate images. Automatic screenshot capture is outside scope.
- Owners may add repository-backed projects manually after generation, within the five-project maximum, without AI calls. Existing repository identity stays fixed; display content and role remain editable.

## Appearance

- Three visual presets, all implemented with Blade components and Tailwind.
- Each preset supplies its own fixed typography, layout styling, and background colors.
- Minimal, Material-inspired, and Editorial are working preset directions; final names and designs remain open.
- Users choose an accent color through suggested swatches, a hex input, or a color picker.
- Accent colors are unrestricted. Do not automatically adjust backgrounds or foregrounds to compensate for a user's choice. Users can change the accent afterward.
- No font picker, light/dark toggle, custom HTML/CSS, or arbitrary layout editor.
- Preview the portfolio before publishing, including appearance changes.

## URLs and publishing

Every portfolio has a canonical URL, including the default portfolio:

```text
/username/portfolio-slug
/username/portfolio-slug/cv
/username/portfolio-slug/projects/project-slug
```

`/username` redirects to the account's default published portfolio. Changing the default does not change another portfolio's canonical address.

- Reserve application route names to prevent username conflicts.
- Draft portfolios are private to their owners.
- Publishing makes the portfolio publicly accessible.
- Save draft edits separately from the published version. Changes go live when the owner chooses Publish changes.
- Publication captures content and appearance together.
- Unpublishing/deleting the default clears the selection; no other existing portfolio is automatically substituted. The next publication becomes default if none is selected. Missing public portfolios and missing defaults show the friendly portfolio-not-found page.
- Username, portfolio slug and project slugs stay fixed; display titles remain editable.
- Publish confirms that the portfolio and original CV PDF become public. Saved draft changes go live together; unsaved form edits are excluded.
- First publication requires successful initial generation, a source PDF and at least one repository-backed project. It does not require completing optional sections, contacts, images, or extra prose.
- Custom domains per user and user subdomains are outside the MVP.

## Generation limits

- Three successfully generated portfolios per Google account in a rolling seven-day period.
- Reserve a quota slot when generation starts to prevent concurrent requests from exceeding the limit.
- Permanently failed generations release their reserved slot; automatic retries remain part of the same attempt.
- Deleting a generated portfolio does not restore its slot.
- Editing, publishing, or changing the default does not consume generation quota.
- Show when the next generation slot becomes available.
- Maximum five repository-backed projects per portfolio.
- PDF and README input limits will be defined before implementation to bound cost and processing time.

Suggested quota message:

> You've used your three portfolio generations for this week. Your next slot opens on [date]. Enjoying Portmyfolio? Email us at [project email] if you'd like to create more.

The real contact email has not been chosen. Existing portfolios remain editable when generation quota is exhausted.

## Technical direction

| Concern | Direction |
| --- | --- |
| Application | One Laravel application |
| Templates and styling | Blade and Tailwind |
| Testing | Pest |
| Project galleries | Spatie Media Library; no parallel ProjectImage model |
| Small interactive controls | Alpine.js where useful |
| Production database | Supabase PostgreSQL, accessed by Laravel through normal database connections and Eloquent |
| Authentication | Google via Laravel Socialite, with Laravel sessions |
| Production file storage | Supabase Storage through Laravel's S3-compatible filesystem integration |
| Background processing | Laravel jobs on Laravel Cloud's managed queue |
| Generation progress | Poll saved status; no WebSocket requirement |
| Cache and sessions | Database-backed initially |
| AI integration | Gemini behind application-owned provider contracts |
| Local environment | Herd, local file storage, local database, and a queue worker |
| Application hosting | Laravel Cloud |

Supabase provides database and storage services; Laravel owns application logic, authentication sessions, and authorization. Supabase Auth and direct browser-to-database access are not planned.

No separate frontend deployment, Python service, vector database, Redis requirement, or general-purpose agent orchestration framework is currently needed.

Framework/runtime versions, connection configuration and local PostgreSQL are established in README.md. Stage 5 uses Laravel HTTP for the Gemini integration. Keep production services geographically close where possible.

## Delivery and assessment

The intended sequence is:

1. Build manual content management, accounts, galleries, one preset, preview, and publishing.
2. Add PDF processing and GitHub README imports.
3. Add initial AI generation and reliable background processing.
4. Complete the three presets, customization, and responsive polish.

The manual-first sequence is an implementation milestone, not a promise of a separate public manual-only product. The finished MVP still requires a CV and repository-backed projects.

Provide realistic seed data, reproducible local setup, and a usable demonstration without live AI calls. Document any local assessment sign-in mechanism required to demonstrate a Google-only production application. Use migrations, factories, appropriate validation and authorization, efficient queries, named routes, clear documentation, and meaningful atomic commits.

The agreed data architecture is documented in DATA_MODEL.md. Model counts, relationships, and CRUD surfaces follow actual product needs.

## Hosting lifetime

Laravel Cloud is the chosen application host. Public hosting does not need to continue indefinitely for the project to remain part of the author's personal portfolio.

Before a possible sunset, capture screenshots, record a demo, and document the architecture and decisions. Retain reproducible local setup and seeded data in the repository.

Sunsetting the application also takes users' hosted portfolios offline. Present an initial course/demo launch as experimental hosting rather than promise permanent availability. A detailed shutdown/export process is not yet defined.

## Decisions still open

- Exact preset designs and names.
- Portfolio focus: resolved below in Stage 5.
- Input limits: resolved below in Stage 5.
- Stable addresses and default behavior: resolved in Stage 4.
- Retention policy for extraction and generation evidence.
- Handling partially completed generation and permanently failed project processing.
- Local assessment login, infrastructure, and fixtures: implemented in Stages 1–3.
- Production domain, project contact email, and operational budget.

These are follow-up product or technical decisions, not authorization to expand the MVP with regeneration, additional input sources, or a layout editor.


## Stage 3 decisions agreed — 24 November 2026

Home/CV share identity within each portfolio; home introduction and CV summary are independent. Dates support year-only or month/year, unknown dates, and explicit current entries. Contacts are optional with labeled HTTP(S) links and separately validated email/phone. Manual project additions are allowed without AI. Original generation evidence stays separate from editable display content; editing focus does not rewrite text. The Stage 3 optional PDF-permission design is superseded by the Stage 4 decision below. Exact editor limits are now recorded in DATA_MODEL.md; outstanding questions above concerning these choices are superseded by this agreement.


## Stage 4 decisions frozen — 25 November 2026

The intended entry remains one source PDF plus repository links/roles, followed by generation and straightforward publication. Do not add manual content-completion hurdles. Portfolio focus remains a Stage 5 input decision. Local fixtures can demonstrate publication before provider integration; there is no production manual-generation bypass. PDFs are always downloadable while the portfolio is published, regardless of any legacy allow_original_download flag. Remove that checkbox and omit the flag from new content/snapshots; do not alter the immutable PDF. Publishing confirmation explicitly warns that the original may contain details edited out of the web CV. Storage buckets remain private. No revision history, slug redirects, or automatic publication on save.


## Stage 5 decisions — 25 November 2026

Focus is optional, up to 5,000 characters; blank means a general professional portfolio. One input screen collects title/address, one required immutable PDF (10 MB, at most 20 pages), and one to five public GitHub repository URLs with a required author role. Scanned PDFs are supported through Gemini; encrypted or malformed PDFs are rejected. No manually authored CV/project prose is required before generation.

Fetch the full default README through GitHub API endpoints. It must contain more than 200 meaningful characters (excluding markup, badges, and link-only content), and be strictly smaller than 100 KB (100,000 bytes). Missing/insufficient evidence gets actionable feedback before paid processing. Fetches cache for one hour; rate limits preserve completed work and expose the reset/retry time.

Retain source PDFs, extracted facts, README snapshots and generation inputs/metadata while the portfolio exists. Delete source/personal payloads and files on portfolio deletion, retaining minimal quota history. Shared public GitHub cache entries expire after one hour.

Gemini uses Laravel's HTTP client behind application-owned contracts, with deterministic test fakes. The initial model is gemini-3.5-flash-lite. The user verified native PDF extraction, structured output, application validation and usage reporting with the bundled fictional CV. Scanned-CV extraction quality remains unverified. No provider credential or live AI call is required for automated tests. PDF structural validation uses the user-approved smalot/pdfparser dependency.
