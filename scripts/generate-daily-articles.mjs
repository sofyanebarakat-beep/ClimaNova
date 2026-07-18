import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const args = new Set(process.argv.slice(2));
const dryRun = args.has("--dry-run");
const config = readJson("scripts/daily-article-config.json");
const historyPath = path.join(root, "scripts/daily-article-history.json");
const history = readJson("scripts/daily-article-history.json");
const githubToken = process.env.GITHUB_MODELS_TOKEN || process.env.GITHUB_TOKEN;
const model = process.env.GITHUB_MODEL || "openai/gpt-4.1";
const minimumArticleWords = positiveInteger(process.env.MIN_ARTICLE_WORDS, 900);
const maximumGenerationAttempts = positiveInteger(process.env.MAX_GENERATION_ATTEMPTS, 3);
const now = new Date();
const dateIso = new Intl.DateTimeFormat("en-CA", {
  timeZone: "Europe/Paris", year: "numeric", month: "2-digit", day: "2-digit"
}).format(now);
const dateFr = new Intl.DateTimeFormat("fr-FR", {
  timeZone: "Europe/Paris", year: "numeric", month: "long", day: "numeric"
}).format(now);

function readJson(relativePath) {
  return JSON.parse(fs.readFileSync(path.join(root, relativePath), "utf8"));
}

function positiveInteger(value, fallback) {
  const parsed = Number.parseInt(value || "", 10);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function diagnosticPayload(payload, outputText = "") {
  return JSON.stringify({
    id: payload?.id,
    model: payload?.model,
    finish_reason: payload?.choices?.[0]?.finish_reason,
    usage: payload?.usage,
    content_length: outputText.length,
    content_preview: outputText.slice(0, 1200)
  });
}

function escapeHtml(value = "") {
  return String(value).replaceAll("&", "&amp;").replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;").replaceAll('"', "&quot;");
}

function slugify(value) {
  return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase()
    .replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 80);
}

function sanitizeBodyHtml(value = "") {
  const allowed = new Set(["p", "h3", "ul", "ol", "li", "table", "thead", "tbody", "tr", "th", "td", "strong", "em", "a", "div"]);
  let html = String(value)
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, "")
    .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, "")
    .replace(/<!--([\s\S]*?)-->/g, "");
  html = html.replace(/<\/?([a-z0-9-]+)\b([^>]*)>/gi, (tag, rawName, rawAttrs) => {
    const name = rawName.toLowerCase();
    if (!allowed.has(name)) return "";
    if (tag.startsWith("</")) return `</${name}>`;
    if (name === "a") {
      const href = rawAttrs.match(/href\s*=\s*["']([^"']+)["']/i)?.[1] || "";
      const safeHref = href.startsWith("/") || href.startsWith("https://") ? href : "#";
      return `<a href="${escapeHtml(safeHref)}">`;
    }
    if (name === "div" && /class\s*=\s*["']cn-direct-answer["']/i.test(rawAttrs)) {
      return '<div class="cn-direct-answer">';
    }
    return `<${name}>`;
  });
  return html;
}

function recentFor(trackId, limit = 30) {
  return history.filter((item) => item.track === trackId).slice(-limit)
    .map((item) => `${item.primary_keyword} — ${item.title}`).join("\n");
}

function planTrack(track, index) {
  const usedToday = history.filter((item) => item.date === dateIso && item.track === track.id);
  const angleIndex = (history.filter((item) => item.track === track.id).length + index) % track.angles.length;
  return {
    track,
    angle: track.angles[angleIndex],
    skip: usedToday.length > 0,
  };
}

const plans = config.tracks.slice(0, config.articles_per_run).map(planTrack);
console.log(`Daily ClimaNova run: ${dateIso} (${plans.length} editorial tracks)`);
for (const plan of plans) {
  console.log(`${plan.skip ? "SKIP" : "PLAN"} ${plan.track.label}: ${plan.angle}`);
}
if (dryRun) process.exit(0);
if (!githubToken) throw new Error("GITHUB_MODELS_TOKEN is required (the workflow supplies github.token automatically; use --dry-run to preview without it).");

const schema = {
  type: "object",
  additionalProperties: false,
  required: ["title", "h1", "primary_keyword", "slug", "meta_description", "excerpt", "featured_answer", "key_points", "sections", "faqs"],
  properties: {
    title: { type: "string" }, h1: { type: "string" }, primary_keyword: { type: "string" },
    slug: { type: "string" }, meta_description: { type: "string" }, excerpt: { type: "string" },
    featured_answer: { type: "string" },
    key_points: { type: "array", minItems: 4, maxItems: 6, items: { type: "string" } },
    sections: {
      type: "array", minItems: 8, maxItems: 12,
      items: {
        type: "object", additionalProperties: false, required: ["heading", "body_html"],
        properties: { heading: { type: "string" }, body_html: { type: "string" } }
      }
    },
    faqs: {
      type: "array", minItems: 12, maxItems: 20,
      items: {
        type: "object", additionalProperties: false, required: ["question", "answer"],
        properties: { question: { type: "string" }, answer: { type: "string" } }
      }
    }
  }
};

async function generate(plan, attempt, previousIssue = "") {
  const { track, angle } = plan;
  const prompt = `Tu es le rédacteur SEO local senior de ${config.brand}. Rédige UN article original en français.

SUJET: ${track.label}; angle du jour: ${angle}.
ZONE: ${config.region}. Adresse de l'entreprise: ${config.address}.
SERVICE: ${config.site_url}${track.service_path}
OBJECTIF: requête locale utile, précise, sans cannibaliser les sujets récents.

Sujets récents à ne pas répéter:
${recentFor(track.id) || "Aucun"}

Exigences éditoriales issues des Skills du dépôt:
- 2 500 à 4 000 mots utiles, ton expert et accessible, informations spécifiques au bâti et au climat de Nice/Côte d'Azur.
- Un seul H1. Les sections fournies deviennent des H2; body_html peut contenir p, h3, ul, ol, table, strong et div class=\"cn-direct-answer\" uniquement.
- Intégrer au moins 4 réponses directes, 2 tableaux comparatifs, coûts présentés comme indicatifs, et des liens vers des sources publiques françaises quand une règle/aide/chiffre peut changer.
- Ne jamais inventer classement Google, avis, certification, prix garanti, délai garanti, statistique ni réglementation.
- Ajouter des liens internes HTML vers ${track.service_path}, /demande-devis/ et deux services connexes.
- Mentionner naturellement Nice, le Paillon, Cimiez, Riquier, le Port, Nice Ouest et quelques communes proches seulement lorsque pertinent.
- FAQ: 12 à 20 réponses de 40 à 80 mots, orientées intention de recherche.
- CTA sobre vers /demande-devis/. Aucun contenu dupliqué, aucun bourrage de mots-clés.
- Le slug doit être sans date, sans accents, en minuscules avec tirets.
- Le corps des sections doit dépasser ${minimumArticleWords} mots. Développe chaque H2 avec des explications concrètes, exemples locaux et étapes utiles.
${attempt > 1 ? `CORRECTION OBLIGATOIRE — tentative ${attempt}: la version précédente a échoué (${previousIssue}). Produis une version sensiblement plus développée et complète.` : ""}`;

  const response = await fetch("https://models.github.ai/inference/chat/completions", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${githubToken}`,
      "Accept": "application/vnd.github+json",
      "X-GitHub-Api-Version": "2026-03-10",
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      model,
      messages: [
        { role: "system", content: "Produis un article SEO local factuel au format JSON demandé. N'invente jamais de preuves commerciales." },
        { role: "user", content: prompt }
      ],
      response_format: {
        type: "json_schema",
        json_schema: { name: "climanova_article", strict: true, schema }
      },
      max_tokens: 16000
    })
  });
  if (!response.ok) throw new Error(`GitHub Models ${response.status}: ${(await response.text()).slice(0, 2000)}`);
  const payload = await response.json();
  const outputText = payload.choices?.[0]?.message?.content;
  if (!outputText) {
    console.error(`GitHub Models diagnostic for ${track.id}: ${diagnosticPayload(payload)}`);
    throw new Error(`No completion content returned for ${track.id}`);
  }
  try {
    return normalizeArticle(JSON.parse(outputText), track);
  } catch (error) {
    console.error(`GitHub Models diagnostic for ${track.id}, attempt ${attempt}: ${diagnosticPayload(payload, outputText)}`);
    throw error;
  }
}

function normalizeArticle(article, track) {
  article.slug = slugify(article.slug || article.primary_keyword);
  article.track = track.id;
  article.service_path = track.service_path;
  article.title = article.title.trim();
  article.h1 = article.h1.trim();
  article.meta_description = article.meta_description.trim().slice(0, 160);
  article.sections = article.sections.map((section) => ({
    heading: section.heading.replace(/<[^>]+>/g, "").trim(),
    body_html: sanitizeBodyHtml(section.body_html)
  }));
  if (!article.slug || fs.existsSync(path.join(root, "blog", article.slug))) {
    article.slug = `${slugify(article.primary_keyword)}-${dateIso}`;
  }
  const plainWords = article.sections.map((s) => s.body_html.replace(/<[^>]+>/g, " ")).join(" ").trim().split(/\s+/).length;
  if (plainWords < minimumArticleWords) throw new Error(`${article.slug}: article too short (${plainWords} words; minimum ${minimumArticleWords})`);
  if (article.sections.length < 8 || article.faqs.length < 12) throw new Error(`${article.slug}: incomplete structure`);
  return article;
}

async function generateWithRetries(plan) {
  let previousIssue = "";
  for (let attempt = 1; attempt <= maximumGenerationAttempts; attempt += 1) {
    try {
      console.log(`Generating ${plan.track.label} (attempt ${attempt}/${maximumGenerationAttempts})...`);
      return await generate(plan, attempt, previousIssue);
    } catch (error) {
      previousIssue = error instanceof Error ? error.message : String(error);
      console.error(`::warning title=${plan.track.label} attempt ${attempt} failed::${previousIssue}`);
      if (attempt < maximumGenerationAttempts) {
        const delayMs = attempt * 3000;
        console.log(`Retrying ${plan.track.label} in ${delayMs / 1000}s with expansion instructions...`);
        await new Promise((resolve) => setTimeout(resolve, delayMs));
      }
    }
  }
  throw new Error(`${plan.track.label} failed after ${maximumGenerationAttempts} attempts: ${previousIssue}`);
}

const fallbackImages = {
  climatisation: ["/images/climanova-service-02.webp", "/images/climanova-service-02.jpg"],
  chauffage: ["/images/climanova-service-01.webp", "/images/climanova-service-01.jpg"],
  electricite: ["/images/climanova-service-03.webp", "/images/climanova-service-03.jpg"],
  plomberie: ["/images/climanova-service-04.webp", "/images/climanova-service-04.jpg"],
  "renovation-energetique": ["/images/climanova-service-05.webp", "/images/climanova-service-05.jpg"],
  "entretien-depannage": ["/images/climanova-service-06.webp", "/images/climanova-service-06.jpg"],
  "climanova-energie": ["/images/climanova-blog-01.webp", "/images/climanova-blog-01.jpg"]
};

function jsonLd(article, image) {
  return JSON.stringify({ "@context": "https://schema.org", "@graph": [
    { "@type": "BlogPosting", headline: article.h1, description: article.meta_description,
      datePublished: dateIso, dateModified: dateIso, inLanguage: "fr-FR",
      mainEntityOfPage: `${config.site_url}/blog/${article.slug}/`, image: `${config.site_url}${image}`,
      author: { "@type": "Organization", name: config.brand }, publisher: { "@type": "Organization", name: config.brand } },
    { "@type": "FAQPage", mainEntity: article.faqs.map((faq) => ({ "@type": "Question", name: faq.question,
      acceptedAnswer: { "@type": "Answer", text: faq.answer } })) },
    { "@type": "BreadcrumbList", itemListElement: [
      { "@type": "ListItem", position: 1, name: "Accueil", item: `${config.site_url}/` },
      { "@type": "ListItem", position: 2, name: "Blog", item: `${config.site_url}/blog/` },
      { "@type": "ListItem", position: 3, name: article.title, item: `${config.site_url}/blog/${article.slug}/` }
    ]},
    { "@type": "LocalBusiness", name: config.brand, url: config.site_url,
      address: { "@type": "PostalAddress", streetAddress: "218 Route de Turin", postalCode: "06300", addressLocality: "Nice", addressCountry: "FR" },
      areaServed: config.nearby_cities.map((name) => ({ "@type": "City", name })) }
  ]});
}

const cta = `<div class="cn-blog-cta-banner cn-blog-cta-banner--full"><h3 class="cn-blog-cta-title">Besoin d'un devis&nbsp;?</h3><p class="cn-blog-cta-banner-text">Recevez un devis adapté à votre projet à Nice et sur la Côte d'Azur.</p><a href="/demande-devis/" class="cn-blog-cta-banner-btn">Demander un devis gratuit</a><p class="cn-blog-cta-note">Service gratuit &amp; sans engagement</p></div>`;

function render(article) {
  const [image, fallback] = fallbackImages[article.track];
  const sections = article.sections.map((section) => `<h2>${escapeHtml(section.heading)}</h2>\n${section.body_html}`).join("\n");
  const faqs = article.faqs.map((faq) => `<h3>${escapeHtml(faq.question)}</h3><p>${escapeHtml(faq.answer)}</p>`).join("\n");
  return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${escapeHtml(article.title)} | ClimaNova Énergie</title><meta name="description" content="${escapeHtml(article.meta_description)}">
<link rel="canonical" href="${config.site_url}/blog/${article.slug}/"><meta property="og:type" content="article"><meta property="og:title" content="${escapeHtml(article.title)}"><meta property="og:description" content="${escapeHtml(article.meta_description)}"><meta property="og:url" content="${config.site_url}/blog/${article.slug}/"><meta property="og:image" content="${config.site_url}${image}"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="${escapeHtml(article.title)}"><meta name="twitter:description" content="${escapeHtml(article.meta_description)}"><meta name="twitter:image" content="${config.site_url}${image}"><link href="/css/global.css" rel="stylesheet"><script type="application/ld+json">${jsonLd(article, image)}</script></head>
<body><div class="page-wrapper"><div data-component="site-header"></div><main class="main-wrapper"><section class="blog-details-banner-section" style="background-image:linear-gradient(90deg,rgba(7,22,31,.86),rgba(7,22,31,.3)),url('${image}');background-size:cover;background-position:center"><div class="container"><div class="blog-details-banner-content-wrapper"><div class="blog-details-banner-text-block"><h1 class="h2-style text-light cn-anim-visible">${escapeHtml(article.h1)}</h1><div class="blog-details-banner-meta-block cn-anim-visible"><div class="text-lg neutral-color-08">Par ClimaNova Énergie</div><div class="blog-details-banner-meta-divider"></div><div class="text-lg neutral-color-08">${dateFr}</div></div></div></div></div></section>
<section class="blog-details-section"><div class="container"><div class="blog-details-content-wrapper"><article class="blog-details-content-block"><div class="blog-details-top-content-block w-richtext cn-anim-visible"><p>${escapeHtml(article.excerpt)}</p><div class="cn-direct-answer"><strong>Réponse courte :</strong> ${escapeHtml(article.featured_answer)}</div><div class="cn-key-takeaway"><strong>Points clés</strong><ul>${article.key_points.map((p) => `<li>${escapeHtml(p)}</li>`).join("")}</ul></div><picture><source srcset="${image}" type="image/webp"><img src="${fallback}" alt="${escapeHtml(article.primary_keyword)} à Nice avec ClimaNova Énergie" width="1400" height="787" loading="eager"></picture>${sections}<h2>Questions fréquentes</h2>${faqs}${cta}</div></article><aside class="cn-blog-sidebar">${cta}</aside></div></div></section></main><div data-component="site-footer"></div></div><script src="/js/components/site-header.js"></script><script src="/js/components/site-footer.js"></script><script src="/js/core/main.js"></script></body></html>`;
}

function renderCard(article) {
  const [image, fallback] = fallbackImages[article.track];
  return `<div role="listitem" class="w-dyn-item"><a href="/blog/${article.slug}/" class="blog-widget w-inline-block cn-anim-hidden"><div class="blog-widget-image-block"><picture><source srcset="${image}" type="image/webp"><img src="${fallback}" loading="lazy" alt="${escapeHtml(article.primary_keyword)}" class="blog-widget-image" width="1400" height="787"></picture></div><div class="blog-widget-content-block"><div class="blog-widget-content-block-inner"><div class="blog-slide-meta-block"><div class="text-md">${dateFr}</div></div><div class="blog-widget-text-block"><h2 class="h4-style">${escapeHtml(article.title)}</h2><p class="text-md">${escapeHtml(article.excerpt)}</p></div></div><div class="blog-widget-button"><div class="blog-button-link cn-text-dark"><div class="button-text">Lire la suite</div></div></div></div></a></div>`;
}

const generated = [];
const generationFailures = [];
for (const plan of plans) {
  if (plan.skip) continue;
  try {
    const article = await generateWithRetries(plan);
    const targetDir = path.join(root, "blog", article.slug);
    if (fs.existsSync(targetDir)) throw new Error(`Refusing to overwrite ${targetDir}`);
    fs.mkdirSync(targetDir, { recursive: false });
    fs.writeFileSync(path.join(targetDir, "index.html"), render(article));
    generated.push(article);
    history.push({ date: dateIso, track: article.track, angle: plan.angle, slug: article.slug,
      primary_keyword: article.primary_keyword, title: article.title });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    generationFailures.push({ track: plan.track.id, message });
    console.error(`::error title=Skipped ${plan.track.label}::${message}`);
  }
}

if (!generated.length && plans.some((plan) => !plan.skip)) {
  throw new Error(`All article generations failed: ${JSON.stringify(generationFailures)}`);
}

if (generated.length) {
  const blogIndexPath = path.join(root, "blog/index.html");
  let blogIndex = fs.readFileSync(blogIndexPath, "utf8");
  const marker = '<div role="list" class="w-dyn-items">';
  if (!blogIndex.includes(marker)) throw new Error("Blog index insertion marker not found");
  blogIndex = blogIndex.replace(marker, `${marker}${generated.map(renderCard).join("")}`);
  fs.writeFileSync(blogIndexPath, blogIndex);

  const sitemapPath = path.join(root, "sitemap.xml");
  let sitemap = fs.readFileSync(sitemapPath, "utf8");
  sitemap = sitemap.replace(/(<loc>https:\/\/climanova-energie\.fr\/blog\/<\/loc><lastmod>)[^<]+/, `$1${dateIso}`);
  for (const article of generated) {
    const entry = `  <url><loc>${config.site_url}/blog/${article.slug}/</loc><lastmod>${dateIso}</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>\n`;
    sitemap = sitemap.replace("</urlset>", `${entry}</urlset>`);
  }
  fs.writeFileSync(sitemapPath, sitemap);
  fs.writeFileSync(historyPath, `${JSON.stringify(history, null, 2)}\n`);
}
console.log(`Generated ${generated.length} article(s).`);
if (generationFailures.length) {
  console.warn(`Completed with ${generationFailures.length} skipped track(s): ${JSON.stringify(generationFailures)}`);
}
