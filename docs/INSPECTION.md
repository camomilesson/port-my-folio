# Inspecting Portmyfolio

Portmyfolio turns a PDF CV and public GitHub READMEs into a private, editable portfolio. The owner watches content appear, edits it on the page, chooses an appearance, and explicitly publishes a saved version. This guide is intended for an assessor using their own device.

## Choose how to inspect it

| Route | What you need | What you can inspect |
| --- | --- | --- |
| [Hosted application](https://portmyfolio.laravel.cloud/) | A Google account; no application keys | The complete generation/editing/publication flow while hosting is available |
| Fresh local checkout, seeded example | [README setup](../README.md#fresh-assessment-demo); no external service credentials | Home/CV/projects, inline editing, uploads, presets, publishing and defaults |
| Fresh local checkout, new generation | The same setup plus a Gemini API key supplied privately | The complete flow, with public GitHub and Gemini connectivity |

For hosted sign-in, the owner may need to add your Google account to the OAuth client's test-user list if the client is still in testing mode. If Google refuses access, ask the owner to check that configuration; use the local seeded route in the meantime. No production database, storage, app encryption key or Google client secret is needed to inspect the hosted app.

For local inspection, **Explore the local demo** signs in fictional Sam Example. This shortcut is unavailable in production. The seeded project names, text and images are explicitly fictional examples, not generated analyses of the linked repositories.

## Local generation setup

Follow the README's fresh SQLite setup first. Put the privately supplied key in the ignored `.env`:

```dotenv
GEMINI_API_KEY=YOUR-PRIVATELY-SUPPLIED-KEY
GEMINI_MODEL=gemini-3.5-flash-lite
```

Run `php artisan config:clear --no-interaction`. Keep the app server running in terminal one, then run these in terminals two and three from the same checkout:

```sh
php artisan queue:work --tries=1 --timeout=120 --no-interaction
php artisan schedule:work --no-interaction
```

Restart workers after configuration changes. Google credentials are unnecessary when using the local demo login. GitHub public README access needs no application token. The first asset build needs internet for fonts; new generation needs internet for GitHub/Gemini. The seeded editing/publication route works after installation without these services.

## Inspection flow

1. **Open the workspace.** Sign in with Google on the hosted app, or choose Explore the local demo locally. A new Google account first chooses its fixed public username. Locally, open **Backend developer** to inspect the completed seed, or **New portfolio** to exercise generation.
2. **Provide the inputs.** For a controlled generation use `resources/fixtures/demo-cv.pdf` from the repository, with `https://github.com/camomilesson/website-to-conjugate-verbs` and `https://github.com/camomilesson/hic-sunt-dracones-map`. Leave optional focus blank for a general portfolio, or provide an audience. Choose **Generate my portfolio**. There is no title or project-role form.
3. **Watch the private portfolio fill.** Expect input checking, CV extraction, individual project content, then home/CV wording and Ready. Browse CV and projects as they appear. Refresh or leave and reopen the portfolio: progress should reconstruct from saved checkpoints. A successful portfolio cannot regenerate; new inputs require a new portfolio. A temporary failure may show a delayed retry or **Retry remaining steps** and retain completed work.
4. **Edit on the page.** Once Ready, use a pencil to change name, title or introduction. Save persists; Cancel discards that field's unsaved text. Visit CV and edit an existing entry. On a project edit summary, Overview, Technical details and the comma-separated technologies. Headers and ordering have no pencils. These changes make no AI requests.
5. **Add images.** On Home choose Add photo and select a JPEG/PNG/WebP. It saves immediately. On a project choose Add images and select several files together; no second confirmation is needed. Click the left/right half of the gallery or use arrow keys to rotate. New images have no captions/descriptions and default to decorative. Limits: 10 MB per file, ten gallery images per project.
6. **Choose an appearance.** Open Appearance and try Minimal, Material and Editorial plus an accent color. It saves to the private draft. Visit Home, CV and a project to see the preset across all three page types; try a narrow browser window too.
7. **Publish explicitly.** Choose Publish portfolio and read the disclosure, check the confirmation and publish. This exposes the **original CV PDF** as well as the website. Use the fictional fixture for this check. Open View published in a private/signed-out window; Home/CV/projects should have no pencils or owner toolbar. The CV's PDF download should work.
8. **Check draft isolation.** Return to the private portfolio and edit a sentence or change appearance. Refresh the public window: it should retain the previous saved publication. Choose Publish changes and confirm; the public window should now show the new version.
9. **Check the default address.** The first publication becomes the default if none exists. With multiple published portfolios, use Portfolio settings → Make default. The bare `/your-username` address redirects to that selected portfolio; `/your-username/portfolio-slug` stays its canonical address. There is no public portfolio at a global `/user` address.
10. **Unpublish the inspection portfolio.** Use Portfolio settings → Unpublish portfolio and confirm. Public pages, image/photo routes and original-PDF downloads should stop resolving; a matching default is cleared. Existing downloaded copies cannot be revoked. Your private draft remains available.

If using the seed, begin at step 4. It already has a successful fixture generation and a source PDF. Keep its separate Published example available if you want a second public portfolio for the default check. The supporting settings forms offer additional manual controls; the intended primary flow is the portfolio page itself.

## If a check stalls

| Symptom | Check |
| --- | --- |
| Google sign-in fails | Exact callback URL, OAuth test-user access, and consistent hostname; use the local demo route if needed |
| Generation stays queued locally | Worker and scheduler running in the same checkout/database; restart the worker after changing `.env` |
| Provider configuration message | Gemini key/model in the server environment, refreshed config and restarted workers |
| GitHub/provider wait | Read the displayed retry time; leave the scheduler/worker running rather than repeatedly starting new portfolios |
| Image upload rejected | Supported format/size, free gallery spaces, PHP upload limits from the README |
| Styles missing | Run `pnpm run build`, then refresh; the first build needs font-download access |
| Public changes seem missing | Save the field, then explicitly Publish changes; draft saves alone are private |

## Technical evidence

The [ER diagram and model contracts](../DATA_MODEL.md) explain the five application models plus Spatie Media. Relational rows handle ownership/lifecycles; validated JSON holds nested CV content and publication snapshots. The [release record](RELEASE.md) lists actual checks and remaining hosted operations. Run the commands in [README verification](../README.md#verification) for automated evidence of ownership, recovery, publication isolation and concurrent changes.

Deliberate choices relative to the class blog example: Google-only identity instead of passwords; portfolio/project/CV/generation records instead of posts/categories; Spatie media instead of a separate image model; validated nested JSON instead of a separate table per CV entry; Blade with small JavaScript modules; explicit publication snapshots; no application quota. These are application design choices. No formal marking rubric was supplied, so this is not a claim that every assessment criterion is satisfied.
