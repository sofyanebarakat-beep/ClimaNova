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

3. **Images: generate 6 article images via the OpenAI Images API directly.** This pipeline runs in an isolated Claude Code cloud sandbox (no access to Codex, local tools, or `$CODEX_HOME`) — so image generation happens by calling OpenAI's API directly over HTTPS, authenticated with the `$OPENAI_API_KEY` environment variable configured on this routine's Environment (never hardcode the key; if the variable is unset/empty, skip image generation for this run, fall back to reusing an existing `/images/` file as in earlier posts, and note this in the commit message rather than failing the whole run). Every new blog post gets **1 featured image + 5 in-article images**. These images are part of the article deliverable, not optional decoration.
   - Generate **one featured/hero image** first. It must be a realistic, brand-appropriate 16:9 landscape image that directly supports `primary_keyword`, `service`, `city` when present, and the article angle. Avoid text inside the image, watermarks, competitor logos, unsafe work practices, or generic stock-photo staging.
   - Generate **five distinct body images** after the featured image. Each body image must illustrate a different H2/H3 section of the article, such as diagnosis, equipment, cost comparison, maintenance step, local installation context, before/after comfort, or safety/repair process.
   - Use the featured image for the banner background, OG/Twitter image, schema `BlogPosting.image`, blog index card, and the first in-post `<figure>`. Insert the five body images naturally inside the article near the matching section, not stacked together.
   - Before writing the HTML, create an image plan table for each post with: `image_role`, `target_section`, `filename_base`, `prompt_summary`, `alt_text`, and `keyword_used`. The `alt_text` must be French, descriptive, and include the primary keyword or a close semantic variant without stuffing.
   - **Calling the API** (repeat once per image, 6 times per post):
     ```bash
     curl -sS https://api.openai.com/v1/images/generations \
       -H "Authorization: Bearer $OPENAI_API_KEY" \
       -H "Content-Type: application/json" \
       -d '{"model":"gpt-image-1","prompt":"<prompt text>","size":"1536x1024","quality":"medium"}' \
       | python3 -c "import sys,json,base64; d=json.load(sys.stdin); open('/tmp/img.png','wb').write(base64.b64decode(d['data'][0]['b64_json']))"
     ```
     Then convert `/tmp/img.png` into the final WebP + JPG pair (try `python3 -c "from PIL import Image; ..."`, installing Pillow with `pip install --quiet pillow` first if it's missing; `cwebp`/`convert` are acceptable alternatives if already present in the sandbox).
   - Copy/convert each generated image into `/images/` as both WebP and JPG. Keep intermediate files out of the repo (only the final `/images/*-ai.webp`/`.jpg` pair gets committed).
   - Naming pattern:
     - Featured image: `climanova-blog-<slug>-featured-ai.webp` and `.jpg`
     - Body images: `climanova-blog-<slug>-01-ai.webp` through `climanova-blog-<slug>-05-ai.webp`, with matching `.jpg` fallbacks
   - Use WebP as the primary source and JPG as fallback everywhere:
     ```html
     <picture>
       <source srcset="/images/climanova-blog-<slug>-01-ai.webp" type="image/webp">
       <img src="/images/climanova-blog-<slug>-01-ai.jpg" loading="lazy" alt="<French alt text with article keyword or semantic variant>" width="1672" height="941">
     </picture>
     ```
   - Add or update a CSS banner class for the post so the featured image appears in the first viewport:
     ```css
     .cn-banner-blog-<short-slug> {
       background-image: url('../../images/climanova-blog-<slug>-featured-ai.webp');
       background-position: center 45%;
     }
     ```
   - Image QA: featured WebP should stay under 300KB; each body WebP should stay under 150KB. JPG fallbacks should be reasonably compressed for social crawlers. Regenerate or recompress if any image is blurry, distorted, logo-like, text-heavy, off-topic, or too similar to another image in the same article.
   - Suggested featured prompt frame:
     ```text
     Use case: photorealistic-natural
     Asset type: website blog featured and in-post image, 16:9 landscape
     Primary request: Create a realistic editorial photo for a French HVAC/plumbing/energy renovation company blog article titled "<title>".
     Scene/backdrop: <specific French home, apartment, building, or technical context tied to the keyword and city>.
     Subject: <qualified technician, homeowner, equipment, or diagnostic moment tied to the service>.
     Style: photorealistic, premium service-business editorial, natural light, believable French setting, crisp detail, no text, no logos, no watermark.
     Composition: wide horizontal 16:9, subject slightly off-center, clean negative space for a banner overlay.
     Avoid: visible brand logos, readable text, unsafe wiring, clutter, cartoon/CGI look, over-saturated color palette.
     ```
   - Suggested body-image prompt frame:
     ```text
     Use case: photorealistic-natural
     Asset type: blog in-article image, 16:9 landscape
     Primary request: Create a realistic editorial image for the section "<H2/H3 section title>" in an article targeting "<primary_keyword>".
     Scene/backdrop: <specific scene that explains this section>.
     Subject: <technician, equipment, homeowner, diagnostic action, or installation detail>.
     Style: photorealistic, clean French service-business editorial, natural light, no text, no logos, no watermark.
     Composition: horizontal 16:9, clear subject, useful as an explanatory article image.
     Avoid: duplicate composition from the featured image, visible brand logos, readable text, unsafe work practices, cartoon/CGI look.
     ```

4. **Run the technical QA checklist** (above) against the drafted HTML before writing the final file. Fix anything that fails before proceeding.

5. **Write the file.** Create `blog/<slug>/index.html` with the generated content and images, `<slug>` = the row's `slug`.

6. **Update `blog/index.html`.** Insert a new `w-dyn-item` card at the top of the list (most recent first), using the post's real featured image (not a placeholder), following the exact markup pattern of existing cards.

7. **Update `sitemap.xml`.** Add a `<url>` entry for `https://climanova-energie.fr/blog/<slug>/` with `lastmod` = today's date (ISO `YYYY-MM-DD`), `priority` 0.8, `changefreq` monthly, placed among the other `/blog/` entries. Bump the `/blog/` index entry's `lastmod` too.

8. **Cross-link.** On 1–2 existing posts that are topically closest to the new one and that already have the `blog-details-sidebar-post-block` sidebar widget, add the new post to their sidebar "Vous aimerez aussi" list (swap out the least-related existing link if the sidebar already has 4 items) and/or add one contextual inline link where natural.

8b. **Recent-articles + full CTA banner (every post, no exceptions).** The new post's own "Vous aimerez aussi" / "Articles récents" sidebar list must contain **exactly 4 items** (topically relevant existing posts, most recent preferred). Immediately after that list, insert this exact CTA banner verbatim — same HTML, same copy, every single post, no bespoke variants:
    ```html
    <div class="cn-blog-cta-banner cn-blog-cta-banner--full"><h3 class="cn-blog-cta-title">Besoin d'un devis&nbsp;?</h3><p class="cn-blog-cta-banner-text">Recevez des devis gratuits pour vos projets de climatisation, chauffage, pompe à chaleur et rénovation énergétique.</p><ul class="cn-blog-cta-list"><li>Étude technique à domicile</li><li>Jusqu'à 3 devis détaillés</li><li>Installateurs qualifiés de votre secteur</li></ul><a href="/demande-devis/" class="cn-blog-cta-banner-btn">Demander des devis gratuits</a><p class="cn-blog-cta-note">Service gratuit &amp; sans engagement</p></div>
    ```
    Never use a plain `cn-blog-cta-banner` (without `--full`) anywhere in a new post — any other CTA block in the body should be this exact `--full` banner too, not a bespoke-copy variant.

9. **Mark done.** Set `"status": "done"` and add `"published": "<today's date>"` on each row generated in this run, in `scripts/content-queue.json`.

10. **Commit and push.**
    ```
    git add blog/<slug1>/ blog/<slug2>/ ... blog/index.html sitemap.xml scripts/content-queue.json
    git commit -m "Add SEO blog posts: <slug1>, <slug2>, ..."
    git push origin main
    ```
    `git push` to `origin/main` is what makes the posts live (the host deploys on push). Do not skip this step, and do not use `--force`.

---

## Guardrails

- Never regenerate or overwrite a post whose slug already exists under `blog/`.
- Never invent a `city` or `service` not present in the queue row.
- If a generated slug happens to collide with an existing folder, skip that row (leave it `pending`), log a note, and move to the next pending row instead of overwriting.
- Keep batch size modest (2–3/day is the default cadence this queue was sized for; see `scripts/content-queue.json`'s ~100 rows). Do not pad with filler topics once the queue is exhausted — report completion instead.
- Do not use Higgsfield or third-party generators for this pipeline. Use ChatGPT/image generation, then store project-bound assets in `/images/`.
- Keep image prompts brand-appropriate (professional, realistic, no text baked into images, no competitor logos) and visually consistent across posts.
