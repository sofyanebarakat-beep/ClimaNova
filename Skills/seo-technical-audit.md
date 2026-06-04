# Technical SEO Audit Skill — ClimaNova Énergie
## Full site audit: Core Web Vitals, crawlability, indexation, structured data

**Website:** https://climanova-energie.fr
**CTA:** https://climanova-energie.fr/demande-devis/

---

## INPUT

```
Page / Section to audit : [URL or "full site"]
Priority                : [speed / indexation / schema / all]
```

---

## PROMPT

You are a Technical SEO expert. Perform a complete technical SEO audit of
https://climanova-energie.fr (or [specific URL]), identify all issues ranked
by SEO impact, and provide exact fixes. Output in French.

---

### PHASE 1 — CRAWLABILITY & INDEXATION

Check and fix:

#### robots.txt audit
Current file analysis:
- Is Googlebot allowed to crawl all important pages?
- Are any important directories blocked?
- Is the sitemap URL declared?

Recommended robots.txt for ClimaNova:
```
User-agent: *
Allow: /

Sitemap: https://climanova-energie.fr/sitemap.xml
```

#### XML Sitemap audit
Generate complete sitemap covering:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <!-- Core pages -->
  <url><loc>https://climanova-energie.fr/</loc><priority>1.0</priority><changefreq>weekly</changefreq></url>
  <url><loc>https://climanova-energie.fr/services/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/services/climatisation/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/services/chauffage/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/services/electricite/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/services/plomberie/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/services/renovation-energetique/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/services/entretien/</loc><priority>0.9</priority></url>
  <url><loc>https://climanova-energie.fr/demande-devis/</loc><priority>0.95</priority></url>
  <url><loc>https://climanova-energie.fr/about-us/</loc><priority>0.7</priority></url>
  <url><loc>https://climanova-energie.fr/blog/</loc><priority>0.8</priority></url>
  <!-- Blog articles — add each /blog/[slug]/ -->
</urlset>
```

#### Canonical tags audit
Check every page for:
- [ ] Self-referencing canonical present
- [ ] No conflicting canonicals
- [ ] Paginated pages handled correctly
- [ ] www vs non-www consistent

---

### PHASE 2 — META TAGS AUDIT

For every page on the site, audit and rewrite:

**Template:**
| Page | Current Title | Optimized Title (≤60) | Current Description | Optimized Description (≤155) |
|---|---|---|---|---|
| Homepage | — | ClimaNova Énergie \| Clim, Chauffage & Plomberie à Nice | — | Expert RGE pour climatisation, chauffage, plomberie et électricité. Devis gratuit 7j/7. Intervention rapide en France. |
| /services/climatisation/ | — | Climatisation à Nice — Installation RGE \| ClimaNova | — | Installation et entretien de climatisation à Nice. Certifié RGE, devis gratuit. Pompe à chaleur, split, réversible. |
| /demande-devis/ | — | Devis Gratuit Climatisation & Énergie \| ClimaNova | — | Demandez un devis gratuit pour climatisation, chauffage ou plomberie. Réponse sous 24h. Sans engagement. |

Generate optimized title + description for ALL pages.

---

### PHASE 3 — HEADING HIERARCHY AUDIT

For each page, verify:
- [ ] Exactly ONE H1 per page
- [ ] H1 contains primary keyword
- [ ] H2s follow logical topic hierarchy
- [ ] H3s are sub-topics of H2s
- [ ] No heading skips (H1 → H3 without H2)
- [ ] No decorative headings (headings used for styling only)

Common issues to fix:
- Multiple H1s on same page
- H1 missing primary keyword
- Navigation links wrapped in heading tags

---

### PHASE 4 — CORE WEB VITALS

Target scores (Google's thresholds):
| Metric | Target | Good | Needs Work | Poor |
|---|---|---|---|---|
| LCP (Largest Contentful Paint) | < 1.8s | < 2.5s | 2.5–4s | > 4s |
| INP (Interaction to Next Paint) | < 150ms | < 200ms | 200–500ms | > 500ms |
| CLS (Cumulative Layout Shift) | < 0.05 | < 0.1 | 0.1–0.25 | > 0.25 |
| TTFB (Time to First Byte) | < 600ms | < 800ms | — | > 1800ms |

**Fixes for ClimaNova site:**

#### LCP Improvements
- [ ] Add `<link rel="preload">` for banner images (.webp)
- [ ] Hero image has explicit width + height attributes
- [ ] Use next-gen formats: WebP for all images
- [ ] Serve images from CDN
- [ ] Font display: swap for Google Fonts

#### CLS Improvements
- [ ] All images have explicit width + height
- [ ] No ads or embeds without reserved space
- [ ] Fonts loaded with font-display: swap
- [ ] No dynamic content inserted above existing content

#### JavaScript Performance
- [ ] Defer non-critical JS
- [ ] Remove unused Webflow JS if possible
- [ ] Minimize jQuery usage
- [ ] Lazy-load below-fold content

---

### PHASE 5 — IMAGE OPTIMIZATION

Audit all images on the site:

| Image | Current Format | Recommended | Alt Text Present | Alt Text Quality |
|---|---|---|---|---|
| Hero banner | .jpg | .webp | Y/N | Good / Missing keyword / Too long |
| Service images | .jpg | .webp | Y/N | — |
| Logo | .svg | Keep SVG | Y/N | — |

**Image SEO rules:**
- [ ] All images have descriptive alt text with keywords
- [ ] File names are descriptive (not "image-001.jpg")
- [ ] Images compressed (< 100KB for decorative, < 300KB for hero)
- [ ] WebP format used for all photos
- [ ] SVG used for logos and icons
- [ ] Lazy loading on below-fold images
- [ ] Explicit width + height to prevent CLS

---

### PHASE 6 — INTERNAL LINKING AUDIT

Map the internal link structure:

**Pages with too few internal links (< 3 incoming):**
Identify orphan pages that need more links.

**Over-linked anchor text:**
Identify if any generic anchor ("cliquez ici", "en savoir plus") is overused.

**Recommended internal link improvements:**
| From Page | To Page | Suggested Anchor | Why |
|---|---|---|---|
| Homepage | /demande-devis/ | "Demander un devis gratuit" | Conversion priority |
| All blog posts | /services/[relevant]/ | [service keyword] | Topic relevance |
| All service pages | /demande-devis/ | "Obtenir un devis" | Conversion |

---

### PHASE 7 — STRUCTURED DATA AUDIT

Check all schema markup on the site:

**Required schema per page type:**
| Page Type | Required Schema |
|---|---|
| Homepage | Organization + WebSite + LocalBusiness |
| Service pages | Service + FAQPage + BreadcrumbList |
| Blog articles | Article + FAQPage + BreadcrumbList |
| Contact/Devis | LocalBusiness + ContactPage |

**Validate using:**
- Google Rich Results Test
- Schema.org validator
- Bing Markup Validator

---

### PHASE 8 — MOBILE SEO AUDIT

Check:
- [ ] Viewport meta tag present on all pages
- [ ] Touch targets ≥ 44×44px
- [ ] Font size ≥ 16px for body text
- [ ] No horizontal scrolling
- [ ] Tap targets not too close together
- [ ] Content not wider than screen

---

### PHASE 9 — HTTPS & SECURITY

- [ ] All pages served over HTTPS
- [ ] HTTP → HTTPS redirect in place
- [ ] No mixed content (HTTP resources on HTTPS pages)
- [ ] HSTS header present
- [ ] Security headers: X-Frame-Options, CSP, X-Content-Type-Options

---

### PHASE 10 — ACTION PRIORITY MATRIX

| Issue | Impact | Effort | Priority |
|---|---|---|---|
| Missing meta descriptions | High | Low | 🔴 Do now |
| Missing alt text on images | High | Low | 🔴 Do now |
| LCP > 2.5s | High | Medium | 🔴 Do now |
| Missing FAQPage schema | High | Low | 🔴 Do now |
| Orphan pages | Medium | Low | 🟡 This month |
| CLS issues | Medium | Medium | 🟡 This month |
| Internal linking gaps | Medium | Low | 🟡 This month |
| Sitemap not submitted | High | Low | 🔴 Do now |

---

## OUTPUT ORDER

1. Crawlability Issues + Fixes
2. Optimized robots.txt
3. Complete XML Sitemap
4. Meta Tags Table (all pages)
5. Heading Hierarchy Issues
6. Core Web Vitals Fixes
7. Image Optimization List
8. Internal Linking Map
9. Schema Audit + Missing Markup
10. Priority Action Matrix
