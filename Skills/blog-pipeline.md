# Blog Pipeline — ClimaNova Énergie
## Daily procedure for publishing the next batch of posts from the content queue

This procedure turns rows in `scripts/content-queue.json` into published blog posts. It is meant to be run by an agent (manually or on a schedule) once a day.

---

## Steps

1. **Read the queue.** Open `scripts/content-queue.json`. Select the next N rows (default N=3) where `"status": "pending"`, ordered by `priority` (1 first), then array order.
   - If zero rows are `pending`, stop — do not error, do not loop. The queue is exhausted.

2. **Generate each article** using the `seo-article-fr` skill (`Skills/seo-article-fr.md`), with `INPUT VARIABLES` filled from the row: `Primary Keyword` = `primary_keyword`, `City` = `city` (if present, else omit city-specific phase 4 content but still include the standard Nice/Alpes-Maritimes local section as in existing posts), `Service` = `service`.
   - Match the exact HTML structure, CSS classes, and JSON-LD schema used in existing posts. Reference `blog/bruit-climatisation-que-faire/index.html` as the structural template: page `<head>` block (meta, OG, Twitter, favicon, webfont, Meta Pixel, hreflang), `@graph` JSON-LD (`BlogPosting`, `FAQPage`, `BreadcrumbList`, `Service`, `LocalBusiness`), banner section, `cn-direct-answer` / `cn-key-takeaway` / `cn-cta-inline` blocks, tables, FAQ (20 Q&As), sidebar "Vous aimerez aussi" + contact widget.
   - CTA links always point to `https://climanova-energie.fr/demande-devis/`.
   - Internal links: prefer linking to `/services/[service]/`, `/demande-devis/`, and 2–3 topically related existing posts (pick from `blog/` directory listing).

3. **Write the file.** Create `blog/<slug>/index.html` with the generated content, `<slug>` = the row's `slug`.

4. **Update `blog/index.html`.** Insert a new `w-dyn-item` card at the top of the list (most recent first), following the exact markup pattern of existing cards (image, date, read time, `h2`, excerpt, "Lire la suite" link).

5. **Update `sitemap.xml`.** Add a `<url>` entry for `https://climanova-energie.fr/blog/<slug>/` with `lastmod` = today's date (ISO `YYYY-MM-DD`), `priority` 0.8, `changefreq` monthly, placed among the other `/blog/` entries.

6. **Cross-link.** On 1–2 existing posts that are topically closest to the new one, add the new post to their sidebar "Vous aimerez aussi" list (swap out the least-related existing link if the sidebar already has 4 items) and/or add one contextual inline link where natural.

7. **Mark done.** Set `"status": "done"` and add `"published": "<today's date>"` on each row that was generated in this run, in `scripts/content-queue.json`.

8. **Commit and push.**
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
- If a generated slug happens to collide with an existing folder, append `-2` and log it — do not overwrite.
- Keep batch size modest (2–3/day is the default cadence this queue was sized for; see `scripts/content-queue.json`'s 101 rows). Do not pad with filler topics once the queue is exhausted — report completion instead.
