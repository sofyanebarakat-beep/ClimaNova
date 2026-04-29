/**
 * Renames auto-generated cn-style-HASH class names to semantic names in all HTML files.
 * Run once from the project root: node scripts/rename-classes.mjs
 */

import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const excludedDirs = new Set([".git", "node_modules", "ClimaNovaEnergie copy", "climavovaall", "scripts"]);

// Map: old hash class → new semantic class
const RENAME_MAP = {
  // ── Layout ──────────────────────────────────────────────────────────────
  "cn-style-3b0bbc2b06": "cn-grid-3",
  "cn-style-bad56beeeb": "cn-grid-5",
  "cn-style-cf949a7d2f": "cn-grid-2",
  "cn-style-7a1e55bbb9": "cn-flex-row",
  "cn-style-2a0be13760": "cn-flex-center",
  "cn-style-899bda87f6": "cn-flex-gap-xs",

  // ── Utility ──────────────────────────────────────────────────────────────
  "cn-style-6b99de8b69": "cn-hidden",
  "cn-style-f73dd04e5b": "cn-pos-rel-z2",
  "cn-style-0187fae056": "cn-overlay-gradient",

  // ── Cards ─────────────────────────────────────────────────────────────────
  "cn-style-049a925463": "cn-card-centered",
  "cn-style-53469c952e": "cn-card",
  "cn-style-9b318d56e5": "cn-card-dark",
  "cn-style-957625052f": "cn-card-accent",

  // ── Badges / Labels ───────────────────────────────────────────────────────
  "cn-style-87fc37029e": "cn-badge--primary",
  "cn-style-ebe306a8f6": "cn-badge",
  "cn-style-8a6cebe863": "cn-label",

  // ── Icons / Images ────────────────────────────────────────────────────────
  "cn-style-732f261912": "cn-icon-circle",
  "cn-style-cd6090f6a8": "cn-icon-lg",
  "cn-style-31eeff6ed9": "cn-avatar-sm",
  "cn-style-313cefb1e4": "cn-img-card",
  "cn-style-8dd7749f71": "cn-emoji",

  // ── Typography utilities ──────────────────────────────────────────────────
  "cn-style-0cb93c6174": "cn-text-card-title",
  "cn-style-62dc5286d8": "cn-text-name",
  "cn-style-767a19556b": "cn-text-author",
  "cn-style-49a9541c70": "cn-text-meta",
  "cn-style-75d941e672": "cn-text-body",
  "cn-style-4cd34d2a4f": "cn-text-quote",
  "cn-style-c94503bd8b": "cn-text-sm-muted",
  "cn-style-0d75823b20": "cn-text-white",
  "cn-style-f949b42b61": "cn-text-white",   // duplicate rule — same semantic name
  "cn-style-2ac59232b8": "cn-text-dark",
  "cn-style-244084f400": "cn-text-white-dim",
  "cn-style-1cffbeb6fa": "cn-text-white-body",
  "cn-style-1bc604e38c": "cn-text-center-mb",
  "cn-style-e0aaada444": "cn-text-muted-narrow",
  "cn-style-7eebe0fb65": "cn-section-eyebrow",
  "cn-style-ff849113d4": "cn-section-subtitle",

  // ── Section backgrounds ───────────────────────────────────────────────────
  "cn-style-af5f499baf": "cn-section--tint",
  "cn-style-cee00a862a": "cn-section--bg",

  // ── Page-specific banner images ───────────────────────────────────────────
  "cn-style-110ef2da63": "cn-banner-about",
  "cn-style-06da2d435d": "cn-banner-blog-budget",
  "cn-style-3d0cb65a9d": "cn-banner-blog-roof",
  "cn-style-5291bb5d7b": "cn-banner-blog-seasonal",
  "cn-style-57d5ca2962": "cn-banner-blog-repairs",
  "cn-style-7ac85c5558": "cn-banner-blog-service",
  "cn-style-ff29257b3a": "cn-banner-blog-extend",

  // ── Webflow animation states (rename for clarity, keep behaviour intact) ──
  "cn-style-8d919cb594": "cn-anim-hidden",
  "cn-style-c6e7979de4": "cn-anim-visible",
  "cn-style-1413375864": "cn-anim-out-r-far",
  "cn-style-173286ea51": "cn-anim-out-r-far2",
  "cn-style-318af51188": "cn-anim-in",
  "cn-style-43cb1a12d3": "cn-anim-out-l-mid",
  "cn-style-794c5f67e8": "cn-anim-out-r-mid",
  "cn-style-821ba785c5": "cn-anim-out-l-far",
  "cn-style-9ef08f8231": "cn-anim-slide-up",
  "cn-style-a5637ddc2f": "cn-anim-out-r-sm",
  "cn-style-ad3afd275a": "cn-anim-out-l-sm",
  "cn-style-d41042c118": "cn-anim-in-y",
  "cn-style-db2767d28c": "cn-anim-in-x",
  "cn-style-dbce869806": "cn-anim-in-origin",

  // ── Broken / remove (malformed url on <video> element) ───────────────────
  "cn-style-14252c0661": null,  // null = remove the class entirely
};

function listHtmlFiles(dir = root) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    if (excludedDirs.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) files.push(...listHtmlFiles(full));
    else if (entry.isFile() && entry.name.endsWith(".html")) files.push(full);
  }
  return files;
}

function replaceClassInHtml(html) {
  // Replace each class token inside class="..." attributes
  return html.replace(/class="([^"]*)"/g, (match, classList) => {
    const tokens = classList.split(/\s+/);
    const renamed = [];
    const seen = new Set();
    for (const token of tokens) {
      if (RENAME_MAP.hasOwnProperty(token)) {
        const newName = RENAME_MAP[token];
        if (newName === null) continue;       // remove
        if (!seen.has(newName)) {
          seen.add(newName);
          renamed.push(newName);
        }
      } else {
        if (!seen.has(token)) {
          seen.add(token);
          renamed.push(token);
        }
      }
    }
    return `class="${renamed.join(" ")}"`;
  });
}

const files = listHtmlFiles();
let changed = 0;
for (const file of files) {
  const original = fs.readFileSync(file, "utf8");
  const updated = replaceClassInHtml(original);
  if (updated !== original) {
    fs.writeFileSync(file, updated);
    changed++;
    console.log(`  updated: ${path.relative(root, file)}`);
  }
}
console.log(`\nDone — ${changed} of ${files.length} HTML files updated.`);
