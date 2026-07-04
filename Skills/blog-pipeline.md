# Blog Pipeline — ClimaNova Énergie
## Daily procedure for publishing the next batch of posts from the content queue

This procedure turns rows in `scripts/content-queue.json` into published blog posts, complete with generated images. It is meant to be run by an agent (manually or on a schedule) once a day.

---

## Skills this pipeline draws on

Every post must be generated using **all five** project skills together, not `seo-article-fr.md` alone. Each contributes a different layer:

| Skill | What it adds to every post |
|---|---|
| `Skills/seo-article-fr.md` | **Base spec.** Full article structure (all H2s), 4,000–6,000 words, 20 FAQs, 7 tables, 5 core JSON-LD schemas (BlogPosting/FAQPage/BreadcrumbList/Service/LocalBusiness), CTA blocks, E-E-A-T. This is the skeleton every post starts from. |
| `Skills/aio-optimization.md` | **Layer on top.** Add a 40–60 word Featured Snippet paragraph right after the intro (direct query answer, no marketing language), a "Points clés" Key Facts box, and a `HowTo` JSON-LD block (added to the `@graph`, alongside — not instead of — the 5 core schemas) whenever the post describes a process. Tighten FAQ answers to the 40–80 word, keyword-first format this skill specifies. |
| `Skills/geo-optimization.md` | **Layer on top.** Weave in 10–15 `❓ Question / ✅ Réponse` Direct Answer Blocks through the body (the `cn-direct-answer` divs already used serve this purpose — make sure there are enough of them), state entity relationships explicitly on first mention (e.g. "MaPrimeRénov' est un dispositif de l'ANAH"), and include one Statistics block with real French sources (ADEME/INSEE/Ministère) where the topic supports it. |
| `Skills/local-seo-fr.md` | **Layer on top, content parts only.** Enrich the mandatory Nice/Alpes-Maritimes local H2 with zone-d'intervention detail (nearby communes, local building stock, local climate) as in Phase 3 of that skill, and make sure the `LocalBusiness` schema block includes `areaServed`, `openingHoursSpecification`, and `aggregateRating` fields. Skip the non-content phases of this skill (GBP fields, citation directories, review-request templates, backlink outreach) — those are business-listing tasks, not article content. |
| `Skills/seo-technical-audit.md` | **QA pass, applied after drafting, before writing the file.** Run the checklist below before finalizing. |

### Technical QA checklist (from seo-technical-audit.md, applied per post)
- [ ] Exactly one `<h1>`, containing the primary keyword
- [ ] No heading level skips (H2 → H3 only, never H1 → H3)
- [ ] Every image has descriptive alt text containing a relevant keyword (never generic "image1.jpg"-style alt text)
- [ ] Every image is served as WebP with a JPG fallback via `<picture>`/`<source>`, with explicit `width`/`height` attributes (prevents CLS)
- [ ] Hero/featured image < 300KB, inline images < 150KB each
- [ ] Canonical tag, OG tags, Twitter tags all present and self-referencing this post's URL
- [ ] At least 3 internal links out (services page + 2+ related posts) and this post added to at least 1 existing post's internal links (the cross-link step below)

---

## Steps

1. **Read the queue.** Open `scripts/content-queue.json`. Select the next N rows (default N=3) where `"status": "pending"`, ordered by `priority` (1 first), then array order.
   - If zero rows are `pending`, stop — do not error, do not loop. The queue is exhausted.

2. **Generate each article** by applying all five skills as described in the table above, using `INPUT VARIABLES` from the row: `Primary Keyword` = `primary_keyword`, `City` = `city` (if present, else omit city-specific Phase 4 content from seo-article-fr.md but still include the standard Nice/Alpes-Maritimes local section, as existing posts do), `Service` = `service`.
   - Match the exact HTML structure, CSS classes, and JSON-LD `@graph` pattern used in existing posts. Reference `blog/bruit-climatisation-que-faire/index.html` as the structural template.
   - CTA links always point to `https://climanova-energie.fr/demande-devis/`.
   - Internal links: prefer `/services/[service]/`, `/demande-devis/`, and 2–3 topically related existing posts (pick from the `blog/` directory listing).

3. **Generate 5 images per post** (1 featured + 4 in-body) using the `mcp__claude_ai_higgsfield__generate_image` tool (already connected).
   - Pick an appropriate model via `models_explore(action:'recommend')` — default to a photorealistic/marketing-style model for real-world HVAC/plumbing/electrical scenes (technician at work, equipment close-ups, French Riviera homes), since these are illustrative editorial photos, not diagrams or characters.
   - **Featured image** (1): 16:9 aspect ratio, represents the post's main topic (e.g. a technician servicing the specific equipment discussed). This becomes the hero banner image, the OG/Twitter image, and the BlogPosting schema `image`.
   - **In-body images** (4): 4:3 or 1:1 aspect ratio, placed at natural section breaks (e.g. after the intro, mid-article near a how-to/diagnostic section, near the pricing table, near the local-Nice section). Each depicts something concretely relevant to the section it sits in — not filler.
   - Write prompts in a consistent, professional, photorealistic style so posts feel visually coherent across the site (natural light, clean modern French interiors/exteriors, real tools/equipment brands where relevant, no text/logos baked into the image).
   - After generation, poll the job (`job_status` / `reveal_generation`) until the image is ready, then download it.
   - Save each image as a WebP + JPG pair with a descriptive filename: `/images/<slug>-featured.webp` + `.jpg`, and `/images/<slug>-1.webp`/`.jpg` through `-4.webp`/`.jpg`.
   - Write descriptive, keyword-relevant alt text for each (per the QA checklist above) — never reuse the same alt text twice within a post.
   - **Never regenerate images for a post that already has them** (i.e. `blog/<slug>/` already exists with its own `<slug>-featured.*` files) — this only applies to new posts from the queue.

4. **Run the technical QA checklist** (above) against the drafted HTML before writing the final file. Fix anything that fails before proceeding.

5. **Write the file.** Create `blog/<slug>/index.html` with the generated content and images, `<slug>` = the row's `slug`.

6. **Update `blog/index.html`.** Insert a new `w-dyn-item` card at the top of the list (most recent first), using the post's real featured image (not a placeholder), following the exact markup pattern of existing cards.

7. **Update `sitemap.xml`.** Add a `<url>` entry for `https://climanova-energie.fr/blog/<slug>/` with `lastmod` = today's date (ISO `YYYY-MM-DD`), `priority` 0.8, `changefreq` monthly, placed among the other `/blog/` entries. Bump the `/blog/` index entry's `lastmod` too.

8. **Cross-link.** On 1–2 existing posts that are topically closest to the new one and that already have the `blog-details-sidebar-post-block` sidebar widget, add the new post to their sidebar "Vous aimerez aussi" list (swap out the least-related existing link if the sidebar already has 4 items) and/or add one contextual inline link where natural.

9. **Mark done.** Set `"status": "done"` and add `"published": "<today's date>"` on each row generated in this run, in `scripts/content-queue.json`.

10. **Commit and push.**
    ```
    git add blog/<slug1>/ blog/<slug2>/ ... blog/index.html sitemap.xml scripts/content-queue.json
    git commit -m "Add SEO blog posts: <slug1>, <slug2>, ..."
    git push origin main
    ```
    New image files under `/images/` are already included via `blog/<slug>/`'s directory add if stored there, or add `images/<slug>-*.{webp,jpg}` explicitly if stored in the shared `/images/` directory. `git push` to `origin/main` is what makes the posts live (the host deploys on push). Do not skip this step, and do not use `--force`.

---

## Guardrails

- Never regenerate or overwrite a post whose slug already exists under `blog/`, and never regenerate images for a post that already has its own image set.
- Never invent a `city` or `service` not present in the queue row.
- If a generated slug happens to collide with an existing folder, skip that row (leave it `pending`), log a note, and move to the next pending row instead of overwriting.
- Keep batch size modest (2–3/day is the default cadence this queue was sized for; see `scripts/content-queue.json`'s ~100 rows). Do not pad with filler topics once the queue is exhausted — report completion instead.
- Keep image prompts brand-appropriate (professional, realistic, no text baked into images, no competitor logos) and visually consistent across posts.
