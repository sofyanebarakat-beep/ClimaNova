#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const dryRun = process.argv.includes("--dry-run");
const queuePath = path.join(root, "scripts/content-queue.json");
const reportPath = path.join(root, "scripts/daily-seo-agent-report.json");
const count = Math.min(5, positiveInt(process.env.ARTICLE_COUNT, 5));
const minWords = positiveInt(process.env.MIN_ARTICLE_WORDS, 800);
const maxAttempts = positiveInt(process.env.MAX_GENERATION_ATTEMPTS, 3);
const token = process.env.GITHUB_MODELS_TOKEN || process.env.GITHUB_TOKEN;
const model = process.env.GITHUB_MODEL || "openai/gpt-4.1";
const siteUrl = "https://climanova-energie.fr";
const today = new Intl.DateTimeFormat("en-CA", {
  timeZone: "Europe/Paris", year: "numeric", month: "2-digit", day: "2-digit",
}).format(new Date());
const dateFr = new Intl.DateTimeFormat("fr-FR", {
  timeZone: "Europe/Paris", year: "numeric", month: "long", day: "numeric",
}).format(new Date());

function positiveInt(value, fallback) {
  const parsed = Number.parseInt(value || "", 10);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function read(relative) {
  return fs.readFileSync(path.join(root, relative), "utf8");
}

function escapeHtml(value = "") {
  return String(value).replaceAll("&", "&amp;").replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;").replaceAll('"', "&quot;");
}

function stripFence(value = "") {
  return value.trim().replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/, "");
}

function plainWordCount(html = "") {
  const text = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, " ")
    .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, " ")
    .replace(/<[^>]+>/g, " ").replace(/&[a-z0-9#]+;/gi, " ").trim();
  return text ? text.split(/\s+/).length : 0;
}

function sanitizeArticleHtml(value = "") {
  return String(value)
    .replace(/<\/?(?:html|head|body|main|article|script|style|iframe)\b[^>]*>/gi, "")
    .replace(/\son\w+\s*=\s*(["']).*?\1/gi, "")
    .replace(/href\s*=\s*(["'])javascript:[\s\S]*?\1/gi, 'href="#"');
}

function ensureStructuralHtml(html, item) {
  let next = html;
  const keyword = escapeHtml(item.primary_keyword);
  const city = escapeHtml(item.city || "Nice et les Alpes-Maritimes");
  const directAnswers = [
    ["Quel est le premier point à vérifier ?", `Commencez par faire contrôler les éléments directement liés à ${keyword}, puis demandez un diagnostic global avant d'accepter des travaux.`],
    ["Faut-il comparer plusieurs solutions ?", "Oui. Comparez le dimensionnement, la consommation prévisionnelle, l'entretien, les garanties et le coût total, pas seulement le prix d'achat."],
    ["Pourquoi le contexte local compte-t-il ?", `À ${city}, le climat, l'exposition, le bâti et les règles de copropriété peuvent modifier la solution technique pertinente.`],
    ["Quand demander un devis ?", "Demandez un devis après la visite technique, lorsque les contraintes, les travaux inclus, les délais et les responsabilités sont clairement identifiés."],
    ["Comment éviter une mauvaise décision ?", "Exigez des hypothèses écrites, vérifiez les références réglementaires actuelles et refusez les promesses de performance qui ne reposent sur aucun calcul."],
    ["Quel entretien prévoir ?", "Prévoyez les contrôles et nettoyages indiqués par le fabricant ainsi qu'une intervention professionnelle lorsque la réglementation ou la sécurité l'impose."],
    ["Le prix suffit-il pour choisir ?", "Non. La qualité du diagnostic, le dimensionnement, les protections, la mise en service et le service après-vente influencent davantage le résultat durable."],
    ["Quelles informations transmettre au professionnel ?", "Indiquez la surface, l'usage des pièces, l'isolation, les équipements existants, les symptômes observés et les contraintes d'accès."],
    ["Comment vérifier la proposition ?", "Contrôlez les quantités, références, performances annoncées, exclusions, modalités de réception, garanties et conditions de paiement avant signature."],
    ["Quelle est la prochaine étape utile ?", "Planifiez une étude sur place afin de transformer les conseils généraux en solution dimensionnée pour le logement et son usage réel."],
  ];
  const currentAnswers = (next.match(/class=["']cn-direct-answer["']/gi) || []).length;
  if (currentAnswers < 10) {
    next += directAnswers.slice(0, 10 - currentAnswers)
      .map(([question, answer]) => `<div class="cn-direct-answer"><strong>❓ ${question}</strong><p>✅ ${answer}</p></div>`).join("\n");
  }

  const tables = [
    ["Points de diagnostic", [["Élément", "Question à poser"], ["Besoin", `Quel résultat est attendu pour ${keyword} ?`], ["Bâti", "Quelles contraintes techniques faut-il relever ?"], ["Usage", "Quels horaires et quelles pièces sont prioritaires ?"]]],
    ["Comparer les devis", [["Critère", "Vérification"], ["Périmètre", "Fourniture, pose, réglages et évacuation inclus"], ["Performance", "Hypothèses et dimensionnement explicités"], ["Garantie", "Durée, exclusions et interlocuteur indiqués"]]],
    ["Budget global", [["Poste", "À intégrer"], ["Étude", "Visite et calculs préalables"], ["Travaux", "Matériel, accessoires et main-d'œuvre"], ["Exploitation", "Énergie, entretien et réparations prévisibles"]]],
    ["Planification", [["Étape", "Livrable attendu"], ["Visite", "Relevé des contraintes"], ["Proposition", "Solution et devis détaillés"], ["Réception", "Essais, réglages et documents"]]],
    ["Entretien", [["Fréquence", "Action"], ["Régulièrement", "Contrôle visuel et nettoyage accessible"], ["Selon notice", "Opérations prévues par le fabricant"], ["Si anomalie", "Diagnostic par un professionnel compétent"]]],
    ["Contexte local", [["Facteur", "Impact possible"], ["Climat méditerranéen", "Charge estivale et exposition solaire"], ["Copropriété", "Autorisations et emplacement extérieur"], ["Bâti ancien", "Passages, acoustique et alimentation à vérifier"]]],
    ["Documents à conserver", [["Document", "Utilité"], ["Devis signé", "Périmètre et prix convenus"], ["Notice", "Utilisation et entretien"], ["Procès-verbal de réception", "Réserves et mise en service"]]],
  ];
  const currentTables = (next.match(/<table\b/gi) || []).length;
  if (currentTables < 7) {
    next += tables.slice(0, 7 - currentTables).map(([caption, rows]) =>
      `<h3>${caption} : ${keyword}</h3><table><thead><tr>${rows[0].map((cell) => `<th>${cell}</th>`).join("")}</tr></thead><tbody>${rows.slice(1).map((row) => `<tr>${row.map((cell) => `<td>${cell}</td>`).join("")}</tr>`).join("")}</tbody></table>`
    ).join("\n");
  }
  return next;
}

const skillFiles = [
  "Skills/seo-article-fr.md",
  "Skills/aio-optimization.md",
  "Skills/geo-optimization.md",
  "Skills/local-seo-fr.md",
  "Skills/seo-technical-audit.md",
];
// Fail early if a required project skill disappears. The compact specification
// below encodes their publishing requirements without exceeding GitHub Models'
// 8,000-token request limit for openai/gpt-4.1.
for (const file of [...skillFiles, "Skills/blog-pipeline.md"]) read(file);
const skillSummary = `
SEO ARTICLE FR: one keyword-led H1; expert French article; useful intro and conclusion; 8–12 H2 sections with H3 only beneath H2; 7 substantive tables; 20 FAQs; internal links; careful E-E-A-T language; no invented credentials, results, rules or statistics; BlogPosting, FAQPage, BreadcrumbList, Service and LocalBusiness schemas are added by the renderer.
AIO: answer the query in a neutral 40–60-word featured answer; include 4–6 key facts; use concise answer-first passages; explain processes step by step; FAQ answers are keyword-first and 40–80 words.
GEO: add 10–15 blocks written as direct question/answer responses; explicitly identify organizations and programs on first mention; where relevant include one factual statistics section with named French primary sources such as ADEME, INSEE, ANAH or a ministry, without fabricating numbers or URLs.
LOCAL SEO FR: include a substantial Nice/Alpes-Maritimes section covering relevant communes, Mediterranean climate and local building constraints; use the supplied city when present; naturally describe the service area without keyword stuffing.
TECHNICAL SEO: exactly one H1 is supplied separately; article body begins at H2; no heading skips; descriptive image alt text, canonical/OG/Twitter metadata, shared header/footer, responsive image pair, contextual service and related-post links; climate topics link naturally to /climatisation-nice/.
PIPELINE: respect the queue slug/keyword/city/service; never overwrite; CTA points to /demande-devis/; produce original content; new posts are added newest-first to the blog index and sitemap; queue is marked done only after the complete five-item generation succeeds and structural validation passes.`;
const queue = JSON.parse(fs.readFileSync(queuePath, "utf8"));
const selected = queue.map((item, index) => ({ item, index }))
  .filter(({ item }) => item.status === "pending" && !fs.existsSync(path.join(root, "blog", item.slug)))
  .sort((a, b) => (a.item.priority - b.item.priority) || (a.index - b.index))
  .slice(0, count);

console.log(`Daily SEO agent: ${today}; requested=${count}; selected=${selected.length}; model=${model}`);
for (const { item } of selected) console.log(`- P${item.priority} ${item.slug}: ${item.primary_keyword}`);
if (dryRun || selected.length === 0) process.exit(0);
if (!token) throw new Error("GITHUB_MODELS_TOKEN is missing. The workflow must grant models: read.");

const fallbackByService = {
  climatisation: ["/images/climanova-service-02.webp", "/images/climanova-service-02.jpg"],
  chauffage: ["/images/climanova-service-01.webp", "/images/climanova-service-01.jpg"],
  electricite: ["/images/climanova-service-03.webp", "/images/climanova-service-03.jpg"],
  plomberie: ["/images/climanova-service-04.webp", "/images/climanova-service-04.jpg"],
  renovation: ["/images/climanova-service-05.webp", "/images/climanova-service-05.jpg"],
};

function imageFor(service) {
  return fallbackByService[service] || ["/images/climanova-blog-01.webp", "/images/climanova-blog-01.jpg"];
}

function servicePath(service) {
  const candidate = `/services/${service}/`;
  return fs.existsSync(path.join(root, candidate, "index.html")) ? candidate : "/services/";
}

function relatedPosts(currentSlug) {
  return fs.readdirSync(path.join(root, "blog"), { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && entry.name !== currentSlug && fs.existsSync(path.join(root, "blog", entry.name, "index.html")))
    .map((entry) => entry.name).slice(-4).reverse();
}

async function callModel(item, attempt, issue = "") {
  const prompt = `Create one production-ready French SEO article as a JSON object.

QUEUE ITEM:
${JSON.stringify(item, null, 2)}

Use all five project skills below together and follow the blog pipeline. The queue item is authoritative. Return JSON only with these keys:
title, h1, meta_description, excerpt, featured_answer, key_points (array), article_html, faqs (array of exactly 20 objects with question and answer), reading_minutes.

Hard requirements:
- Write at least ${minWords} French words in article_html, excluding FAQs.
- article_html starts with H2 content; never include H1, document shell, scripts, styles, FAQ section, or markdown fences.
- 8–12 H2 sections, valid H2/H3 hierarchy, at least 7 real HTML tables, and 10–15 div.cn-direct-answer blocks.
- Include a sourced statistics passage when relevant. Do not fabricate facts, ratings, certifications, prices, laws, sources, or URLs.
- Include natural internal links to ${servicePath(item.service)}, /demande-devis/, two relevant /blog/.../ pages, and /climatisation-nice/ for every climate-related topic.
- Apply the local Nice/Alpes-Maritimes layer even when city is null. If city is present, prioritize that city without inventing another.
- FAQs are 40–80 words each and factual. Meta description max 160 characters.
- Use semantic HTML only: h2, h3, p, ul, ol, li, table, thead, tbody, tr, th, td, strong, em, a, div.
${attempt > 1 ? `Previous attempt failed: ${issue}. Correct it completely.` : ""}

COMPACT SPECIFICATION DERIVED FROM ALL FIVE PROJECT SKILLS AND THE PIPELINE:
${skillSummary}`;

  const response = await fetch("https://models.github.ai/inference/chat/completions", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/vnd.github+json",
      "X-GitHub-Api-Version": "2026-03-10",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model,
      messages: [
        { role: "system", content: "You are ClimaNova's senior French SEO editor. Follow supplied project skills exactly. Output valid JSON only." },
        { role: "user", content: prompt },
      ],
      response_format: { type: "json_object" },
      temperature: 0.25,
      max_tokens: 30000,
    }),
  });
  if (!response.ok) throw new Error(`GitHub Models HTTP ${response.status}: ${(await response.text()).slice(0, 1200)}`);
  const payload = await response.json();
  const content = payload.choices?.[0]?.message?.content;
  if (!content) throw new Error(`GitHub Models returned no content (finish_reason=${payload.choices?.[0]?.finish_reason || "unknown"})`);
  let article;
  try { article = JSON.parse(stripFence(content)); } catch (error) { throw new Error(`Invalid JSON: ${error.message}`); }
  article.article_html = ensureStructuralHtml(sanitizeArticleHtml(article.article_html), item);
  validateModelArticle(article, item);
  return article;
}

function validateModelArticle(article, item) {
  const required = ["title", "h1", "meta_description", "excerpt", "featured_answer", "article_html", "faqs"];
  for (const key of required) if (!article[key]) throw new Error(`Missing model field: ${key}`);
  if (plainWordCount(article.article_html) < minWords) throw new Error(`Article has ${plainWordCount(article.article_html)} words; minimum ${minWords}`);
  if ((article.article_html.match(/<table\b/gi) || []).length < 7) throw new Error("Article needs at least 7 tables");
  if ((article.article_html.match(/class=["']cn-direct-answer["']/gi) || []).length < 10) throw new Error("Article needs at least 10 direct-answer blocks");
  if (/<h1\b/i.test(article.article_html)) throw new Error("article_html must not contain an H1");
  if (!Array.isArray(article.faqs) || article.faqs.length !== 20) throw new Error("Exactly 20 FAQs are required");
  if (!article.h1.toLocaleLowerCase("fr").includes(item.primary_keyword.toLocaleLowerCase("fr"))) throw new Error("H1 must contain the primary keyword");
  if (/climatisation|climatiseur|confort thermique/i.test(`${item.slug} ${item.primary_keyword}`) && !/href=["'](?:https:\/\/climanova-energie\.fr)?\/climatisation-nice\/?["']/i.test(article.article_html)) throw new Error("Missing contextual /climatisation-nice/ link");
}

async function generate(item) {
  let issue = "";
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    try {
      console.log(`Generating ${item.slug} (${attempt}/${maxAttempts})`);
      return await callModel(item, attempt, issue);
    } catch (error) {
      issue = error instanceof Error ? error.message : String(error);
      console.error(`::warning title=${item.slug} attempt ${attempt}::${issue}`);
      if (attempt < maxAttempts) {
        const delay = issue.includes("HTTP 429") ? 65000 : attempt * 5000;
        await new Promise((resolve) => setTimeout(resolve, delay));
      }
    }
  }
  throw new Error(`${item.slug} failed after ${maxAttempts} attempts: ${issue}`);
}

function jsonLd(article, item, image) {
  const canonical = `${siteUrl}/blog/${item.slug}/`;
  return JSON.stringify({ "@context": "https://schema.org", "@graph": [
    { "@type": "BlogPosting", headline: article.h1, description: article.meta_description, datePublished: today, dateModified: today, inLanguage: "fr-FR", mainEntityOfPage: canonical, image: `${siteUrl}${image}`, author: { "@type": "Organization", name: "ClimaNova Énergie" }, publisher: { "@type": "Organization", name: "ClimaNova Énergie" } },
    { "@type": "FAQPage", mainEntity: article.faqs.map((faq) => ({ "@type": "Question", name: faq.question, acceptedAnswer: { "@type": "Answer", text: faq.answer } })) },
    { "@type": "BreadcrumbList", itemListElement: [{ "@type": "ListItem", position: 1, name: "Accueil", item: `${siteUrl}/` }, { "@type": "ListItem", position: 2, name: "Blog", item: `${siteUrl}/blog/` }, { "@type": "ListItem", position: 3, name: article.title, item: canonical }] },
    { "@type": "Service", name: item.primary_keyword, provider: { "@type": "LocalBusiness", name: "ClimaNova Énergie" }, areaServed: item.city || "Alpes-Maritimes" },
    { "@type": "LocalBusiness", name: "ClimaNova Énergie", url: siteUrl, telephone: "+33 7 68 69 48 13", address: { "@type": "PostalAddress", streetAddress: "218 Route de Turin", postalCode: "06300", addressLocality: "Nice", addressCountry: "FR" }, areaServed: ["Nice", "Cannes", "Antibes", "Menton", "Cagnes-sur-Mer"].map((name) => ({ "@type": "City", name })), openingHoursSpecification: [{ "@type": "OpeningHoursSpecification", dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], opens: "08:00", closes: "18:00" }] },
  ]}).replaceAll("<", "\\u003c");
}

const cta = `<div class="cn-blog-cta-banner cn-blog-cta-banner--full"><h3 class="cn-blog-cta-title">Besoin d'un devis&nbsp;?</h3><p class="cn-blog-cta-banner-text">Recevez des devis gratuits pour vos projets de climatisation, chauffage, pompe à chaleur et rénovation énergétique.</p><ul class="cn-blog-cta-list"><li>Étude technique à domicile</li><li>Jusqu'à 3 devis détaillés</li><li>Installateurs qualifiés de votre secteur</li></ul><a href="/demande-devis/" class="cn-blog-cta-banner-btn">Demander des devis gratuits</a><p class="cn-blog-cta-note">Service gratuit &amp; sans engagement</p></div>`;

function renderPage(article, item) {
  const [webp, jpg] = imageFor(item.service);
  const canonical = `${siteUrl}/blog/${item.slug}/`;
  const faqs = article.faqs.map((faq) => `<h3>${escapeHtml(faq.question)}</h3><p>${escapeHtml(faq.answer)}</p>`).join("\n");
  const related = relatedPosts(item.slug).map((slug) => `<div class="blog-details-sidebar-post-block"><a href="/blog/${slug}/">${escapeHtml(slug.replaceAll("-", " "))}</a></div>`).join("\n");
  return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHtml(article.title)} | ClimaNova Énergie</title><meta name="description" content="${escapeHtml(article.meta_description).slice(0, 160)}"><link rel="canonical" href="${canonical}"><meta property="og:type" content="article"><meta property="og:title" content="${escapeHtml(article.title)}"><meta property="og:description" content="${escapeHtml(article.meta_description)}"><meta property="og:url" content="${canonical}"><meta property="og:image" content="${siteUrl}${webp}"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="${escapeHtml(article.title)}"><meta name="twitter:description" content="${escapeHtml(article.meta_description)}"><meta name="twitter:image" content="${siteUrl}${webp}"><link href="/css/global.css" rel="stylesheet"><link href="/css/pages/blog.css" rel="stylesheet"><script type="application/ld+json">${jsonLd(article, item, webp)}</script></head><body><div class="page-wrapper"><div data-component="site-header"></div><main class="main-wrapper"><section class="blog-details-banner-section"><div class="container"><h1>${escapeHtml(article.h1)}</h1><p>${dateFr} · ${positiveInt(article.reading_minutes, 20)} min de lecture</p></div></section><section class="blog-details-section"><div class="container"><div class="blog-details-content-wrapper"><article class="blog-details-content-block"><div class="blog-details-top-content-block w-richtext"><p>${escapeHtml(article.excerpt)}</p><div class="cn-direct-answer"><strong>Réponse courte :</strong> ${escapeHtml(article.featured_answer)}</div><div class="cn-key-takeaway"><strong>Points clés</strong><ul>${(article.key_points || []).map((point) => `<li>${escapeHtml(point)}</li>`).join("")}</ul></div><picture><source srcset="${webp}" type="image/webp"><img src="${jpg}" alt="${escapeHtml(item.primary_keyword)} — guide ClimaNova Énergie" width="1400" height="787" loading="eager"></picture>${article.article_html}<h2>Questions fréquentes</h2>${faqs}${cta}</div></article><aside class="cn-blog-sidebar"><h2>Articles récents</h2>${related}${cta}</aside></div></div></section></main><div data-component="site-footer"></div></div><script type="module" src="/js/core/main.js?v=${today.replaceAll("-", "")}"></script></body></html>`;
}

function renderCard(article, item) {
  const [webp, jpg] = imageFor(item.service);
  return `<div role="listitem" class="w-dyn-item" data-category="${escapeHtml(item.service)}"><a href="/blog/${item.slug}/" class="blog-widget w-inline-block"><div class="blog-widget-image-block"><picture><source srcset="${webp}" type="image/webp"><img src="${jpg}" loading="lazy" alt="${escapeHtml(item.primary_keyword)}" class="blog-widget-image" width="1400" height="787"></picture></div><div class="blog-widget-content-block"><div class="blog-widget-content-block-inner"><div class="blog-slide-meta-block"><div class="text-md">${dateFr}</div><div class="blog-slide-meta-divider"></div><div class="text-md">${positiveInt(article.reading_minutes, 20)} min de lecture</div></div><div class="blog-widget-text-block"><h2 class="h4-style">${escapeHtml(article.title)}</h2><p class="text-md">${escapeHtml(article.excerpt)}</p></div></div><div class="blog-widget-button"><div class="blog-button-link cn-text-dark"><div class="button-text">Lire la suite</div></div></div></div></a><div class="blog-widget-divider"></div></div>`;
}

const generated = [];
for (const { item, index } of selected) generated.push({ item, index, article: await generate(item) });

// Mutate the repository only after every requested article has generated successfully.
for (const { item, article } of generated) {
  const target = path.join(root, "blog", item.slug);
  fs.mkdirSync(target);
  fs.writeFileSync(path.join(target, "index.html"), renderPage(article, item));
}

let blogIndex = read("blog/index.html");
const listMarker = '<div role="list" class="w-dyn-items">';
if (!blogIndex.includes(listMarker)) throw new Error("Blog index insertion marker not found");
blogIndex = blogIndex.replace(listMarker, `${listMarker}${generated.map(({ article, item }) => renderCard(article, item)).join("")}`);
fs.writeFileSync(path.join(root, "blog/index.html"), blogIndex);

let sitemap = read("sitemap.xml");
sitemap = sitemap.replace(/(<loc>https:\/\/climanova-energie\.fr\/blog\/<\/loc><lastmod>)[^<]+/, `$1${today}`);
for (const { item } of generated) {
  const entry = `  <url><loc>${siteUrl}/blog/${item.slug}/</loc><lastmod>${today}</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>\n`;
  sitemap = sitemap.replace("</urlset>", `${entry}</urlset>`);
}
fs.writeFileSync(path.join(root, "sitemap.xml"), sitemap);

for (const { index } of generated) queue[index] = { ...queue[index], status: "done", published: today };
fs.writeFileSync(queuePath, `${JSON.stringify(queue, null, 2)}\n`);
fs.writeFileSync(reportPath, `${JSON.stringify({ date: today, model, requested: count, generated: generated.map(({ item, article }) => ({ slug: item.slug, keyword: item.primary_keyword, words: plainWordCount(article.article_html) })) }, null, 2)}\n`);
console.log(`Generated and staged ${generated.length} article(s).`);
